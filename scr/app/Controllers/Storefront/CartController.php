<?php
namespace MotoParts\App\Controllers\Storefront;

use MotoParts\App\Core\CartCsrf;
use MotoParts\App\Core\View;
use MotoParts\App\Models\CartProduct;
use MotoParts\App\Services\CartService;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class CartController
{
    private CartService $cart;

    public function __construct()
    {
        ini_set('display_errors', '0');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->cart = new CartService($_SESSION);
    }

    private function model(): CartProduct
    {
        require dirname(__DIR__, 3) . '/config/database.php';
        return new CartProduct($conn);
    }

    private function databaseError(\Throwable $exception): string
    {
        error_log('MotoParts cart: ' . get_class($exception) . ' code=' . (int) $exception->getCode());
        return 'Không thể tải dữ liệu giỏ hàng. Vui lòng thử lại sau.';
    }

    private function redirect(string $message, string $type = 'danger'): void
    {
        $_SESSION['cart_flash'] = ['message' => $message, 'type' => $type];
        // Fixed local destination; no caller-supplied return URL.
        header('Location: ../pages/cart.php', true, 303);
        exit;
    }

    private function post(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ../pages/cart.php', true, 303);
            exit;
        }
        if (!CartCsrf::valid($_POST['csrf_token'] ?? null)) {
            $this->redirect('Phiên gửi biểu mẫu không hợp lệ. Vui lòng thử lại.');
        }
    }

    public function index(): void
    {
        $products = [];
        $total = 0;
        $error = '';
        $notice = '';
        $flash = $_SESSION['cart_flash'] ?? null;
        unset($_SESSION['cart_flash']);
        $csrfToken = CartCsrf::token();
        try {
            $ids = array_keys($this->cart->read());
            $rows = $ids ? $this->model()->findMany($ids) : [];
            [$products, $total, $changed] = $this->cart->reconcile($rows);
            if ($changed) $notice = 'Giỏ hàng đã được điều chỉnh theo tồn kho hiện tại; sản phẩm không còn hoặc hết hàng đã được loại bỏ.';
        } catch (\Throwable $exception) {
            http_response_code(503);
            $error = $this->databaseError($exception);
        }
        View::storefront('storefront/cart/index', compact('products', 'total', 'error', 'notice', 'flash', 'csrfToken'));
    }

    public function add(): void
    {
        $this->post();
        $id = CartService::integer($_POST['product_id'] ?? null);
        $quantity = CartService::integer($_POST['quantity'] ?? null);
        if ($id === null || $quantity === null) $this->redirect('Mã sản phẩm hoặc số lượng không hợp lệ.');
        try {
            $product = $this->model()->find($id);
            if (!$product) $this->redirect('Sản phẩm không còn tồn tại.');
            if ((int) $product['stock'] < 1) $this->redirect('Sản phẩm đã hết hàng.');
            $limited = $this->cart->add($product, $quantity);
        } catch (\Throwable $exception) {
            $this->redirect($this->databaseError($exception));
        }
        $this->redirect($limited ? 'Số lượng vượt tồn kho; đã giới hạn theo số hàng hiện có.' : 'Đã thêm sản phẩm vào giỏ hàng.', $limited ? 'warning' : 'success');
    }

    public function update(): void
    {
        $this->post();
        $input = $_POST['quantities'] ?? null;
        if (!is_array($input) || !$input) $this->redirect('Số lượng không hợp lệ.');
        $quantities = [];
        $current = $this->cart->read();
        foreach ($input as $key => $value) {
            $id = CartService::integer($key);
            $quantity = CartService::integer($value, true);
            if ($id === null || $quantity === null) $this->redirect('Mã sản phẩm hoặc số lượng không hợp lệ.');
            if (!isset($current[$id])) $this->redirect('Sản phẩm không có trong giỏ hàng.');
            $quantities[$id] = $quantity;
        }
        try {
            $products = $this->model()->findMany(array_keys($quantities));
            $this->cart->update($quantities, $products);
        } catch (\InvalidArgumentException $exception) {
            $this->redirect($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->redirect($this->databaseError($exception));
        }
        $this->redirect('Đã cập nhật giỏ hàng.', 'success');
    }

    public function remove(): void
    {
        $this->post();
        $id = CartService::integer($_POST['id'] ?? null);
        if ($id === null) $this->redirect('Mã sản phẩm không hợp lệ.');
        if (!$this->cart->remove($id)) $this->redirect('Sản phẩm không có trong giỏ hàng.');
        $this->redirect('Đã xóa sản phẩm khỏi giỏ hàng.', 'success');
    }
}