<?php
if (PHP_SAPI !== 'cli' || !isset($db, $live, $app)) {
    exit("Run: php tests/product-management.php --isolated\n");
}
$orderChecksStart = $checks;
// Replace only the earlier minimal fixture in the temporary test database.
$db->query('RENAME TABLE order_details TO historical_price_fixture');
$schema = $live->query('SHOW CREATE TABLE order_details')->fetch_row()[1];
$db->query(preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $schema));
function fixture_order($owner, string $status = 'pending', string $total = '24.98', ?string $note = null): int {
    global $db;
    $stmt = $db->prepare('INSERT INTO orders(user_id,fullname,phone,address,note,total,status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $name = 'Recipient <script>test</script>';
    $phone = '0901234567';
    $address = "Saved address\n<em>building</em>";
    $stmt->bind_param('issssss', $owner, $name, $phone, $address, $note, $total, $status);
    $stmt->execute();
    $id = $db->insert_id;
    $stmt = $db->prepare('INSERT INTO order_details(order_id,product_id,quantity,price) VALUES (?,1,2,12.34),(?,2,3,0.10)');
    $stmt->bind_param('ii', $id, $id);
    $stmt->execute();
    return $id;
}
function order_test_options(string $html): array {
    $result = [];
    foreach (customer_test_dom($html)->query('//select[@name="status"]/option') as $option) {
        if ($option->getAttribute('value') !== '') $result[] = $option->getAttribute('value');
    }
    return $result;
}
function order_test_stock(): array {
    global $db;
    return $db->query('SELECT id,stock FROM products ORDER BY id')->fetch_all(MYSQLI_ASSOC);
}
function order_test_status(int $id): string {
    global $db;
    return $db->query('SELECT status FROM orders WHERE id=' . $id)->fetch_row()[0];
}
$registered = fixture_order(2, 'pending', '24.98', '<b>Saved note</b>');
$guest = fixture_order(null);
$stateIds = [];
foreach (['pending','confirmed','shipping','completed','cancelled'] as $state) $stateIds[$state] = fixture_order(2, $state);
$db->query("UPDATE products SET name='Renamed <b>product</b>',price=9999.99 WHERE id=1");
$beforeStock = order_test_stock();
$beforeOrders = $db->query('SELECT id,status,total FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC);
[$status,$html] = request('admin/order-detail.php?id=' . $registered);
check($status === 200 && str_contains($html,'customer-detail.php?id=2'), 'Registered order links to admin customer');
check(str_contains($html,'Recipient &lt;script&gt;test&lt;/script&gt;') && str_contains($html,'Saved address') && str_contains($html,'&lt;b&gt;Saved note&lt;/b&gt;'), 'Order recipient snapshot and note escaped');
check(str_contains($html,'Renamed &lt;b&gt;product&lt;/b&gt;') && str_contains($html,'12,34 ₫') && str_contains($html,'24,68 ₫') && str_contains($html,'0,30 ₫') && !str_contains($html,'9.999,99'), 'Historical prices and decimal line amounts independent of current price');
check(str_contains($html,'24,98 ₫') && !str_contains($html,'alert-warning'), 'Exact decimal total matches without float discrepancy');
check(str_contains($html,'<img src='), 'Existing product image displayed');
check(str_contains($html,'action="update-order.php"') && str_contains($html,'name="return_to" value="detail"') && str_contains($html,'name="csrf_token"'), 'Detail uses existing endpoint and CSRF');
[$status,$html] = request('admin/order-detail.php?id=' . $guest);
check($status === 200 && str_contains($html,'Khách vãng lai') && !str_contains($html,'href="customer-detail.php'), 'Guest order never inferred from matching recipient');
check(str_contains($html,'Không có ghi chú.'), 'NULL note is handled');
$expected = ['pending'=>['confirmed','cancelled'],'confirmed'=>['shipping','cancelled'],'shipping'=>['completed'],'completed'=>[],'cancelled'=>[]];
foreach ($stateIds as $state=>$orderId) {
    $html = request('admin/order-detail.php?id=' . $orderId)[1];
    check(order_test_options($html) === $expected[$state], 'Allowed transitions only: ' . $state);
    if (!$expected[$state]) check(!str_contains($html,'action="update-order.php"'), 'Terminal order has no update form: ' . $state);
    check(substr_count($html,'class="nav-link active"') === 1, 'Order sidebar active: ' . $state);
}
foreach (['','abc','0','-1','2147483648','999999','1.5'] as $badId) {
    [$status,$html] = request('admin/order-detail.php?id=' . $badId);
    check($status === 404 && str_contains($html,'app-sidebar') && str_contains($html,'Quay lại danh sách đơn'), 'Layout 404: ' . $badId);
}
check(request('admin/order-detail.php')[0] === 404 && request('admin/order-detail.php?id%5B%5D=1')[0] === 404, 'Missing and array IDs rejected');
check($beforeStock === order_test_stock() && $beforeOrders === $db->query('SELECT id,status,total FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC), 'GET detail never changes orders or stock');
check(str_contains(request('admin/orders.php')[1], 'order-detail.php?id=' . $registered), 'Order list links to admin detail');
$index = request('admin/index.php')[1];
check(substr_count($index,'>Xem chi tiết</a>') === 10 && str_contains($index,'order-detail.php?id=' . $guest), 'Dashboard ten latest orders link to detail');
$customerHtml = request('admin/customer-detail.php?id=2')[1];
check(str_contains($customerHtml,'href="order-detail.php?id=' . $registered), 'Customer history links to admin detail');
$db->query('UPDATE orders SET total=25.00 WHERE id=' . $guest);
$html = request('admin/order-detail.php?id=' . $guest)[1];
check(str_contains($html,'alert-warning') && str_contains($html,'25,00 ₫') && str_contains($html,'24,98 ₫'), 'Mismatch displays stored and calculated totals');
check($db->query('SELECT total FROM orders WHERE id=' . $guest)->fetch_row()[0] === '25.00', 'Mismatch does not silently alter total');
// Broken-reference fixtures are permitted only on this isolated DB connection.
$db->query('SET FOREIGN_KEY_CHECKS=0');
try {
    $missingAccount = fixture_order(999999);
    $missingProduct = fixture_order(null, 'pending', '25.98');
    $db->query('INSERT INTO order_details(order_id,product_id,quantity,price) VALUES (' . $missingProduct . ',999999,1,1.00)');
} finally {
    $db->query('SET FOREIGN_KEY_CHECKS=1');
}
[$status,$html] = request('admin/order-detail.php?id=' . $missingAccount);
check($status === 200 && str_contains($html,'Không còn tài khoản khách hàng') && !str_contains($html,'href="customer-detail.php'), 'Missing account does not break detail');
[$status,$html] = request('admin/order-detail.php?id=' . $missingProduct);
check($status === 200 && str_contains($html,'Sản phẩm không còn trong hệ thống (#999999)') && !str_contains($html,'alert-warning'), 'LEFT JOIN retains missing product and historical amount');
$adminCookie = $cookie;
$cookie = $temp . '/anonymous-order-detail.txt';
check(request('admin/order-detail.php?id=' . $registered)[0] === 302, 'Anonymous detail denied');
check(request('admin/update-order.php',['order_id'=>$registered,'status'=>'cancelled'])[0] === 302, 'Anonymous update denied');
$cookie = $adminCookie;
request('test-session.php?id=2');
check(request('admin/order-detail.php?id=' . $registered)[0] === 403, 'Customer detail denied');
check(request('admin/update-order.php',['order_id'=>$registered,'status'=>'cancelled','csrf_token'=>$token])[0] === 403, 'Customer update denied');
request('test-session.php?id=1');
$post = ['order_id'=>(string)$registered,'status'=>'cancelled','csrf_token'=>$token,'return_to'=>'detail'];
$beforeStock = order_test_stock();
[$status,,$headers] = request('admin/update-order.php',array_replace($post,['csrf_token'=>'invalid']));
check($status === 303 && str_contains($headers,'/scr/admin/order-detail.php?id=' . $registered), 'CSRF error redirects to same detail');
check(str_contains(request('admin/order-detail.php?id=' . $registered)[1],'Yêu cầu không hợp lệ') && order_test_status($registered) === 'pending' && $beforeStock === order_test_stock(), 'CSRF error flash and no mutation');
[$status,,$headers] = request('admin/update-order.php',array_replace($post,['status'=>'completed']));
check($status === 303 && str_contains($headers,'order-detail.php?id=' . $registered), 'Invalid transition returns to detail');
check(order_test_status($registered) === 'pending' && $beforeStock === order_test_stock(), 'Invalid transition leaves stock and order unchanged');
check(str_contains(request('admin/order-detail.php?id=' . $registered)[1],'Không được chuyển'), 'Transition error flash shown');
[$status,,$headers] = request('admin/update-order.php',$post);
check($status === 303 && str_contains($headers,'order-detail.php?id=' . $registered) && order_test_status($registered) === 'cancelled', 'Cancel succeeds and returns to same order');
$afterStock = order_test_stock();
check((int)$afterStock[0]['stock'] === (int)$beforeStock[0]['stock']+2 && (int)$afterStock[1]['stock'] === (int)$beforeStock[1]['stock']+3, 'Cancellation restores exact quantities');
check(str_contains(request('admin/order-detail.php?id=' . $registered)[1],'Đã cập nhật đơn hàng #'), 'Success flash shown on detail');
request('admin/update-order.php',$post);
check($afterStock === order_test_stock() && order_test_status($registered) === 'cancelled', 'Repeated cancellation never restores stock twice');
foreach (['confirmed','shipping','completed'] as $next) {
    $response = request('admin/update-order.php',array_replace($post,['order_id'=>(string)$stateIds['pending'],'status'=>$next]));
    check($response[0] === 303 && order_test_status($stateIds['pending']) === $next && $afterStock === order_test_stock(), 'Forward transition preserves stock: ' . $next);
}
request('admin/update-order.php',array_replace($post,['order_id'=>(string)$stateIds['pending']]));
check(order_test_status($stateIds['pending']) === 'completed' && $afterStock === order_test_stock(), 'Completed order cannot be cancelled');
request('admin/update-order.php',array_replace($post,['order_id'=>(string)$stateIds['confirmed']]));
check(order_test_status($stateIds['confirmed']) === 'cancelled', 'Confirmed order can be cancelled');
$afterStock = order_test_stock();
foreach (['https://example.com','//example.com','order-detail.php?id=999','../pages/order-detail.php','detail%0d%0aLocation:test'] as $target) {
    [$status,,$headers] = request('admin/update-order.php',array_replace($post,['return_to'=>$target]));
    check($status === 303 && preg_match('~Location: [^\r\n]+/scr/admin/orders\.php\r?\n~', $headers), 'Untrusted return destination ignored');
}
$fromList = $post;
unset($fromList['return_to']);
check(str_contains(request('admin/update-order.php',$fromList)[2],'/scr/admin/orders.php'), 'Existing list form returns to list');
check(request('admin/update-order.php')[0] === 303 && $afterStock === order_test_stock(), 'GET update endpoint does not write');
$rollbackId = fixture_order(2);
$db->query("CREATE TRIGGER order_update_failure BEFORE UPDATE ON orders FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='private error'");
try {
    request('admin/update-order.php',array_replace($post,['order_id'=>(string)$rollbackId]));
    check(order_test_status($rollbackId) === 'pending' && $afterStock === order_test_stock(), 'Failure after stock restoration rolls back entire transaction');
    $html = request('admin/order-detail.php?id=' . $rollbackId)[1];
    check(str_contains($html,'Đơn hàng chưa được cập nhật') && !str_contains($html,'private error'), 'DB error message is generic');
} finally {
    $db->query('DROP TRIGGER order_update_failure');
}
request('admin/update-order.php',array_replace($post,['order_id'=>(string)$missingProduct]));
check(order_test_status($missingProduct) === 'pending' && $afterStock === order_test_stock(), 'Missing product aborts cancellation and rolls back earlier restores');
echo 'Order detail checks completed: ' . ($checks - $orderChecksStart) . PHP_EOL;
