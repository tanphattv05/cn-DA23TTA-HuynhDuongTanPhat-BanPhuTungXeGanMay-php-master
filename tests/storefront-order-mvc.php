<?php
if (PHP_SAPI !== 'cli' || !isset($app, $db, $testName)) exit('Run isolated harness.');
$historyStart = $checks;
$historyCookie = $cookie;
$cookie = $temp . '/history-tests.txt';
foreach (['my-orders.php', 'order-detail.php?id=1'] as $page) {
    [$status, , $headers] = request('pages/' . $page);
    check($status === 302 && str_contains($headers, '/scr/pages/login.php'), 'History guest redirected: ' . $page);
}
// This assertion must be RED before implementation.
foreach (['my-orders.php', 'order-detail.php'] as $page) {
    $source = file_get_contents($app . '/pages/' . $page);
    check(strlen($source) < 500 && str_contains($source, 'OrderController') && !str_contains($source, 'SELECT'), 'Thin history entry: ' . $page);
}
$db->query("INSERT INTO users(fullname,email,password,phone,role) VALUES ('History A','history-a@test.invalid','unused','0901234567','customer'),('History B','history-b@test.invalid','unused','0901234567','customer'),('History Empty','history-empty@test.invalid','unused','0901234567','customer')");
$historyUsers = $db->query("SELECT id FROM users WHERE email LIKE 'history-%@test.invalid' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
[$historyA, $historyB, $historyEmpty] = array_column($historyUsers, 'id');
$db->query("INSERT INTO products(category_id,name,price,stock,image) VALUES (1,'Product <script>history</script>',999999.99,20,'history.png')");
$historyProduct = (int) $db->insert_id;
copy($png, $app . '/assets/images/products/history.png');
$historyInsert = $db->prepare('INSERT INTO orders(user_id,fullname,phone,address,note,total,status,created_at) VALUES (?,?,?,?,?,?,?,?)');
$historyDetail = $db->prepare('INSERT INTO order_details(order_id,product_id,quantity,price) VALUES (?,?,3,1234.00)');
$historyIds = [];
$labels = ['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','shipping'=>'Đang giao hàng','completed'=>'Đã hoàn thành','cancelled'=>'Đã hủy'];
foreach (array_merge(array_keys($labels), array_fill(0, 7, 'pending')) as $i => $state) {
    $name = 'Recipient <script>history</script>';
    $phone = '0901234567';
    $address = 'Address <img src=x onerror=alert(1)>';
    $note = $i === 0 ? '<b>History note</b>' : null;
    $total = '4567.00';
    $date = '2026-01-02 03:04:05';
    $historyInsert->bind_param('isssssss', $historyA, $name, $phone, $address, $note, $total, $state, $date);
    $historyInsert->execute();
    $id = (int) $db->insert_id;
    $historyIds[] = $id;
    $historyDetail->bind_param('ii', $id, $historyProduct);
    $historyDetail->execute();
}
$foreignIds = [];
foreach ([$historyB, null, 1] as $owner) {
    $historyInsert->bind_param('isssssss', $owner, $name, $phone, $address, $note, $total, $state, $date);
    $historyInsert->execute();
    $foreignIds[] = (int) $db->insert_id;
}
$historySnapshot = static function () use ($db): array {
    return [
        $db->query('SELECT id,user_id,fullname,phone,address,note,total,status,created_at FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC),
        $db->query('SELECT id,order_id,product_id,quantity,price FROM order_details ORDER BY id')->fetch_all(MYSQLI_ASSOC),
        $db->query('SELECT id,stock,price FROM products ORDER BY id')->fetch_all(MYSQLI_ASSOC)
    ];
};
$beforeHistory = $historySnapshot();
request('test-session.php?id=' . $historyA);
[$status, $html] = request('pages/my-orders.php?user_id=' . $historyB);
$rows = customer_test_rows($html);
$visibleIds = array_map(static fn($row) => (int) ltrim(trim($row[0]), '#'), $rows);
check($status === 200 && $visibleIds === array_reverse($historyIds), 'History shows all 12 own orders newest first without new pagination');
check(!array_intersect($visibleIds, $foreignIds), 'History excludes foreign/guest/admin orders despite matching recipient');
check(str_contains($html, 'Recipient &lt;script&gt;history&lt;/script&gt;'), 'History recipient escaped');
foreach ($labels as $label) check(str_contains($html, $label), 'History Vietnamese label: ' . $label);
check(substr_count($html, 'href="order-detail.php?id=') === 12 && str_contains($html, '02/01/2026 03:04') && str_contains($html, '4.567 ₫'), 'History date total links retained');
[$status, $html] = request('pages/order-detail.php?id=' . $historyIds[0]);
check($status === 200 && str_contains($html, 'Đơn hàng #' . $historyIds[0]), 'Customer reads own detail');
check(str_contains($html, '1.234 ₫') && str_contains($html, '3.702 ₫') && str_contains($html, '4.567 ₫') && !str_contains($html, '1.000.000'), 'Historical price line total and saved order total');
check(str_contains($html, 'history.png') && str_contains($html, 'Product &lt;script&gt;history&lt;/script&gt;'), 'Image and escaped product name');
check(str_contains($html, 'Recipient &lt;script&gt;history&lt;/script&gt;') && str_contains($html, '&lt;img src=x onerror=alert(1)&gt;') && str_contains($html, '&lt;b&gt;History note&lt;/b&gt;'), 'Recipient address note escaped');
check(!str_contains($html, '<script>history') && !str_contains($html, '<img src=x') && !str_contains($html, 'unused'), 'No executable fixture HTML or password');
check(str_contains($html, 'href="my-orders.php"') && !str_contains($html, 'update-order.php') && !str_contains($html, 'app-sidebar'), 'Storefront layout back link without mutations');
foreach ($labels as $state => $label) {
    $id = $historyIds[array_search($state, array_keys($labels), true)];
    check(str_contains(request('pages/order-detail.php?id=' . $id)[1], $label), 'Detail Vietnamese label: ' . $state);
}
$notFound = request('pages/order-detail.php?id=2147483647');
check($notFound[0] === 404 && str_contains($notFound[1], 'Không tìm thấy đơn hàng'), 'Nonexistent order storefront 404');
foreach (['', '0', '-1', 'abc', '1.5', '2147483648', str_repeat('9', 100), '1%20OR%201=1', '1%27', '1&id[]=2'] as $badId) {
    [$status, $body] = request('pages/order-detail.php?id=' . $badId);
    check($status === 404 && $body === $notFound[1], 'Invalid ID identical 404: ' . $badId);
}
check(request('pages/order-detail.php')[0] === 404, 'Missing ID 404');
foreach ($foreignIds as $foreignId) {
    [$status, $body] = request('pages/order-detail.php?id=' . $foreignId . '&user_id=' . $historyB);
    check($status === 404 && $body === $notFound[1], 'Foreign/guest identical 404: ' . $foreignId);
}
check(request('pages/order-detail.php?id=' . $foreignIds[0], ['user_id'=>$historyB])[0] === 404, 'POST cannot forge owner');
check(request('admin/orders.php')[0] === 403, 'History customer denied Admin');
check($historySnapshot() === $beforeHistory, 'Reading history changes no orders details prices stock');
request('test-session.php?id=' . $historyEmpty);
[$status, $html] = request('pages/my-orders.php');
check($status === 200 && str_contains($html, 'Bạn chưa có đơn hàng nào.') && str_contains($html, 'href="products.php"'), 'Empty history retained');
request('test-session.php?id=1');
check(request('admin/orders.php')[0] === 200 && request('admin/order-detail.php?id=' . $historyIds[0])[0] === 200, 'Admin routes still available');
check(request('pages/order-detail.php?id=' . $historyIds[0])[0] === 404, 'Admin storefront still owner-scoped');
request('test-session.php?id=' . $historyA);
$db->query("UPDATE products SET name='Renamed fixture',price=888888.88 WHERE id=" . $historyProduct);
$html = request('pages/order-detail.php?id=' . $historyIds[0])[1];
check(str_contains($html, 'Renamed fixture') && str_contains($html, '1.234 ₫') && str_contains($html, '3.702 ₫') && !str_contains($html, '888.889'), 'Product change preserves historical prices');
// Only temporary DB: simulate a legacy orphan without changing schema.
$db->query('SET FOREIGN_KEY_CHECKS=0');
try { $db->query('UPDATE order_details SET product_id=2147483647 WHERE order_id=' . $historyIds[0]); }
finally { $db->query('SET FOREIGN_KEY_CHECKS=1'); }
[$status, $html] = request('pages/order-detail.php?id=' . $historyIds[0]);
check($status === 200 && str_contains($html, 'Sản phẩm không còn tồn tại') && str_contains($html, '3.702 ₫'), 'Orphan retains historical line and fallback');
check(!str_contains($html, 'history.png'), 'Missing product has no broken image');
check(request('pages/order-detail.php?id=' . $historyIds[1])[0] === 200, 'NULL note safe');
foreach (['orders', 'order_details'] as $table) {
    $db->query('RENAME TABLE ' . $table . ' TO history_unavailable');
    try {
        $pages = $table === 'orders' ? ['my-orders.php', 'order-detail.php?id=' . $historyIds[0]] : ['order-detail.php?id=' . $historyIds[0]];
        foreach ($pages as $page) {
            [$status, $body] = request('pages/' . $page);
            check($status === 503 && str_contains($body, 'Không thể tải đơn hàng') && !preg_match('/SELECT |mysqli|Stack trace|xampp|history_unavailable/', $body), 'DB failure safe 503: ' . $table . '/' . $page);
        }
    } finally { $db->query('RENAME TABLE history_unavailable TO ' . $table); }
}
foreach (['Controllers/Storefront/OrderController.php','Models/StorefrontOrder.php','Views/storefront/orders/index.php','Views/storefront/orders/detail.php','Core/OrderStatus.php'] as $path) {
    check(request('app/' . $path)[0] === 403, 'History internal denied: ' . $path);
}
$controller = file_get_contents($app . '/app/Controllers/Storefront/OrderController.php');
$model = file_get_contents($app . '/app/Models/StorefrontOrder.php');
check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b|<div/', $controller), 'History Controller no SQL/HTML');
check(!preg_match('/\$_(GET|POST|SESSION)|password|SELECT\s+\*/i', $model), 'History Model no request/session/password/wildcard');
foreach (['index', 'detail'] as $view) {
    $source = file_get_contents($app . '/app/Views/storefront/orders/' . $view . '.php');
    check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|mysqli_query|mysqli_fetch_assoc)\b|\$_(GET|POST|SESSION)/', $source), 'History View renders only: ' . $view);
}
$directModel = new \MotoParts\App\Models\StorefrontOrder($db);
check($directModel->findOwned($foreignIds[0], (int) $historyA) === null && $directModel->items($foreignIds[0], (int) $historyA) === [], 'Model scopes both order and lines to owner');
check(\MotoParts\App\Core\OrderStatus::labels() === $labels, 'Shared labels match Admin labels');
$cookie = $historyCookie;
echo 'Storefront order MVC checks completed: ' . ($checks - $historyStart) . PHP_EOL;
