<?php
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
if (!isset($app,$db)) {
    if (!in_array('--isolated',$argv,true)) exit("Run: php tests/storefront-product-reviews.php --isolated [--reviews-browser]\n");
    require __DIR__.'/product-management.php'; exit;
}
$reviewStart=$checks;
check($db->query('SELECT DATABASE()')->fetch_row()[0]===$testName && str_starts_with($testName,'motoparts_test_'),'Reviews fixture isolated');
$db->query(file_get_contents($root.'/docs/migrations/phase-19-product-reviews.sql'));
check($db->query('SELECT COUNT(*) FROM product_reviews')->fetch_row()[0]==0,'Reviews migration empty');
$db->query("INSERT INTO users(fullname,email,password,role) VALUES ('Reviewer <b>A</b>','review-a@test.invalid','unused','customer'),('Reviewer B','review-b@test.invalid','unused','customer')");
$reviewA=(int)$db->insert_id;$reviewB=$reviewA+1;
$db->query("INSERT INTO categories(name) VALUES ('Review test')");
$reviewCategory=(int)$db->insert_id;
$db->query("INSERT INTO products(category_id,name,price,stock) VALUES ($reviewCategory,'Review product',100,5),($reviewCategory,'Not purchased',200,0)");
$reviewProduct=(int)$db->insert_id;$reviewOther=$reviewProduct+1;
$db->query("INSERT INTO orders(user_id,fullname,phone,address,total,status) VALUES ($reviewA,'Receiver','0900000000','Fixture',100,'pending'),(NULL,'Receiver','0900000000','Fixture',100,'completed')");
$reviewOrder=(int)$db->insert_id;$guestOrder=$reviewOrder+1;
$db->query("INSERT INTO order_details(order_id,product_id,quantity,price) VALUES ($reviewOrder,$reviewProduct,1,100),($guestOrder,$reviewProduct,1,100)");
$reviewCookie=$cookie;$cookie=$temp.'/review-cookie.txt';
$reviewPath='pages/product-detail.php?id='.$reviewProduct;
$reviewHtml=static fn()=>request($reviewPath)[1];
$getToken=static function()use($reviewHtml){return customer_test_dom($reviewHtml())->query('//input[@name="csrf_token"]')->item(0)->getAttribute('value');};
$postReview=static fn(array $data)=>request('actions/product_review.php',http_build_query($data));
$post=['product_id'=>(string)$reviewProduct,'operation'=>'save','rating'=>'5','comment'=>'Good'];
check(request('actions/product_review.php')[0]===405,'Reviews POST only');
check($postReview($post)[0]===302,'Reviews guest login required');
check(!str_contains($reviewHtml(),'id="review-form"') && str_contains($reviewHtml(),'Đăng nhập để đánh giá'),'Reviews guest read only');
request('test-session.php?id=1');
check($postReview($post)[0]===403 && !str_contains($reviewHtml(),'id="review-form"'),'Reviews admin denied');
request('test-session.php?id='.$reviewB);
$post['csrf_token']=$getToken();
check($postReview($post)[0]===403 && !str_contains($reviewHtml(),'id="review-form"'),'Reviews no purchase and guest order not attributed');
request('test-session.php?id='.$reviewA);
$post['csrf_token']=$getToken();
foreach(['pending','confirmed','shipping','cancelled'] as $status) {
    $db->query("UPDATE orders SET status='$status' WHERE id=$reviewOrder");
    check($postReview($post)[0]===403 && !str_contains($reviewHtml(),'id="review-form"'),'Reviews rejects '.$status);
}
$db->query("UPDATE orders SET status='completed' WHERE id=$reviewOrder");
check(str_contains($reviewHtml(),'id="review-form"'),'Reviews completed buyer form');
$businessBefore=$db->query('SELECT id,price,stock FROM products ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$orderBefore=$db->query('SELECT id,total,status FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC);
foreach(['bad','',['x']] as $bad) check($postReview(array_replace($post,['csrf_token'=>$bad]))[0]===403,'Reviews invalid CSRF');
foreach(['0','6','-1','1.5','1e0','1 OR 1=1',['5']] as $bad) check($postReview(array_replace($post,['rating'=>$bad]))[0]===400,'Reviews invalid rating '.json_encode($bad));
foreach([str_repeat('á',1001),['x'],"\xFF"] as $bad) check($postReview(array_replace($post,['comment'=>$bad]))[0]===400,'Reviews invalid comment');
foreach(['0','-1','1 OR 1=1',['1']] as $bad) check($postReview(array_replace($post,['product_id'=>$bad]))[0]===400,'Reviews invalid product ID');
check($postReview(array_replace($post,['product_id'=>'2147483647']))[0]===404,'Reviews missing product');
check($postReview(array_replace($post,['product_id'=>(string)$reviewOther]))[0]===403,'Reviews wrong product purchase');
check($postReview(array_replace($post,['operation'=>'unsafe']))[0]===400,'Reviews operation allowlist');
for($rating=1;$rating<=5;$rating++) {
    [$status,,$headers]=$postReview(array_replace($post,['rating'=>(string)$rating,'return'=>'https://evil.invalid','user_id'=>$reviewB]));
    $row=$db->query('SELECT user_id,rating FROM product_reviews')->fetch_assoc();
    check($status===303 && (int)$row['rating']===$rating && (int)$row['user_id']===$reviewA && !str_contains($headers,'evil.invalid'),'Reviews save rating '.$rating);
}
$beforeRepeat=$db->query('SELECT * FROM product_reviews')->fetch_all(MYSQLI_ASSOC);
$postReview($post);$postReview($post);
check($beforeRepeat===$db->query('SELECT * FROM product_reviews')->fetch_all(MYSQLI_ASSOC),'Reviews repeated save unchanged including timestamps');
$xss='<script>alert(19)</script>';
$postReview(array_replace($post,['comment'=>$xss]));
$html=$reviewHtml();
check(!str_contains($html,$xss) && str_contains($html,'&lt;script&gt;alert(19)&lt;/script&gt;') && str_contains($html,'Reviewer &lt;b&gt;A&lt;/b&gt;'),'Reviews XSS escaped name and comment');
check($postReview(array_replace($post,['comment'=>str_repeat('á',1000)]))[0]===303,'Reviews Unicode 1000 accepted');
$postReview(array_replace($post,['comment'=>"' OR 1=1 --"]));
check($db->query('SELECT comment FROM product_reviews')->fetch_row()[0]==="' OR 1=1 --",'Reviews SQL input literal');
check($businessBefore===$db->query('SELECT id,price,stock FROM products ORDER BY id')->fetch_all(MYSQLI_ASSOC)
    && $orderBefore===$db->query('SELECT id,total,status FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC),'Reviews preserve stock prices totals statuses');
// A second verified buyer; writes are always scoped by authenticated user, not review_id.
$db->query("INSERT INTO orders(user_id,fullname,phone,address,total,status) VALUES ($reviewB,'B','0','Fixture',100,'completed')");
$orderB=(int)$db->insert_id;
$db->query("INSERT INTO order_details(order_id,product_id,quantity,price) VALUES ($orderB,$reviewProduct,1,100)");
request('test-session.php?id='.$reviewB);$tokenB=$getToken();
$postReview(array_replace($post,['csrf_token'=>$tokenB,'rating'=>'1','comment'=>'B own']));
$summary=customer_test_dom($reviewHtml())->query('//*[contains(@class,"review-summary")]')->item(0);
check((int)$summary->getAttribute('data-total')===2 && (float)$summary->getAttribute('data-average')===3.0,'Reviews average and total correct');
request('test-session.php?id='.$reviewA);
$postReview(array_replace($post,['rating'=>'4','review_id'=>'2','user_id'=>$reviewB]));
check($db->query("SELECT comment FROM product_reviews WHERE user_id=$reviewB")->fetch_row()[0]==='B own','Reviews cannot edit another user');
foreach([1,2] as $attempt) check($postReview(array_replace($post,['operation'=>'delete','user_id'=>$reviewB]))[0]===303,'Reviews repeat delete '.$attempt);
check((int)$db->query('SELECT COUNT(*) FROM product_reviews')->fetch_row()[0]===1 && $db->query('SELECT comment FROM product_reviews')->fetch_row()[0]==='B own','Reviews cannot delete another user');
check(!str_contains($reviewHtml(),'id="review-delete"'),'Reviews own delete form removed');
// Create enough legitimate buyers to test bounded pagination.
$model=new \MotoParts\App\Models\ProductReview($db);
for($n=0;$n<11;$n++){
    $db->query("INSERT INTO users(fullname,email,password,role) VALUES ('Page buyer $n','page-review-$n@test.invalid','unused','customer')");$buyer=(int)$db->insert_id;
    $db->query("INSERT INTO orders(user_id,fullname,phone,address,total,status) VALUES ($buyer,'Page','0','Fixture',100,'completed')");$order=(int)$db->insert_id;
    $db->query("INSERT INTO order_details(order_id,product_id,quantity,price) VALUES ($order,$reviewProduct,1,100)");
    $model->save($buyer,$reviewProduct,3,'Page '.$n);
}
foreach(['1'=>10,'2'=>2,'999999'=>2,'-1'=>10,'abc'=>10] as $page=>$count) {
    $dom=customer_test_dom(request($reviewPath.'&review_page='.$page)[1]);
    check($dom->query('//article[contains(@class,"review-item")]')->length===$count,'Reviews bounded page '.$page);
}
$page1=$model->page($reviewProduct,0);$page2=$model->page($reviewProduct,10);
check(count(array_unique(array_merge(array_column($page1,'id'),array_column($page2,'id'))))===12,'Reviews stable pagination no duplicates');
$model->save($reviewA,$reviewOther,5,'Not a purchase');
check($model->own($reviewA,$reviewOther)===null,'Reviews write statement independently verifies purchase');
try {$db->query("INSERT INTO product_reviews(user_id,product_id,rating) VALUES ($reviewB,$reviewProduct,3)");$unique=false;} catch(mysqli_sql_exception $e){$unique=$e->getCode()===1062;}
check($unique,'Reviews database unique enforced');
try {$db->query("INSERT INTO product_reviews(user_id,product_id,rating) VALUES ($reviewA,$reviewProduct,0)");$ratingCheck=false;} catch(mysqli_sql_exception $e){$ratingCheck=in_array($e->getCode(),[4025,3819],true);}
check($ratingCheck,'Reviews database rating check enforced');
try {$db->query("INSERT INTO product_reviews(user_id,product_id,rating) VALUES ($reviewA,2147483647,5)");$fk=false;} catch(mysqli_sql_exception $e){$fk=$e->getCode()===1452;}
check($fk,'Reviews database foreign key enforced');
$db->query('RENAME TABLE product_reviews TO reviews_unavailable');
try {[$status,$html]=request($reviewPath);check($status===200 && str_contains($html,'Đánh giá chưa sẵn sàng') && str_contains($html,'add_cart.php') && !str_contains($html,$testName),'Reviews missing migration preserves product/cart');}
finally {$db->query('RENAME TABLE reviews_unavailable TO product_reviews');}
foreach(['backend/Models/ProductReview.php','backend/Controllers/Storefront/ReviewController.php','frontend/Views/storefront/products/reviews.php'] as $path) check(request($path)[0]===403,'Reviews internal guard '.$path);
$cookie=$reviewCookie;
if(in_array('--reviews-browser',$argv,true)){
    file_put_contents($app.'/review-session.php','<?php session_start(); $_SESSION=["user"=>["id"=>'.$reviewA.']]; header("Location: pages/product-detail.php?id='.$reviewProduct.'");');
    file_put_contents($app.'/favorite-session.php','<?php session_start(); $_SESSION=["user"=>["id"=>'.$favoriteA.']]; header("Location: pages/favorites.php");');
    $environment=getenv();
    $environment['MOTOPARTS_UI_BASE_URL']='http://127.0.0.1:'.$port.'/'.$project.'/scr/';
    $environment['MOTOPARTS_CATALOG_QUERY']='Catalog17 Part';$environment['MOTOPARTS_NAVBAR_FIXTURE']='1';
    $environment['MOTOPARTS_FAVORITES_FIXTURE']='1';$environment['MOTOPARTS_REVIEWS_FIXTURE']='1';
    $process=proc_open(['node',__DIR__.'/storefront-ui-browser.mjs'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,$root,$environment);
    check(is_resource($process),'Reviews browser started');fclose($pipes[0]);check(proc_close($process)===0,'Reviews browser passed');
}
echo 'Product reviews checks completed: '.($checks-$reviewStart).PHP_EOL;
