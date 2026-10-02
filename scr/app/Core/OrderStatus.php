<?php
namespace MotoParts\App\Core;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

// Presentation metadata shared by Admin and storefront; transitions stay in Admin.
final class OrderStatus
{
    public static function labels(): array
    {
        return [
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'shipping' => 'Đang giao hàng',
            'completed' => 'Đã hoàn thành',
            'cancelled' => 'Đã hủy'
        ];
    }

    public static function classes(): array
    {
        return [
            'pending' => 'bg-warning text-dark',
            'confirmed' => 'bg-primary',
            'shipping' => 'bg-info text-dark',
            'completed' => 'bg-success',
            'cancelled' => 'bg-danger'
        ];
    }
}
