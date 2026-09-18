<?php
require_once __DIR__ . '/includes/product-bootstrap.php';
require_once __DIR__ . '/includes/product-upload.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Chỉ chấp nhận POST.');
}
$idInput = product_text($_POST, 'id');
$id = filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
$destination = 'product-form.php' . ($id ? '?id=' . $id : '');
[$data, $errors] = product_validate($_POST);
if ($idInput !== '' && !$id) $errors[] = 'Mã sản phẩm không hợp lệ.';
if (!isset($_POST['csrf_token']) || !is_string($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $errors[] = 'Phiên gửi biểu mẫu không hợp lệ hoặc dữ liệu quá lớn. Vui lòng thử lại.';
}
$newImage = null;
$transaction = false;
try {
    if (!$errors) {
        $conn->begin_transaction();
        $transaction = true;
        $current = null;
        if ($id) {
            $current = product_query('SELECT id, image FROM products WHERE id = ? FOR UPDATE', 'i', [$id])->get_result()->fetch_assoc();
            if (!$current) $errors[] = 'Sản phẩm không còn tồn tại.';
        }
        $category = product_query('SELECT id FROM categories WHERE id = ? LOCK IN SHARE MODE', 'i', [(int) $data['category_id']])->get_result()->fetch_assoc();
        if (!$category) $errors[] = 'Danh mục không tồn tại.';
        if (!$errors) {
            $newImage = product_upload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
            $image = $newImage ?? ($current['image'] ?? '');
            $values = [(int) $data['category_id'], $data['name'], $data['price'], $image, $data['brand'], (int) $data['stock'], $data['description']];
            if ($id) {
                $values[] = $id;
                product_query('UPDATE products SET category_id=?, name=?, price=?, image=?, brand=?, stock=?, description=? WHERE id=?', 'issssisi', $values);
            } else {
                product_query('INSERT INTO products (category_id,name,price,image,brand,stock,description) VALUES (?,?,?,?,?,?,?)', 'issssis', $values);
            }
            $conn->commit();
            $transaction = false;
            $_SESSION['product_success'] = $id ? 'Đã cập nhật sản phẩm.' : 'Đã thêm sản phẩm.';
            unset($_SESSION['product_form']);
            product_redirect('products.php');
        }
    }
} catch (mysqli_sql_exception $e) {
    $errors[] = 'Không thể lưu sản phẩm. Vui lòng thử lại sau.';
} catch (RuntimeException $e) {
    $errors[] = $e->getMessage();
} catch (Throwable $e) {
    $errors[] = 'Không thể xử lý sản phẩm. Vui lòng thử lại sau.';
}
if ($transaction) {
    try { $conn->rollback(); } catch (Throwable $e) {}
}
if ($newImage !== null) @unlink(__DIR__ . '/../assets/images/products/' . $newImage);
$_SESSION['product_form'] = ['id' => $id ?: null, 'data' => $data, 'errors' => $errors];
product_redirect($destination);
