<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
// Reuse Admin middleware, input and presentation helpers.
require_once __DIR__ . '/product-bootstrap.php';

function customer_page(array $input): int
{
    return filter_var(product_text($input, 'page'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647]
    ]) ?: 1;
}

function customer_date(?string $value): string
{
    $timestamp = $value ? strtotime($value) : false;
    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
}

function customer_pagination(string $path, array $query, int $page, int $pages): void
{
    if ($pages <= 1) {
        return;
    }
    require dirname(__DIR__, 3) . '/frontend/includes/admin/pagination.php';
}
