<?php
namespace MotoParts\App\Middleware;

use MotoParts\App\Core\View;
use MotoParts\App\Models\UserAuth;
use MotoParts\App\Services\AuthService;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class Authenticate
{
    public const BASE_URL = '/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr';

    public static function connection(): \mysqli
    {
        global $conn;
        ini_set('display_errors', '0');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        if (!isset($conn)) require dirname(__DIR__, 2) . '/config/database.php';
        return $conn;
    }

    public static function current(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user'])) return null;
        $sessionUser = $_SESSION['user'];
        $id = is_array($sessionUser) ? filter_var($sessionUser['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $roleValid = is_array($sessionUser) && (!isset($sessionUser['role']) || in_array($sessionUser['role'], ['admin', 'customer'], true));
        $user = $id && $roleValid ? (new UserAuth(self::connection()))->findById($id) : null;
        if (!$user || !in_array($user['role'], ['customer', 'admin'], true)) {
            // Keep the cart, but invalidate private data belonging to the old identity.
            unset($_SESSION['user'], $_SESSION['completed_order_id'], $_SESSION['completed_order_user_id'],
                $_SESSION['checkout_old'], $_SESSION['checkout_submit_token']);
            return null;
        }
        return $_SESSION['user'] = AuthService::publicUser($user);
    }

    public static function requireUser(): array
    {
        try { $user = self::current(); }
        catch (\Throwable $exception) {
            error_log('MotoParts authentication: ' . get_class($exception) . ' code=' . (int) $exception->getCode());
            self::error(503, 'Không thể kiểm tra tài khoản. Vui lòng thử lại sau.');
        }
        if (!$user) {
            $_SESSION['login_error'] = 'Vui lòng đăng nhập để tiếp tục.';
            header('Location: ' . self::BASE_URL . '/pages/login.php', true, 302);
            exit;
        }
        return $user;
    }

    public static function error(int $status, string $message): void
    {
        http_response_code($status);
        View::storefront('storefront/auth/error', compact('message'));
        exit;
    }
}
