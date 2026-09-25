<?php
namespace MotoParts\App\Controllers\Storefront;

use MotoParts\App\Core\CartCsrf;
use MotoParts\App\Core\View;
use MotoParts\App\Models\Checkout;
use MotoParts\App\Services\CartService;
use MotoParts\App\Services\CheckoutService;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class CheckoutController
{
    public function __construct()
    {
        ini_set('display_errors', '0');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        // Keep the PHP session lock until commit AND token/cart updates complete.
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    private function model(): Checkout
    {
        require dirname(__DIR__, 3) . '/config/database.php';
        return new Checkout($conn);
    }

    private function redirect(string $page): void
    {
        header('Location: ../pages/' . $page, true, 303);
        exit;
    }

    private function userId(): ?int
    {
        if (!isset($_SESSION['user'])) return null;
        $id = is_array($_SESSION['user']) ? CartService::integer($_SESSION['user']['id'] ?? null) : null;
        if ($id === null) throw new \DomainException('Tài khoản không hợp lệ. Vui lòng đăng nhập lại.');
        return $id;
    }

    private function databaseError(\Throwable $exception): string
    {
        error_log('MotoParts checkout: ' . get_class($exception) . ' code=' . (int) $exception->getCode());
        return 'Không thể xử lý thanh toán. Vui lòng thử lại sau.';
    }

    private function submissionToken(): string
    {
        if (empty($_SESSION['checkout_submit_token']) || !is_string($_SESSION['checkout_submit_token'])) {
            $_SESSION['checkout_submit_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['checkout_submit_token'];
    }

    private function recipient(array $input): array
    {
        $data = [];
        $errors = [];
        $labels = ['fullname' => 'Họ và tên', 'phone' => 'Số điện thoại', 'address' => 'Địa chỉ', 'note' => 'Ghi chú'];
        foreach (['fullname' => 100, 'phone' => 20, 'address' => 500, 'note' => 500] as $key => $limit) {
            $value = $input[$key] ?? '';
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
                $errors[] = $labels[$key] . ' không hợp lệ.';
                $value = '';
            }
            $value = preg_replace('/\A\s+|\s+\z/u', '', $value);
            if ($key === 'phone') $value = preg_replace('/\s+/u', '', $value);
            if (mb_strlen($value, 'UTF-8') > $limit) $errors[] = $labels[$key] . ' không được vượt quá ' . $limit . ' ký tự.';
            if ($key !== 'note' && $value === '') $errors[] = $labels[$key] . ' là bắt buộc.';
            // Bound flash-session size while retaining ordinary invalid values.
            $data[$key] = mb_substr($value, 0, $limit + 1, 'UTF-8');
        }
        if (!preg_match('/\A[0-9]{9,11}\z/', $data['phone'])) $errors[] = 'Số điện thoại phải có từ 9 đến 11 chữ số.';
        return [$data, array_unique($errors)];
    }

    public function index(): void
    {
        if (empty($_SESSION['cart'])) {
            $_SESSION['cart_flash'] = ['type' => 'warning', 'message' => 'Giỏ hàng đang trống. Vui lòng chọn sản phẩm.'];
            $this->redirect('cart.php');
        }
        $products = [];
        $total = '0.00';
        $error = $_SESSION['checkout_error'] ?? '';
        $old = $_SESSION['checkout_old'] ?? [];
        unset($_SESSION['checkout_error'], $_SESSION['checkout_old']);
        $unavailable = false;
        $csrfToken = CartCsrf::token();
        $submitToken = $this->submissionToken();
        try {
            $model = $this->model();
            $userId = $this->userId();
            if ($userId !== null) {
                $user = $model->user($userId);
                if (!$user) throw new \DomainException('Tài khoản không còn hợp lệ. Vui lòng đăng nhập lại.');
                $old['fullname'] = $old['fullname'] ?? $user['fullname'];
                $old['phone'] = $old['phone'] ?? ($user['phone'] ?? '');
            }
            $cart = new CartService($_SESSION);
            $rows = $model->products(array_keys($cart->read()));
            [, , $changed] = $cart->reconcile($rows);
            if ($changed || !$cart->read()) {
                $_SESSION['cart_flash'] = ['type' => 'warning', 'message' => 'Giỏ hàng đã thay đổi theo tồn kho. Vui lòng kiểm tra trước khi thanh toán.'];
                $_SESSION['checkout_old'] = $old;
                if ($error !== '') $_SESSION['checkout_error'] = $error;
                $this->redirect('cart.php');
            }
            [$products, $total] = (new CheckoutService($model))->summarize(CheckoutService::normalize($cart->read()), $rows);
        } catch (\DomainException $exception) {
            http_response_code(409);
            $error = $exception->getMessage();
            $unavailable = true;
        } catch (\Throwable $exception) {
            http_response_code(503);
            $error = $this->databaseError($exception);
            $unavailable = true;
            $_SESSION['checkout_old'] = $old;
        }
        View::storefront('storefront/checkout/index', compact('products', 'total', 'error', 'old', 'csrfToken', 'submitToken', 'unavailable'));
    }

    public function place(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('checkout.php');
        if (!CartCsrf::valid($_POST['csrf_token'] ?? null)) {
            $_SESSION['checkout_error'] = 'Phiên gửi biểu mẫu không hợp lệ. Vui lòng thử lại.';
            $this->redirect('checkout.php');
        }
        $token = $_POST['submit_token'] ?? null;
        if (!is_string($token) || empty($_SESSION['checkout_submit_token'])
            || !hash_equals($_SESSION['checkout_submit_token'], $token)) {
            $_SESSION['checkout_error'] = 'Phiếu đặt hàng đã được xử lý hoặc hết hiệu lực. Vui lòng kiểm tra lại.';
            $this->redirect('checkout.php');
        }
        [$data, $errors] = $this->recipient($_POST);
        $_SESSION['checkout_old'] = $data;
        if ($errors) {
            $_SESSION['checkout_error'] = implode(' ', $errors);
            $this->redirect('checkout.php');
        }
        try {
            $userId = $this->userId();
            $orderId = (new CheckoutService($this->model()))->place($_SESSION['cart'] ?? [], $data, $userId);
        } catch (\DomainException $exception) {
            $_SESSION['checkout_error'] = $exception->getMessage();
            $this->redirect('checkout.php');
        } catch (\Throwable $exception) {
            $_SESSION['checkout_error'] = $this->databaseError($exception);
            $this->redirect('checkout.php');
        }
        // Only after a successful commit; no session unlock before consuming the token.
        unset($_SESSION['cart'], $_SESSION['checkout_old'], $_SESSION['checkout_error'], $_SESSION['checkout_submit_token']);
        $_SESSION['completed_order_id'] = $orderId;
        $_SESSION['completed_order_user_id'] = $userId;
        $this->redirect('order-success.php');
    }

    public function success(): void
    {
        $orderId = CartService::integer($_SESSION['completed_order_id'] ?? null);
        $ownerId = $_SESSION['completed_order_user_id'] ?? null;
        unset($_SESSION['completed_order_id'], $_SESSION['completed_order_user_id']);
        if (!$orderId) $this->redirect('products.php');
        $canViewOrder = $ownerId !== null && $ownerId === ($_SESSION['user']['id'] ?? null);
        View::storefront('storefront/checkout/success', compact('orderId', 'canViewOrder'));
    }
}