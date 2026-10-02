<?php
if (PHP_SAPI !== 'cli' || !isset($app, $db)) exit('Run isolated harness.');
$foundationStart = $checks;
$foundationCookie = $cookie;
$cookie = $temp . '/foundation.txt';
check(is_file($app . '/backend/Controllers/Storefront/HomeController.php'), 'Home Controller exists in backend');
file_put_contents($app . '/foundation-state.php', '<?php session_start(); if(isset($_POST["seed"])) $_SESSION=["cart"=>[123=>2,456=>3],"user"=>["id"=>2,"fullname"=>"<b>Foundation</b>","email"=>"fixture@test.invalid","phone"=>"0901234567","role"=>$_POST["role"]??"customer"]]; echo json_encode($_SESSION);');
foreach (['index.php', ''] as $url) {
    [$status,$html] = request($url);
    check($status===200 && str_contains($html,'PHỤ TÙNG XE MÁY CHÍNH HÃNG'), 'Guest home URL preserved: '.$url);
    check(substr_count($html,'<!DOCTYPE html>')===1 && substr_count($html,'id="mainNavbar"')===1 && substr_count($html,'bootstrap.bundle.min.js')===1, 'Home renders layout exactly once');
    check(str_contains($html,'/scr/pages/products.php') && str_contains($html,'/scr/assets/css/style.css') && str_contains($html,'/scr/assets/js/main.js'), 'Home asset and product URLs unchanged');
    check(str_contains($html,'Đăng nhập') && !str_contains($html,'actions/logout.php'), 'Guest navbar preserved');
}
foreach (['customer','admin'] as $role) {
    request('foundation-state.php',['seed'=>1,'role'=>$role]);
    $before=json_decode(request('foundation-state.php')[1],true);
    [$status,$html]=request('index.php');
    $after=json_decode(request('foundation-state.php')[1],true);
    check($status===200 && str_contains($html,'&lt;b&gt;Foundation&lt;/b&gt;') && !str_contains($html,'<b>Foundation</b>'), 'Home greeting escapes session name: '.$role);
    check($before['user']===$after['user'] && $before['cart']===$after['cart'], 'Home preserves identity/cart: '.$role);
    $xpath=customer_test_dom($html);
    check(trim($xpath->query('//a[contains(@href,"pages/cart.php")]/span')->item(0)->textContent)==='5', 'Navbar cart count retained: '.$role);
    check($xpath->query('//form[contains(@action,"logout.php") and @method="post"]/input[@name="auth_csrf_token"]')->length===1, 'Navbar logout remains POST with CSRF: '.$role);
    check(str_contains($html,'/scr/admin/index.php')===($role==='admin'), 'Navbar Admin link matches session role: '.$role);
}
file_put_contents($app . '/foundation-wrapper.php', '<?php require __DIR__."/includes/header.php"; require __DIR__."/includes/navbar.php"; echo "<main>Wrapper fixture</main>"; require __DIR__."/includes/footer.php";');
[$status,$html]=request('foundation-wrapper.php');
check($status===200 && substr_count($html,'<!DOCTYPE html>')===1 && str_contains($html,'Wrapper fixture') && substr_count($html,'id="mainNavbar"')===1, 'Legacy includes forward to new layout once');
foreach (['backend/bootstrap.php','backend/Core/View.php','backend/Controllers/Storefront/HomeController.php','frontend/Views/storefront/home/index.php','frontend/includes/storefront/header.php','frontend/includes/storefront/navbar.php','frontend/includes/storefront/footer.php'] as $path) {
    check(request($path)[0]===403, 'Foundation internal file denied: '.$path);
}
foreach (['header','navbar','footer'] as $part) {
    $source=file_get_contents($app.'/includes/'.$part.'.php');
    check(strlen($source)<500 && str_contains($source,'frontend/includes/storefront/') && !str_contains($source,'<nav'), 'Single source layout via wrapper: '.$part);
}
check(strlen(file_get_contents($app.'/index.php'))<400 && str_contains(file_get_contents($app.'/index.php'),'HomeController'), 'Home entry is thin');
check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b|\$_SESSION/',file_get_contents($app.'/frontend/Views/storefront/home/index.php')), 'Home View receives data without DB/session');
check(str_contains((new ReflectionClass(\MotoParts\App\Core\View::class))->getFileName(),'backend'), 'Legacy namespace resolves canonical backend View');
foreach (['storefront/../config/database','../storefront/home/index','storefront/home/index.php'] as $bad) {
    $denied=false;
    try { \MotoParts\App\Core\View::storefront($bad,[]); } catch (InvalidArgumentException $e) { $denied=true; }
    check($denied, 'Renderer rejects invalid template: '.$bad);
}
$cookie=$foundationCookie;
echo 'Frontend foundation checks completed: '.($checks-$foundationStart).PHP_EOL;
