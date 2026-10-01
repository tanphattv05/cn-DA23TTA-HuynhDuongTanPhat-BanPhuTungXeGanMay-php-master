<?php
if (PHP_SAPI !== 'cli' || !isset($app,$db,$testName)) exit("Run isolated harness.\n");
$authStart=$checks;
$authOriginalCookie=$cookie;
$cookie=$temp.'/auth-tests.txt';
file_put_contents($app.'/auth-state.php', <<<'PHP'
<?php
session_start();
if (isset($_POST['seed'])) $_SESSION=['cart'=>[123=>2],'cart_csrf_token'=>'cart-marker','csrf_token'=>'admin-marker'];
if (isset($_POST['user_id'])) $_SESSION['user']=['id'=>(int)$_POST['user_id'],'fullname'=>'Forged','role'=>$_POST['role']??'customer'];
if (isset($_POST['private'])) { $_SESSION['completed_order_id']=999; $_SESSION['checkout_old']=['address'=>'Private']; }
echo json_encode(['sid'=>session_id(),'session'=>$_SESSION]);
PHP
);
function auth_state(): array { return json_decode(request('auth-state.php')[1],true); }
function auth_form_token(string $page='login.php'): string {
    $html=request('pages/'.$page)[1];
    $node=customer_test_dom($html)->query('//input[@name="csrf_token"]')->item(0);
    return $node ? $node->getAttribute('value') : '';
}
request('auth-state.php',['seed'=>1]);
$authToken=auth_form_token();
check(strlen($authToken)===64,'Auth form supplies random CSRF token');
$secret='Auth-only-test!912';
$registration=['fullname'=>'  Nguyễn <b>Khách</b>  ','email'=>'  MVC.Auth@Example.test  ','phone'=>'090 123 4567','password'=>$secret,'password_confirm'=>$secret,'csrf_token'=>$authToken,'role'=>'admin'];
$count=(int)$db->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
foreach ([
    ['fullname'=>' '],['fullname'=>str_repeat('a',101)],['email'=>'bad'],['email'=>str_repeat('a',101).'@test.invalid'],
    ['phone'=>'bad'],['password'=>'12345','password_confirm'=>'12345'],['password_confirm'=>'different'],
    ['password'=>str_repeat('a',73),'password_confirm'=>str_repeat('a',73)],['password'=>"abc\0def",'password_confirm'=>"abc\0def"],
    ['fullname[]'=>'bad','fullname'=>null],['password[]'=>'bad','password'=>null],['csrf_token'=>'bad']
] as $invalid) {
    $payload=array_replace($registration,$invalid);
    foreach ($payload as $k=>$v) if ($v===null) unset($payload[$k]);
    check(request('actions/register.php',$payload)[0]===303 && (int)$db->query('SELECT COUNT(*) FROM users')->fetch_row()[0]===$count,'Invalid registration rejected: '.key($invalid));
}
$old=request('pages/register.php')[1];
check(str_contains($old,'Nguyễn &lt;b&gt;Khách&lt;/b&gt;') && !str_contains($old,$secret) && !str_contains(json_encode(auth_state()),$secret),'Old input escaped without password');
check(request('actions/register.php',array_diff_key($registration,['csrf_token'=>1]))[0]===303 && (int)$db->query('SELECT COUNT(*) FROM users')->fetch_row()[0]===$count,'Registration missing CSRF cannot write');
[$status,,$headers]=request('actions/register.php',$registration);
$created=$db->query("SELECT id,fullname,email,phone,password,role FROM users WHERE email='mvc.auth@example.test'")->fetch_assoc();
check($status===303 && str_contains($headers,'pages/login.php') && $created && $created['role']==='customer','Registration forces customer and redirects to login');
$authUserId=(int)$created['id'];
check($created['email']==='mvc.auth@example.test' && $created['phone']==='0901234567' && $created['fullname']==='Nguyễn <b>Khách</b>','Registration normalizes public fields');
check($created['password']!==$secret && password_verify($secret,$created['password']),'Password stored as verifiable hash');
check(!isset(auth_state()['session']['user']),'Registration does not auto-login');
request('actions/register.php',$registration);
check((int)$db->query("SELECT COUNT(*) FROM users WHERE email='mvc.auth@example.test'")->fetch_row()[0]===1,'Duplicate registration does not add user');
$login=['email'=>' MVC.AUTH@EXAMPLE.TEST ','password'=>$secret,'csrf_token'=>$authToken,'role'=>'admin','user_id'=>1,'return_to'=>'https://example.com'];
foreach (['missing@example.test','mvc.auth@example.test'] as $email) {
    request('actions/login.php',array_replace($login,['email'=>$email,'password'=>'Wrong-secret']));
    $messages[]=auth_state()['session']['login_error']??'';
    check(!isset(auth_state()['session']['user']),'Wrong credentials do not authenticate');
}
check($messages[0]!=='' && $messages[0]===$messages[1],'Unknown email and wrong password share generic message');
foreach (['bad',''] as $tokenBad) {
    request('actions/login.php',array_replace($login,['csrf_token'=>$tokenBad]));
    check(!isset(auth_state()['session']['user']),'Bad login CSRF rejected');
}
$before=auth_state();
[$status,,$headers]=request('actions/login.php',$login);
$after=auth_state();
check($status===303 && str_contains($headers,'Location: ../index.php') && !str_contains($headers,'example.com'),'Customer login fixed redirect');
check($before['sid']!==$after['sid'] && $after['session']['cart']==$before['session']['cart'],'Login regenerates session while preserving cart');
check($after['session']['user']['id']===$authUserId && $after['session']['user']['role']==='customer','Login identity cannot be forged by POST');
$keys=array_keys($after['session']['user']); sort($keys);
check($keys===['email','fullname','id','phone','role'] && !str_contains(json_encode($after['session']),$secret) && !str_contains(json_encode($after['session']),$created['password']),'Session has only public user fields');
check($after['session']['cart_csrf_token']==='cart-marker' && $after['session']['csrf_token']==='admin-marker','Login preserves other feature CSRF tokens');
check(request('admin/index.php')[0]===403,'Customer denied Admin');
$html=request('pages/products.php')[1];
check(str_contains($html,'Nguyễn &lt;b&gt;Khách&lt;/b&gt;') && str_contains($html,'method="post"') && !preg_match('~href="[^"]*actions/logout.php~',$html),'Storefront navbar has escaped user and POST logout');
$newAuthToken=customer_test_dom($html)->query('//form[contains(@action,"logout.php")]/input[@name="auth_csrf_token"]')->item(0)->getAttribute('value');
check(request('actions/logout.php')[0]===303 && isset(auth_state()['session']['user']),'GET logout does not sign out');
foreach ([[],['auth_csrf_token'=>'bad']] as $data) {
    request('actions/logout.php',$data);
    check(isset(auth_state()['session']['user']),'Missing/wrong logout CSRF leaves identity');
}
request('auth-state.php',['private'=>1]);
$before=auth_state();
request('actions/logout.php',['auth_csrf_token'=>$newAuthToken]);
$after=auth_state();
check(!isset($after['session']['user']) && !isset($after['session']['completed_order_id']) && !isset($after['session']['checkout_old']),'Logout removes authentication and private receipt');
check($after['sid']!==$before['sid'] && $after['session']['cart']==$before['session']['cart'],'Logout regenerates session and retains cart');
check(request('admin/index.php')[0]===302 && request('pages/my-orders.php')[0]===302 && request('pages/order-detail.php?id=1')[0]===302,'Guest blocked from Admin and private orders');
check(str_contains(request('pages/products.php')[1],'Đăng nhập'),'Navbar becomes guest after logout');
$hash=password_hash('Admin-test-secret',PASSWORD_DEFAULT);
$stmt=$db->prepare('UPDATE users SET password=? WHERE id=1'); $stmt->bind_param('s',$hash); $stmt->execute();
$adminEmail=$db->query('SELECT email FROM users WHERE id=1')->fetch_row()[0];
$authToken=auth_form_token();
[$status,,$headers]=request('actions/login.php',['email'=>$adminEmail,'password'=>'Admin-test-secret','csrf_token'=>$authToken]);
check($status===303 && str_contains($headers,'../admin/index.php') && request('admin/index.php')[0]===200,'Admin login and authorization work');
$adminHtml=request('admin/index.php')[1];
check(!preg_match('~href="[^"]*actions/logout.php~',$adminHtml) && substr_count($adminHtml,'actions/logout.php')===2,'Both Admin logout controls use POST');
request('auth-state.php',['user_id'=>$authUserId,'role'=>'admin']);
check(request('admin/index.php')[0]===403,'Forged session role cannot elevate customer');
$owned=$db->query('SELECT id FROM orders WHERE user_id=2 LIMIT 1')->fetch_row();
if ($owned) check(request('pages/order-detail.php?id='.$owned[0])[0]===404,'Customer cannot read another customer order');
request('auth-state.php',['user_id'=>2147483647,'role'=>'admin']);
check(request('pages/my-orders.php')[0]===302 && !isset(auth_state()['session']['user']),'Deleted user invalidates session on protected page');
request('auth-state.php',['user_id'=>1,'role'=>'invalid']);
check(request('admin/index.php')[0]!==200 && !isset(auth_state()['session']['user']),'Invalid session role is rejected');
foreach (['Controllers/Storefront/AuthController.php','Models/UserAuth.php','Services/AuthService.php','Middleware/Authenticate.php','Middleware/RequireRole.php','Core/AuthCsrf.php','Views/storefront/auth/login.php','Views/storefront/auth/register.php'] as $path) {
    check(request('app/'.$path)[0]===403,'Auth internal file denied: '.$path);
}
foreach (['pages/login.php','pages/register.php','actions/login.php','actions/register.php','actions/logout.php','admin/auth.php'] as $path) {
    $source=file_get_contents($app.'/'.$path);
    check(strlen($source)<700 && !preg_match('/\bSELECT\b|mysqli_query/',$source),'Thin auth entry: '.$path);
}
check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/',file_get_contents($app.'/app/Controllers/Storefront/AuthController.php')),'Auth Controller has no SQL');
check(!preg_match('/\$_(GET|POST|SESSION)|SELECT\s+\*/i',file_get_contents($app.'/app/Models/UserAuth.php')),'Auth Model has no request or wildcard SELECT');
check(!preg_match('/\$_(GET|POST)|\b(SELECT|INSERT|UPDATE|DELETE)\b|<html/',file_get_contents($app.'/app/Services/AuthService.php')),'Auth Service has no SQL/request/HTML');
foreach (['login','register'] as $view) check(!preg_match('/\bSELECT\b|mysqli_query|\$_POST/',file_get_contents($app.'/app/Views/storefront/auth/'.$view.'.php')),'Auth View only renders: '.$view);
check(!str_contains(file_get_contents($temp.'/server.log'),$secret),'No raw password in server log');
// Two PHP processes use separate DB connections; the second reaches the unique
// insert while the first registration is still uncommitted (not visible to its precheck).
$workerPath=$temp.'/auth-worker.php';
$workerCode="<?php\nif (PHP_SAPI !== 'cli') exit;\ndefine('MOTOPARTS_MVC_ENTRY',true);\nrequire ".var_export($app.'/app/bootstrap.php',true).";\nrequire ".var_export($app.'/config/database.php',true).";\n";
$workerCode.= <<<'PHP'
$service=new \MotoParts\App\Services\AuthService(new \MotoParts\App\Models\UserAuth($conn));
$holder=$argv[2]==='holder';
if ($holder) $conn->begin_transaction();
else file_put_contents($argv[1].'.ready',(string)$conn->thread_id);
try {
    $service->register(['fullname'=>'Concurrent fixture','email'=>'auth-race@example.test','phone'=>'0901234567','password'=>'Race-only-secret','password_confirm'=>'Race-only-secret']);
    if ($holder) {
        file_put_contents($argv[1].'.ready',(string)$conn->thread_id);
        $released=false;
        for ($i=0;$i<150;$i++) {
            if (is_file($argv[1].'.release')) { $released=true; break; }
            usleep(100000);
        }
        if (!$released) throw new RuntimeException('Test barrier timeout');
        $conn->commit();
    }
    file_put_contents($argv[1],'created');
} catch (DomainException $e) {
    file_put_contents($argv[1],$e->getMessage()==='Email này đã được sử dụng.' ? 'rejected' : 'unexpected');
} catch (Throwable $e) { file_put_contents($argv[1],'error-'.(int)$e->getCode()); exit(1); }
PHP;
file_put_contents($workerPath,$workerCode);
$workers=[];
try {
    foreach (['holder','contender'] as $mode) {
        $output=$temp.'/auth-worker-'.$mode.'.txt';
        $process=proc_open([PHP_BINARY,$workerPath,$output,$mode],[0=>['pipe','r'],1=>['file',$temp.'/auth-worker.log','a'],2=>['file',$temp.'/auth-worker.log','a']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start auth worker');
        fclose($pipes[0]);
        $workers[]=['process'=>$process,'output'=>$output];
        $ready=false;
        for ($attempt=0;$attempt<100;$attempt++) {
            if (is_file($output.'.ready')) { $ready=true; break; }
            usleep(50000);
        }
        check($ready,'Registration worker ready: '.$mode);
    }
    $waiting=false;
    $threadId=(int)file_get_contents($workers[1]['output'].'.ready');
    for ($attempt=0;$attempt<100;$attempt++) {
        $waiting=(int)$db->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state='LOCK WAIT' AND trx_mysql_thread_id=".$threadId)->fetch_row()[0]===1;
        if ($waiting) break;
        usleep(100000);
    }
    check($waiting,'Competing registration passes precheck and waits on unique email index');
    file_put_contents($workers[0]['output'].'.release','commit');
    $finished=false;
    for ($attempt=0;$attempt<100;$attempt++) {
        if (is_file($workers[0]['output']) && is_file($workers[1]['output'])) { $finished=true; break; }
        usleep(100000);
    }
    check($finished,'Both competing registrations finish');
    $results=array_map(static fn($worker)=>file_get_contents($worker['output']),$workers); sort($results);
    check($results===['created','rejected'],'Concurrent duplicate converted to friendly rejection: '.implode(',',$results));
    check((int)$db->query("SELECT COUNT(*) FROM users WHERE email='auth-race@example.test' AND role='customer'")->fetch_row()[0]===1,'Email race creates exactly one customer');
} finally {
    foreach ($workers as $worker) {
        if (proc_get_status($worker['process'])['running']) proc_terminate($worker['process']);
        proc_close($worker['process']);
    }
}
request('auth-state.php',['seed'=>1]);
$authToken=auth_form_token();
request('actions/login.php',['email'=>"' OR 1=1 --",'password'=>$secret,'csrf_token'=>$authToken]);
check(!isset(auth_state()['session']['user']),'SQL injection cannot authenticate');
request('actions/login.php',['email[]'=>'bad','password[]'=>'bad','csrf_token'=>$authToken]);
check(!isset(auth_state()['session']['user']),'Array credentials rejected without server error');
$count=(int)$db->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
check(request('actions/register.php')[0]===303 && request('actions/login.php')[0]===303 && (int)$db->query('SELECT COUNT(*) FROM users')->fetch_row()[0]===$count,'GET cannot register or login');
$db->query('RENAME TABLE users TO auth_users_unavailable');
try {
    [$status,$body]=request('actions/login.php',['email'=>'mvc.auth@example.test','password'=>$secret,'csrf_token'=>$authToken]);
    check($status===303 && !isset(auth_state()['session']['user']) && !str_contains($body,'mysqli'),'DB login failure does not authenticate or expose details');
    request('auth-state.php',['user_id'=>1,'role'=>'admin']);
    [$status,$body]=request('admin/index.php');
    check($status===503 && !preg_match('/Stack trace|SELECT |xampp|mysqli/', $body),'Protected page DB failure is friendly 503');
} finally { $db->query('RENAME TABLE auth_users_unavailable TO users'); }
$adminLogoutToken=customer_test_dom(request('admin/index.php')[1])->query('//input[@name="auth_csrf_token"]')->item(0)->getAttribute('value');
request('actions/logout.php',['auth_csrf_token'=>$adminLogoutToken]);
check(!isset(auth_state()['session']['user']) && auth_state()['session']['cart']==[123=>2] && request('admin/index.php')[0]===302,'Admin logout preserves cart and removes access');
$cookie=$authOriginalCookie;
echo 'Auth MVC checks completed: '.($checks-$authStart).PHP_EOL;