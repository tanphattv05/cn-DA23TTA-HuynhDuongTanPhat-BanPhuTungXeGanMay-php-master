<?php
if (PHP_SAPI !== 'cli' || !isset($app, $db, $testName) || !preg_match('/\Amotoparts_test_[a-f0-9]+\z/', $testName)) exit("Run isolated harness.\n");
$dashboardStart = $checks;
$originalCookie = $cookie;
$paths = ['app/Models/Dashboard.php', 'app/Controllers/Admin/DashboardController.php', 'app/Views/admin/dashboard/index.php'];
foreach (['admin', 'anonymous', 'customer'] as $actor) {
    $cookie = $actor === 'anonymous' ? $temp . '/anonymous-dashboard.txt' : $originalCookie;
    if ($actor !== 'anonymous') request('test-session.php?id=' . ($actor === 'admin' ? 1 : 2));
    foreach ($paths as $path) {
        [$status, $body] = request($path);
        check($status === 403 && $body === '', 'Dashboard internal file denied to ' . $actor . ': ' . $path);
    }
    [$status, , $headers] = request('admin/index.php');
    check($actor === 'admin' ? $status === 200 : ($actor === 'customer' ? $status === 403 : ($status === 302 && str_contains($headers, '/scr/pages/login.php'))), 'Dashboard authorization: ' . $actor);
}
$cookie = $originalCookie;
request('test-session.php?id=1');
function dashboard_cards(string $html): array {
    $values = [];
    foreach (customer_test_dom($html)->query('//h2') as $node) $values[] = trim($node->textContent);
    return $values;
}
$baseCounts = [];
foreach (['SELECT COUNT(*) FROM products', 'SELECT COUNT(*) FROM categories', "SELECT COUNT(*) FROM users WHERE role='customer'"] as $sql) {
    $baseCounts[] = (string) $db->query($sql)->fetch_row()[0];
}
// This final suite uses only the disposable test database; no real orders are touched.
check($db->query('SELECT DATABASE()')->fetch_row()[0] === $testName, 'Dashboard fixture connection is isolated');
$db->query('DELETE FROM order_details');
$db->query('DELETE FROM orders');
[$status, $html] = request('admin/index.php');
check($status === 200 && dashboard_cards($html) === array_merge($baseCounts, ['0','0','0 ₫']), 'Empty orders: all counters and zero revenue');
check(str_contains($html, 'Chưa có đơn hàng.') && !str_contains($html, 'order-detail.php?id='), 'Empty dashboard has message and no order links');
fixture_order(null, 'cancelled', '999.00');
fixture_order(2, 'pending', '888.00');
$html = request('admin/index.php')[1];
check(dashboard_cards($html) === array_merge($baseCounts, ['2','1','0 ₫']), 'Pending and cancelled never contribute to revenue');
$db->query('DELETE FROM order_details');
$db->query('DELETE FROM orders');
$states = ['pending','confirmed','shipping','completed','cancelled'];
$labels = ['Chờ xác nhận','Đã xác nhận','Đang giao hàng','Đã hoàn thành','Đã hủy'];
$classes = ['bg-warning text-dark','bg-primary','bg-info text-dark','bg-success','bg-danger'];
$ids = [];
for ($i = 0; $i < 15; $i++) {
    $ids[] = fixture_order($i % 2 ? 2 : null, $states[$i % 5], (string)(100 * ($i + 1)));
}
// Dates deliberately oppose ID order.
$db->query("UPDATE orders SET created_at=DATE_SUB('2025-01-31 12:00:00', INTERVAL id DAY)");
$beforeOrders = $db->query('SELECT id,status,total FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$beforeStock = order_test_stock();
[$status, $html] = request('admin/index.php');
check($status === 200 && dashboard_cards($html) === array_merge($baseCounts, ['15','3','2.700 ₫']), 'Mixed states: counts and completed-only revenue are exact');
$dom = customer_test_dom($html);
$links = [];
foreach ($dom->query('//tbody/tr/td/a') as $node) $links[] = $node->getAttribute('href');
$expectedIds = array_slice(array_reverse($ids), 0, 10);
check($links === array_map(fn($id) => 'order-detail.php?id=' . $id, $expectedIds), 'Exactly ten recent orders by ID descending, correct detail links');
foreach ($dom->query('//tbody/tr') as $index => $row) {
    $stateIndex = (14 - $index) % 5;
    $badge = $dom->query('.//span', $row)->item(0);
    check(trim($badge->textContent) === $labels[$stateIndex] && $badge->getAttribute('class') === 'badge ' . $classes[$stateIndex], 'Recent order status label and color: row ' . $index);
}
check(str_contains($html, 'Recipient &lt;script&gt;test&lt;/script&gt;') && !str_contains($html, '<script>test</script>'), 'Dashboard escapes recipient HTML');
check(substr_count($html, 'class="nav-link active"') === 1 && preg_match('~href="[^"]+/admin/index.php"\s+class="nav-link active"~', $html), 'Only dashboard sidebar is active');
check($beforeOrders === $db->query('SELECT id,status,total FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC) && $beforeStock === order_test_stock(), 'Dashboard read does not change orders or stock');
check(request('admin/' . $links[0])[0] === 200, 'Recent order link opens Admin detail');
$modelSource = file_get_contents($app . '/app/Models/Dashboard.php');
check(!preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b|password|\$_(GET|POST|SESSION)/', $modelSource), 'Dashboard model is read-only without request or password access');
check(!preg_match('/\b(SELECT|mysqli)\b/', file_get_contents($app . '/app/Controllers/Admin/DashboardController.php')), 'Dashboard controller contains no SQL');
check(!preg_match('/\b(SELECT|mysqli)\b|\$_(GET|POST|SESSION)/', file_get_contents($app . '/app/Views/admin/dashboard/index.php')), 'Dashboard view contains no SQL or request handling');
check(str_contains(file_get_contents($app . '/admin/index.php'), 'DashboardController') && strlen(file_get_contents($app . '/admin/index.php')) < 250, 'Legacy dashboard URL is thin entry');
// Simulate query failure only in the disposable database, restore immediately.
$db->query('RENAME TABLE orders TO dashboard_orders_unavailable');
try {
    [$status, $html] = request('admin/index.php');
    check($status === 503 && str_contains($html, 'Không thể tải tổng quan') && str_contains($html, 'app-sidebar') && !str_contains($html, $testName), 'DB failure uses layout and generic message');
} finally {
    $db->query('RENAME TABLE dashboard_orders_unavailable TO orders');
}
echo 'Dashboard checks completed: ' . ($checks - $dashboardStart) . PHP_EOL;