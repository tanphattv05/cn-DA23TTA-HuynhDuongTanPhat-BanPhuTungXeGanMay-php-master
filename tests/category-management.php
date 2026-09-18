<?php
// Included by the existing isolated HTTP harness; never run against live tables.
if (PHP_SAPI !== 'cli' || !isset($db, $app, $token)) {
    exit("Run: php tests/product-management.php --isolated\n");
}
$categoryChecksStart = $checks;
$productsBefore = $db->query('SELECT * FROM products ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$historyBefore = $db->query('SELECT * FROM order_details')->fetch_all(MYSQLI_ASSOC);
$categorySnapshot = static function () use ($db) {
    return $db->query('SELECT * FROM categories ORDER BY id')->fetch_all(MYSQLI_ASSOC);
};
request('test-session.php?id=2');
foreach (['categories.php', 'category-form.php', 'save-category.php', 'includes/category-input.php'] as $path) {
    check(request('admin/' . $path)[0] === 403, 'Category customer denied: ' . $path);
}
$before = $categorySnapshot();
check(request('admin/save-category.php', ['id'=>'','name'=>'Forbidden'])[0] === 403, 'Customer category POST denied');
check($before === $categorySnapshot(), 'Customer POST changes nothing');
request('test-session.php?id=1');
check(request('admin/save-category.php')[0] === 405, 'Category writes require POST');
[$status, $html] = request('admin/categories.php?q=Other');
check($status === 200 && preg_match('/<td>Other<\/td>\s*<td><\/td>\s*<td>0<\/td>/', $html), 'Empty category displayed with zero products');
$html = request('admin/categories.php?q=Test%20category')[1];
check(preg_match('/<td>Test category<\/td>\s*<td><\/td>\s*<td>24<\/td>/', $html), 'Category product count is accurate');
$data = ['csrf_token'=>$token, 'id'=>'', 'name'=>'  New category  ', 'description'=>'  New description  '];
[$status, , $headers] = request('admin/save-category.php', $data);
check($status === 303 && str_contains($headers, '/scr/admin/categories.php'), 'Create category redirects to list');
$createdCategory = $db->query("SELECT * FROM categories WHERE name='New category'")->fetch_assoc();
check($createdCategory && $createdCategory['description'] === 'New description', 'Create trims name and description');
$newId = (string) $createdCategory['id'];
check(str_contains(request('admin/categories.php')[1], 'Đã thêm danh mục.'), 'Create success flash');
foreach (['product-form.php', 'product-form.php?id=1'] as $path) {
    check(str_contains(request('admin/' . $path)[1], '>New category</option>'), 'New category appears in product dropdown: ' . $path);
}
$edit = array_replace($data, ['id'=>$newId, 'name'=>'New category', 'description'=>'Updated']);
request('admin/save-category.php', $edit);
check($db->query('SELECT description FROM categories WHERE id=' . (int) $newId)->fetch_row()[0] === 'Updated', 'Edit with unchanged name succeeds');
request('admin/save-category.php', array_replace($edit, ['id'=>'1', 'name'=>'Renamed existing']));
check($db->query('SELECT name FROM categories WHERE id=1')->fetch_row()[0] === 'Renamed existing', 'Rename retains existing category ID');
check($productsBefore === $db->query('SELECT * FROM products ORDER BY id')->fetch_all(MYSQLI_ASSOC), 'Rename preserves products and relationships');
foreach ([
    ['name'=>'   '], ['name'=>str_repeat('á',101)], ['description'=>str_repeat('ộ',2001)],
    ['name'=>'Other'], ['name'=>'other'], ['name'=>'OTHER '], ['csrf_token'=>'wrong'],
    ['name'=>"\xFF"]
] as $invalid) {
    $before = $categorySnapshot();
    check(request('admin/save-category.php', array_replace($edit, $invalid))[0] === 303, 'Invalid category ' . key($invalid) . ' uses PRG');
    check($before === $categorySnapshot(), 'Invalid category ' . key($invalid) . ' does not write');
    check(str_contains(request('admin/category-form.php?id=' . $newId)[1], 'alert-danger'), 'Invalid category displays Vietnamese error');
}
$before = $categorySnapshot();
request('admin/save-category.php', array_replace($data, ['name'=>'other']));
check($before === $categorySnapshot(), 'Create duplicate respects database collation');
$longDescription = str_repeat('ộ',2000);
request('admin/save-category.php', array_replace($edit, ['name'=>str_repeat('á',100), 'description'=>$longDescription]));
$row = $db->query('SELECT name,description FROM categories WHERE id=' . (int) $newId)->fetch_assoc();
check(mb_strlen($row['name'],'UTF-8') === 100 && $row['description'] === $longDescription, 'Unicode 100/2000 character boundaries accepted');
request('admin/save-category.php', array_replace($edit, ['name'=>'Keep this value', 'description'=>str_repeat('x',2001)]));
$html = request('admin/category-form.php?id=' . $newId)[1];
check(str_contains($html,'value="Keep this value"') && str_contains($html,str_repeat('x',2001)), 'Errors retain entered name and description');
check(str_contains($html,'maxlength="2000"') && str_contains($html,'maxlength="100"'), 'HTML field limits match server limits');
foreach (['abc','-1','0','2147483648','999999',''] as $badId) {
    check(request('admin/category-form.php?id=' . $badId)[0] === 404, 'Invalid or missing category GET: ' . $badId);
}
check(request('admin/category-form.php?id%5B%5D=1')[0] === 404, 'Array category GET ID rejected');
foreach (['abc','-1','0','2147483648','999999'] as $badId) {
    $before = $categorySnapshot();
    [$status, , $headers] = request('admin/save-category.php', array_replace($edit, ['id'=>$badId]));
    check($status === 303 && str_contains($headers,'/scr/admin/categories.php') && $before === $categorySnapshot(), 'Bad POST ID rejected: ' . $badId);
    check(str_contains(request('admin/categories.php')[1],'alert-danger'), 'Bad POST ID displays error');
}
foreach (['id', 'name', 'description', 'csrf_token'] as $arrayField) {
    $bad = $edit;
    unset($bad[$arrayField]);
    $bad[$arrayField . '[0]'] = '1';
    $before = $categorySnapshot();
    request('admin/save-category.php', $bad);
    check($before === $categorySnapshot(), 'Array payload rejected: ' . $arrayField);
}
$before = $categorySnapshot();
$withoutId = $edit;
unset($withoutId['id']);
request('admin/save-category.php', $withoutId);
check($before === $categorySnapshot(), 'Missing POST ID cannot accidentally create category');
$before = $categorySnapshot();
$db->query("CREATE TRIGGER category_test_fail BEFORE INSERT ON categories FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='private database detail'");
request('admin/save-category.php', array_replace($data, ['name'=>'DB failure fixture']));
$html = request('admin/category-form.php')[1];
check($before === $categorySnapshot() && str_contains($html,'Không thể lưu danh mục') && !str_contains($html,'private database detail'), 'Database failure remains private');
$db->query('DROP TRIGGER category_test_fail');
$insertCategory = $db->prepare('INSERT INTO categories (name) VALUES (?)');
for ($i=0; $i<23; $i++) {
    $fixtureName = sprintf('Category pagination %02d', $i);
    $insertCategory->bind_param('s',$fixtureName);
    $insertCategory->execute();
}
[$status,$html] = request('admin/categories.php?q=Category%20pagination&page=2');
check($status === 200 && substr_count($html,'>Sửa</a>') === 10, 'Category page two has ten matching results');
check(str_contains($html,'q=Category+pagination&amp;page=3'), 'Category pagination preserves keyword');
check(substr_count(request('admin/categories.php?q=Category%20pagination&page=3')[1],'>Sửa</a>') === 3, 'Last category page has three results');
check(substr_count(request('admin/categories.php?q=Category%20pagination&page=999')[1],'>Sửa</a>') === 3, 'Oversized page clamps to last page');
check(str_contains(request('admin/categories.php?q=nonexistent')[1],'Không tìm thấy danh mục'), 'Empty category search result');
request('admin/save-category.php', array_replace($edit, ['name'=>'<script>alert(1)</script>', 'description'=>'<img src=x onerror=alert(1)>']));
$html = request('admin/categories.php?q=alert')[1];
check(str_contains($html,'&lt;script&gt;') && str_contains($html,'&lt;img') && !str_contains($html,'<script>alert(1)</script>'), 'Category name and description escaped');
foreach (['categories.php','category-form.php','category-form.php?id=1','products.php','product-form.php'] as $path) {
    $html = request('admin/' . $path)[1];
    check(substr_count($html,'class="nav-link active"') === 1, 'One active sidebar item: ' . $path);
}
check($productsBefore === $db->query('SELECT * FROM products ORDER BY id')->fetch_all(MYSQLI_ASSOC), 'Category suite leaves all product fields unchanged');
check($historyBefore === $db->query('SELECT * FROM order_details')->fetch_all(MYSQLI_ASSOC), 'Category suite leaves historical prices unchanged');
echo 'Category checks completed: ' . ($checks - $categoryChecksStart) . PHP_EOL;
