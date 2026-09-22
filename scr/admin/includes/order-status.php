<?php
require_once __DIR__ . '/product-bootstrap.php';

function order_status_labels(): array
{
    return [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'shipping' => 'Đang giao hàng',
        'completed' => 'Đã hoàn thành',
        'cancelled' => 'Đã hủy'
    ];
}

function order_transitions(): array
{
    return [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping' => ['completed'],
        'completed' => [],
        'cancelled' => []
    ];
}

// Format MySQL DECIMAL strings without converting historical totals to floats.
function order_money(string $amount): string
{
    [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '00');
    return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integer)
        . ',' . str_pad(substr($fraction, 0, 2), 2, '0') . ' ₫';
}

function order_status_classes(): array
{
    return [
        'pending' => 'bg-warning text-dark',
        'confirmed' => 'bg-primary',
        'shipping' => 'bg-info text-dark',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger'
    ];
}
