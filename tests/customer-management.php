<?php
// Called only by the existing isolated test harness.
if (PHP_SAPI !== 'cli' || !isset($db, $live, $app)) {
    exit("Run: php tests/product-management.php --isolated\n");
}
$customerChecksStart = $checks;
function customer_test_dom(string $html): DOMXPath {
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}
function customer_test_rows(string $html): array {
    $xpath = customer_test_dom($html);
    $rows = [];
    foreach ($xpath->query('//tbody/tr') as $tr) {
        $cells = [];
        foreach ($xpath->query('./td', $tr) as $td) $cells[] = trim($td->textContent);
        $rows[] = $cells;
    }
    return $rows;
}
function customer_test_stats(string $html): array {
    $xpath = customer_test_dom($html);
    $stats = [];
    foreach ($xpath->query('//dt') as $dt) {
        $dd = $xpath->query('following-sibling::dd[1]', $dt)->item(0);
        if ($dd) $stats[trim($dt->textContent)] = trim($dd->textContent);
    }
    return $stats;
}
$schema = $live->query('SHOW CREATE TABLE orders')->fetch_row()[1];
$db->query(preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $schema));
$insertUser = $db->prepare("INSERT INTO users (fullname,email,phone,password,role,created_at) VALUES (?,?,?,'private-fixture-marker','customer','2026-01-02 03:04:05')");
$clientIds = [];
for ($i=0; $i<23; $i++) {
    $fullname = sprintf('Client %02d', $i);
    if ($i === 22) $fullname .= ' <script>alert(1)</script>';
    $email = sprintf('client%02d@test.invalid', $i);
    $phone = sprintf('090000%04d', $i);
    $insertUser->bind_param('sss', $fullname, $email, $phone);
    $insertUser->execute();
    $clientIds[] = $db->insert_id;
}
$mainId = $clientIds[0];
$otherId = $clientIds[1];
$orderInsert = $db->prepare('INSERT INTO orders (user_id,fullname,phone,address,total,status,created_at) VALUES (?,?,?,?,?,?,?)');
$mainOrders = [];
$statuses = array_merge(['completed','completed'], array_fill(0,3,'cancelled'), array_fill(0,6,'pending'), array_fill(0,6,'confirmed'), array_fill(0,6,'shipping'));
foreach ($statuses as $i => $status) {
    $recipient = $i === 0 ? '<b>Recipient</b>' : 'Client 00';
    $phone = '0900000000';
    $address = 'Isolated fixture only';
    $total = $i === 0 ? '100.10' : ($i === 1 ? '200.20' : '999.99');
    $created = '2026-02-03 04:05:06';
    $orderInsert->bind_param('issssss', $mainId, $recipient, $phone, $address, $total, $status, $created);
    $orderInsert->execute();
    $mainOrders[] = $db->insert_id;
}
foreach ([$otherId, null, null, 1] as $ownerId) {
    // Same contact details as main customer must never imply ownership.
    $recipient = 'Client 00';
    $phone = '0900000000';
    $address = 'Isolated fixture only';
    $total = '876543.21';
    $status = 'completed';
    $created = '2026-02-03 04:05:06';
    $orderInsert->bind_param('issssss', $ownerId, $recipient, $phone, $address, $total, $status, $created);
    $orderInsert->execute();
}
$usersBefore = $db->query('SELECT id,fullname,email,phone,role,created_at FROM users ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$ordersBefore = $db->query('SELECT id,user_id,fullname,phone,address,note,total,status,created_at FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC);
// Use a fresh cookie jar to exercise unauthenticated requests.
$adminCookie = $cookie;
$cookie = $temp . '/anonymous-customers.txt';
foreach (['customers.php','customer-detail.php?id=' . $mainId,'includes/customer-view.php'] as $path) {
    [$status,,$headers] = request('admin/' . $path);
    check($status === 302 && str_contains($headers,'/scr/pages/login.php'), 'Anonymous denied: ' . $path);
}
$cookie = $adminCookie;
request('test-session.php?id=2');
foreach (['customers.php','customer-detail.php?id=' . $mainId,'includes/customer-view.php'] as $path) {
    check(request('admin/' . $path)[0] === 403, 'Customer denied: ' . $path);
}
request('test-session.php?id=1');
[$status, $html] = request('admin/customers.php?q=client00%40test.invalid');
$rows = customer_test_rows($html);
check($status === 200 && count($rows) === 1 && $rows[0][0] === (string) $mainId, 'Email search selects correct customer');
check($rows[0][5] === '23' && $rows[0][6] === '300,30 ₫', 'List counts all orders but sums only completed');
check($rows[0][4] === '02/01/2026 03:04', 'Registration date rendered');
$rows = customer_test_rows(request('admin/customers.php?q=0900000000')[1]);
check(count($rows) === 1 && $rows[0][0] === (string) $mainId, 'Phone search');
$rows = customer_test_rows(request('admin/customers.php?q=Client%2000')[1]);
check(count($rows) === 1 && $rows[0][0] === (string) $mainId, 'Fullname search');
check(str_contains(request('admin/customers.php?q=admin%40test.invalid')[1], 'Không tìm thấy khách hàng'), 'Admin excluded even when email matches');
$rows = customer_test_rows(request('admin/customers.php?q=customer%40test.invalid')[1]);
check(count($rows) === 1 && $rows[0][0] === '2' && $rows[0][5] === '0' && $rows[0][6] === '0,00 ₫', 'Customer with no orders remains in list with zero totals');
$allListed = [];
for ($p=1;$p<=3;$p++) {
    [$status,$html] = request('admin/customers.php?q=Client&page=' . $p);
    $rows = customer_test_rows($html);
    check($status === 200 && count($rows) === ($p === 3 ? 3 : 10), 'Customer pagination page ' . $p);
    foreach ($rows as $row) $allListed[] = (int) $row[0];
    check(!str_contains($html,'private-fixture-marker'), 'No credential fixture in list response');
}
$expectedClients = array_reverse($clientIds);
check($allListed === $expectedClients, 'Stable customer pagination without duplicates');
check(str_contains(request('admin/customers.php?q=Client&page=2')[1], 'q=Client&amp;page=3'), 'Customer pagination keeps keyword');
check(count(customer_test_rows(request('admin/customers.php?q=Client&page=999')[1])) === 3, 'Customer page outside range clamps to last');
foreach (['-1','abc','0','1.5','999999999999999999999999','%5B%5D'] as $badPage) {
    check(str_contains(request('admin/customers.php?q=Client&page=' . $badPage)[1],'Trang 1/3'), 'Invalid customer page defaults safely: ' . $badPage);
}
check(str_contains(request('admin/customers.php?q=Client&page%5B%5D=2')[1],'Trang 1/3'), 'Array page handled safely');
check(str_contains(request('admin/customers.php?q=%25')[1],'Không tìm thấy khách hàng'), 'Search wildcard treated literally');
[$status,$html] = request('admin/customer-detail.php?id=' . $mainId);
$stats = customer_test_stats($html);
check($status === 200 && $stats['Tổng số đơn'] === '23' && $stats['Đã hoàn thành'] === '2' && $stats['Đã hủy'] === '3' && $stats['Tổng tiền hoàn thành'] === '300,30 ₫', 'Detail statistics match exact fixture');
$historyIds = [];
foreach ([1,2,3] as $p) {
    [$status,$html] = request('admin/customer-detail.php?id=' . $mainId . '&page=' . $p);
    $rows = customer_test_rows($html);
    check($status === 200 && count($rows) === ($p === 3 ? 3 : 10), 'History pagination page ' . $p);
    foreach ($rows as $row) $historyIds[] = (int) ltrim($row[0], '#');
    check(!str_contains($html,'private-fixture-marker'), 'No credential fixture in detail response');
    $links = customer_test_dom($html)->query('//tbody/tr/td[1]/a');
    $adminLinks = $links->length === count($rows);
    foreach ($links as $link) {
        $adminLinks = $adminLinks && preg_match('/^order-detail\.php\?id=[1-9][0-9]*$/', $link->getAttribute('href'));
    }
    check($adminLinks, 'History links only to admin order detail');
}
check($historyIds === array_reverse($mainOrders), 'History excludes other users, admin and guest orders with matching contacts');
$html = request('admin/customer-detail.php?id=' . $mainId . '&page=3')[1];
check(str_contains($html,'&lt;b&gt;Recipient&lt;/b&gt;') && !str_contains($html,'<b>Recipient</b>'), 'Recipient HTML escaped');
check(str_contains($html,'Đã hoàn thành') && str_contains($html,'Đã hủy'), 'Vietnamese history statuses');
check(str_contains($html,'id=' . $mainId . '&amp;page=2'), 'History pagination preserves customer ID');
check(count(customer_test_rows(request('admin/customer-detail.php?id=' . $mainId . '&page=999')[1])) === 3, 'History page outside range clamps to last');
check(str_contains(request('admin/customer-detail.php?id=' . $mainId . '&page%5B%5D=2')[1],'Trang 1/3'), 'Invalid history page handled safely');
[$status,$html] = request('admin/customer-detail.php?id=2');
$stats = customer_test_stats($html);
check($status === 200 && str_contains($html,'Khách hàng chưa có đơn hàng.') && $stats['Tổng số đơn'] === '0' && $stats['Đã hoàn thành'] === '0' && $stats['Đã hủy'] === '0' && $stats['Tổng tiền hoàn thành'] === '0,00 ₫', 'Empty customer detail shows zero statistics');
foreach (['','abc','0','-1','1','999999','2147483648','1.5','1%20OR%201=1'] as $badId) {
    [$status,$html] = request('admin/customer-detail.php?id=' . $badId);
    check($status === 404 && str_contains($html,'app-sidebar') && str_contains($html,'Quay về danh sách khách hàng'), 'Layout 404 for invalid, absent or admin ID: ' . $badId);
}
check(request('admin/customer-detail.php')[0] === 404, 'Missing ID returns 404');
check(request('admin/customer-detail.php?id%5B%5D=2')[0] === 404, 'Array ID returns 404');
$html = request('admin/customers.php?q=Client%2022')[1];
check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html,'<script>alert(1)</script>'), 'Customer fullname escaped in list');
$html = request('admin/customer-detail.php?id=' . end($clientIds))[1];
check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;'), 'Customer fullname escaped in detail');
foreach (['customers.php','customer-detail.php?id=' . $mainId,'customer-detail.php?id=1','products.php','categories.php'] as $path) {
    $html = request('admin/' . $path)[1];
    check(substr_count($html,'class="nav-link active"') === 1, 'One active sidebar item: ' . $path);
}
// Simulate an orders read failure only in the isolated schema.
$db->query('RENAME TABLE orders TO isolated_orders_backup');
try {
    foreach (['customers.php','customer-detail.php?id=' . $mainId] as $path) {
        [$status,$html] = request('admin/' . $path);
        check($status === 503 && str_contains($html,'Vui lòng thử lại sau.') && !str_contains($html,'SQLSTATE') && !str_contains($html,$testName), 'Database error remains private: ' . $path);
    }
} finally {
    $db->query('RENAME TABLE isolated_orders_backup TO orders');
}
check($usersBefore === $db->query('SELECT id,fullname,email,phone,role,created_at FROM users ORDER BY id')->fetch_all(MYSQLI_ASSOC), 'Customer pages do not mutate users');
check($ordersBefore === $db->query('SELECT id,user_id,fullname,phone,address,note,total,status,created_at FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC), 'Customer pages do not mutate orders');
echo 'Customer checks completed: ' . ($checks - $customerChecksStart) . PHP_EOL;
