<?php
// CLI only. Copies PHP sources and table schemas; never writes to the live database.
if (PHP_SAPI !== 'cli' || !in_array('--isolated', $argv, true)) {
    exit("Run: php tests/storefront-addresses.php --isolated\n");
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
    $db->query("INSERT INTO products(id,category_id,name,price,stock) VALUES(1,1,'Address checkout',12.34,20)");
    $db->query("UPDATE users SET phone='0900000000' WHERE id=2");
    $db->query("INSERT INTO users(id,fullname,email,password,phone,role) VALUES(3,'Other','other@test.invalid','unused','0900000003','customer')");
    file_put_contents($app.'/address-fixture.php', '<?php session_start(); $_SESSION=["cart"=>[1=>1]]; if((int)$_GET["id"]) $_SESSION["user"]=["id"=>(int)$_GET["id"]];');
    $seed=static fn($id)=>request('address-fixture.php?id='.$id);
    $token=static function($html,$field='csrf_token'){preg_match('/name="'.$field.'" value="([^"]+)"/',$html,$m);return $m[1]??'';};
    $seed(2);
    check(request('pages/checkout.php')[0]===200,'Checkout available before migration');
    check(request('pages/addresses.php')[0]===503,'Missing migration safe message');
    $db->query(file_get_contents($root.'/docs/migrations/phase-21.2-shipping-addresses.sql'));
    check($db->query('SELECT COUNT(*) FROM shipping_addresses')->fetch_row()[0]==0,'Migration creates empty table');
    $seed(0);
    check(request('pages/addresses.php')[0]===302 && request('actions/addresses.php',['operation'=>'save'])[0]===302,'Guest denied');
    $seed(1);
    check(request('pages/addresses.php')[0]===403 && request('actions/addresses.php',['operation'=>'save'])[0]===403,'Admin denied');
    $seed(2);
    $html=request('pages/addresses.php')[1]; $csrf=$token($html);
    check(str_contains($html,'Bạn chưa lưu địa chỉ nào.'),'Empty address state');
    check(request('actions/addresses.php')[0]===405,'POST only');
    $base=['csrf_token'=>$csrf,'operation'=>'save','id'=>'','fullname'=>'Receiver','phone'=>'0901234567','address'=>'Street A','user_id'=>'3'];
    $rows=static fn()=>$db->query('SELECT * FROM shipping_addresses ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    foreach ([['csrf_token'=>'bad'],['fullname'=>''],['phone'=>'bad'],['address'=>str_repeat('á',501)],['address'=>['bad']],['id'=>'1 OR 1=1'],['operation'=>'bad']] as $bad) {
        check(request('actions/addresses.php',array_replace($base,$bad))[0]===303 && !$rows(),'Reject invalid '.key($bad));
    }
    request('actions/addresses.php',$base); $a=(int)$rows()[0]['id'];
    check(count($rows())===1 && (int)$rows()[0]['user_id']===2 && (int)$rows()[0]['is_default']===1,'First address default; injected owner ignored');
    request('actions/addresses.php',array_replace($base,['address'=>'Street B'])); $b=(int)$rows()[1]['id'];
    check(count($rows())===2 && (int)$rows()[1]['is_default']===0,'Multiple addresses');
    foreach (['delete','default'] as $operation) {
        $before=$rows();
        request('actions/addresses.php',array_replace($base,['operation'=>$operation,'id'=>$a,'csrf_token'=>'bad']));
        check($rows()===$before,'CSRF protects '.$operation);
    }
    $before=$rows();
    try {$db->query("UPDATE shipping_addresses SET is_default=1 WHERE id=$b"); check(false,'Unique default');}
    catch(mysqli_sql_exception $e){check($e->getCode()===1062 && $rows()===$before,'Database rejects second default');}
    request('actions/addresses.php',array_replace($base,['operation'=>'default','id'=>$b]));
    check((int)$rows()[1]['is_default']===1 && (int)$rows()[0]['is_default']===0,'Switch default atomically');
    $before=$rows();
    request('actions/addresses.php',array_replace($base,['operation'=>'default','id'=>$b]));
    check($rows()===$before,'Repeated default POST stable');
    request('actions/addresses.php',array_replace($base,['operation'=>'default','id'=>'2147483647']));
    check($rows()===$before,'Missing target rolls back without clearing default');
    request('actions/addresses.php',array_replace($base,['id'=>$a,'address'=>"<script>alert(1)</script> ' OR 1=1 --"]));
    $html=request('pages/addresses.php?id='.$a)[1];
    check(str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>alert(1)</script>'),'Edit and XSS escaping');
    $seed(3); $otherToken=$token(request('pages/addresses.php')[1]);
    check(!str_contains(request('pages/addresses.php')[1],'Street B') && request('pages/addresses.php?id='.$a)[0]===404,'Lists and edit scoped to customer');
    foreach(['save','delete','default'] as $op) {
        $before=$rows();
        request('actions/addresses.php',array_replace($base,['csrf_token'=>$otherToken,'operation'=>$op,'id'=>$a]));
        check($rows()===$before,'IDOR denied '.$op);
    }
    request('actions/addresses.php',array_replace($base,['csrf_token'=>$otherToken,'address'=>'Other private']));
    $other=(int)$rows()[2]['id'];
    $seed(2);
    $html=request('pages/checkout.php')[1];
    check(str_contains($html,'Street B') && str_contains($html,'value="'.$b.'" selected') && !str_contains($html,'Other private'),'Checkout prefills default; only own choices');
    $checkout=['csrf_token'=>$token($html),'submit_token'=>$token($html,'submit_token'),'address_id'=>$other,'fullname'=>'Manual','phone'=>'0901111111','address'=>'Manual street','note'=>''];
    $countOrders=static fn()=>(int)$db->query('SELECT COUNT(*) FROM orders')->fetch_row()[0];
    $stock=static fn()=>(int)$db->query('SELECT stock FROM products WHERE id=1')->fetch_row()[0];
    foreach ([['address_id'=>$other],['address_id'=>'999999'],['address_id'=>['bad']],['csrf_token'=>'bad']] as $bad) {
        request('actions/checkout.php',array_replace($checkout,$bad));
        check($countOrders()===0 && $stock()===20,'Invalid checkout address/CSRF leaves stock and orders');
    }
    $html=request('pages/checkout.php')[1];
    check(str_contains($html,'Manual street') && !str_contains($html,'value="'.$b.'" selected'),'Draft takes precedence over default');
    $checkout['address_id']=$b;
    [$status,,$headers]=request('actions/checkout.php',$checkout);
    $order=$db->query('SELECT * FROM orders ORDER BY id DESC LIMIT 1')->fetch_assoc();
    check($status===303 && str_contains($headers,'order-success.php') && $order['address']==='Street B' && $order['fullname']==='Receiver' && $order['phone']==='0901234567','Checkout saved recipient reloaded server-side');
    check($stock()===19 && $order['total']==='12.34','Existing checkout deduction and total preserved');
    request('actions/checkout.php',$checkout);
    check($countOrders()===1 && $stock()===19,'One-time checkout token still enforced');
    $seed(2); $csrf=$token(request('pages/addresses.php')[1]);
    request('actions/addresses.php',array_replace($base,['csrf_token'=>$csrf,'operation'=>'delete','id'=>$b]));
    check((int)$rows()[0]['is_default']===1 && count($rows())===2,'Delete default promotes oldest own address');
    check($db->query('SELECT * FROM orders ORDER BY id LIMIT 1')->fetch_assoc()===$order,'Deleting saved address leaves historical order unchanged');
    $html=request('pages/checkout.php')[1];
    $manual=['csrf_token'=>$token($html),'submit_token'=>$token($html,'submit_token'),'address_id'=>'','fullname'=>'Custom receiver','phone'=>'0902222222','address'=>'Custom street','note'=>'Custom note'];
    request('actions/checkout.php',$manual);
    check($countOrders()===2 && $db->query('SELECT address FROM orders ORDER BY id DESC LIMIT 1')->fetch_row()[0]==='Custom street','Manual address checkout');
    $seed(0);$html=request('pages/checkout.php')[1];
    $guest=array_replace($manual,['csrf_token'=>$token($html),'submit_token'=>$token($html,'submit_token'),'address_id'=>$a]);
    request('actions/checkout.php',$guest);check($countOrders()===2,'Guest cannot select saved address');
    $guest['address_id']='';request('actions/checkout.php',$guest);
    $last=$db->query('SELECT user_id,address FROM orders ORDER BY id DESC LIMIT 1')->fetch_assoc();
    check($countOrders()===3 && $last['user_id']===null && $last['address']==='Custom street','Guest manual checkout preserved');
    $seed(1);$html=request('pages/checkout.php')[1];
    request('actions/checkout.php',array_replace($guest,['csrf_token'=>$token($html),'submit_token'=>$token($html,'submit_token'),'address_id'=>$a]));
    check($countOrders()===3,'Admin cannot use customer address');
    $seed(2);$csrf=$token(request('pages/addresses.php')[1]);
    request('actions/addresses.php',array_replace($base,['csrf_token'=>$csrf,'operation'=>'delete','id'=>$a]));
    check(str_contains(request('pages/addresses.php')[1],'Bạn chưa lưu địa chỉ nào.'),'Deleting last address safe');
    $db->query('DELETE FROM users WHERE id=3');
    check(!$rows(),'Foreign key cascade applies only to fixture owner');
    check(request('backend/Models/ShippingAddress.php')[0]===403 && request('frontend/Views/storefront/profile/addresses.php')[0]===403,'Internal files guarded');
    check(request('assets/js/checkout-address.js')[0]===200,'Checkout address JavaScript served');
    echo "Completed $checks address checks. Live database untouched.\n";
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
