<?php
// CLI only. Copies PHP sources and table schemas; never writes to the live database.
if (PHP_SAPI !== 'cli' || !in_array('--isolated', $argv, true)) {
    exit("Run: php tests/product-management.php --isolated\n");
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$root = dirname(__DIR__);
require $root . '/scr/config/database.php';
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
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/scr', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), 'adminlte')) continue;
        $relative = substr($file->getPathname(), strlen($root . '/scr') + 1);
        $target = $app . '/' . $relative;
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
        copy($file->getPathname(), $target);
    }
    mkdir($app . '/assets/images/products', 0777, true);
    $config = "<?php mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); \$conn = new mysqli("
        . var_export($host, true) . ',' . var_export($username, true) . ',' . var_export($password, true)
        . ',' . var_export($testName, true) . "); \$conn->set_charset('utf8mb4');";
    file_put_contents($app . '/config/database.php', $config);
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
    check(request('admin/products.php')[0] === 302, 'Anonymous redirected to login');
    request('test-session.php?id=2');
    foreach (['products.php','product-form.php','save-product.php','includes/product-bootstrap.php','includes/product-upload.php','includes/product-validation.php'] as $endpoint) {
        check(request('admin/' . $endpoint)[0] === 403, 'Customer denied ' . $endpoint);
    }
    check(request('admin/save-product.php', ['name'=>'attack'])[0] === 403, 'Customer POST denied');
    request('test-session.php?id=1');
    [$status,$body] = request('admin/product-form.php');
    check($status === 200, 'Admin form loads');
    preg_match('/name="csrf_token" value="([^"]+)"/', $body, $match);
    $token = $match[1] ?? '';
    check(strlen($token) === 64, 'CSRF generated');
    check(request('admin/save-product.php')[0] === 405, 'GET write endpoint denied');
    $data = ['csrf_token'=>$token, 'id'=>'', 'name'=>'Isolated sample', 'category_id'=>'1', 'price'=>'123.45', 'brand'=>'TestBrand', 'stock'=>'8', 'description'=>'Test description'];
    $png = $temp . '/image.png';
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $upload = new CURLFile($png, 'image/png', 'fake.php');
    check(request('admin/save-product.php', $data + ['image'=>$upload])[0] === 303, 'Create uses redirect after POST');
    $product = $db->query('SELECT * FROM products')->fetch_assoc();
    check($product && $product['price'] === '123.45' && $product['stock'] === '8', 'Valid product persisted');
    check(preg_match('/^[a-f0-9]{48}\.png$/', $product['image']) && is_file($app . '/assets/images/products/' . $product['image']), 'Actual MIME and server-generated filename');
    $oldImage = $product['image'];
    // Minimal historical-price fixture; no orders are placed.
    $db->query('CREATE TABLE order_details (product_id INT NOT NULL, price DECIMAL(12,2) NOT NULL)');
    $db->query('INSERT INTO order_details VALUES (' . (int) $product['id'] . ',123.45)');
    $data['id'] = (string) $product['id'];
    $data['name'] = 'Edited sample';
    $data['price'] = '456.78';
    request('admin/save-product.php', $data);
    $product = $db->query('SELECT * FROM products')->fetch_assoc();
    check($product['name'] === 'Edited sample' && $product['image'] === $oldImage, 'Edit without upload keeps image');
    check($db->query('SELECT price FROM order_details')->fetch_row()[0] === '123.45', 'Editing product leaves historical price unchanged');
    request('admin/save-product.php', $data + ['image'=>$upload]);
    $product = $db->query('SELECT * FROM products')->fetch_assoc();
    check($product['image'] !== $oldImage && is_file($app . '/assets/images/products/' . $oldImage), 'Replace image preserves old file');
    check(str_contains(request('pages/products.php')[1], 'Edited sample'), 'New product visible on customer page');
    foreach ([
        ['price'=>'-1'], ['price'=>'1.001'], ['price'=>'10000000000'], ['stock'=>'-1'], ['stock'=>'1.5'],
        ['category_id'=>'99999'], ['id'=>'99999'], ['csrf_token'=>'invalid'], ['name'=>str_repeat('x',256)], ['brand'=>str_repeat('x',101)]
    ] as $invalid) {
        $before = $db->query('SELECT * FROM products')->fetch_assoc();
        check(request('admin/save-product.php', array_replace($data,$invalid))[0] === 303, 'Invalid ' . key($invalid) . ' redirects');
        check($before === $db->query('SELECT * FROM products')->fetch_assoc(), 'Invalid ' . key($invalid) . ' leaves database unchanged');
    }
    request('admin/save-product.php', array_replace($data, ['price'=>'-1']));
    $errorForm = request('admin/product-form.php?id=' . $data['id'])[1];
    check(str_contains($errorForm,'value="-1"') && str_contains($errorForm,'alert-danger'), 'Validation error preserves fields');
    $bad = $temp . '/bad.png';
    file_put_contents($bad, '<?php echo "not an image";');
    $large = $temp . '/large.png';
    file_put_contents($large, file_get_contents($png) . str_repeat('x', 2 * 1024 * 1024));
    foreach ([$bad,$large] as $file) {
        $before = $db->query('SELECT * FROM products')->fetch_assoc();
        request('admin/save-product.php', $data + ['image'=>new CURLFile($file, 'image/png', 'image.png')]);
        check($before === $db->query('SELECT * FROM products')->fetch_assoc(), 'Reject ' . basename($file));
    }
    $beforeFiles = glob($app . '/assets/images/products/*');
    $db->query("CREATE TRIGGER test_fail BEFORE INSERT ON products FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='isolated failure'");
    request('admin/save-product.php', array_replace($data, ['id'=>'']) + ['image'=>$upload]);
    check($beforeFiles === glob($app . '/assets/images/products/*'), 'Database failure removes newly uploaded image');
    $db->query('DROP TRIGGER test_fail');
    $stmt = $db->prepare("INSERT INTO products (category_id,name,price,brand,stock) VALUES (1,?,1,'PagingBrand',1)");
    for ($i=0;$i<23;$i++) { $name = sprintf('Paging sample %02d',$i); $stmt->bind_param('s',$name); $stmt->execute(); }
    [$status,$body] = request('admin/products.php?q=PagingBrand&category=1&page=2');
    check($status === 200 && substr_count($body,'>Sửa</a>') === 10, 'Search, category and page 2 return 10 rows');
    check(str_contains($body,'q=PagingBrand&amp;category=1&amp;page=3'), 'Pagination preserves search and category');
    check(substr_count(request('admin/products.php?q=PagingBrand&category=1&page=3')[1],'>Sửa</a>') === 3, 'Last page has remaining 3 rows');
    check(str_contains(request('admin/products.php?q=PagingBrand&category=2')[1],'Không tìm thấy'), 'Category filter excludes other categories');
    check(substr_count(request('admin/products.php?q=Edited')[1],'>Sửa</a>') === 1, 'Name search');
    foreach (['products.php','product-form.php','product-form.php?id=1'] as $path) {
        $html = request('admin/' . $path)[1];
        check(substr_count($html,'class="nav-link active"') === 1, 'Exactly one active sidebar: ' . $path);
    }
    check(request('admin/product-form.php?id=99999')[0] === 404, 'Missing product form denied');
    foreach (['0','9999999999.99'] as $price) {
        request('admin/save-product.php', array_replace($data, ['price'=>$price, 'stock'=>'0']));
        $row = $db->query('SELECT price,stock FROM products WHERE id=1')->fetch_assoc();
        check($row['price'] === ($price === '0' ? '0.00' : $price) && $row['stock'] === '0', 'Accept numeric boundary ' . $price);
    }
    request('admin/save-product.php', array_replace($data, ['name'=>'<script>alert(1)</script>']));
    $html = request('admin/products.php?q=alert')[1];
    check(str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>alert(1)</script>'), 'Escape product HTML');
    $svg = $temp . '/image.svg';
    file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>');
    $before = $db->query('SELECT * FROM products WHERE id=1')->fetch_assoc();
    request('admin/save-product.php', $data + ['image'=>new CURLFile($svg,'image/png','fake.png')]);
    check($before === $db->query('SELECT * FROM products WHERE id=1')->fetch_assoc(), 'Reject SVG disguised as PNG');
    require __DIR__ . '/category-management.php';
    require __DIR__ . '/customer-management.php';
    echo "Completed $checks checks. Live database untouched.\n";
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
