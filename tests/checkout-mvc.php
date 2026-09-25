<?php
if (PHP_SAPI !== 'cli' || !isset($app, $db, $testName) || !preg_match('/\Amotoparts_test_[a-f0-9]+\z/', $testName)) exit("Run isolated harness.\n");
$checkoutStart = $checks;
check($db->query('SELECT DATABASE()')->fetch_row()[0] === $testName, 'Checkout fixtures are isolated');
$db->query("INSERT INTO categories(name) VALUES ('Checkout fixture')");
$checkoutCategory = $db->insert_id;
$stmt = $db->prepare('INSERT INTO products(category_id,name,price,stock) VALUES (?,?,?,?)');
$checkoutIds = [];
foreach ([['Checkout <b>A</b>','12.34',20],['Checkout B','0.10',30],['Checkout empty','1.00',0]] as [$name,$price,$stock]) {
    $stmt->bind_param('issi',$checkoutCategory,$name,$price,$stock);
    $stmt->execute(); $checkoutIds[] = $db->insert_id;
}
[$checkoutA,$checkoutB,$checkoutZero]=$checkoutIds;
$db->query("UPDATE users SET fullname='Nguyễn Lan',phone='0912345678' WHERE id=2");
file_put_contents($app.'/checkout-state.php', <<<'PHP'
<?php
session_start();
if (isset($_POST['reset'])) $_SESSION=['csrf_token'=>'admin-preserved'];
if (isset($_POST['cart'])) $_SESSION['cart']=json_decode($_POST['cart'],true);
if (isset($_POST['user'])) {
    $id=(int)$_POST['user'];
    if ($id) $_SESSION['user']=['id'=>$id,'fullname'=>'Session display','phone'=>'000000000','role'=>$id===1?'admin':'customer'];
    else unset($_SESSION['user']);
}
echo json_encode(['cart'=>$_SESSION['cart']??[], 'has_cart'=>isset($_SESSION['cart']),
    'old'=>$_SESSION['checkout_old']??null, 'error'=>$_SESSION['checkout_error']??null,
    'submit'=>$_SESSION['checkout_submit_token']??null, 'receipt'=>$_SESSION['completed_order_id']??null,
    'admin_token'=>$_SESSION['csrf_token']??null, 'cart_token'=>$_SESSION['cart_csrf_token']??null]);
PHP
);
function checkout_state(): array { return json_decode(request('checkout-state.php')[1],true); }
function checkout_seed(array $cart, int $user=0): void {
    request('checkout-state.php',['reset'=>'1','cart'=>json_encode($cart),'user'=>(string)$user]);
}
function checkout_form(): array {
    [$status,$html]=request('pages/checkout.php');
    if ($status!==200) throw new RuntimeException('Checkout form unavailable: '.$status);
    $dom=customer_test_dom($html);
    return [
        'csrf_token'=>$dom->query('//input[@name="csrf_token"]')->item(0)->getAttribute('value'),
        'submit_token'=>$dom->query('//input[@name="submit_token"]')->item(0)->getAttribute('value')
    ];
}
function checkout_snapshot(): array {
    global $db;
    return [
        $db->query('SELECT id,user_id,total,status FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC),
        $db->query('SELECT order_id,product_id,quantity,price FROM order_details ORDER BY id')->fetch_all(MYSQLI_ASSOC),
        $db->query('SELECT id,stock FROM products ORDER BY id')->fetch_all(MYSQLI_ASSOC)
    ];
}
$checkoutOriginalCookie=$cookie;
$cookie=$temp.'/checkout-tests.txt';
$recipient=['fullname'=>'  Nguyễn <b>Khách</b>  ','phone'=>'090 123 4567','address'=>'  Test <em>address</em>  ','note'=>'<script>note</script>'];
$baseCart=[$checkoutA=>2,$checkoutB=>3];
checkout_seed([]);
check(request('pages/checkout.php')[0]===303 && str_contains(request('pages/cart.php')[1],'Giỏ hàng đang trống'), 'Empty checkout redirects with message');
checkout_seed($baseCart,2);
$tokens=checkout_form();
$html=request('pages/checkout.php')[1];
check(str_contains($html,'value="Nguyễn Lan"') && str_contains($html,'value="0912345678"'), 'Customer fields come from existing account');
check(str_contains($html,'Checkout &lt;b&gt;A&lt;/b&gt;') && trim(customer_test_dom($html)->query('//strong[contains(@class,"text-danger")]')->item(0)->textContent)==='25 ₫' && str_contains($html,'Thanh toán khi nhận hàng'), 'Checkout escapes products and retains COD layout');
check(strlen($tokens['csrf_token'])===64 && strlen($tokens['submit_token'])===64 && checkout_state()['admin_token']==='admin-preserved', 'CSRF and one-use token do not replace Admin token');
$before=checkout_snapshot();
foreach ([
    ['fullname'=>'  '],['fullname'=>str_repeat('ế',101)],['phone'=>'abc'],['phone'=>'12345678'],['address'=>" \n "],
    ['address'=>str_repeat('a',501)],['note'=>str_repeat('n',501)],['fullname[]'=>'array','fullname'=>null],
    ['phone[]'=>'array','phone'=>null],['address[]'=>'array','address'=>null],['note[]'=>'array','note'=>null]
] as $invalid) {
    $payload=array_replace($recipient,$tokens,$invalid);
    foreach ($payload as $key=>$value) if ($value===null) unset($payload[$key]);
    check(request('actions/checkout.php',$payload)[0]===303 && checkout_snapshot()===$before && checkout_state()['cart']==$baseCart, 'Invalid recipient leaves DB/cart unchanged: '.key($invalid));
}
request('actions/checkout.php',array_replace($recipient,$tokens,['fullname'=>'Tên <i>old</i>','phone'=>'bad']));
$html=request('pages/checkout.php')[1];
check(str_contains($html,'value="Tên &lt;i&gt;old&lt;/i&gt;"') && str_contains($html,'value="bad"') && str_contains($html,'Test &lt;em&gt;address&lt;/em&gt;'), 'Old input overrides account, trims and escapes HTML');
foreach ([['csrf_token'=>'wrong'],['csrf_token'=>null],['csrf_token[]'=>'array','csrf_token'=>null],['submit_token'=>'wrong'],['submit_token'=>null]] as $invalid) {
    $payload=array_replace($recipient,$tokens,$invalid);
    foreach ($payload as $key=>$value) if ($value===null) unset($payload[$key]);
    check(request('actions/checkout.php',$payload)[0]===303 && checkout_snapshot()===$before, 'Invalid CSRF/submission token cannot write: '.key($invalid));
}
check(request('actions/checkout.php')[0]===303 && checkout_snapshot()===$before, 'GET checkout action never writes');
foreach ([[$checkoutA=>21],[$checkoutZero=>1],[2147483647=>1],[$checkoutA=>0],[$checkoutA=>-1],['bad'=>1],[$checkoutA=>'1.5']] as $badCart) {
    request('checkout-state.php',['cart'=>json_encode($badCart)]);
    request('actions/checkout.php',$recipient+$tokens);
    check(checkout_snapshot()===$before && checkout_state()['cart']==$badCart, 'Invalid or unavailable cart rejected without writes');
}
request('checkout-state.php',['cart'=>json_encode($baseCart)]);
// Inject a failure on the second detail, after first stock deduction.
foreach (['detail','stock'] as $failure) {
    $trigger = $failure==='detail'
        ? "CREATE TRIGGER checkout_fail BEFORE INSERT ON order_details FOR EACH ROW BEGIN IF NEW.product_id=$checkoutB THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='private detail failure'; END IF; END"
        : "CREATE TRIGGER checkout_fail BEFORE UPDATE ON products FOR EACH ROW BEGIN IF NEW.id=$checkoutB THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='private stock failure'; END IF; END";
    $db->query($trigger);
    try {
        request('actions/checkout.php',$recipient+$tokens);
        $state=checkout_state();
        check(checkout_snapshot()===$before && $state['cart']==$baseCart && $state['old']['address']==='Test <em>address</em>' && $state['submit']===$tokens['submit_token'], 'Entire transaction rolled back; cart/input/token retained: '.$failure);
        $html=request('pages/checkout.php')[1];
        check(str_contains($html,'Không thể xử lý thanh toán') && !str_contains($html,'private '.$failure) && !str_contains($html,$testName), 'Database failure is generic: '.$failure);
    } finally { $db->query('DROP TRIGGER checkout_fail'); }
}
// Zero affected-row stock update must also abort the entire order.
$db->query("CREATE TRIGGER checkout_no_stock BEFORE UPDATE ON products FOR EACH ROW BEGIN IF NEW.id=$checkoutB THEN SET NEW.stock=OLD.stock; END IF; END");
try {
    request('actions/checkout.php',$recipient+$tokens);
    check(checkout_snapshot()===$before && checkout_state()['cart']==$baseCart, 'Zero affected stock rows rolls back first product and all details');
} finally { $db->query('DROP TRIGGER checkout_no_stock'); }
checkout_seed($baseCart);
$guestTokens=checkout_form();
$db->query('UPDATE products SET price=13.37 WHERE id='.$checkoutA);
[$status,,$headers]=request('actions/checkout.php',$recipient+$guestTokens+['user_id'=>2,'price'=>0,'total'=>0,'return_to'=>'https://example.com']);
$guestOrder=$db->query('SELECT id,user_id,fullname,phone,address,note,total,status FROM orders ORDER BY id DESC LIMIT 1')->fetch_assoc();
$guestId=(int)$guestOrder['id'];
check($status===303 && str_contains($headers,'../pages/order-success.php') && $guestOrder['user_id']===null && $guestOrder['status']==='pending', 'Guest pending order uses session identity and safe PRG');
check($guestOrder['total']==='27.04' && $guestOrder['phone']==='0901234567' && $guestOrder['fullname']==='Nguyễn <b>Khách</b>', 'Order rereads current DB prices and normalized recipient');
$sum=$db->query('SELECT SUM(price*quantity) FROM order_details WHERE order_id='.$guestId)->fetch_row()[0];
check($sum==='27.04' && $db->query('SELECT price FROM order_details WHERE order_id='.$guestId.' AND product_id='.$checkoutA)->fetch_row()[0]==='13.37', 'Historical price and exact total match details');
check((int)$db->query('SELECT stock FROM products WHERE id='.$checkoutA)->fetch_row()[0]===18 && (int)$db->query('SELECT stock FROM products WHERE id='.$checkoutB)->fetch_row()[0]===27, 'Commit deducts both products correctly');
$state=checkout_state();
check(!$state['has_cart'] && $state['old']===null && $state['error']===null && $state['submit']===null, 'Only commit clears cart, input, error and one-use token');
$afterGuest=checkout_snapshot();
request('actions/checkout.php',$recipient+$guestTokens);
check(checkout_snapshot()===$afterGuest, 'Replaying successful submission token cannot create another order');
$html=request('pages/order-success.php?id=999999')[1];
check(str_contains($html,'#'.$guestId) && !str_contains($html,'href="order-detail.php') && !str_contains($html,'badge bg-danger'), 'Guest success uses only receipt session and cart badge clears');
check(request('pages/order-success.php')[0]===303 && checkout_snapshot()===$afterGuest, 'Refresh success does not create order or disclose another receipt');
checkout_seed([$checkoutA=>1],2);
$customerTokens=checkout_form();
request('actions/checkout.php',$recipient+$customerTokens+['user_id'=>1]);
$customerOrder=$db->query('SELECT id,user_id FROM orders ORDER BY id DESC LIMIT 1')->fetch_assoc();
$customerOrderId=(int)$customerOrder['id'];
check((int)$customerOrder['user_id']===2, 'Customer order uses server session ID, not POST ID');
check(str_contains(request('pages/order-success.php')[1],'order-detail.php?id='.$customerOrderId), 'Customer success links to owned order');
check(request('pages/order-detail.php?id='.$customerOrderId)[0]===200 && request('pages/order-detail.php?id='.$guestId)[0]===404, 'Customer sees own order but not guest order');
check(str_contains(request('pages/my-orders.php')[1],'order-detail.php?id='.$customerOrderId), 'Customer history includes new order');
request('checkout-state.php',['user'=>'1']);
check(request('pages/order-detail.php?id='.$customerOrderId)[0]===404, 'Other authenticated account cannot view customer order');
request('checkout-state.php',['cart'=>json_encode([$checkoutA=>1])]);
check(request('pages/checkout.php')[0]===200,'Admin can open storefront checkout');
// Admin views and cancellation on the newly placed guest order.
$adminHtml=request('admin/order-detail.php?id='.$guestId)[1];
check(str_contains($adminHtml,'Khách vãng lai') && str_contains($adminHtml,'13,37') && str_contains(request('admin/orders.php?q='.$guestId)[1],'order-detail.php?id='.$guestId), 'Admin sees guest and historical price');
$db->query('UPDATE products SET price=99.00 WHERE id='.$checkoutA);
check(str_contains(request('admin/order-detail.php?id='.$guestId)[1],'13,37') && $db->query('SELECT total FROM orders WHERE id='.$guestId)->fetch_row()[0]==='27.04', 'Changing current product price leaves historical order unchanged');
$dashboard=request('admin/index.php')[1];
$expectedRevenue=$db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='completed'")->fetch_row()[0];
$dashboardValues=dashboard_cards($dashboard);
check(end($dashboardValues)===number_format((float)$expectedRevenue,0,',','.').' ₫', 'Dashboard revenue excludes new pending orders');
$adminToken=customer_test_dom($adminHtml)->query('//input[@name="csrf_token"]')->item(0)->getAttribute('value');
$stockBefore=(int)$db->query('SELECT stock FROM products WHERE id='.$checkoutA)->fetch_row()[0];
$cancel=['order_id'=>$guestId,'status'=>'cancelled','csrf_token'=>$adminToken,'return_to'=>'detail'];
request('admin/update-order.php',$cancel);
request('admin/update-order.php',$cancel);
check((int)$db->query('SELECT stock FROM products WHERE id='.$checkoutA)->fetch_row()[0]===$stockBefore+2, 'New checkout order cancellation restores stock once');
checkout_seed([$checkoutA=>1],2147483647);
check(request('pages/checkout.php')[0]===409, 'Deleted/nonexistent session user cannot checkout');
checkout_seed([$checkoutA=>1]);
$tokens=checkout_form();
request('checkout-state.php',['user'=>'bad']);
request('checkout-state.php',['user'=>'2147483647']);
$before=checkout_snapshot();
request('actions/checkout.php',$recipient+$tokens);
check(checkout_snapshot()===$before && checkout_state()['has_cart'], 'Deleted user during submission leaves cart intact');
checkout_seed([$checkoutA=>100,2147483647=>1]);
check(request('pages/checkout.php')[0]===303 && !isset(checkout_state()['cart'][2147483647]) && checkout_state()['cart'][$checkoutA]<100, 'GET checkout reconciles stale cart then returns to cart for review');
checkout_seed([$checkoutZero=>1]);
check(request('pages/checkout.php')[0]===303 && checkout_state()['cart']===[], 'GET checkout handles fully out-of-stock cart');
checkout_seed([$checkoutA=>1]);
$db->query('RENAME TABLE products TO checkout_products_unavailable');
try {
    [$status,$html]=request('pages/checkout.php');
    check($status===503 && str_contains($html,'navbar') && str_contains($html,'Không thể xử lý thanh toán') && !str_contains($html,$testName), 'Checkout read error returns safe 503 layout');
} finally { $db->query('RENAME TABLE checkout_products_unavailable TO products'); }
foreach (['app/Models/Checkout.php','app/Services/CheckoutService.php','app/Controllers/Storefront/CheckoutController.php','app/Views/storefront/checkout/index.php','app/Views/storefront/checkout/success.php'] as $path) {
    check(request($path)[0]===403,'Checkout internal path forbidden: '.$path);
}
foreach (['pages/checkout.php','pages/order-success.php','actions/checkout.php'] as $entry) {
    $source=file_get_contents($app.'/'.$entry);
    check(strlen($source)<250 && str_contains($source,'CheckoutController'),'Thin checkout entry: '.$entry);
}
check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/',file_get_contents($app.'/app/Controllers/Storefront/CheckoutController.php')),'Checkout controller has no SQL');
check(!preg_match('/\$_(GET|POST|SESSION)|password/',file_get_contents($app.'/app/Models/Checkout.php')),'Checkout model has no request/session/password');
check(!preg_match('/\$_(GET|POST|SESSION)|<html|echo\s/',file_get_contents($app.'/app/Services/CheckoutService.php')),'Checkout service has no request/session/HTML');
foreach (['index','success'] as $view) check(!preg_match('/\b(SELECT|mysqli_query)\b|\$_(GET|POST|SESSION)/',file_get_contents($app.'/app/Views/storefront/checkout/'.$view.'.php')),'Checkout view only renders: '.$view);

// DECIMAL boundary: reject overflow before multiplication; preserve all writes atomically.
$db->query('UPDATE products SET stock=2147483647,price=9999999999.99 WHERE id='.$checkoutA);
checkout_seed([$checkoutA=>1]);
$boundaryTokens=checkout_form();
request('checkout-state.php',['cart'=>json_encode([$checkoutA=>2147483647])]);
$beforeBoundary=checkout_snapshot();
request('actions/checkout.php',$recipient+$boundaryTokens);
check(checkout_snapshot()===$beforeBoundary && str_contains(checkout_state()['error'],'vượt giới hạn'), 'Huge DECIMAL multiplication rejected before integer overflow');
request('checkout-state.php',['cart'=>json_encode([$checkoutA=>1])]);
request('actions/checkout.php',$recipient+$boundaryTokens);
$boundaryOrder=$db->query('SELECT id,total FROM orders ORDER BY id DESC LIMIT 1')->fetch_assoc();
check($boundaryOrder['total']==='9999999999.99' && $db->query('SELECT SUM(price*quantity) FROM order_details WHERE order_id='.(int)$boundaryOrder['id'])->fetch_row()[0]==='9999999999.99', 'Maximum DECIMAL total stored exactly without float rounding');
// Separate PHP processes/connections contend for the last stock in the isolated DB.
// Parent holds the product lock until both workers have started.
$db->query('UPDATE products SET stock=5,price=3.09 WHERE id='.$checkoutA);
$workerPath=$temp.'/checkout-worker.php';
$workerCode = "<?php\nif (PHP_SAPI !== 'cli') exit;\ndefine('MOTOPARTS_MVC_ENTRY',true);\nrequire " . var_export($app.'/app/bootstrap.php',true) . ";\nrequire " . var_export($app.'/config/database.php',true) . ";\n";
$workerCode .= '$model = new \\MotoParts\\App\\Models\\Checkout($conn); $service = new \\MotoParts\\App\\Services\\CheckoutService($model);' . "\n";
$workerCode .= <<<'PHP'
file_put_contents($argv[2].'.ready','ready');
try {
    $id=$service->place([(int)$argv[1]=>4],['fullname'=>'Isolated worker','phone'=>'0901234567','address'=>'Test','note'=>''],null);
    file_put_contents($argv[2],json_encode(['result'=>'created','id'=>$id]));
} catch (DomainException $e) {
    file_put_contents($argv[2],json_encode(['result'=>'rejected']));
} catch (Throwable $e) {
    file_put_contents($argv[2],json_encode(['result'=>'error','code'=>$e->getCode()]));
    exit(1);
}
PHP;
file_put_contents($workerPath,$workerCode);
$workers=[];
$countBefore=(int)$db->query('SELECT COUNT(*) FROM orders')->fetch_row()[0];
$db->begin_transaction();
try {
    $db->query('SELECT id FROM products WHERE id='.$checkoutA.' FOR UPDATE');
    for ($i=0;$i<2;$i++) {
        $output=$temp.'/checkout-worker-'.$i.'.json';
        $process=proc_open([PHP_BINARY,$workerPath,(string)$checkoutA,$output],[0=>['pipe','r'],1=>['file',$temp.'/checkout-worker.log','a'],2=>['file',$temp.'/checkout-worker.log','a']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start checkout worker');
        fclose($pipes[0]);
        $workers[]=['process'=>$process,'output'=>$output];
    }
    $ready=false;
    for ($attempt=0;$attempt<100;$attempt++) {
        if (is_file($workers[0]['output'].'.ready') && is_file($workers[1]['output'].'.ready')) { $ready=true; break; }
        usleep(50000);
    }
    check($ready,'Both checkout workers started while product row is locked');
    usleep(150000);
    check(!is_file($workers[0]['output']) && !is_file($workers[1]['output']),'Neither competing checkout bypasses held product lock');
    $db->commit();
    $finished=false;
    for ($attempt=0;$attempt<200;$attempt++) {
        if (is_file($workers[0]['output']) && is_file($workers[1]['output'])) { $finished=true; break; }
        usleep(50000);
    }
    check($finished,'Both competing checkout transactions finished');
    $results=[];
    foreach ($workers as $worker) $results[]=json_decode(file_get_contents($worker['output']),true)['result'];
    sort($results);
    check($results===['created','rejected'],'Only one competing checkout can buy the available stock');
    check((int)$db->query('SELECT stock FROM products WHERE id='.$checkoutA)->fetch_row()[0]===1
        && (int)$db->query('SELECT COUNT(*) FROM orders')->fetch_row()[0]===$countBefore+1,'Competition leaves nonnegative stock and exactly one complete order');
} finally {
    $db->rollback();
    foreach ($workers as $worker) {
        $status=proc_get_status($worker['process']);
        if ($status['running']) proc_terminate($worker['process']);
        proc_close($worker['process']);
    }
}
$cookie=$checkoutOriginalCookie;
echo 'Checkout MVC checks completed: '.($checks-$checkoutStart).PHP_EOL;