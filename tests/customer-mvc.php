<?php
if (PHP_SAPI !== 'cli' || !isset($app, $db)) exit("Run isolated harness.\n");
$customerMvcStart = $checks;
$internalPaths = ['app/Models/Customer.php','app/Controllers/Admin/CustomerController.php','app/Views/admin/customers/index.php','app/Views/admin/customers/detail.php'];
foreach ($internalPaths as $path) {
    [$status,$body] = request($path);
    check($status === 403 && $body === '', 'Customer MVC internal file denied to admin: '.$path);
}
$originalCookie = $cookie;
$cookie = $temp.'/anonymous-customer-mvc.txt';
foreach ($internalPaths as $path) check(request($path)[0] === 403, 'Anonymous customer MVC internal file denied: '.$path);
foreach (['customers.php','customer-detail.php?id='.$mainId] as $entry) {
    [$status,,$headers] = request('admin/'.$entry);
    check($status === 302 && str_contains($headers,'/scr/pages/login.php'), 'Legacy customer MVC entry requires login: '.$entry);
}
$cookie = $originalCookie;
request('test-session.php?id=2');
foreach ($internalPaths as $path) check(request($path)[0] === 403, 'Customer cannot access MVC internals: '.$path);
request('test-session.php?id=1');
$model = file_get_contents($app.'/app/Models/Customer.php');
check(!preg_match('/password|SELECT\s+(?:u\.)?\*/i',$model), 'Customer model selects explicit public columns only');
check(!preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b/',$model), 'Customer model only contains read queries');
check(!preg_match('/\$_(GET|POST|SESSION)|header\s*\(/',$model), 'Customer model has no HTTP/session behavior');
$controller = file_get_contents($app.'/app/Controllers/Admin/CustomerController.php');
check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/',$controller), 'Customer controller has no SQL');
foreach (['index','detail'] as $template) {
    $source = file_get_contents($app.'/app/Views/admin/customers/'.$template.'.php');
    check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|mysqli)\b|\$_POST/',$source), 'Customer view has no SQL/POST: '.$template);
}
foreach (['customers.php','customer-detail.php'] as $entry) {
    $source = file_get_contents($app.'/admin/'.$entry);
    check(str_contains($source,'CustomerController') && !preg_match('/\b(SELECT|mysqli)\b|\$_(GET|POST)/',$source), 'Legacy customer URL is a thin entry: '.$entry);
}
echo 'Customer MVC checks completed: '.($checks-$customerMvcStart).PHP_EOL;
