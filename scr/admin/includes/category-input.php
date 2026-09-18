<?php
// Reuse the established admin auth, CSRF, query, escaping and redirect helpers.
require_once __DIR__ . '/product-bootstrap.php';

function category_id(array $input)
{
    if (!array_key_exists('id', $input) || $input['id'] === '') {
        return null;
    }
    if (!is_string($input['id']) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $input['id'])) {
        return false;
    }
    return filter_var($input['id'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647]
    ]);
}

function category_validate(array $input): array
{
    $data = ['name' => product_text($input, 'name'), 'description' => product_text($input, 'description')];
    $errors = [];
    if ($data['name'] === '' || !mb_check_encoding($data['name'], 'UTF-8') || mb_strlen($data['name'], 'UTF-8') > 100) {
        $errors[] = 'Tên danh mục bắt buộc và không được vượt quá 100 ký tự.';
    }
    if ((isset($input['description']) && !is_string($input['description']))
        || !mb_check_encoding($data['description'], 'UTF-8')
        || mb_strlen($data['description'], 'UTF-8') > 2000) {
        $errors[] = 'Mô tả phải là văn bản hợp lệ, tối đa 2.000 ký tự.';
    }
    return [$data, $errors];
}
