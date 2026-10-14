<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!isset($app, $db)) {
    if (!in_array('--isolated', $argv, true)) exit("Run: php tests/storefront-favorites.php --isolated [--favorites-browser]\n");
    require __DIR__.'/product-management.php'; exit;
}
$favoriteStart=$checks;
check($db->query('SELECT DATABASE()')->fetch_row()[0]===$testName && str_starts_with($testName,'motoparts_test_'),'Favorites migration isolated');
$db->query(file_get_contents($root.'/docs/migrations/phase-18-favorites.sql'));
check($db->query('SELECT COUNT(*) FROM favorites')->fetch_row()[0]==0,'Favorites empty migration imported');
$db->query("INSERT INTO users(fullname,email,password,role) VALUES ('Favorite A','favorite-a@test.invalid','unused','customer'),('Favorite B','favorite-b@test.invalid','unused','customer')");
$favoriteA=(int)$db->insert_id; $favoriteB=$favoriteA+1;
$db->query("INSERT INTO categories(name) VALUES ('Favorite category')");
$favoriteCategory=(int)$db->insert_id;
$stmt=$db->prepare('INSERT INTO products(category_id,name,price,stock) VALUES (?,?,?,?)');
$name='Favorite <script>alert(18)</script> & part';$price='1200.50';$stock=3;
$stmt->bind_param('issi',$favoriteCategory,$name,$price,$stock);$stmt->execute();$favoriteProduct=(int)$db->insert_id;
$name='Favorite sold out';$stock=0;$stmt->execute();$favoriteOut=(int)$db->insert_id;$stmt->close();
$favoriteCookie=$cookie;$cookie=$temp.'/favorite-guest.txt';
check(request('pages/favorites.php')[0]===302,'Favorites guest login redirect');
[$status,,$headers]=request('actions/toggle_favorite.php',['product_id'=>$favoriteProduct,'operation'=>'add','return'=>'https://evil.invalid']);
check($status===302 && str_contains($headers,'/pages/login.php') && !str_contains($headers,'evil.invalid'),'Favorites guest no open redirect');
check(request('actions/toggle_favorite.php')[0]===405,'Favorites requires POST');
request('test-session.php?id=1');
check(request('pages/favorites.php')[0]===403 && request('actions/toggle_favorite.php',['product_id'=>$favoriteProduct])[0]===403,'Favorites admin denied');
check(!str_contains(request('pages/products.php')[1],'class="favorite-control'),'Favorites admin has no write control');
request('test-session.php?id='.$favoriteA);
$page=request('pages/favorites.php')[1];
check(str_contains($page,'Bạn chưa có sản phẩm yêu thích.'),'Favorites empty state');
$dom=customer_test_dom($page);
check($dom->query('//nav//a[@aria-current="page" and contains(@href,"favorites.php")]')->length===1,'Favorites customer active nav');
$detail=request('pages/product-detail.php?id='.$favoriteProduct)[1];
$token=customer_test_dom($detail)->query('//form[contains(@action,"toggle_favorite")]//input[@name="csrf_token"]')->item(0)->getAttribute('value');
$post=['product_id'=>(string)$favoriteProduct,'operation'=>'add','csrf_token'=>$token];
foreach(['', 'bad', ['array']] as $bad) check(request('actions/toggle_favorite.php',http_build_query(array_replace($post,['csrf_token'=>$bad])))[0]===403,'Favorites CSRF rejected');
foreach(['0','-1','1 OR 1=1','2147483648',['1']] as $bad) check(request('actions/toggle_favorite.php',http_build_query(array_replace($post,['product_id'=>$bad])))[0]===400,'Favorites invalid ID');
check(request('actions/toggle_favorite.php',array_replace($post,['operation'=>'flip']))[0]===400,'Favorites operation allowlist');
check(request('actions/toggle_favorite.php',array_replace($post,['product_id'=>'2147483647']))[0]===404,'Favorites missing product');
foreach([1,2] as $attempt) check(request('actions/toggle_favorite.php',$post+['user_id'=>$favoriteB,'return'=>'https://evil.invalid'])[0]===303,'Favorites add/repeat '.$attempt);
check((int)$db->query('SELECT COUNT(*) FROM favorites')->fetch_row()[0]===1,'Favorites unique pair/idempotent add');
check((int)$db->query('SELECT user_id FROM favorites')->fetch_row()[0]===$favoriteA,'Favorites ignores posted identity');
$page=request('pages/favorites.php')[1];$dom=customer_test_dom($page);
check(!str_contains($page,'<script>alert(18)</script>') && str_contains($page,'&lt;script&gt;alert(18)&lt;/script&gt;'),'Favorites escaped product XSS');
check($dom->query('//button[@aria-pressed="true"]')->length===1,'Favorites saved heart state');
check(str_contains(request('pages/product-detail.php?id='.$favoriteProduct)[1],'aria-pressed="true"'),'Favorites detail saved state');
check(str_contains(request('pages/products.php?q=Favorite')[1],'aria-pressed="true"'),'Favorites catalog batched state');
check(request('actions/toggle_favorite.php',array_replace($post,['product_id'=>(string)$favoriteOut]))[0]===303,'Favorites can save sold-out product');
$dom=customer_test_dom(request('pages/favorites.php')[1]);
check($dom->query('//form[contains(@action,"add_cart")]')->length===1 && $dom->query('//form[contains(@action,"toggle_favorite")]')->length===2,'Favorites only in-stock add-to-cart form');
check(request('actions/add_cart.php',['product_id'=>$favoriteProduct,'quantity'=>1,'csrf_token'=>$token])[0]===303
    && str_contains(request('pages/cart.php')[1],'Favorite &lt;script&gt;'),'Favorites existing cart action works');
$cookie=$temp.'/favorite-b.txt';request('test-session.php?id='.$favoriteB);
check(!str_contains(request('pages/favorites.php?user_id='.$favoriteA)[1],'Favorite &lt;script&gt;'),'Favorites cannot read another customer');
$tokenB=customer_test_dom(request('pages/product-detail.php?id='.$favoriteProduct)[1])->query('//form[contains(@action,"toggle_favorite")]//input[@name="csrf_token"]')->item(0)->getAttribute('value');
check(request('actions/toggle_favorite.php',array_replace($post,['operation'=>'remove','csrf_token'=>$tokenB,'user_id'=>$favoriteA]))[0]===303
    && (int)$db->query('SELECT COUNT(*) FROM favorites')->fetch_row()[0]===2,'Favorites cannot remove another customer');
$cookie=$temp.'/favorite-guest.txt';
check(request('actions/toggle_favorite.php',array_replace($post,['operation'=>'remove','source'=>'https://evil.invalid']))[0]===303,'Favorites remove succeeds');
check(!str_contains(request('pages/product-detail.php?id='.$favoriteProduct)[1],'aria-pressed="true"'),'Favorites removed heart state');
request('actions/toggle_favorite.php',$post);
// Constraint enforcement, not just controller validation.
try {$db->query("INSERT INTO favorites(user_id,product_id) VALUES ($favoriteA,$favoriteProduct)");$duplicate=false;}catch(mysqli_sql_exception $e){$duplicate=$e->getCode()===1062;}
check($duplicate,'Favorites database unique constraint');
try {$db->query("INSERT INTO favorites(user_id,product_id) VALUES ($favoriteA,2147483647)");$foreign=false;}catch(mysqli_sql_exception $e){$foreign=$e->getCode()===1452;}
check($foreign,'Favorites database product foreign key');
$db->query("INSERT INTO favorites(user_id,product_id) VALUES ($favoriteB,$favoriteOut)");
$db->query("DELETE FROM users WHERE id=$favoriteB");
check((int)$db->query("SELECT COUNT(*) FROM favorites WHERE user_id=$favoriteB")->fetch_row()[0]===0,'Favorites user delete cascades only favorites');
$db->query("DELETE FROM products WHERE id=$favoriteOut");
check((int)$db->query("SELECT COUNT(*) FROM favorites WHERE product_id=$favoriteOut")->fetch_row()[0]===0,'Favorites product delete cascades');
// Missing migration must not disable the existing catalog.
$db->query('RENAME TABLE favorites TO favorites_unavailable');
try {
    check(request('pages/favorites.php')[0]===503,'Favorites missing migration safe 503');
    [$status,$html]=request('pages/products.php?q=Favorite');
    check($status===200 && str_contains($html,'chưa sẵn sàng') && !str_contains($html,$testName),'Favorites unavailable leaves catalog working');
} finally {$db->query('RENAME TABLE favorites_unavailable TO favorites');}
foreach(['backend/Models/Favorite.php','backend/Controllers/Storefront/FavoriteController.php','frontend/includes/storefront/favorite-button.php','frontend/Views/storefront/favorites/index.php'] as $path) check(request($path)[0]===403,'Favorites internal denied '.$path);
request('test-session.php?id='.$favoriteB);
check(request('pages/favorites.php')[0]===302,'Favorites deleted account session rejected');
$cookie=$favoriteCookie;
if(in_array('--favorites-browser',$argv,true)) {
    // Test-only login shortcut, never copied to the real website.
    file_put_contents($app.'/favorite-session.php','<?php session_start(); $_SESSION=["user"=>["id"=>'.$favoriteA.']]; header("Location: pages/favorites.php");');
    $environment=getenv();
    $environment['MOTOPARTS_UI_BASE_URL']='http://127.0.0.1:'.$port.'/'.$project.'/scr/';
    $environment['MOTOPARTS_CATALOG_QUERY']='Catalog17 Part';
    $environment['MOTOPARTS_NAVBAR_FIXTURE']='1';
    $environment['MOTOPARTS_FAVORITES_FIXTURE']='1';
    $process=proc_open(['node',__DIR__.'/storefront-ui-browser.mjs'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,$root,$environment);
    check(is_resource($process),'Favorites browser started');fclose($pipes[0]);
    check(proc_close($process)===0,'Favorites browser passed');
}
echo 'Storefront favorites checks completed: '.($checks-$favoriteStart).PHP_EOL;
