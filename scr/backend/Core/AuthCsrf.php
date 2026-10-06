<?php
namespace MotoParts\App\Core;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class AuthCsrf
{
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!is_string($_SESSION['auth_csrf_token'] ?? null) || strlen($_SESSION['auth_csrf_token']) !== 64) {
            $_SESSION['auth_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['auth_csrf_token'];
    }

    public static function valid($token): bool
    {
        return is_string($token) && is_string($_SESSION['auth_csrf_token'] ?? null)
            && hash_equals($_SESSION['auth_csrf_token'], $token);
    }
}
