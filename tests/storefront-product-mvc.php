<?php
if (PHP_SAPI !== 'cli' || !isset($app, $db, $testName) || !preg_match('/\Amotoparts_test_[a-f0-9]+\z/', $testName)) exit("Run isolated harness.\n");
$storefrontStart = $checks;
check($db->query('SELECT DATABASE()')->fetch_row()[0] === $testName, 'Storefront uses isolated fixture database');
$db->query("INSERT INTO categories(name) VALUES ('Catalog & category')");
$categoryId = $db->insert_id;
$fixture = ['name'=>'Part <script>alert(1)</script> & "quote"', 'brand'=>'Brand <b>X</b>', 'description'=>"Description <em>raw</em>\nSecond line"];
$stmt = $db->prepare('INSERT INTO products(category_id,name,price,image,brand,stock,description) VALUES (?, ?, 123456.78, ?, ?, 7, ?)');
$imageName = 'storefront test.png';
$stmt->bind_param('issss', $categoryId, $fixture['name'], $imageName, $fixture['brand'], $fixture['description']);
$stmt->execute();
$catalogId = $db->insert_id;
copy($png, $app . '/assets/images/products/' . $imageName);
if (!is_dir($app . '/assets/js')) mkdir($app . '/assets/js', 0777, true);
copy($root . '/scr/assets/js/main.js', $app . '/assets/js/main.js');
file_put_contents($app . '/storefront-session.php', '<?php session_start(); $id=(int)($_GET["id"]??0); if ($id) { $_SESSION["user"]=["id"=>$id,"fullname"=>"Viewer <b>Name</b>","role"=>$id===1?"admin":"customer"]; } else { unset($_SESSION["user"]); } $_SESSION["cart"]=[];');
$originalCookie = $cookie;
$cookie = $temp . '/storefront-cookie.txt';
$internal = ['app/Models/StorefrontProduct.php','app/Controllers/Storefront/ProductController.php','app/Views/storefront/products/index.php','app/Views/storefront/products/detail.php'];
foreach (['anonymous'=>0,'customer'=>2,'admin'=>1] as $actor=>$actorId) {
    request('storefront-session.php?id=' . $actorId);
    foreach (['pages/products.php','pages/product-detail.php?id=' . $catalogId] as $path) {
        [$status, $html] = request($path);
        check($status === 200 && str_contains($html, 'navbar') && str_contains($html, '</html>'), 'Public catalog with layout: ' . $actor . ' ' . $path);
        check($actorId ? (str_contains($html, 'Viewer &lt;b&gt;Name&lt;/b&gt;') && str_contains($html, 'Đăng xuất')) : str_contains($html, 'Đăng nhập'), 'Navbar login state: ' . $actor . ' ' . $path);
        check(($actor === 'admin') === str_contains($html, '/admin/index.php'), 'Navbar Admin link: ' . $actor . ' ' . $path);
    }
    foreach ($internal as $path) {
        [$status, $body] = request($path);
        check($status === 403 && $body === '', 'Storefront internal denied to ' . $actor . ': ' . $path);
    }
}
request('storefront-session.php?id=0');
$list = request('pages/products.php')[1];
$detail = request('pages/product-detail.php?id=' . $catalogId)[1];
foreach ([$list, $detail] as $html) {
    check(str_contains($html, 'Part &lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;quote&quot;') && !str_contains($html, '<script>alert(1)</script>'), 'Product name escaped');
    check(str_contains($html, 'Catalog &amp; category') && str_contains($html, 'Brand &lt;b&gt;X&lt;/b&gt;'), 'Category and brand escaped');
    check(str_contains($html, '123.457') && str_contains($html, '₫'), 'Vietnamese price formatting retained');
    check(str_contains($html, '../assets/images/products/storefront%20test.png'), 'Image URL resolves inside scr');
}
check(request('assets/images/products/storefront%20test.png')[0] === 200, 'Product image HTTP 200');
check(str_contains($list, 'product-detail.php?id=' . $catalogId), 'List links to correct detail');
$links = customer_test_dom($list)->query('//a[contains(@href,"product-detail.php?id=")]');
check($links->item(0)->getAttribute('href') === 'product-detail.php?id=' . $catalogId && $links->length === (int)$db->query('SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id')->fetch_row()[0], 'List preserves complete catalog and descending ID');
check(str_contains($detail, 'Còn hàng: 7') && str_contains($detail, 'max="7"'), 'Stock and quantity limit retained');
check(str_contains($detail, 'Description &lt;em&gt;raw&lt;/em&gt;<br') && str_contains($detail, 'Second line'), 'Description escaped with line breaks');
check(str_contains($detail, 'href="products.php"'), 'Detail back link retained');
check(str_contains($detail, 'action="../actions/add_cart.php" method="post"') && str_contains($detail, 'name="product_id"'), 'Existing cart POST form retained');
$beforeProducts = $db->query('SELECT id,price,stock FROM products ORDER BY id')->fetch_all(MYSQLI_ASSOC);
foreach (['','?id=0','?id=-1','?id=abc','?id=1.5','?id=2147483648','?id=2147483647','?id[]=1','?id=1%20OR%201=1','?id=%2B1','?id=1e0','?id=01'] as $query) {
    [$status, $html] = request('pages/product-detail.php' . $query);
    check($status === 404 && str_contains($html, 'Không tìm thấy sản phẩm') && str_contains($html, 'navbar') && str_contains($html, 'href="products.php"') && !str_contains($html, 'name="product_id"'), 'Friendly strict product 404: ' . $query);
}
check($beforeProducts === $db->query('SELECT id,price,stock FROM products ORDER BY id')->fetch_all(MYSQLI_ASSOC), 'Invalid GET/injection does not mutate products');
$cartToken = customer_test_dom($detail)->query('//input[@name="csrf_token"]')->item(0)->getAttribute('value');
[$status, , $headers] = request('actions/add_cart.php', ['product_id'=>$catalogId,'quantity'=>2,'csrf_token'=>$cartToken]);
check($status === 303 && str_contains($headers, '../pages/cart.php'), 'Existing add_cart redirects to cart');
foreach (['pages/products.php','pages/product-detail.php?id=' . $catalogId] as $path) {
    $dom = customer_test_dom(request($path)[1]);
    check(trim($dom->query('//nav//span[contains(@class,"badge")]')->item(0)->textContent) === '2', 'Cart count survives MVC rendering: ' . $path);
}
$cart = request('pages/cart.php')[1];
check(str_contains($cart, 'name="quantities[' . $catalogId . ']"') && str_contains($cart, 'value="2"'), 'Cart contains selected product and quantity');
request('actions/add_cart.php', ['product_id'=>$catalogId,'quantity'=>100,'csrf_token'=>$cartToken]);
$badge = customer_test_dom(request('pages/products.php')[1])->query('//nav//span[contains(@class,"badge")]')->item(0);
check(trim($badge->textContent) === '7', 'Existing cart stock cap retained');
check($beforeProducts === $db->query('SELECT id,price,stock FROM products ORDER BY id')->fetch_all(MYSQLI_ASSOC), 'Adding to session cart does not change price or stock');
$db->query('UPDATE products SET stock=0 WHERE id=' . (int)$catalogId);
$detail = request('pages/product-detail.php?id=' . $catalogId)[1];
check(str_contains($detail, 'Sản phẩm hiện đã hết hàng.') && !str_contains($detail, 'actions/add_cart.php'), 'Out-of-stock detail has no cart form');
$db->query('UPDATE products SET image=NULL,brand=NULL,description=NULL WHERE id=' . (int)$catalogId);
check(request('pages/product-detail.php?id=' . $catalogId)[0] === 200 && request('pages/products.php')[0] === 200, 'Nullable product fields render safely');
check(str_contains($list, '/scr/assets/js/main.js') && request('assets/js/main.js')[0] === 200, 'Footer JavaScript stays inside scr and serves successfully');
foreach (['products.php','product-detail.php'] as $entry) {
    $source = file_get_contents($app . '/pages/' . $entry);
    check(str_contains($source, 'Controllers\\Storefront\\ProductController') && strlen($source)<250, 'Thin legacy storefront entry: ' . $entry);
}
check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/', file_get_contents($app . '/app/Controllers/Storefront/ProductController.php')), 'Storefront controller has no SQL');
$modelSource = file_get_contents($app . '/app/Models/StorefrontProduct.php');
check(!preg_match('/\$_(GET|POST|SESSION)|password|\b(INSERT|UPDATE|DELETE)\b/', $modelSource), 'Storefront model is read-only without request or sensitive data');
foreach (['index','detail'] as $view) {
    check(!preg_match('/\b(SELECT|mysqli_query)\b|\$_(GET|POST)/', file_get_contents($app . '/app/Views/storefront/products/' . $view . '.php')), 'Storefront view has no SQL or request: ' . $view);
}
$db->query('RENAME TABLE products TO storefront_products_unavailable');
try {
    foreach (['pages/products.php','pages/product-detail.php?id=' . $catalogId] as $path) {
        [$status, $html] = request($path);
        check($status === 503 && str_contains($html, 'Không thể tải sản phẩm') && str_contains($html, 'navbar') && !str_contains($html, $testName) && !str_contains($html, 'SELECT'), 'Query failure is safe with layout: ' . $path);
    }
} finally { $db->query('RENAME TABLE storefront_products_unavailable TO products'); }
$configPath = $app . '/config/database.php';
$configSource = file_get_contents($configPath);
try {
    file_put_contents($configPath, "<?php throw new mysqli_sql_exception('private-connection-data', 2002);");
    [$status, $html] = request('pages/products.php');
    check($status === 503 && str_contains($html, 'Không thể tải sản phẩm') && !str_contains($html, 'private-connection-data'), 'Connection initialization failure is safely rendered');
} finally { file_put_contents($configPath, $configSource); }
$log = file_get_contents($temp . '/server.log');
check(str_contains($log, 'MotoParts storefront products: mysqli_sql_exception code=') && !str_contains($log, 'private-connection-data'), 'PHP log records safe diagnostic without connection message');
$db->query('DELETE FROM order_details');
$db->query('DELETE FROM products');
[$status, $html] = request('pages/products.php');
check($status === 200 && str_contains($html, 'Chưa có sản phẩm nào!'), 'Empty catalog retains friendly message');
$cookie = $originalCookie;
echo 'Storefront product checks completed: ' . ($checks - $storefrontStart) . PHP_EOL;