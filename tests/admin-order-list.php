<?php
if (PHP_SAPI !== 'cli' || !isset($db, $app)) exit("Run isolated harness.\n");
$listChecksStart = $checks;
function list_fixture(string $name, string $phone, string $status, string $date): int {
    global $db;
    $stmt = $db->prepare("INSERT INTO orders(fullname,phone,address,total,status,created_at) VALUES (?,?,'Test only',10,?,?)");
    $stmt->bind_param('ssss',$name,$phone,$status,$date);
    $stmt->execute();
    return $db->insert_id;
}
function list_result(array $query): array {
    [$status,$html,$headers] = request('admin/orders.php?' . http_build_query($query));
    $ids = [];
    foreach (customer_test_dom($html)->query('//tbody/tr/td[1]/a') as $link) {
        parse_str(parse_url($link->getAttribute('href'),PHP_URL_QUERY),$params);
        $ids[] = (int)$params['id'];
    }
    preg_match('/([0-9]+) đơn hàng — Trang ([0-9]+)\/([0-9]+)/u',$html,$match);
    return [$status,$html,$ids,$match ? (int)$match[1] : null,$match ? (int)$match[2] : null,$match ? (int)$match[3] : null];
}
$batch = [];
for ($i=0;$i<23;$i++) $batch[] = list_fixture('Filter batch','090-765-4321','pending','2026-05-10 12:00:00');
$states = [];
foreach (['pending','confirmed','shipping','completed','cancelled'] as $state) {
    $states[$state] = list_fixture('State fixture','080-123',$state,'2026-05-11 10:00:00');
}
$boundary = [];
foreach (['2026-05-09 23:59:59','2026-05-10 00:00:00','2026-05-10 23:59:59','2026-05-11 00:00:00'] as $date) {
    $boundary[] = list_fixture('Date boundary','080-999','pending',$date);
}
$numericPhone = list_fixture('Numeric phone','0901234567','pending','2026-05-10 12:00:00');
$snapshot = $db->query('SELECT id,status,total FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$stockBefore = order_test_stock();
foreach ([(string)$batch[0], '#'.$batch[0], '000'.$batch[0]] as $q) {
    $r=list_result(['q'=>$q]);
    check($r[0]===200 && $r[2]===[$batch[0]] && $r[3]===1, 'Exact order ID search: '.$q);
}
check(list_result(['q'=>'0901234567'])[3]===0, 'Numeric keyword is an order ID, not a phone fallback');
check(list_result(['q'=>'#9999999999999999999999'])[3]===0, 'Oversized numeric ID produces no matches');
check(list_result(['q'=>'Filter batch'])[3]===23, 'Recipient name search');
check(list_result(['q'=>'-765-'])[3]===23, 'Recipient phone string search');
foreach ($states as $state=>$id) {
    check(list_result(['q'=>'State fixture','status'=>$state])[2]===[$id], 'Status filter: '.$state);
}
check(list_result(['q'=>'Date boundary','from'=>'2026-05-10','to'=>'2026-05-10'])[2]===[$boundary[2],$boundary[1]], 'Inclusive start and complete end day');
check(list_result(['q'=>'Date boundary','from'=>'2026-05-10'])[3]===3, 'From-only date filter');
check(list_result(['q'=>'Date boundary','to'=>'2026-05-10'])[3]===3, 'To-only date filter');
$context=['q'=>'Filter batch','status'=>'pending','from'=>'2026-05-10','to'=>'2026-05-11'];
$seen=[];
foreach ([1,2,3] as $page) {
    $r=list_result($context+['page'=>(string)$page]);
    check($r[0]===200 && $r[3]===23 && $r[5]===3 && count($r[2])===($page===3?3:10), 'Combined COUNT and pagination: '.$page);
    $seen=array_merge($seen,$r[2]);
    foreach (customer_test_dom($r[1])->query('//nav[@aria-label="Phân trang"]//a') as $link) {
        parse_str(parse_url($link->getAttribute('href'),PHP_URL_QUERY),$query);
        check(array_intersect_key($query,$context)===$context, 'Pagination preserves every filter');
    }
}
check($seen===array_reverse($batch), 'Stable id DESC without duplicate or missing rows');
check(list_result($context+['page'=>'999'])[4]===3, 'Out-of-range page clamps to last');
foreach (['abc','-1','0','1.5','999999999999999999999', ['2']] as $page) {
    check(list_result($context+['page'=>$page])[4]===1, 'Invalid page defaults to first');
}
foreach ([
    ['from'=>'2026-02-30'], ['to'=>'2026-13-01'], ['from'=>'2026-5-01'],
    ['from'=>'2026-05-11','to'=>'2026-05-10'], ['status'=>'unknown'],
    ['q'=>str_repeat('á',101)], ['status'=>['pending']], ['from'=>['2026-05-10']], ['q'=>['x']],
    ['to'=>'2026-05-10 OR 1=1']
] as $invalid) {
    $r=list_result($invalid);
    check($r[0]===400 && str_contains($r[1],'alert-danger') && $r[2]===[], 'Invalid filters return explicit error without querying results');
}
check(list_result(['q'=>str_repeat('á',100)])[0]===200, '100-character search allowed');
check(list_result(['from'=>'2024-02-29','to'=>'2024-02-29'])[0]===200, 'Leap day accepted');
check(list_result(['from'=>'2025-02-29'])[0]===400, 'Invalid leap day rejected');
check(list_result(['q'=>"%' OR 1=1 --"])[3]===0, 'SQL-like input remains literal');
$r=list_result(['q'=>'<script>alert(1)</script>']);
check(!str_contains($r[1],'<script>alert(1)</script>') && str_contains($r[1],'&lt;script&gt;'), 'Search value escaped');
$r=list_result(['q'=>'No such fixture']);
check($r[3]===0 && str_contains($r[1],'Không tìm thấy đơn hàng phù hợp.'), 'Empty result clearly shown');
check($snapshot===$db->query('SELECT id,status,total FROM orders ORDER BY id')->fetch_all(MYSQLI_ASSOC) && $stockBefore===order_test_stock(), 'Filtering GETs do not mutate orders or stock');
$anonymousCookie=$cookie;
$cookie=$temp.'/anonymous-list-filters.txt';
check(request('admin/orders.php?status=pending')[0]===302, 'Anonymous list denied');
$cookie=$anonymousCookie;
request('test-session.php?id=2');
check(request('admin/orders.php?status=pending')[0]===403, 'Customer list denied');
check(request('admin/includes/order-filters.php')[0]===403, 'Customer filter helper denied');
request('test-session.php?id=1');
$r=list_result($context+['page'=>'3']);
check(substr_count($r[1],'class="nav-link active"')===1 && str_contains($r[1],'table-responsive'), 'Sidebar and responsive table preserved');
$forms=customer_test_dom($r[1])->query('//form[@action="update-order.php"]');
$form=$forms->item(0);
$post=[];
foreach(customer_test_dom($r[1])->query('//form[@action="update-order.php"][1]//input') as $input) {
    $post[$input->getAttribute('name')]=$input->getAttribute('value');
}
// First form context; all forms on this page share the same validated filters.
check($forms->length===3 && $post['return_to']==='list' && $post['list_context[page]']==='3', 'Update forms carry current page and filters');
foreach($context as $key=>$value) check($post['list_context['.$key.']']===$value, 'Form retains '.$key);
$post['status']='confirmed';
[$status,,$headers]=request('admin/update-order.php',array_replace($post,['csrf_token'=>'bad']));
preg_match('/Location: ([^\r\n]+)/',$headers,$location);
parse_str(parse_url($location[1],PHP_URL_QUERY),$returned);
check($status===303 && $returned===$context+['page'=>'3'] && order_test_status((int)$post['order_id'])==='pending', 'CSRF failure preserves context without mutation');
$r=list_result($returned);
check(str_contains($r[1],'Yêu cầu không hợp lệ'), 'CSRF error displayed on filtered list');
[$status,,$headers]=request('admin/update-order.php',$post);
preg_match('/Location: ([^\r\n]+)/',$headers,$location);
parse_str(parse_url($location[1],PHP_URL_QUERY),$returned);
check($status===303 && $returned===$context+['page'=>'3'] && order_test_status((int)$post['order_id'])==='confirmed', 'Successful update restores exact filter context');
$r=list_result($returned);
check($r[3]===22 && $r[4]===3 && str_contains($r[1],'Đã cập nhật đơn hàng'), 'Updated order leaves old status filter and success remains visible');
check($stockBefore===order_test_stock(), 'Confirmation does not affect stock');
$shrunk=[];
for($i=0;$i<11;$i++) $shrunk[]=list_fixture('Shrink batch','070-0','pending','2026-05-10 12:00:00');
request('admin/update-order.php',[
    'csrf_token'=>$token,'order_id'=>(string)$shrunk[0],'status'=>'confirmed','return_to'=>'list',
    'list_context[q]'=>'Shrink batch','list_context[status]'=>'pending','list_context[page]'=>'2'
]);
$r=list_result(['q'=>'Shrink batch','status'=>'pending','page'=>'2']);
check($r[3]===10 && $r[4]===1 && count($r[2])===10, 'Page shrinks safely after result set loses last row');
foreach ([
    ['list_context'=>'https://example.com'],
    ['list_context[status]'=>'invalid'],
    ['list_context[from]'=>'bad'],
] as $badContext) {
    [$status,,$headers]=request('admin/update-order.php',array_merge([
        'csrf_token'=>'bad','order_id'=>(string)$batch[0],'status'=>'confirmed','return_to'=>'list'
    ],$badContext));
    check($status===303 && preg_match('~Location: [^\r\n]+/scr/admin/orders\.php\r?\n~',$headers), 'Invalid return context falls back to controlled list');
}
[$status,,$headers]=request('admin/update-order.php',[
    'csrf_token'=>'bad','order_id'=>(string)$batch[0],'status'=>'confirmed','return_to'=>'list',
    'list_context[q]'=>"https://example.com/\r\nX-Test:evil", 'list_context[url]'=>'https://example.com'
]);
check($status===303 && preg_match('~Location: [^\r\n]+/scr/admin/orders\.php\?~',$headers) && !str_contains($headers,"\r\nX-Test:"), 'Return keyword encoded; unknown URL field ignored');
echo 'Order list checks completed: '.($checks-$listChecksStart).PHP_EOL;
