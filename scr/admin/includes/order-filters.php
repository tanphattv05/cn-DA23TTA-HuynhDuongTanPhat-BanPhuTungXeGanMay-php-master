<?php
require_once __DIR__ . '/order-status.php';
require_once __DIR__ . '/customer-view.php';

function order_list_filters(array $input): array
{
    $filters = [];
    $errors = [];
    foreach (['q' => 100, 'status' => 20, 'from' => 10, 'to' => 10] as $key => $limit) {
        $value = product_text($input, $key);
        if ((isset($input[$key]) && !is_string($input[$key]))
            || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > $limit) {
            $errors[] = $key === 'q' ? 'Từ khóa phải là văn bản hợp lệ, tối đa 100 ký tự.' : 'Bộ lọc không hợp lệ.';
        }
        // Bound redisplayed values as well as SQL input.
        $filters[$key] = mb_substr($value, 0, $limit, 'UTF-8');
    }
    if ($filters['status'] !== '' && !array_key_exists($filters['status'], order_status_labels())) {
        $errors[] = 'Trạng thái lọc không hợp lệ.';
    }
    foreach (['from' => 'Từ ngày', 'to' => 'Đến ngày'] as $key => $label) {
        $value = $filters[$key];
        if ($value !== '' && (!preg_match('/\A([1-9][0-9]{3})-([0-9]{2})-([0-9]{2})\z/', $value, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]))) {
            $errors[] = $label . ' không hợp lệ. Vui lòng nhập ngày theo định dạng YYYY-MM-DD.';
        }
    }
    if (!$errors && $filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']) {
        $errors[] = 'Từ ngày không được lớn hơn đến ngày.';
    }
    return [$filters, array_unique($errors)];
}

function order_list_return_path($context): string
{
    if (!is_array($context)) {
        return 'orders.php';
    }
    [$filters, $errors] = order_list_filters($context);
    if ($errors) {
        return 'orders.php';
    }
    // Whitelist fields and construct a fixed local path; never accept a URL.
    return 'orders.php?' . http_build_query(array_merge($filters, ['page' => customer_page($context)]));
}
