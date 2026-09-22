<?php
if (PHP_SAPI !== 'cli' || !isset($app, $db)) exit("Run isolated harness.\n");
$orderMvcStart = $checks;
$internalPaths = [
    'app/Models/Order.php',
    'app/Controllers/Admin/OrderController.php',
    'app/Views/admin/orders/index.php',
    'app/Views/admin/orders/detail.php'
];
$originalCookie = $cookie;
foreach (['admin', 'anonymous', 'customer'] as $actor) {
    if ($actor === 'anonymous') {
        $cookie = $temp . '/anonymous-order-mvc.txt';
    } else {
        $cookie = $originalCookie;
        request('test-session.php?id=' . ($actor === 'admin' ? 1 : 2));
    }
    foreach ($internalPaths as $path) {
        [$status, $body] = request($path);
        check($status === 403 && $body === '', 'Order MVC internal denied to ' . $actor . ': ' . $path);
    }
    foreach (['orders.php', 'order-detail.php?id=' . $registered, 'update-order.php'] as $entry) {
        if ($actor === 'admin') continue;
        [$status, , $headers] = request('admin/' . $entry);
        check($actor === 'customer' ? $status === 403 : ($status === 302 && str_contains($headers, '/scr/pages/login.php')),
            'Legacy order MVC entry protected for ' . $actor . ': ' . $entry);
    }
}
$cookie = $originalCookie;
request('test-session.php?id=1');

$modelSource = file_get_contents($app . '/app/Models/Order.php');
check(!preg_match('/password|SELECT\s+(?:[a-z]+\.)?\*/i', $modelSource), 'Order model selects explicit columns without passwords');
check(!preg_match('/\$_(GET|POST|SESSION)|header\s*\(/', $modelSource), 'Order model has no HTTP/session behavior');
$controllerSource = file_get_contents($app . '/app/Controllers/Admin/OrderController.php');
check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/', $controllerSource), 'Order controller has no SQL');
foreach (['index', 'detail'] as $template) {
    $source = file_get_contents($app . '/app/Views/admin/orders/' . $template . '.php');
    check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|mysqli)\b|\$_(POST|GET|SESSION)/', $source), 'Order view has no SQL/request/session: ' . $template);
}
foreach (['orders.php', 'order-detail.php', 'update-order.php'] as $entry) {
    $source = file_get_contents($app . '/admin/' . $entry);
    check(str_contains($source, 'OrderController') && !preg_match('/\b(SELECT|mysqli)\b|\$_(GET|POST)/', $source), 'Legacy order URL is a thin entry: ' . $entry);
}

// Two real connections to the isolated database prove the model holds the row lock.
// No HTTP concurrency is assumed: the harness PHP web server is single-threaded.
if (!defined('MOTOPARTS_MVC_ENTRY')) define('MOTOPARTS_MVC_ENTRY', true);
require_once $app . '/app/bootstrap.php';
$lockOrder = fixture_order(2);
$beforeLockStock = order_test_stock();
$firstModel = new \MotoParts\App\Models\Order($db);
$secondConnection = new mysqli($host, $username, $password, $testName);
$secondConnection->set_charset('utf8mb4');
$secondConnection->query('SET SESSION innodb_lock_wait_timeout = 1');
$secondModel = new \MotoParts\App\Models\Order($secondConnection);
try {
    $firstModel->begin();
    check($firstModel->lock($lockOrder)['status'] === 'pending', 'Model reads current state under row lock');
    $secondModel->begin();
    $blocked = false;
    try {
        $secondModel->lock($lockOrder);
    } catch (mysqli_sql_exception $exception) {
        $blocked = $exception->getCode() === 1205;
    }
    check($blocked, 'Competing connection cannot acquire the same order lock');
    $secondModel->rollback();
    $firstModel->rollback();
    $secondModel->begin();
    check($secondModel->lock($lockOrder)['status'] === 'pending', 'Rollback releases row lock for next transaction');
    $secondModel->rollback();
    check(order_test_status($lockOrder) === 'pending' && $beforeLockStock === order_test_stock(), 'Lock contention test leaves order and stock unchanged');
} finally {
    $firstModel->rollback();
    $secondModel->rollback();
    $secondConnection->close();
}
echo 'Order MVC checks completed: ' . ($checks - $orderMvcStart) . PHP_EOL;