<?php
namespace MotoParts\App\Controllers\Admin;

use MotoParts\App\Core\View;
use MotoParts\App\Models\Customer;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class CustomerController
{
    private Customer $customers;

    public function __construct()
    {
        // Existing helper invokes auth.php before any action or HTML.
        global $conn, $baseUrl;
        require_once dirname(__DIR__, 3) . '/admin/includes/customer-view.php';
        require_once dirname(__DIR__, 3) . '/admin/includes/order-status.php';
        $this->customers = new Customer($conn);
    }

    public function index(): void
    {
        $q = mb_substr(product_text($_GET, 'q'), 0, 100, 'UTF-8');
        $page = customer_page($_GET);
        $rows = [];
        $total = 0;
        $pages = 1;
        $error = '';
        try {
            $total = $this->customers->count($q);
            $pages = max(1, (int) ceil($total / 10));
            $page = min($page, $pages);
            $rows = $this->customers->paginate($q, 10, ($page - 1) * 10);
        } catch (\Throwable $e) {
            http_response_code(503);
            $error = 'Không thể tải danh sách khách hàng. Vui lòng thử lại sau.';
        }
        $pageTitle = 'Quản lý khách hàng';
        View::admin('admin/customers/index', compact('q', 'page', 'rows', 'total', 'pages', 'error', 'pageTitle'));
    }

    public function detail(): void
    {
        $idInput = product_text($_GET, 'id');
        $id = preg_match('/\A[1-9][0-9]{0,9}\z/', $idInput)
            ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]])
            : false;
        $page = customer_page($_GET);
        $pages = 1;
        $customer = null;
        $orders = [];
        $stats = [];
        $error = '';
        $notFound = false;
        try {
            if ($id) {
                $customer = $this->customers->find($id);
            }
            if (!$customer) {
                http_response_code(404);
                $notFound = true;
                $error = 'Không tìm thấy khách hàng.';
            } else {
                $stats = $this->customers->orderStatistics($id);
                $pages = max(1, (int) ceil((int) $stats['order_count'] / 10));
                $page = min($page, $pages);
                $orders = $this->customers->orderHistory($id, 10, ($page - 1) * 10);
            }
        } catch (\Throwable $e) {
            http_response_code(503);
            $error = 'Không thể tải thông tin khách hàng. Vui lòng thử lại sau.';
        }
        $statusLabels = order_status_labels();
        $pageTitle = $notFound ? '404 — Không tìm thấy khách hàng' : 'Chi tiết khách hàng';
        View::admin('admin/customers/detail', compact('id', 'page', 'pages', 'customer', 'orders', 'stats', 'error', 'statusLabels', 'pageTitle'));
    }
}
