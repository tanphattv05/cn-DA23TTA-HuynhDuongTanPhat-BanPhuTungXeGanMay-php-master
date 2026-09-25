<?php
namespace MotoParts\App\Core;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class CartCsrf
{
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['cart_csrf_token']) || !is_string($_SESSION['cart_csrf_token'])) {
            $_SESSION['cart_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['cart_csrf_token'];
    }

    public static function valid($token): bool
    {
        return is_string($token) && isset($_SESSION['cart_csrf_token'])
            && is_string($_SESSION['cart_csrf_token']) && hash_equals($_SESSION['cart_csrf_token'], $token);
    }
}