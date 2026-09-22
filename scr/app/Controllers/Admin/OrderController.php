<?php
namespace MotoParts\App\Controllers\Admin;

use MotoParts\App\Core\View;
use MotoParts\App\Models\Order;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class OrderController
{
    private Order $orders;

    public function __construct()
    {
        // Existing bootstrap checks auth.php before any action or HTML.
        global $conn, $baseUrl;
        require_once dirname(__DIR__, 3) . '/admin/includes/order-filters.php';
        $this->orders = new Order($conn);
    }

    public function index(): void
    {
        [$filters, $filterErrors] = order_list_filters($_GET);
        $page = customer_page($_GET);
        $pages = 1;
        $total = 0;
        $orders = [];
        $success = $_SESSION['admin_success'] ?? '';
        $error = $_SESSION['admin_error'] ?? '';
        unset($_SESSION['admin_success'], $_SESSION['admin_error']);
        $loadError = '';
        if ($filterErrors) {
            http_response_code(400);
        } else {
            try {
                $total = $this->orders->count($filters);
                $pages = max(1, (int) ceil($total / 10));
                $page = min($page, $pages);
                $orders = $this->orders->paginate($filters, 10, ($page - 1) * 10);
            } catch (\Throwable $e) {
                http_response_code(503);
                $loadError = 'Không thể tải danh sách đơn hàng. Vui lòng thử lại sau.';
            }
        }
        $statusLabels = order_status_labels();
        $transitions = order_transitions();
        $returnContext = array_merge($filters, ['page' => $page]);
        $csrfToken = $_SESSION['csrf_token'];
        $pageTitle = 'Quản lý đơn hàng';
        View::admin('admin/orders/index', compact(
            'filters', 'filterErrors', 'page', 'pages', 'total', 'orders', 'success',
            'error', 'loadError', 'statusLabels', 'transitions', 'returnContext', 'csrfToken', 'pageTitle'
        ));
    }

    public function detail(): void
    {
        $idText = product_text($_GET, 'id');
        $id = preg_match('/\A[1-9][0-9]{0,9}\z/', $idText)
            ? filter_var($idText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]])
            : false;
        $order = null;
        $items = [];
        $totals = [];
        $error = '';
        $notFound = false;
        try {
            if ($id) {
                $order = $this->orders->find($id);
            }
            if (!$order) {
                http_response_code(404);
                $notFound = true;
                $error = 'Không tìm thấy đơn hàng.';
            } else {
                $items = $this->orders->items($id);
                $totals = $this->orders->totals($id, $order['total']);
            }
        } catch (\Throwable $e) {
            http_response_code(503);
            $error = 'Không thể tải đơn hàng. Vui lòng thử lại sau.';
        }
        $success = $_SESSION['admin_success'] ?? '';
        $actionError = $_SESSION['admin_error'] ?? '';
        unset($_SESSION['admin_success'], $_SESSION['admin_error']);
        $statusLabels = order_status_labels();
        $allowed = $order ? (order_transitions()[$order['status']] ?? []) : [];
        $csrfToken = $_SESSION['csrf_token'];
        $pageTitle = $notFound ? '404 — Không tìm thấy đơn hàng' : 'Chi tiết đơn hàng';
        View::admin('admin/orders/detail', compact(
            'order', 'items', 'totals', 'error', 'success', 'actionError', 'statusLabels', 'allowed', 'csrfToken', 'pageTitle'
        ));
    }

    public function update(): void
    {
        $returnPath = 'orders.php';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            product_redirect($returnPath);
        }
        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 2147483647]
        ]);
        // Accept fixed markers and validated filter fields, never a supplied URL.
        if ($orderId && ($_POST['return_to'] ?? '') === 'detail') {
            $returnPath = 'order-detail.php?id=' . $orderId;
        } elseif (($_POST['return_to'] ?? '') === 'list') {
            $returnPath = order_list_return_path($_POST['list_context'] ?? null);
        }
        unset($_SESSION['admin_error'], $_SESSION['admin_success']);
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $_SESSION['admin_error'] = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
            product_redirect($returnPath);
        }
        $newStatus = $_POST['status'] ?? '';
        if (!$orderId || $orderId < 1 || !is_string($newStatus)) {
            $_SESSION['admin_error'] = 'Dữ liệu không hợp lệ.';
            product_redirect($returnPath);
        }
        try {
            $this->orders->begin();
            // Re-read the current status under the lock, including repeated cancellation.
            $order = $this->orders->lock($orderId);
            if (!$order) {
                throw new \RuntimeException('Không tìm thấy đơn hàng.');
            }
            $allowed = order_transitions()[$order['status']] ?? [];
            if (!in_array($newStatus, $allowed, true)) {
                throw new \RuntimeException('Không được chuyển đơn hàng sang trạng thái này.');
            }
            if ($newStatus === 'cancelled') {
                $this->orders->restoreStock($orderId);
            }
            $this->orders->updateStatus($orderId, $newStatus);
            $this->orders->commit();
            $_SESSION['admin_success'] = 'Đã cập nhật đơn hàng #' . $orderId . '.';
        } catch (\Throwable $error) {
            try { $this->orders->rollback(); } catch (\Throwable $rollbackError) {}
            if ($error instanceof \mysqli_sql_exception) {
                $_SESSION['admin_error'] = 'Có lỗi database. Đơn hàng chưa được cập nhật.';
            } else {
                $_SESSION['admin_error'] = $error instanceof \RuntimeException
                    ? $error->getMessage() : 'Không thể cập nhật đơn hàng. Vui lòng thử lại sau.';
            }
        }
        product_redirect($returnPath);
    }
}