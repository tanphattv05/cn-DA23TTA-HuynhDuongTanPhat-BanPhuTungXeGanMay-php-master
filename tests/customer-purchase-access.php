<?php
// CLI only. Copies PHP sources and table schemas; never writes to the live database.
if (PHP_SAPI !== 'cli' || !in_array('--isolated', $argv, true)) {
    exit("Run: php tests/customer-purchase-access.php --isolated\n");
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$root = dirname(__DIR__);
define('MOTOPARTS_MVC_ENTRY', true); require $root . '/scr/backend/config/database.php';
$live = $conn;
$testName = 'motoparts_test_' . bin2hex(random_bytes(6));
$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $testName;
$project = 'cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master';
$app = $temp . '/' . $project . '/scr';
$server = null;
$created = false;
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo "PASS: $label\n";
}
function request($path, $data = null) {
    global $port, $project, $cookie;
    $ch = curl_init('http://127.0.0.1:' . $port . '/' . $project . '/scr/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie, CURLOPT_TIMEOUT => 10]);
    if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
    $raw = curl_exec($ch);
    if ($raw === false) throw new RuntimeException(curl_error($ch));
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$status, substr($raw, $headerSize), substr($raw, 0, $headerSize)];
}
try {
    $live->query("CREATE DATABASE `$testName` CHARACTER SET utf8mb4");
    $created = true;
    $db = new mysqli($host, $username, $password, $testName);
    $db->set_charset('utf8mb4');
    foreach (['categories', 'products', 'users', 'orders', 'order_details'] as $table) {
        $schema = $live->query('SHOW CREATE TABLE ' . $table)->fetch_row()[1];
        $schema = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $schema);
        $db->query($schema);
    }
    $db->query("INSERT INTO categories (id,name) VALUES (1,'Test category'),(2,'Other')");
    $db->query("INSERT INTO users (id,fullname,email,password,role) VALUES (1,'Admin','admin@test.invalid','unused','admin'),(2,'Customer','customer@test.invalid','unused','customer')");
    mkdir($app, 0777, true);
    mkdir($app . '/assets/css', 0777, true);
    copy($root . '/scr/assets/css/style.css', $app . '/assets/css/style.css');
    mkdir($app . '/assets/js', 0777, true);
    copy($root . '/scr/assets/js/checkout-address.js', $app . '/assets/js/checkout-address.js');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/scr', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), 'adminlte')) continue;
        $relative = substr($file->getPathname(), strlen($root . '/scr') + 1);
        $target = $app . '/' . $relative;
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
        copy($file->getPathname(), $target);
    }
    mkdir($app . '/assets/images/products', 0777, true);
    $config = "<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); \$conn = new mysqli("
        . var_export($host, true) . ',' . var_export($username, true) . ',' . var_export($password, true)
        . ',' . var_export($testName, true) . "); \$conn->set_charset('utf8mb4');";
    file_put_contents($app . '/backend/config/database.php', $config);
    // This fixture exists only in the temporary copy.
    file_put_contents($app . '/test-session.php', '<?php session_start(); $_SESSION["user"] = ["id" => (int) $_GET["id"]];');
    $cookie = $temp . '/cookies.txt';
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    $port = (int) substr(strrchr($address, ':'), 1);
    fclose($socket);
    $server = proc_open([PHP_BINARY, '-d', 'upload_max_filesize=4M', '-d', 'post_max_size=8M', '-S', '127.0.0.1:' . $port, '-t', $temp],
        [0 => ['pipe','r'], 1 => ['file',$temp . '/server.log','a'], 2 => ['file',$temp . '/server.log','a']], $pipes);
    if (!is_resource($server)) throw new RuntimeException('Cannot start isolated PHP server.');
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $probe = @fsockopen('127.0.0.1', $port);
        if ($probe) { fclose($probe); break; }
        usleep(100000);
    }
    $db->query("INSERT INTO products(id,category_id,name,price,stock) VALUES(1,1,'Fixture',12.34,20)");
    $hash=password_hash('Fixture123',PASSWORD_DEFAULT);$stmt=$db->prepare('UPDATE users SET password=?');$stmt->bind_param('s',$hash);$stmt->execute();
    file_put_contents($app.'/state.php','<?php session_start(); if(isset($_GET["seed"])) { $_SESSION=["cart"=>[1=>5]]; if((int)$_GET["seed"]) $_SESSION["user"]=["id"=>(int)$_GET["seed"]]; } echo json_encode($_SESSION);');
    $state=static fn()=>json_decode(request('state.php')[1],true);
    $token=static function($html,$name='csrf_token'){preg_match('/name="'.$name.'" value="([^"]+)"/',$html,$m);return $m[1]??'';};
    foreach([0=>302,1=>403] as $id=>$expected) {
        request('state.php?seed='.$id);
        foreach(['pages/cart.php','pages/checkout.php','pages/order-success.php','pages/my-orders.php','pages/order-detail.php?id=1','pages/profile.php','pages/addresses.php','pages/favorites.php'] as $path) check(request($path)[0]===$expected,'Access '.$id.' '.$path);
        foreach(['actions/add_cart.php','actions/update_cart.php','actions/remove_cart.php','actions/checkout.php'] as $path) {
            check(request($path,['product_id'=>1,'quantity'=>1,'user_id'=>2,'role'=>'customer'])[0]===$expected,'POST '.$id.' '.$path);
            check(request($path)[0]===$expected,'GET '.$id.' '.$path);
        }
        check(empty($state()['cart']),'Unauthorized cart discarded');
    }
    request('state.php?seed=0');
    check(request('pages/products.php')[0]===200 && request('pages/product-detail.php?id=1')[0]===200,'Public catalog accessible');
    request('actions/add_cart.php',['product_id'=>1,'quantity'=>1,'return'=>'https://evil.invalid']);
    $html=request('pages/login.php')[1];check(str_contains($html,'Vui lòng đăng nhập để mua hàng'),'Purchase message');
    [$status,,$headers]=request('actions/login.php',['csrf_token'=>$token($html),'email'=>'customer@test.invalid','password'=>'Fixture123','return'=>'https://evil.invalid']);
    check($status===303 && str_contains($headers,'../pages/product-detail.php?id=1') && !str_contains($headers,'evil.invalid'),'Safe product return');
    check(empty($state()['cart']) && $state()['cart_owner']===2,'No guest cart or POST replay');
    $csrf=$token(request('pages/product-detail.php?id=1')[1]);
    request('actions/add_cart.php',['product_id'=>1,'quantity'=>2,'csrf_token'=>'bad']);check(empty($state()['cart']),'Add CSRF');
    request('actions/add_cart.php',['product_id'=>1,'quantity'=>2,'csrf_token'=>$csrf]);check(($state()['cart'][1]??0)===2,'Customer add');
    foreach(['actions/update_cart.php','actions/remove_cart.php'] as $path) {request($path,['csrf_token'=>'bad','id'=>1,'quantities'=>[1=>1]]);check($state()['cart'][1]===2,'CSRF '.$path);}
    request('actions/update_cart.php',['csrf_token'=>$csrf,'quantities'=>[1=>1]]);check($state()['cart'][1]===1,'Customer update');
    request('actions/remove_cart.php',['csrf_token'=>$csrf,'id'=>1]);check(empty($state()['cart']),'Customer remove');
    request('actions/add_cart.php',['product_id'=>1,'quantity'=>1,'csrf_token'=>$csrf]);
    $html=request('pages/checkout.php')[1];
    $post=['csrf_token'=>$token($html),'submit_token'=>$token($html,'submit_token'),'fullname'=>'Receiver','phone'=>'0901234567','address'=>'Fixture street','note'=>'','user_id'=>1];
    request('actions/checkout.php',array_replace($post,['csrf_token'=>'bad']));check($db->query('SELECT COUNT(*) FROM orders')->fetch_row()[0]==0,'Checkout CSRF');
    request('actions/checkout.php',$post);$order=$db->query('SELECT * FROM orders')->fetch_assoc();
    check($order && (int)$order['user_id']===2 && $order['total']==='12.34','Order session owner');
    request('actions/checkout.php',$post);check($db->query('SELECT COUNT(*) FROM orders')->fetch_row()[0]==1 && $db->query('SELECT stock FROM products WHERE id=1')->fetch_row()[0]==19,'No duplicate deduction');
    check(request('pages/order-detail.php?id='.$order['id'])[0]===200,'Own order');
    $db->query("INSERT INTO users(id,fullname,email,password,role) VALUES(3,'Other','other@test.invalid','unused','customer')");
    request('actions/add_cart.php',['product_id'=>1,'quantity'=>1,'csrf_token'=>$csrf]);
    request('test-session.php?id=3');check(request('pages/order-detail.php?id='.$order['id'])[0]===404,'Other order denied');
    request('pages/cart.php');check(empty($state()['cart']) && $state()['cart_owner']===3,'No cross-account cart');
    $csrf=$token(request('pages/product-detail.php?id=1')[1]);request('actions/add_cart.php',['product_id'=>1,'quantity'=>1,'csrf_token'=>$csrf]);
    request('actions/logout.php',['auth_csrf_token'=>$token(request('pages/cart.php')[1],'auth_csrf_token')]);check(empty($state()['user']) && empty($state()['cart']),'Logout clears cart');
    request('actions/add_cart.php',['product_id'=>'//evil.invalid','return'=>'https://evil.invalid']);$html=request('pages/login.php')[1];
    [$status,,$headers]=request('actions/login.php',['csrf_token'=>$token($html),'email'=>'customer@test.invalid','password'=>'Fixture123']);check(str_contains($headers,'../index.php') && !str_contains($headers,'evil.invalid'),'Reject unsafe return');
    echo "Completed $checks purchase access checks. Live database untouched.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($created) $live->query("DROP DATABASE `$testName`");
    // Only remove the random temporary directory created by this process.
    $resolved = realpath($temp);
    $tempRoot = realpath(sys_get_temp_dir());
    if ($resolved && dirname($resolved) === $tempRoot && basename($resolved) === $testName) {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($resolved);
    }
}
