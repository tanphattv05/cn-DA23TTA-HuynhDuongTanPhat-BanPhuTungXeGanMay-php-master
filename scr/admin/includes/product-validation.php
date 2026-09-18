<?php
// Direct HTTP requests still pass through the existing admin authorization.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once __DIR__ . '/product-bootstrap.php';
    http_response_code(404);
    exit;
}
function product_text(array $input, string $key): string
{
    return isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
}

function product_validate(array $input): array
{
    $data = [];
    foreach (['name', 'category_id', 'price', 'brand', 'stock', 'description'] as $key) {
        $data[$key] = product_text($input, $key);
    }
    $errors = [];
    foreach (['name' => 255, 'brand' => 100] as $key => $limit) {
        if (($key === 'name' && $data[$key] === '') || !preg_match('//u', $data[$key]) || mb_strlen($data[$key], 'UTF-8') > $limit) {
            $errors[] = $key === 'name' ? 'Tên sản phẩm bắt buộc, tối đa 255 ký tự.' : 'Thương hiệu tối đa 100 ký tự.';
        }
    }
    if (!preg_match('/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/', $data['price'])) {
        $errors[] = 'Giá phải từ 0 đến 9999999999.99, dùng dấu chấm và tối đa 2 chữ số thập phân.';
    }
    if (!preg_match('/\A[0-9]{1,10}\z/', $data['stock']) || (float) $data['stock'] > 2147483647) {
        $errors[] = 'Tồn kho phải là số nguyên từ 0 đến 2147483647.';
    }
    if (!preg_match('/\A[1-9][0-9]{0,9}\z/', $data['category_id']) || (float) $data['category_id'] > 2147483647) {
        $errors[] = 'Vui lòng chọn danh mục hợp lệ.';
    }
    if (!preg_match('//u', $data['description']) || strlen($data['description']) > 65535) {
        $errors[] = 'Mô tả không hợp lệ hoặc vượt quá 65535 byte UTF-8.';
    }
    return [$data, $errors];
}
