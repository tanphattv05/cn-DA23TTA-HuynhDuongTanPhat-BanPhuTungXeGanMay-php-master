<?php
if (PHP_SAPI !== 'cli' || !isset($app, $db)) exit("Run isolated harness.\n");
$mvcChecksStart = $checks;
$internalPaths = [
    'app/bootstrap.php',
    'app/Core/View.php',
    'app/Models/Category.php',
    'app/Controllers/Admin/CategoryController.php',
    'app/Views/admin/categories/index.php',
    'app/Views/admin/categories/form.php'
];
// The isolated PHP server ignores .htaccess: these exercise the PHP guard.
foreach ($internalPaths as $path) {
    [$status,$html] = request($path);
    check($status === 403 && $html === '', 'MVC internal URL denied even with admin session: ' . $path);
}
$originalCookie = $cookie;
$cookie = $temp . '/anonymous-category-mvc.txt';
foreach ($internalPaths as $path) {
    check(request($path)[0] === 403, 'Anonymous MVC internal URL denied: ' . $path);
}
foreach (['categories.php','category-form.php','save-category.php'] as $entry) {
    [$status,,$headers] = request('admin/' . $entry);
    check($status === 302 && str_contains($headers,'/scr/pages/login.php'), 'Legacy MVC entry requires admin authentication: ' . $entry);
}
$before = $db->query('SELECT id,name,description FROM categories ORDER BY id')->fetch_all(MYSQLI_ASSOC);
check(request('admin/save-category.php',['id'=>'','name'=>'Unauthorized MVC'])[0] === 302, 'Anonymous MVC POST denied');
check($before === $db->query('SELECT id,name,description FROM categories ORDER BY id')->fetch_all(MYSQLI_ASSOC), 'Anonymous MVC POST does not write');
$cookie = $originalCookie;
foreach (['index','form'] as $template) {
    $source = file_get_contents($app . '/app/Views/admin/categories/' . $template . '.php');
    check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|mysqli)\b|\$_POST/', $source), 'View contains no SQL or POST handling: ' . $template);
}
$model = file_get_contents($app . '/app/Models/Category.php');
check(!preg_match('/\$_(GET|POST|SESSION)|header\s*\(|http_response_code\s*\(503\)/', $model), 'Category model has no request/session/redirect behavior');
echo 'MVC category checks completed: ' . ($checks - $mvcChecksStart) . PHP_EOL;
