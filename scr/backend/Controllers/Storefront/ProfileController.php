<?php
namespace MotoParts\App\Controllers\Storefront;
use MotoParts\App\Core\AuthCsrf;
use MotoParts\App\Core\View;
use MotoParts\App\Middleware\Authenticate;
use MotoParts\App\Models\Profile;
use MotoParts\App\Services\AuthService;
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class ProfileController
{
    private array $user;
    public function __construct()
    {
        $this->user = Authenticate::requireUser();
        if ($this->user['role'] !== 'customer') Authenticate::error(403, 'Chỉ khách hàng được sử dụng hồ sơ cá nhân.');
        if (($_SESSION['profile_owner'] ?? null) !== $this->user['id']) {
            unset($_SESSION['profile_old'], $_SESSION['profile_flash']);
            $_SESSION['profile_owner'] = $this->user['id'];
        }
    }

    public function index(): void
    {
        $flash = $_SESSION['profile_flash'] ?? null;
        $data = $_SESSION['profile_old'] ?? $this->user;
        unset($_SESSION['profile_flash'], $_SESSION['profile_old']);
        $csrfToken = AuthCsrf::token();
        View::storefront('storefront/profile/index', compact('flash', 'data', 'csrfToken'));
    }

    public function save(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); return; }
        try {
            if (!AuthCsrf::valid($_POST['csrf_token'] ?? null)) throw new \DomainException('Phiên gửi biểu mẫu không hợp lệ. Vui lòng thử lại.');
            $model = new Profile(Authenticate::connection());
            if (($_POST['operation'] ?? '') === 'profile') {
                $data = AuthService::publicInput($_POST);
                $_SESSION['profile_old'] = array_map(static fn($v) => mb_substr($v, 0, 101, 'UTF-8'), $data);
                if ($data['fullname'] === '' || mb_strlen($data['fullname'], 'UTF-8') > 100) throw new \DomainException('Họ tên bắt buộc, tối đa 100 ký tự.');
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 100) throw new \DomainException('Email không hợp lệ.');
                if (!preg_match('/\A[0-9]{9,11}\z/', $data['phone'])) throw new \DomainException('Số điện thoại phải có từ 9 đến 11 chữ số.');
                $model->update($this->user['id'], $data);
                Authenticate::current();
                unset($_SESSION['profile_old']);
                $message = 'Cập nhật hồ sơ thành công.';
            } elseif (($_POST['operation'] ?? '') === 'password') {
                $current = $_POST['current_password'] ?? null;
                $next = $_POST['new_password'] ?? null;
                if (!is_string($current) || $current === '' || strlen($current) > 72 || str_contains($current, "\0")) throw new \DomainException('Mật khẩu hiện tại không hợp lệ.');
                if (!is_string($next) || strlen($next) < 6 || strlen($next) > 72 || str_contains($next, "\0")) throw new \DomainException('Mật khẩu mới phải dài từ 6 đến 72 byte và không chứa ký tự null.');
                if ($next !== ($_POST['password_confirm'] ?? null)) throw new \DomainException('Xác nhận mật khẩu mới không khớp.');
                $model->changePassword($this->user['id'], $current, $next);
                session_regenerate_id(true);
                unset($_SESSION['auth_csrf_token'], $_SESSION['profile_old']);
                $message = 'Đổi mật khẩu thành công.';
            } else throw new \DomainException('Thao tác không hợp lệ.');
            $_SESSION['profile_flash'] = ['type' => 'success', 'message' => $message];
        } catch (\DomainException $e) {
            $_SESSION['profile_flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            $_SESSION['profile_flash'] = ['type' => 'danger', 'message' => 'Không thể cập nhật tài khoản. Vui lòng thử lại sau.'];
        }
        header('Location: ../pages/profile.php', true, 303);
    }
}
