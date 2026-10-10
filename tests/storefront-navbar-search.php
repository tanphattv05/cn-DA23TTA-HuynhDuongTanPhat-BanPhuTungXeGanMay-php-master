<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!isset($app, $db)) {
    if (!in_array('--isolated', $argv, true)) exit("Run: php tests/storefront-navbar-search.php --isolated [--navbar-browser]\n");
    require __DIR__.'/product-management.php'; exit;
}
$navbarStart=$checks;
$navbarCookie=$cookie; $cookie=$temp.'/navbar-cookie.txt';
$navbarDom=customer_test_dom(request('pages/products.php')[1]);
$form=$navbarDom->query('//form[@id="navbar-search"]')->item(0);
check($form && $form->getAttribute('method')==='get' && $form->getAttribute('role')==='search'
    && $form->getAttribute('action')==='/'.$project.'/scr/pages/products.php','Navbar GET/action/search landmark');
check($navbarDom->query('//form[@id="navbar-search"]//*[@name]')->length===1
    && $navbarDom->query('//form[@id="navbar-search"]//input[@name="q" and @maxlength="100"]')->length===1,'Navbar only sends q');
check($navbarDom->query('//label[@for="navbar-search-q" and @class="visually-hidden"]')->length===1
    && $navbarDom->query('//form[@id="navbar-search"]//button[@type="submit" and @aria-label="Tìm kiếm sản phẩm"]')->length===1,'Navbar accessible label/button');
check($navbarDom->query('//*[@id="navbar-search-q"]')->item(0)->getAttribute('placeholder')==='Tìm phụ tùng, thương hiệu...','Navbar placeholder');
foreach(['Catalog17 Part','SearchBrandOdd','','   ','%','_','!','\\',"'".'"','<script>alert(1)</script>'] as $q) {
    [$status,$html]=request('pages/products.php?'.http_build_query(['q'=>$q,'stock'=>'in_stock','page'=>2]));
    $dom=customer_test_dom($html);
    check($status===200 && $dom->query('//*[@id="navbar-search-q"]')->item(0)->getAttribute('value')===trim($q)
        && !str_contains($html,'<script>alert(1)</script>'),'Navbar escaped/repopulated '.json_encode($q));
}
foreach([['bad'],str_repeat('x',101)] as $q) {
    [$status,$html]=request('pages/products.php?'.http_build_query(['q'=>$q]));
    check($status===200 && mb_strlen(customer_test_dom($html)->query('//*[@id="navbar-search-q"]')->item(0)->getAttribute('value'))<=100,'Navbar invalid q uses catalog normalization');
}
foreach(['index.php','pages/login.php','pages/register.php','pages/cart.php'] as $path) {
    check(customer_test_dom(request($path.'?q=ignored')[1])->query('//*[@id="navbar-search-q"]')->item(0)->getAttribute('value')==='','Navbar other page empty '.$path);
}
foreach(['customer','admin'] as $role) {
    $dom=customer_test_dom(request('ui-fixture.php?view=home/index&member='.$role)[1]);
    check($dom->query('//form[@id="navbar-search"]')->length===1 && trim($dom->query('//nav//span[contains(@class,"badge")]')->item(0)->textContent)==='3','Navbar badge '.$role);
    check($dom->query('//nav//a[contains(@href,"admin/index.php")]')->length===($role==='admin'?1:0)
        && $dom->query('//nav//a[contains(@href,"my-orders.php")]')->length===1,'Navbar role menu '.$role);
    check($dom->query('//nav//form[@method="post"]//input[@name="auth_csrf_token" and string-length(@value)>0]')->length===1,'Navbar logout CSRF '.$role);
}
request('test-session.php?id=1');
[$status,$html]=request('admin/index.php');
check($status===200 && !str_contains($html,'id="navbar-search"'),'Admin has no storefront search');
check(!preg_match('/mysqli_|\bSELECT .+ FROM\b|\$_POST/',file_get_contents($root.'/scr/frontend/includes/storefront/navbar.php')),'Navbar presentation boundary');
$cookie=$navbarCookie;
if(in_array('--navbar-browser',$argv,true)) {
    $environment=getenv();
    $environment['MOTOPARTS_UI_BASE_URL']='http://127.0.0.1:'.$port.'/'.$project.'/scr/';
    $environment['MOTOPARTS_CATALOG_QUERY']='Catalog17 Part';
    $environment['MOTOPARTS_NAVBAR_FIXTURE']='1';
    $process=proc_open(['node',__DIR__.'/storefront-ui-browser.mjs'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,$root,$environment);
    check(is_resource($process),'Navbar browser started');
    fclose($pipes[0]);
    check(proc_close($process)===0,'Navbar browser passed');
}
echo 'Storefront navbar checks completed: '.($checks-$navbarStart).PHP_EOL;
