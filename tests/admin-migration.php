<?php
if (PHP_SAPI !== 'cli' || !isset($app,$db)) exit('Run isolated harness.');
$adminMigrationStart=$checks;
foreach (['Category','Product','Customer','Order','Dashboard'] as $module) {
    foreach (['Models/'.$module,'Controllers/Admin/'.$module.'Controller'] as $path) {
        check(is_file($app.'/backend/'.$path.'.php') && !is_file($app.'/app/'.$path.'.php'), 'Single Admin backend: '.$path);
        $class='MotoParts\\App\\'.str_replace('/','\\',$path);
        check(realpath((new ReflectionClass($class))->getFileName())===realpath($app.'/backend/'.$path.'.php'), 'Admin autoload backend: '.$path);
        check(request('backend/'.$path.'.php')[0]===403, 'Admin backend protected: '.$path);
    }
}
foreach (['categories/index','categories/form','products/index','products/form','customers/index','customers/detail','orders/index','orders/detail','dashboard/index'] as $view) {
    check(is_file($app.'/frontend/Views/admin/'.$view.'.php') && !is_file($app.'/app/Views/admin/'.$view.'.php'), 'Single Admin frontend: '.$view);
    check(request('frontend/Views/admin/'.$view.'.php')[0]===403, 'Admin View protected: '.$view);
}
foreach (['product-bootstrap','product-validation','product-upload','category-input','customer-view','order-status','order-filters'] as $helper) {
    check(request('backend/Support/Admin/'.$helper.'.php')[0]===403,'Admin helper protected: '.$helper);
    $source = $app.'/admin/includes/'.$helper.'.php';
    check(!is_file($source), 'Unused helper wrapper removed: '.$helper);
}
foreach (['header','footer','pagination'] as $layout) check(request('frontend/includes/admin/'.$layout.'.php')[0]===403,'Admin layout protected: '.$layout);
check(request('backend/Middleware/admin.php')[0]===403,'Admin authorization bridge internal protected');
foreach (glob($app.'/admin/*.php') as $entry) {
    $source=file_get_contents($entry);
    if (!str_contains($source,'Controllers\\Admin')) continue;
    check(str_contains($source,'/backend/bootstrap.php') && !str_contains($source,'/app/bootstrap.php'),'Admin entry uses backend: '.basename($entry));
}
$migrationCookie=$cookie;
request('test-session.php?id=1');
foreach (['index.php','categories.php','category-form.php','products.php','product-form.php','customers.php','orders.php'] as $page) {
    [$status,$html]=request('admin/'.$page);
    check($status===200 && substr_count($html,'class="nav-link active"')===1,'Admin layout/sidebar retained: '.$page);
    check(str_contains($html,'/scr/admin/assets/adminlte/css/adminlte.css') && str_contains($html,'/scr/admin/assets/adminlte/js/adminlte.js'),'Admin asset URLs retained: '.$page);
}
request('test-session.php?id=2');
foreach (['header.php','footer.php','product-upload.php'] as $helper) check(request('admin/includes/'.$helper)[0]===404,'Retired wrapper cannot execute: '.$helper);
$cookie=$migrationCookie;
echo 'Admin migration checks completed: '.($checks-$adminMigrationStart).PHP_EOL;
