<?php
namespace MotoParts\App\Controllers\Storefront;

use MotoParts\App\Core\AuthCsrf;
use MotoParts\App\Core\View;
use MotoParts\App\Middleware\Authenticate;
use MotoParts\App\Models\UserAuth;
use MotoParts\App\Services\AuthService;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class AuthController
{
    public function __construct()
    {
        ini_set('display_errors', '0');
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    private function service(): AuthService { return new AuthService(new UserAuth(Authenticate::connection())); }

    private function redirect(string $path): void
    {
        header('Location: ' . $path, true, 303);
        exit;
    }

    private function failure(\Throwable $exception): string
    {
        error_log('MotoParts auth: ' . get_class($exception) . ' code=' . (int) $exception->getCode());
        return 'Không thể xử lý tài khoản. Vui lòng thử lại sau.';
    }

    private function form(string $kind): void
    {
        try { if (Authenticate::current()) $this->redirect('../index.php'); }
        catch (\Throwable $exception) { Authenticate::error(503, $this->failure($exception)); }
        $error = $_SESSION[$kind . '_error'] ?? '';
        $success = $_SESSION['login_success'] ?? '';
        $oldEmail = $_SESSION['login_email'] ?? '';
        $old = $_SESSION['register_old'] ?? [];
        unset($_SESSION[$kind . '_error']);
        if ($kind === 'login') unset($_SESSION['login_success'], $_SESSION['login_email']);
        else unset($_SESSION['register_old']);
        $csrfToken = AuthCsrf::token();
        View::storefront('storefront/auth/' . $kind, compact('error', 'success', 'oldEmail', 'old', 'csrfToken'));
    }

    public function loginForm(): void { $this->form('login'); }
    public function registerForm(): void { $this->form('register'); }

    private function post(string $kind): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('../pages/' . $kind . '.php');
        if (!AuthCsrf::valid($_POST['csrf_token'] ?? null)) {
            $_SESSION[$kind . '_error'] = 'Phiên gửi biểu mẫu không hợp lệ. Vui lòng thử lại.';
            $this->redirect('../pages/' . $kind . '.php');
        }
    }

    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $old = AuthService::publicInput($_POST);
            foreach ($old as $key => $value) $old[$key] = mb_substr($value, 0, 101, 'UTF-8');
            $_SESSION['register_old'] = $old;
        }
        $this->post('register');
        unset($_SESSION['register_error']);
        try { $this->service()->register($_POST); }
        catch (\DomainException $exception) { $_SESSION['register_error'] = $exception->getMessage(); }
        catch (\Throwable $exception) { $_SESSION['register_error'] = $this->failure($exception); }
        if (isset($_SESSION['register_error'])) $this->redirect('../pages/register.php');
        unset($_SESSION['register_old']);
        $_SESSION['login_success'] = 'Đăng ký thành công. Bạn có thể đăng nhập.';
        $this->redirect('../pages/login.php');
    }

    public function login(): void
    {
        $this->post('login');
        $_SESSION['login_email'] = substr(AuthService::publicInput($_POST)['email'], 0, 101);
        try { $user = $this->service()->login($_POST); }
        catch (\DomainException $exception) { $_SESSION['login_error'] = $exception->getMessage(); $this->redirect('../pages/login.php'); }
        catch (\Throwable $exception) { $_SESSION['login_error'] = $this->failure($exception); $this->redirect('../pages/login.php'); }
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        unset($_SESSION['login_email'], $_SESSION['login_error'], $_SESSION['auth_csrf_token'],
            $_SESSION['completed_order_id'], $_SESSION['completed_order_user_id'], $_SESSION['checkout_old'], $_SESSION['checkout_submit_token']);
        $this->redirect($user['role'] === 'admin' ? '../admin/index.php' : '../index.php');
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && AuthCsrf::valid($_POST['auth_csrf_token'] ?? null)) {
            $cart = is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
            $_SESSION = ['cart' => $cart];
            session_regenerate_id(true);
        }
        $this->redirect('../index.php');
    }
}
