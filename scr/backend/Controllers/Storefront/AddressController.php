<?php
namespace MotoParts\App\Controllers\Storefront;
use MotoParts\App\Core\AuthCsrf;
use MotoParts\App\Core\View;
use MotoParts\App\Middleware\Authenticate;
use MotoParts\App\Models\ShippingAddress;
use MotoParts\App\Services\CartService;
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class AddressController
{
    private array $user;
    public function __construct()
    {
        $this->user = Authenticate::requireUser();
        if ($this->user['role'] !== 'customer') Authenticate::error(403, 'Chỉ khách hàng được quản lý địa chỉ.');
        if (($_SESSION['address_owner'] ?? null) !== $this->user['id']) {
            unset($_SESSION['address_old'], $_SESSION['address_flash']);
            $_SESSION['address_owner'] = $this->user['id'];
        }
    }
    public function index(): void
    {
        $rows = []; $error = ''; $edit = $_SESSION['address_old'] ?? [];
        $flash = $_SESSION['address_flash'] ?? null;
        unset($_SESSION['address_old'], $_SESSION['address_flash']);
        try {
            $model = new ShippingAddress(Authenticate::connection());
            $rows = $model->all($this->user['id']);
            if (isset($_GET['id'])) {
                $id = CartService::integer($_GET['id']);
                $edit = $id ? $model->owned($id, $this->user['id']) : null;
                if (!$edit) { http_response_code(404); $error = 'Không tìm thấy địa chỉ.'; $edit = []; }
            }
        } catch (\Throwable $e) { http_response_code(503); $error = 'Sổ địa chỉ chưa khả dụng. Vui lòng thử lại sau.'; }
        $csrfToken = AuthCsrf::token();
        View::storefront('storefront/profile/addresses', compact('rows', 'edit', 'flash', 'error', 'csrfToken'));
    }
    public function save(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); return; }
        try {
            if (!AuthCsrf::valid($_POST['csrf_token'] ?? null)) throw new \DomainException('Phiên gửi biểu mẫu không hợp lệ.');
            $operation = $_POST['operation'] ?? '';
            if (!in_array($operation, ['save', 'delete', 'default'], true)) throw new \DomainException('Thao tác không hợp lệ.');
            $rawId = $_POST['id'] ?? '';
            $id = $rawId === '' && $operation === 'save' ? null : CartService::integer($rawId);
            if ($id === null && !($rawId === '' && $operation === 'save')) throw new \DomainException('Mã địa chỉ không hợp lệ.');
            $data = [];
            if ($operation === 'save') {
                foreach (['fullname'=>100, 'phone'=>20, 'address'=>500] as $key=>$limit) {
                    $value = $_POST[$key] ?? null;
                    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) throw new \DomainException('Thông tin địa chỉ không hợp lệ.');
                    $data[$key] = trim($value);
                    if ($key === 'phone') $data[$key] = preg_replace('/\s+/u', '', $data[$key]);
                    if ($data[$key] === '' || mb_strlen($data[$key], 'UTF-8') > $limit) throw new \DomainException('Họ tên/điện thoại/địa chỉ bắt buộc, tối đa lần lượt 100/20/500 ký tự.');
                }
                $_SESSION['address_old'] = $data + ['id'=>$id ?? ''];
                if (!preg_match('/\A[0-9]{9,11}\z/', $data['phone'])) throw new \DomainException('Số điện thoại phải có từ 9 đến 11 chữ số.');
            }
            (new ShippingAddress(Authenticate::connection()))->save($this->user['id'], $operation, $id, $data, ($_POST['is_default'] ?? '') === '1');
            unset($_SESSION['address_old']);
            $_SESSION['address_flash'] = 'Đã cập nhật sổ địa chỉ.';
        } catch (\DomainException $e) { $_SESSION['address_flash'] = $e->getMessage(); }
        catch (\Throwable $e) { $_SESSION['address_flash'] = 'Không thể lưu địa chỉ. Vui lòng thử lại sau.'; }
        header('Location: ../pages/addresses.php', true, 303);
    }
}
