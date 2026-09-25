<?php
if (PHP_SAPI !== 'cli' || !isset($app, $db, $testName) || !preg_match('/\Amotoparts_test_[a-f0-9]+\z/', $testName)) exit("Run isolated harness.\n");
$cartStart = $checks;
check($db->query('SELECT DATABASE()')->fetch_row()[0] === $testName, 'Cart fixtures use isolated DB');
$db->query("INSERT INTO categories(name) VALUES ('Cart test')");
$cartCategory = $db->insert_id;
$stmt = $db->prepare('INSERT INTO products(category_id,name,price,stock,image) VALUES (?, ?, ?, ?, ?)');
$cartIds = [];
foreach ([['Cart <script>X</script>', '123.45', 5], ['Other cart item','10.00',9], ['Empty stock','9.00',0]] as [$name,$price,$stock]) {
    $filename = 'cart-test.png';
    $stmt->bind_param('issis', $cartCategory, $name, $price, $stock, $filename);
    $stmt->execute();
    $cartIds[] = $db->insert_id;
}
[$cartA,$cartB,$cartZero] = $cartIds;
copy($png, $app . '/assets/images/products/cart-test.png');
file_put_contents($app . '/cart-state.php', '<?php session_start(); if (isset($_GET["reset"])) { $_SESSION=["cart"=>[], "csrf_token"=>"admin-marker"]; } if (isset($_GET["role"])) { $_SESSION["user"]=["id"=>1,"fullname"=>"Test admin","role"=>"admin"]; } echo json_encode(["cart"=>$_SESSION["cart"]??[], "has_cart"=>isset($_SESSION["cart"]), "user"=>isset($_SESSION["user"]), "admin_token"=>$_SESSION["csrf_token"]??null, "cart_token"=>$_SESSION["cart_csrf_token"]??null, "old"=>isset($_SESSION["checkout_old"])]);');
function cart_state(): array { return json_decode(request('cart-state.php')[1], true); }
function cart_token(int $id): string {
    return customer_test_dom(request('pages/product-detail.php?id=' . $id)[1])->query('//input[@name="csrf_token"]')->item(0)->getAttribute('value');
}
$cartOriginalCookie = $cookie;
$cookie = $temp . '/cart-tests-cookie.txt';
request('cart-state.php?reset=1');
$cartToken = cart_token($cartA);
check(strlen($cartToken) === 64 && cart_state()['admin_token'] === 'admin-marker', 'Random cart token leaves Admin token unchanged');
$add = ['product_id'=>$cartA,'quantity'=>2,'csrf_token'=>$cartToken];
[$status, , $headers] = request('actions/add_cart.php', $add + ['price'=>'0','stock'=>'9999','return_to'=>'https://example.com']);
check($status === 303 && str_contains($headers,'Location: ../pages/cart.php') && cart_state()['cart'] == [$cartA=>2], 'Guest add uses fixed PRG and ignores forged price/stock/redirect');
$html = request('pages/cart.php')[1];
check(str_contains($html,'Đã thêm sản phẩm vào giỏ hàng.') && !str_contains(request('pages/cart.php')[1],'Đã thêm sản phẩm vào giỏ hàng.'), 'Success flash shown exactly once');
check(cart_state()['cart'] == [$cartA=>2], 'Refreshing GET does not repeat add');
request('actions/add_cart.php', $add);
check(cart_state()['cart'] == [$cartA=>4], 'Repeated add accumulates');
request('actions/add_cart.php', $add);
check(cart_state()['cart'] == [$cartA=>5] && str_contains(request('pages/cart.php')[1],'Số lượng vượt tồn kho'), 'Add caps combined quantity at stock with notice');
foreach (['0','-1','abc','1.5','2147483648','1 OR 1=1'] as $bad) {
    $before = cart_state()['cart'];
    request('actions/add_cart.php', array_replace($add,['product_id'=>$bad]));
    check(cart_state()['cart'] === $before, 'Invalid add ID leaves cart: ' . $bad);
    request('actions/add_cart.php', array_replace($add,['quantity'=>$bad]));
    check(cart_state()['cart'] === $before, 'Invalid add quantity leaves cart: ' . $bad);
}
foreach ([$cartZero,2147483647] as $bad) {
    $before = cart_state()['cart'];
    request('actions/add_cart.php', array_replace($add,['product_id'=>$bad]));
    check(cart_state()['cart'] === $before && str_contains(request('pages/cart.php')[1],$bad===$cartZero?'đã hết hàng':'không còn tồn tại'), 'Out-of-stock/missing add reports failure');
}
request('actions/add_cart.php', array_replace($add,['product_id'=>$cartB,'quantity'=>3]));
$before = cart_state()['cart'];
foreach (['add_cart.php'=>['product_id'=>$cartA,'quantity'=>1], 'update_cart.php'=>['quantities['.$cartA.']'=>1], 'remove_cart.php'=>['id'=>$cartA]] as $endpoint=>$payload) {
    foreach ([[],['csrf_token'=>'bad'],['csrf_token[]'=>'array']] as $csrf) {
        check(request('actions/'.$endpoint,$payload+$csrf)[0]===303 && cart_state()['cart']===$before, 'Missing/wrong/array CSRF denied: '.$endpoint);
    }
    check(request('actions/'.$endpoint.'?id='.$cartA)[0]===303 && cart_state()['cart']===$before, 'GET action cannot modify cart: '.$endpoint);
}
check(str_contains(request('pages/cart.php')[1], 'Phiên gửi biểu mẫu không hợp lệ'), 'CSRF rejection has clear flash');
$update = ['csrf_token'=>$cartToken,'quantities['.$cartA.']'=>2,'quantities['.$cartB.']'=>4];
request('actions/update_cart.php',$update);
check(cart_state()['cart']==[$cartA=>2,$cartB=>4], 'Valid bulk update');
foreach (['-1','10','1.5','bad'] as $bad) {
    $before = cart_state()['cart'];
    request('actions/update_cart.php',array_replace($update,['quantities['.$cartA.']'=>1,'quantities['.$cartB.']'=>$bad]));
    check(cart_state()['cart']===$before, 'Invalid bulk update is atomic: '.$bad);
}
foreach ([['quantities'=>'wrong'], ['quantities['.$cartZero.']'=>1], ['quantities[abc]'=>1], ['quantities['.$cartA.'][]'=>1]] as $bad) {
    $before = cart_state()['cart'];
    request('actions/update_cart.php',$bad+['csrf_token'=>$cartToken]);
    check(cart_state()['cart']===$before, 'Invalid update shape/membership rejected');
}
$html = request('pages/cart.php')[1];
$dom = customer_test_dom($html);
check($dom->query('//form//form')->length===0 && $dom->query('//input[@form="cart-update"]')->length===2, 'Cart forms do not nest and quantity fields belong to update form');
check($dom->query('//form[contains(@action,"remove_cart.php") and @method="post"]')->length===2 && !str_contains($html,'remove_cart.php?id='), 'Remove controls are POST forms');
check(str_contains($html,'Cart &lt;script&gt;X&lt;/script&gt;') && !str_contains($html,'<script>X</script>'), 'Cart name escaped');
check(trim($dom->query('//nav//span[contains(@class,"badge")]')->item(0)->textContent)==='6', 'Navbar counts total quantity');
check(request('assets/images/products/cart-test.png')[0]===200 && str_contains($html,'../assets/images/products/cart-test.png'), 'Cart image works');
check(str_contains($html,'href="checkout.php"') && str_contains($html,'href="products.php"'), 'Cart checkout and continue links retained');
request('actions/remove_cart.php',['id'=>'bad','csrf_token'=>$cartToken]);
check(cart_state()['cart']==[$cartA=>2,$cartB=>4], 'Invalid removal cannot damage cart');
request('actions/remove_cart.php',['id'=>$cartA,'csrf_token'=>$cartToken]);
check(cart_state()['cart']==[$cartB=>4], 'Remove affects selected item only');
request('actions/update_cart.php',['quantities['.$cartB.']'=>0,'csrf_token'=>$cartToken]);
check(cart_state()['cart']===[] && str_contains(request('pages/cart.php')[1],'Giỏ hàng của bạn đang trống.'), 'Zero quantity retains existing remove convention');
check(request('pages/checkout.php')[0]===303, 'Empty cart checkout redirects');
request('actions/add_cart.php',$add);
$db->query('UPDATE products SET price=250.00,stock=1 WHERE id='.$cartA);
$html = request('pages/cart.php')[1];
check(cart_state()['cart']==[$cartA=>1] && str_contains($html,'250') && str_contains($html,'được điều chỉnh'), 'Cart refresh uses current DB price and clamps reduced stock');
check(!str_contains(request('pages/cart.php')[1],'được điều chỉnh'), 'Stock correction notice appears once');
request('actions/add_cart.php',array_replace($add,['product_id'=>$cartB,'quantity'=>2]));
$db->query('DELETE FROM products WHERE id='.$cartB);
$html = request('pages/cart.php')[1];
check(cart_state()['cart']==[$cartA=>1] && str_contains($html,'được điều chỉnh'), 'Deleted product removed safely with notice');
$db->query('UPDATE products SET stock=0 WHERE id='.$cartA);
request('pages/cart.php');
check(cart_state()['cart']===[], 'Zero-stock product removed during display');
$db->query('UPDATE products SET stock=5 WHERE id='.$cartA);
request('actions/add_cart.php',$add);
$before = cart_state()['cart'];
$db->query('RENAME TABLE products TO cart_products_unavailable');
try {
    [$status,$html]=request('pages/cart.php');
    check($status===503 && str_contains($html,'Không thể tải dữ liệu giỏ hàng') && str_contains($html,'navbar') && !str_contains($html,$testName), 'Cart DB failure gives safe layout 503');
    check(cart_state()['cart']===$before, 'DB read failure does not discard cart');
    request('actions/add_cart.php',$add);
    check(cart_state()['cart']===$before, 'DB mutation failure does not modify session');
} finally { $db->query('RENAME TABLE cart_products_unavailable TO products'); }
check(str_contains(request('pages/cart.php')[1],'Không thể tải dữ liệu giỏ hàng'), 'DB error flash available after recovery');
// Actual login/logout against fixture accounts, without changing live users.
$hash = password_hash('Cart-test-secret', PASSWORD_DEFAULT);
$stmt=$db->prepare('UPDATE users SET password=? WHERE id=2');
$stmt->bind_param('s',$hash); $stmt->execute();
$email=$db->query('SELECT email FROM users WHERE id=2')->fetch_row()[0];
request('actions/login.php',['email'=>$email,'password'=>'Cart-test-secret']);
check(cart_state()['user'] && cart_state()['cart']===$before, 'Actual login preserves guest cart');
request('actions/add_cart.php',array_replace($add,['quantity'=>1]));
check(cart_state()['cart']==[$cartA=>3], 'Logged-in customer can add');
request('cart-state.php?role=admin');
check(request('pages/cart.php')[0]===200, 'Admin can view storefront cart');
$internal=['app/Models/CartProduct.php','app/Services/CartService.php','app/Core/CartCsrf.php','app/Controllers/Storefront/CartController.php','app/Views/storefront/cart/index.php'];
foreach ($internal as $path) check(request($path)[0]===403,'Internal cart file denied: '.$path);
request('actions/logout.php');
$state=cart_state();
check(!$state['user'] && $state['cart']==[$cartA=>3] && $state['admin_token']===null && $state['cart_token']===null && !$state['old'], 'Logout retains only cart and removes auth/tokens');
check(request('admin/index.php')[0]===302, 'Logged-out cart owner cannot access Admin');
$newToken=cart_token($cartA);
check($newToken!==$cartToken, 'Logout renews cart CSRF token');
request('actions/remove_cart.php',['id'=>$cartA,'csrf_token'=>$cartToken]);
check(cart_state()['cart']==[$cartA=>3], 'Old pre-logout token rejected');
$checkout=request('pages/checkout.php')[1];
check(str_contains($checkout,'Số lượng: 3') && str_contains($checkout,'750'), 'Legacy checkout reads unchanged cart and DB prices');
$orderCount=(int)$db->query('SELECT COUNT(*) FROM orders')->fetch_row()[0];
$checkoutDom=customer_test_dom($checkout);
$checkoutData=['fullname'=>'Isolated cart tester','phone'=>'0901234567','address'=>'Test address','note'=>'Isolated only',
    'csrf_token'=>$checkoutDom->query('//input[@name="csrf_token"]')->item(0)->getAttribute('value'),
    'submit_token'=>$checkoutDom->query('//input[@name="submit_token"]')->item(0)->getAttribute('value')];
$db->query('UPDATE products SET stock=2 WHERE id='.$cartA);
request('actions/checkout.php',$checkoutData);
check((int)$db->query('SELECT COUNT(*) FROM orders')->fetch_row()[0]===$orderCount && (int)$db->query('SELECT stock FROM products WHERE id='.$cartA)->fetch_row()[0]===2, 'Legacy checkout refuses stale stock without creating order');
request('pages/cart.php');
check(cart_state()['cart']==[$cartA=>2], 'Display reconciles stale checkout cart');
request('actions/checkout.php',$checkoutData);
$placed=$db->query('SELECT id,total FROM orders ORDER BY id DESC LIMIT 1')->fetch_assoc();
check((int)$db->query('SELECT COUNT(*) FROM orders')->fetch_row()[0]===$orderCount+1 && $placed['total']==='500.00', 'Isolated legacy checkout uses database total');
check(!cart_state()['has_cart'] && (int)$db->query('SELECT stock FROM products WHERE id='.$cartA)->fetch_row()[0]===0, 'Successful isolated checkout clears cart and deducts stock');
check($db->query('SELECT price FROM order_details WHERE order_id='.(int)$placed['id'])->fetch_row()[0]==='250.00','Checkout preserves historical price');
foreach (['pages/cart.php','actions/add_cart.php','actions/update_cart.php','actions/remove_cart.php'] as $entry) {
    $source=file_get_contents($app.'/'.$entry);
    check(strlen($source)<250 && str_contains($source,'CartController'),'Thin cart entry: '.$entry);
}
check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/',file_get_contents($app.'/app/Controllers/Storefront/CartController.php')),'Cart controller has no SQL');
check(!preg_match('/\$_(GET|POST|SESSION)/',file_get_contents($app.'/app/Models/CartProduct.php')),'Cart model has no request/session');
check(!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/',file_get_contents($app.'/app/Services/CartService.php')),'Cart service has no SQL');
check(!preg_match('/\b(SELECT|mysqli)\b|\$_(GET|POST)/',file_get_contents($app.'/app/Views/storefront/cart/index.php')),'Cart view has no SQL/request');
$cookie=$cartOriginalCookie;
echo 'Cart MVC checks completed: '.($checks-$cartStart).PHP_EOL;