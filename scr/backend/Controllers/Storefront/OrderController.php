<?php
namespace MotoParts\App\Controllers\Storefront;

use MotoParts\App\Core\OrderStatus;
use MotoParts\App\Core\View;
use MotoParts\App\Middleware\Authenticate;
use MotoParts\App\Models\StorefrontOrder;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class OrderController
{
    private int $userId;

    public function __construct()
    {
        ini_set('display_errors', '0');
        $this->userId = Authenticate::requireUser()['id'];
    }

    private function model(): StorefrontOrder
    {
        return new StorefrontOrder(Authenticate::connection());
    }

    private function render(string $view, array $data): void
    {
        $data['statusLabels'] = OrderStatus::labels();
        $data['statusClasses'] = OrderStatus::classes();
        View::storefront('storefront/orders/' . $view, $data);
    }

    private function error(\Throwable $exception): string
    {
        http_response_code(503);
        error_log('MotoParts storefront orders: ' . get_class($exception) . ' code=' . (int) $exception->getCode());
        return 'Không thể tải đơn hàng. Vui lòng thử lại sau.';
    }

    public function index(): void
    {
        $orders = [];
        $error = '';
        try { $orders = $this->model()->forCustomer($this->userId); }
        catch (\Throwable $exception) { $error = $this->error($exception); }
        $this->render('index', compact('orders', 'error'));
    }

    public function detail(): void
    {
        $input = $_GET['id'] ?? null;
        $id = is_string($input) ? filter_var($input, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) : false;
        $order = null;
        $orderDetails = [];
        $error = '';
        try {
            if ($id !== false) {
                $model = $this->model();
                $order = $model->findOwned($id, $this->userId);
                if ($order) {
                    $orderDetails = $model->items($id, $this->userId);
                    foreach ($orderDetails as &$item) {
                        $item['name'] = $item['name'] ?? 'Sản phẩm không còn tồn tại';
                        $filename = basename((string) ($item['image'] ?? ''));
                        $item['imageUrl'] = $filename !== '' && is_file(dirname(__DIR__, 3) . '/assets/images/products/' . $filename)
                            ? '../assets/images/products/' . rawurlencode($filename) : null;
                    }
                    unset($item);
                }
            }
            if (!$order) http_response_code(404);
        } catch (\Throwable $exception) {
            $order = null;
            $orderDetails = [];
            $error = $this->error($exception);
        }
        $this->render('detail', compact('order', 'orderDetails', 'error'));
    }
}
