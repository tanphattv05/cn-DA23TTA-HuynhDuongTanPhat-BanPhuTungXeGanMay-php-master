<?php
if (PHP_SAPI !== 'cli' || !isset($app, $db, $root)) exit("Run isolated harness.\n");
$productMvcStart = $checks;
$paths = ['app/Models/Product.php','app/Controllers/Admin/ProductController.php','app/Views/admin/products/index.php','app/Views/admin/products/form.php'];
foreach ($paths as $path) check(request($path)[0] === 403, 'Product MVC internal URL denied to admin: '.$path);
$adminCookie = $cookie;
$cookie = $temp . '/anonymous-product-mvc.txt';
foreach ($paths as $path) check(request($path)[0] === 403, 'Product MVC internal URL denied anonymously: '.$path);
foreach (['products.php','product-form.php','save-product.php'] as $entry) {
    check(request('admin/'.$entry)[0] === 302, 'Legacy product URL still requires login: '.$entry);
}
$cookie = $adminCookie;
foreach (['index','form'] as $template) {
    $source = file_get_contents($app.'/app/Views/admin/products/'.$template.'.php');
    check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|mysqli)\b|\$_POST/', $source), 'Product view has no SQL/POST: '.$template);
}
$model = file_get_contents($app.'/app/Models/Product.php');
check(!preg_match('/\$_(GET|POST|SESSION|FILES)|header\s*\(/', $model), 'Product model has no request/session/upload behavior');
$controller = file_get_contents($app.'/app/Controllers/Admin/ProductController.php');
check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/', $controller), 'Product controller contains no SQL');
$form = request('admin/product-form.php?id=1')[1];
check(str_contains($form,'<script src="assets/product-form.js"></script>') && str_contains($form,'id="image-preview"'), 'Preview script and target stay at legacy URL');
if (!is_dir($app.'/admin/assets')) mkdir($app.'/admin/assets',0777,true);
copy($root.'/scr/admin/assets/product-form.js',$app.'/admin/assets/product-form.js');
[$status,$script] = request('admin/assets/product-form.js');
check($status === 200 && $script === file_get_contents($root.'/scr/admin/assets/product-form.js'), 'JavaScript URL serves original preview script');
$image = $db->query('SELECT image FROM products WHERE id=1')->fetch_row()[0];
$expectedImage = '/'.$project.'/scr/assets/images/products/'.rawurlencode(basename($image));
check(str_contains($form, 'src="'.$expectedImage.'"'), 'Admin image stays under scr/assets/images/products');
$storefront = request('pages/products.php')[1];
check(str_contains($storefront,'../assets/images/products/'.$image), 'Storefront points to same image directory');
[$status,$bytes] = request('assets/images/products/'.rawurlencode($image));
check($status === 200 && $bytes === file_get_contents($app.'/assets/images/products/'.$image), 'Product image URL returns uploaded bytes');
$data = ['csrf_token'=>$token,'id'=>'','name'=>'MVC no image','category_id'=>'1','price'=>'42.50','stock'=>'2','brand'=>'MVC','description'=>''];
[$status,,$headers] = request('admin/save-product.php',$data);
$new = $db->query("SELECT id,image,name FROM products WHERE name='MVC no image'")->fetch_assoc();
check($status === 303 && str_contains($headers,'/scr/admin/products.php') && $new && $new['image'] === '', 'MVC create without image succeeds');
$data['id'] = (string)$new['id'];
$data['name'] = 'MVC no image edited';
request('admin/save-product.php',$data);
check($db->query('SELECT name FROM products WHERE id='.(int)$new['id'])->fetch_row()[0] === $data['name'], 'MVC edit without image succeeds');
$before = $db->query('SELECT * FROM products WHERE id=1')->fetch_assoc();
$beforeFiles = glob($app.'/assets/images/products/*');
$data['id']='1';
$db->query("CREATE TRIGGER mvc_update_failure BEFORE UPDATE ON products FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='private fixture error'");
try {
    request('admin/save-product.php',$data+['image'=>new CURLFile($png,'image/png','image.png')]);
    check($before === $db->query('SELECT * FROM products WHERE id=1')->fetch_assoc(), 'Failed MVC update rolls back product');
    check($beforeFiles === glob($app.'/assets/images/products/*'), 'Failed MVC update cleans only newly uploaded image');
    check(is_file($app.'/assets/images/products/'.$before['image']), 'Failed MVC update retains previous image');
    $html=request('admin/product-form.php?id=1')[1];
    check(str_contains($html,'Không thể lưu sản phẩm') && !str_contains($html,'private fixture error'), 'MVC update DB error remains private');
} finally {
    $db->query('DROP TRIGGER mvc_update_failure');
}
foreach (['products.php','product-form.php','product-form.php?id=1'] as $entry) {
    check(substr_count(request('admin/'.$entry)[1],'class="nav-link active"') === 1, 'Product MVC sidebar active: '.$entry);
}
check(!preg_match('/(?:require|include)[^;]*(?:tests|docs)/',file_get_contents($app.'/app/Controllers/Admin/ProductController.php')), 'Product runtime does not depend on repository tests/docs');
echo 'Product MVC checks completed: '.($checks-$productMvcStart).PHP_EOL;
