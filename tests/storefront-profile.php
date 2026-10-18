<?php
// CLI only. Copies PHP sources and table schemas; never writes to the live database.
if (PHP_SAPI !== 'cli' || !in_array('--isolated', $argv, true)) {
    exit("Run: php tests/storefront-profile.php --isolated\n");
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
    if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
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
    foreach (['categories', 'products', 'users'] as $table) {
        $schema = $live->query('SHOW CREATE TABLE ' . $table)->fetch_row()[1];
        $schema = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $schema);
        $db->query($schema);
    }
    $db->query("INSERT INTO categories (id,name) VALUES (1,'Test category'),(2,'Other')");
    $db->query("INSERT INTO users (id,fullname,email,password,role) VALUES (1,'Admin','admin@test.invalid','unused','admin'),(2,'Customer','customer@test.invalid','unused','customer')");
    mkdir($app, 0777, true);
    mkdir($app . '/assets/css', 0777, true);
    copy($root . '/scr/assets/css/style.css', $app . '/assets/css/style.css');
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
    check(request('pages/profile.php')[0]===302, 'Guest page denied');
    check(request('actions/profile.php', ['operation'=>'profile'])[0]===302, 'Guest write denied');
    request('test-session.php?id=1');
    check(request('pages/profile.php')[0]===403, 'Admin page denied');
    check(request('actions/profile.php', ['operation'=>'profile'])[0]===403, 'Admin write denied');
    request('test-session.php?id=2');
    [$status,$html]=request('pages/profile.php');
    check($status===200 && str_contains($html,'customer@test.invalid'), 'Customer profile displayed');
    preg_match('/name="csrf_token" value="([^"]+)"/', $html, $match); $token=$match[1];
    $snapshot=static fn()=>$db->query('SELECT * FROM users ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    $before=$snapshot();
    $valid=['operation'=>'profile','csrf_token'=>$token,'fullname'=>'New customer','email'=>'new@test.invalid','phone'=>'0901234567','user_id'=>'1','id'=>'1','role'=>'admin'];
    check(request('actions/profile.php')[0]===405, 'GET cannot write');
    foreach ([['csrf_token'=>'bad'],['email'=>'admin@test.invalid'],['email'=>'bad'],['phone'=>'abc'],['fullname'=>''],['fullname'=>str_repeat('á',101)],['email'=>['bad']]] as $bad) {
        check(request('actions/profile.php',array_replace($valid,$bad))[0]===303 && $snapshot()===$before, 'Invalid input rejected: '.key($bad));
    }
    check(request('actions/profile.php',$valid)[0]===303, 'Successful update redirects');
    $after=$snapshot();
    check($after[0]===$before[0] && $after[1]['role']==='customer' && $after[1]['email']==='new@test.invalid', 'IDOR/role injection ignored');
    check(str_contains(request('pages/profile.php')[1],'Cập nhật hồ sơ thành công.'), 'Success message');
    file_put_contents($app.'/profile-session.php', '<?php session_start(); echo json_encode($_SESSION["user"]);');
    $sessionUser=json_decode(request('profile-session.php')[1],true);
    check($sessionUser['email']==='new@test.invalid' && $sessionUser['fullname']==='New customer' && !isset($sessionUser['password']), 'Session synchronized without hash');
    request('actions/profile.php',$valid);
    check(str_contains(request('pages/profile.php')[1],'Cập nhật hồ sơ thành công.'),'Unchanged email accepted');
    $xss=array_replace($valid,['fullname'=>'<script>alert(1)</script>']); request('actions/profile.php',$xss);
    $html=request('pages/profile.php')[1];
    check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html,'<script>alert(1)</script>'), 'HTML escaped');
    $hash=password_hash('Original123',PASSWORD_DEFAULT);
    $stmt=$db->prepare('UPDATE users SET password=? WHERE id=2');$stmt->bind_param('s',$hash);$stmt->execute();
    $pass=['operation'=>'password','csrf_token'=>$token,'current_password'=>'Original123','new_password'=>'NewPassword123','password_confirm'=>'NewPassword123','user_id'=>1];
    $before=$snapshot();
    foreach ([['current_password'=>'wrong'],['password_confirm'=>'wrong'],['new_password'=>'short'],['new_password'=>str_repeat('a',73)],['csrf_token'=>'bad'],['current_password'=>['bad']]] as $bad) {
        check(request('actions/profile.php',array_replace($pass,$bad))[0]===303 && $snapshot()===$before, 'Password rejection: '.key($bad));
    }
    [$status,,$headers]=request('actions/profile.php',$pass);
    check($status===303 && str_contains($headers,'PHPSESSID='), 'Password change rotates session');
    $after=$snapshot();
    check(password_verify('NewPassword123',$after[1]['password']) && !password_verify('Original123',$after[1]['password']) && $after[0]===$before[0], 'Password hash and ownership');
    $html=request('pages/profile.php')[1];
    check(str_contains($html,'Đổi mật khẩu thành công.') && !str_contains($html,'NewPassword123') && !str_contains($html,$after[1]['password']), 'Password never rendered');
    preg_match('/name="csrf_token" value="([^"]+)"/', $html, $match);
    check($match[1]!==$token,'CSRF rotated after password change');
    check(request('actions/profile.php',$pass)[0]===303 && $snapshot()===$after,'Repeated password POST rejected');
    check(request('backend/Models/Profile.php')[0]===403 && request('frontend/Views/storefront/profile/index.php')[0]===403,'Internal PHP guarded');
    request('actions/profile.php', array_replace($valid,['csrf_token'=>$match[1],'email'=>'invalid','fullname'=>'PRIVATE DRAFT']));
    $db->query("INSERT INTO users(id,fullname,email,password,role) VALUES(3,'Other','other@test.invalid','unused','customer')");
    request('test-session.php?id=3');
    check(!str_contains(request('pages/profile.php')[1],'PRIVATE DRAFT'),'Profile draft isolated across account switches');
    echo "Completed $checks profile checks; live database untouched.\n";
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
