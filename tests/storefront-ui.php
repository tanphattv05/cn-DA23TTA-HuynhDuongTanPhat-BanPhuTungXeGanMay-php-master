<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!isset($app, $db)) {
    if (!in_array('--isolated', $argv, true)) exit("Run: php tests/storefront-ui.php --isolated\n");
    require __DIR__.'/product-management.php';
    exit;
}
$uiStart = $checks;
// This fixture is written only into the harness's temporary application. It
// renders the actual View/layout with adversarial data, never writes database rows.
file_put_contents($app.'/ui-fixture.php', <<<'PHP'
<?php
define('MOTOPARTS_MVC_ENTRY', true);
require __DIR__.'/backend/bootstrap.php';
session_start();
$views=['home/index','products/index','products/detail','cart/index','auth/login','auth/register','auth/error','checkout/index','checkout/success','orders/index','orders/detail'];
$view=$_GET['view']??'';
if(!in_array($view,$views,true)){http_response_code(404);exit;}
$empty=isset($_GET['empty']); $out=isset($_GET['out']);
$user=isset($_GET['member'])?['id'=>2,'fullname'=>'Khách <script>unsafe</script> '.str_repeat('Tên dài ',12),'role'=>$_GET['member']==='admin'?'admin':'customer']:null;
$_SESSION=$user?['user'=>$user,'cart'=>[42=>3]]:['cart'=>[]];
$product=['id'=>42,'name'=>'Phụ tùng <script>unsafe</script> '.str_repeat('tên dài ',12),'price'=>'123456.00','stock'=>$out?0:4,'brand'=>'Brand & Co','description'=>"Mô tả <b>test</b>\nChi tiết",'category_name'=>'Danh mục & xe','image'=>'fixture.png','image_url'=>'../assets/images/products/fixture.png','quantity'=>2,'subtotal'=>'246912.00'];
$products=$empty?[]:[$product];
$orders=[];
$statusLabels=\MotoParts\App\Core\OrderStatus::labels();
$statusClasses=\MotoParts\App\Core\OrderStatus::classes();
foreach(array_keys($statusLabels) as $i=>$state) $orders[]=['id'=>$i+1,'status'=>$state,'created_at'=>'2026-10-06 12:00:00','fullname'=>'Khách & hàng','phone'=>'0900000000','address'=>str_repeat('Địa chỉ thử ',12),'note'=>'Ghi chú <b>test</b>','total'=>'246912.00'];
$order=$orders[0]; if($empty)$orders=[];
$orderDetails=[['name'=>$product['name'],'imageUrl'=>$product['image_url'],'price'=>'123456.00','quantity'=>2,'line_total'=>'246912.00']];
$error=isset($_GET['error'])?'Thông tin chưa hợp lệ <script>unsafe</script>':'';
$message='Không thể tiếp tục'; $success=''; $oldEmail='fixture@example.invalid';
$old=['fullname'=>'Khách thử','email'=>$oldEmail,'phone'=>'0900000000','address'=>'Địa chỉ thử','note'=>''];
$canAddToCart=!$out; $csrfToken=str_repeat('a',64); $submitToken=str_repeat('b',64);
$flash=null; $notice=''; $unavailable=false; $total=246912; $orderId=42; $canViewOrder=$user!==null;
$filters=['q'=>'','category'=>'','min_price'=>'','max_price'=>'','stock'=>'all','sort'=>'newest'];
$validationErrors=[]; $categories=[]; $totalPages=1; $page=1; $pagination=[];
if($view==='products/index') $total=count($products);
// Catalog presentation data follows the actual Controller contract.
$catalogVariables=compact('filters','validationErrors','categories','totalPages','page','pagination');
\MotoParts\App\Core\View::storefront('storefront/'.$view,$catalogVariables+compact('user','product','products','orders','order','orderDetails','statusLabels','statusClasses','error','message','success','oldEmail','old','canAddToCart','csrfToken','submitToken','flash','notice','unavailable','total','orderId','canViewOrder'));
PHP);
$uiCookie=$cookie; $cookie=$temp.'/ui-cookie.txt';
$views=['home/index','products/index','products/detail','cart/index','auth/login','auth/register','auth/error','checkout/index','checkout/success','orders/index','orders/detail'];
$uiForms=[];
foreach($views as $view) {
    [$status,$html]=request('ui-fixture.php?view='.$view.'&member=customer');
    check($status===200, 'UI render '.$view);
    $dom=customer_test_dom($html);
    check($dom->query('//body[contains(concat(" ",normalize-space(@class)," ")," storefront-body ")]')->length===1, 'UI shared body namespace '.$view);
    check($dom->query('//a[@class="skip-link" and @href="#main-content"]')->length===1, 'UI skip target '.$view);
    $cssLink=$dom->query('//link[contains(@href,"assets/css/style.css")]')->item(0);
    check($cssLink && str_ends_with($cssLink->getAttribute('href'),'?v='.hash_file('sha256',$root.'/scr/assets/css/style.css')), 'UI content-versioned CSS '.$view);
    check($dom->query('//main[@id="main-content"]')->length===1 && $dom->query('//h1')->length===1, 'UI one main and heading '.$view);
    check($dom->query('//script[not(@src)]')->length===0 && !str_contains($html,'<script>unsafe'), 'UI adversarial data escaped '.$view);
    foreach($dom->query('//input[not(@type="hidden")]|//textarea|//select') as $input) {
        $id=$input->getAttribute('id');
        check($id!=='' && $dom->query('//label[@for="'.$id.'"]')->length===1, 'UI input labelled '.$view.' '.$id);
    }
    foreach($dom->query('//img') as $img) check($img->hasAttribute('alt') && trim($img->getAttribute('alt'))!=='', 'UI image alt '.$view);
    foreach($dom->query('//form') as $form) {
        $action=$form->getAttribute('action'); $uiForms[$action]=true;
        if($form->getAttribute('id')==='catalog-filters') {
            check($form->getAttribute('method')==='get' && $action==='products.php', 'UI read-only catalog GET');
            check($dom->query('.//input[@name="page" or @name="csrf_token"]',$form)->length===0, 'UI catalog resets page and needs no CSRF');
            continue;
        }
        check($form->getAttribute('method')==='post', 'UI form POST '.$action);
        check($dom->query('.//input[@type="hidden" and (@name="csrf_token" or @name="auth_csrf_token") and string-length(@value)>0]',$form)->length===1, 'UI form CSRF '.$action);
    }
    foreach($dom->query('//input[@type="password"]') as $input) check($input->getAttribute('value')==='', 'UI password not repopulated '.$view);
    $toggle=$dom->query('//button[@data-bs-target="#mainNavbar"]')->item(0);
    check($toggle!==null && $toggle->getAttribute('aria-controls')==='mainNavbar' && $toggle->getAttribute('aria-expanded')==='false' && $toggle->getAttribute('aria-label')!=='', 'UI accessible navbar toggle '.$view);
    check($dom->query('//footer//a')->length>=3, 'UI footer navigation '.$view);
    if($view==='cart/index') {
        $quantity=$dom->query('//input[@name="quantities[42]"]')->item(0);
        check($quantity && $quantity->getAttribute('form')==='cart-update' && $quantity->getAttribute('min')==='0', 'UI cart bulk update/zero removal preserved');
    }
    if($view==='checkout/index') check($dom->query('//form//input[@name="submit_token" and string-length(@value)>0]')->length===1, 'UI checkout one-time token preserved');
    if($view==='orders/index') foreach($statusLabels=\MotoParts\App\Core\OrderStatus::labels() as $label) check(str_contains($html,$label), 'UI shared status '.$label);
}
foreach(['add_cart','update_cart','remove_cart','login','register','checkout'] as $action) check(isset($uiForms['../actions/'.$action.'.php']), 'UI action URL '.$action);
check(isset($uiForms['/'.$project.'/scr/actions/logout.php']), 'UI logout endpoint preserved');
foreach(['products/index','cart/index','orders/index'] as $view) {
    $dom=customer_test_dom(request('ui-fixture.php?view='.$view.'&empty=1')[1]);
    check($dom->query('//*[contains(@class,"empty-state")]')->length===1, 'UI empty state '.$view);
}
$dom=customer_test_dom(request('ui-fixture.php?view=products/detail&out=1')[1]);
check($dom->query('//button[@disabled]')->length===1 && $dom->query('//form[contains(@action,"add_cart")]')->length===0, 'UI sold-out disabled with no submission');
foreach(['auth/login','auth/register','checkout/index'] as $view) {
    $html=request('ui-fixture.php?view='.$view.'&error=1')[1];
    $dom=customer_test_dom($html);
    check($dom->query('//*[@role="alert"]')->length===1 && !str_contains($html,'<script>unsafe'), 'UI escaped form error '.$view);
}
foreach(['index.php','pages/products.php','pages/cart.php','pages/login.php','pages/register.php'] as $path) {
    [$status,$html]=request($path); $dom=customer_test_dom($html);
    check($status===200 && $dom->query('//nav//a[@aria-current="page"]')->length===1, 'UI single active navigation '.$path);
}
foreach(['customer','admin'] as $role) {
    $dom=customer_test_dom(request('ui-fixture.php?view=home/index&member='.$role)[1]);
    check($dom->query('//nav//a[contains(@href,"admin/index.php")]')->length===($role==='admin'?1:0), 'UI Admin link '.$role);
    check(trim($dom->query('//nav//span[contains(@class,"badge")]')->item(0)->textContent)==='3', 'UI cart badge '.$role);
}
$dom=customer_test_dom(request('ui-fixture.php?view=home/index')[1]);
check($dom->query('//nav//a[contains(@href,"login.php")]')->length===1 && $dom->query('//nav//form')->length===0, 'UI guest auth navigation');
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/scr/frontend/Views/storefront',FilesystemIterator::SKIP_DOTS)) as $file) {
    check(!preg_match('/mysqli_|\bSELECT .+ FROM\b|\$_POST/',file_get_contents($file->getPathname())), 'UI View boundary '.$file->getFilename());
}
$css=file_get_contents($root.'/scr/assets/css/style.css');
check(str_contains($css,'.storefront-body') && str_contains($css,':focus-visible') && str_contains($css,'prefers-reduced-motion') && !preg_match('~/(app|includes|config)/~',$css), 'UI scoped CSS accessibility and paths');
check(is_file($root.'/scr/assets/js/main.js'), 'UI existing JavaScript retained');
$protected=json_decode(file_get_contents(__DIR__.'/fixtures/storefront-ui-protected.json'),true,512,JSON_THROW_ON_ERROR);
foreach($protected as $group=>$files) {
    $actual=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$group,FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
        $actual[$relative]=hash_file('sha256',$file->getPathname());
    }
    ksort($files);ksort($actual);
    check($files===$actual,'UI preserves backend/Admin/vendor/uploads/entrypoint bytes: '.$group);
}
foreach(['backend/bootstrap.php','frontend/Views/storefront/home/index.php'] as $path) check(request($path)[0]===403, 'UI internal denied '.$path);
$cookie=$uiCookie;
$cssCopy=$app.'/assets/css/style.css';
$originalCss=file_get_contents($cssCopy);
try {
    file_put_contents($cssCopy,$originalCss."\n/* isolated cache invalidation check */\n");
    $html=request('index.php')[1];
    check(str_contains($html,'style.css?v='.hash_file('sha256',$cssCopy)), 'UI CSS URL changes with content');
    check(!str_contains($html,'style.css?v='.hash('sha256',$originalCss)), 'UI old CSS version not reused');
} finally { file_put_contents($cssCopy,$originalCss); }
echo 'Storefront UI checks completed: '.($checks-$uiStart).PHP_EOL;
