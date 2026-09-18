<?php
require_once __DIR__ . '/includes/category-input.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Chỉ chấp nhận POST.');
}
$id = category_id($_POST);
if ($id === false || !array_key_exists('id', $_POST)) {
    $_SESSION['category_error'] = 'Mã danh mục không hợp lệ. Không có dữ liệu nào được lưu.';
    product_redirect('categories.php');
}
[$data, $errors] = category_validate($_POST);
if (!isset($_POST['csrf_token']) || !is_string($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $errors[] = 'Phiên gửi biểu mẫu không hợp lệ. Vui lòng thử lại.';
}
$transaction = false;
$missing = false;
try {
    if (!$errors) {
        $conn->begin_transaction();
        $transaction = true;
        if ($id !== null && !product_query('SELECT id FROM categories WHERE id = ? FOR UPDATE', 'i', [$id])->get_result()->fetch_assoc()) {
            $missing = true;
        }
        if (!$missing) {
            // SQL equality deliberately inherits the column's database collation.
            // Without a UNIQUE constraint, concurrent inserts can still race.
            $duplicate = product_query('SELECT id FROM categories WHERE name = ? AND id <> ? LIMIT 1', 'si', [$data['name'], $id ?? 0])->get_result()->fetch_assoc();
            if ($duplicate) {
                $errors[] = 'Tên danh mục đã tồn tại. Vui lòng chọn tên khác.';
            } else {
                if ($id === null) {
                    product_query('INSERT INTO categories (name, description) VALUES (?, ?)', 'ss', [$data['name'], $data['description']]);
                } else {
                    product_query('UPDATE categories SET name = ?, description = ? WHERE id = ?', 'ssi', [$data['name'], $data['description'], $id]);
                }
                $conn->commit();
                $transaction = false;
                unset($_SESSION['category_form']);
                $_SESSION['category_success'] = $id === null ? 'Đã thêm danh mục.' : 'Đã cập nhật danh mục.';
                product_redirect('categories.php');
            }
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Không thể lưu danh mục. Vui lòng thử lại sau.';
}
if ($transaction) {
    try { $conn->rollback(); } catch (Throwable $e) { /* Keep database errors private. */ }
}
if ($missing) {
    $_SESSION['category_error'] = 'Danh mục không tồn tại. Không có dữ liệu nào được lưu.';
    product_redirect('categories.php');
}
$_SESSION['category_form'] = ['id' => $id, 'data' => $data, 'errors' => $errors];
product_redirect('category-form.php' . ($id !== null ? '?id=' . $id : ''));
