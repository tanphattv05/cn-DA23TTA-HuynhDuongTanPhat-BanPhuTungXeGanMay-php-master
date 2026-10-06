<?php
if (PHP_SAPI !== 'cli' || !isset($app, $db)) exit('Run isolated harness.');
$migrationStart = $checks;
$classes = [
    'Controllers/Storefront/ProductController', 'Controllers/Storefront/AuthController',
    'Controllers/Storefront/CartController', 'Controllers/Storefront/CheckoutController',
    'Controllers/Storefront/OrderController', 'Models/StorefrontProduct', 'Models/UserAuth',
    'Models/CartProduct', 'Models/Checkout', 'Models/StorefrontOrder',
    'Services/AuthService', 'Services/CartService', 'Services/CheckoutService',
    'Middleware/Authenticate', 'Middleware/RequireRole', 'Core/AuthCsrf', 'Core/CartCsrf', 'Core/OrderStatus'
];
foreach ($classes as $path) {
    check(is_file($app.'/backend/'.$path.'.php') && !is_file($app.'/app/'.$path.'.php'), 'Single backend implementation: '.$path);
    $class='MotoParts\\App\\'.str_replace('/', '\\', $path);
    check(realpath((new ReflectionClass($class))->getFileName())===realpath($app.'/backend/'.$path.'.php'), 'Autoload resolves backend: '.$path);
    check(request('backend/'.$path.'.php')[0]===403, 'Backend direct HTTP denied: '.$path);
}
foreach (['products/index','products/detail','auth/login','auth/register','auth/error','cart/index','checkout/index','checkout/success','orders/index','orders/detail'] as $view) {
    check(is_file($app.'/frontend/Views/storefront/'.$view.'.php') && !is_file($app.'/app/Views/storefront/'.$view.'.php'), 'Single frontend template: '.$view);
    check(request('frontend/Views/storefront/'.$view.'.php')[0]===403, 'Frontend direct HTTP denied: '.$view);
}
foreach (array_merge(glob($app.'/pages/*.php'),glob($app.'/actions/*.php')) as $entry) {
    $source=file_get_contents($entry);
    if (!str_contains($source,'Controllers\\Storefront')) continue;
    check(str_contains($source,'/backend/bootstrap.php') && !str_contains($source,'/app/bootstrap.php'), 'Storefront entry uses backend: '.basename($entry));
}
check(is_file($app.'/backend/Controllers/Admin/OrderController.php') && is_file($app.'/frontend/Views/admin/orders/detail.php'), 'Admin remains available after migration');
echo 'Storefront migration checks completed: '.($checks-$migrationStart).PHP_EOL;
