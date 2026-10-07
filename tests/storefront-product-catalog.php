<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!isset($app,$db)) {
    if (!in_array('--isolated',$argv,true)) exit("Run: php tests/storefront-product-catalog.php --isolated\n");
    require __DIR__.'/product-management.php'; exit;
}
$catalogStart=$checks;
check($db->query('SELECT DATABASE()')->fetch_row()[0]===$testName && str_starts_with($testName,'motoparts_test_'), 'Catalog fixture isolated');
$savedCookie=$cookie; $cookie=$temp.'/catalog17-cookie.txt';
$db->query("INSERT INTO categories(name) VALUES ('Catalog17 A'),('Catalog17 B')");
$catA=(int)$db->insert_id; $catB=$catA+1;
$insert=$db->prepare('INSERT INTO products(category_id,name,brand,price,stock,created_at) VALUES (?,?,?,?,?,?)');
$fixture=[];
for($n=1;$n<=29;$n++) {
    $category=$n%2?$catA:$catB; $name='Catalog17 Part '.sprintf('%02d',$n); $brand=$n%2?'SearchBrandOdd':'SearchBrandEven';
    $price=(string)($n*100).'.50'; $stock=$n%3?5:0; $date='2026-01-0'.(($n%3)+1).' 12:00:00';
    if($n===29) {$name='Catalog17 Part 28';$price='2800.50';}
    $insert->bind_param('isssis',$category,$name,$brand,$price,$stock,$date); $insert->execute();
    $fixture[]=['id'=>(int)$db->insert_id,'category'=>$category,'name'=>$name,'price'=>(int)str_replace('.','',$price),'stock'=>$stock,'created'=>$date];
}
$name='Literal %_!\\ <script>catalog17</script>'; $brand='Special & Brand'; $price='0.00'; $stock=0;
$insert->bind_param('isssis',$catA,$name,$brand,$price,$stock,$date); $insert->execute(); $special=(int)$db->insert_id; $insert->close();
$fetchCatalog=static function(array $query=[]):array {
    [$status,$html]=request('pages/products.php'.($query?'?'.http_build_query($query):''));
    $dom=customer_test_dom($html); $ids=[];
    foreach($dom->query('//a[starts-with(@href,"product-detail.php?id=")]') as $link) $ids[]=(int)substr($link->getAttribute('href'),strlen('product-detail.php?id='));
    $summary=$dom->query('//*[@id="catalog-results"]')->item(0);
    return ['status'=>$status,'html'=>$html,'dom'=>$dom,'ids'=>$ids,'total'=>$summary?(int)$summary->getAttribute('data-total'):null,'page'=>$summary?(int)$summary->getAttribute('data-page'):null,'pages'=>$summary?(int)$summary->getAttribute('data-pages'):null];
};
$base=['q'=>'Catalog17 Part'];
$r=$fetchCatalog($base);
check($r['status']===200 && $r['total']===29 && $r['pages']===3 && count($r['ids'])===12,'Catalog 29 results / 3 pages / 12 rows');
$all=[];
foreach([1,2,3] as $p) {$r=$fetchCatalog($base+['page'=>$p]); array_push($all,...$r['ids']);check(count($r['ids'])===($p===3?5:12),'Catalog page size '.$p);}
check(count(array_unique($all))===29,'Catalog pagination no duplicates/missing rows');
foreach(['newest','price_asc','price_desc','name_asc'] as $sort) {
    $expected=$fixture;
    usort($expected,static function($a,$b)use($sort){$order=match($sort){'newest'=>strcmp($b['created'],$a['created']),'price_asc'=>$a['price']<=>$b['price'],'price_desc'=>$b['price']<=>$a['price'],'name_asc'=>strcmp($a['name'],$b['name'])}; return $order?:($b['id']<=>$a['id']);});
    $r=$fetchCatalog($base+['sort'=>$sort]);
    check($r['ids']===array_column(array_slice($expected,0,12),'id'),'Catalog sort stable '.$sort);
}
foreach(['Catalog17 Part 01','  catalog17 part 01  ','SearchBrandOdd','%','_','!','\\'] as $q) {
    $r=$fetchCatalog(['q'=>$q]); $expected=$q==='SearchBrandOdd'?15:1;
    check($r['total']===$expected && ($expected!==1 || count($r['ids'])===1),'Catalog search literal/case/trim '.$q);
}
check($fetchCatalog(['q'=>'Special & Brand'])['ids']===[$special],'Catalog brand search');
check($fetchCatalog(['q'=>'Catalog17 Part 0'])['total']===9,'Catalog partial name');
$r=$fetchCatalog(['q'=>'<script>catalog17</script>']);
check($r['ids']===[$special] && str_contains($r['html'],'&lt;script&gt;catalog17&lt;/script&gt;') && !str_contains($r['html'],'<script>catalog17</script>'),'Catalog escapes q and product HTML');
check($fetchCatalog(['q'=>'NoSuchCatalog17'])['total']===0,'Catalog empty search');
check($fetchCatalog(['q'=>"' OR 1=1 --"])['total']===0,'Catalog search injection remains literal');
check($fetchCatalog($base+['category'=>(string)$catA])['total']===15,'Catalog valid category');
foreach(['2147483647','-1','0','abc','1 OR 1=1',['1']] as $bad) {
    $r=$fetchCatalog($base+['category'=>$bad]);
    check($r['status']===200 && $r['total']===null && !$r['ids'] && $r['dom']->query('//*[@role="alert"]')->length===1,'Catalog invalid/missing category '.json_encode($bad));
}
foreach([
    ['min_price'=>'1000'],['max_price'=>'1000'],['min_price'=>'1000','max_price'=>'2000'],['min_price'=>'0','max_price'=>'9999999999'],
] as $bounds) {
    $expected=array_filter($fixture,static fn($p)=>(!isset($bounds['min_price'])||$p['price']>=(int)$bounds['min_price']*100)&&(!isset($bounds['max_price'])||$p['price']<=(int)$bounds['max_price']*100));
    check($fetchCatalog($base+$bounds)['total']===count($expected),'Catalog actual decimal price bounds '.json_encode($bounds));
}
foreach(['min_price','max_price'] as $field) foreach(['-1','abc','1e3','1.5','10000000000','999999999999999999999999',['1']] as $bad) {
    $r=$fetchCatalog($base+[$field=>$bad]);
    check($r['status']===200 && $r['total']===null && !$r['ids'] && str_contains($r['html'],'role="alert"'),'Catalog rejects '.$field.' '.json_encode($bad));
}
$r=$fetchCatalog($base+['min_price'=>'2000','max_price'=>'1000']);
check(!$r['ids'] && str_contains($r['html'],'Giá tối thiểu không được lớn hơn giá tối đa.') && str_contains($r['html'],'value="2000"') && str_contains($r['html'],'value="1000"'),'Catalog reverse bounds retain inputs');
foreach(['in_stock'=>20,'out_of_stock'=>9,'bad'=>29] as $stock=>$total) check($fetchCatalog($base+['stock'=>$stock])['total']===$total,'Catalog stock '.$stock);
foreach(['price; DROP TABLE products','bad',str_repeat('x',100),['price_asc']] as $sort) check($fetchCatalog($base+['sort'=>$sort])['ids']===$fetchCatalog($base)['ids'],'Catalog sort allowlist '.json_encode($sort));
foreach(['0','-2','abc','1.5',['2']] as $p) check($fetchCatalog($base+['page'=>$p])['page']===1,'Catalog invalid page '.json_encode($p));
check($fetchCatalog($base+['page'=>'999999999'])['page']===3,'Catalog clamp to last page');
check($fetchCatalog($base+['page'=>str_repeat('9',50)])['page']===1,'Catalog page integer overflow defaults safely');
check($fetchCatalog(['q'=>'NoSuchCatalog17','page'=>'999999999'])['page']===1,'Catalog empty page stays one');
$combo=$base+['category'=>(string)$catA,'min_price'=>'100','max_price'=>'2901','stock'=>'in_stock','sort'=>'price_desc'];
$r=$fetchCatalog($combo); $expected=array_filter($fixture,static fn($p)=>$p['category']===$catA&&$p['stock']>0);
usort($expected,static fn($a,$b)=>($b['price']<=>$a['price'])?:($b['id']<=>$a['id']));
check($r['total']===count($expected) && $r['ids']===array_column($expected,'id'),'Catalog combined predicates COUNT/list match');
$query=$base+['category'=>(string)$catA,'min_price'=>'0','max_price'=>'9999999999','stock'=>'all','sort'=>'price_asc','page'=>'2'];
$r=$fetchCatalog($query); $links=$r['dom']->query('//nav[@aria-label="Phân trang sản phẩm"]//a');
foreach($links as $link) {
    parse_str((string)parse_url($link->getAttribute('href'),PHP_URL_QUERY),$kept);
    check($kept['q']===$base['q'] && $kept['category']===(string)$catA && $kept['min_price']==='0' && $kept['max_price']==='9999999999' && $kept['sort']==='price_asc' && ($kept['stock']??'all')==='all','Catalog pagination preserves filters');
}
check($r['dom']->query('//nav[@aria-label="Phân trang sản phẩm"]//*[@aria-current="page"]')->length===1,'Catalog current page accessibility');
$stockPage=$fetchCatalog($base+['stock'=>'in_stock','page'=>'2']);
check(str_contains($stockPage['html'],'stock=in_stock&amp;page=1'),'Catalog pagination keeps non-default stock');
check($r['dom']->query('//form[@id="catalog-filters" and @method="get" and @action="products.php"]')->length===1 && $r['dom']->query('//form[@id="catalog-filters"]//input[@name="page"]')->length===0,'Catalog GET resets page');
check($r['dom']->query('//form[@id="catalog-filters"]//a[@href="products.php"]')->length===1,'Catalog clear bare URL');
check($fetchCatalog($base+['limit'=>'1000'])['ids']===$fetchCatalog($base)['ids'],'Catalog fixed limit ignores request');
foreach([str_repeat('a',101),['bad']] as $q) check($fetchCatalog(['q'=>$q])['total']===null,'Catalog invalid/long q');
check($fetchCatalog(['q'=>'   '])['total']===$fetchCatalog()['total'],'Catalog empty trimmed q');
check(!preg_match('/mysqli_|\bSELECT .+ FROM|\$_GET|\$_POST/',file_get_contents($root.'/scr/frontend/Views/storefront/products/index.php')),'Catalog View presentation only');
$model=new \MotoParts\App\Models\StorefrontProduct($db);
$filters=['q'=>'Catalog17 Part','category'=>'','min_price'=>'','max_price'=>'','stock'=>'all','sort'=>'newest'];
$preparedBefore=(int)$db->query("SHOW SESSION STATUS LIKE 'Com_stmt_prepare'")->fetch_row()[1];
check($model->count($filters)===29 && count($model->page($filters,24))===5,'Catalog shared conditions Model count/page');
check((int)$db->query("SHOW SESSION STATUS LIKE 'Com_stmt_prepare'")->fetch_row()[1] >= $preparedBefore+2,'Catalog executes prepared count and list statements');
// Known broken table verifies safe 503 even when filtering is requested.
$db->query('RENAME TABLE products TO catalog17_unavailable');
try {$r=$fetchCatalog($base);check($r['status']===503 && !str_contains($r['html'],$testName) && !str_contains($r['html'],'SELECT'),'Catalog safe query failure');}
finally {$db->query('RENAME TABLE catalog17_unavailable TO products');}
$cookie=$savedCookie;
if (in_array('--browser',$argv,true)) {
    $environment=getenv();
    $environment['MOTOPARTS_UI_BASE_URL']='http://127.0.0.1:'.$port.'/'.$project.'/scr/';
    $environment['MOTOPARTS_CATALOG_QUERY']='Catalog17 Part';
    $browser=proc_open(['node',__DIR__.'/storefront-ui-browser.mjs'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$browserPipes,$root,$environment);
    check(is_resource($browser),'Catalog isolated browser started');
    fclose($browserPipes[0]);
    check(proc_close($browser)===0,'Catalog isolated browser passed');
}
echo 'Storefront catalog checks completed: '.($checks-$catalogStart).PHP_EOL;
