<?php
namespace MotoParts\App\Controllers\Admin;

use MotoParts\App\Core\View;
use MotoParts\App\Models\Dashboard;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class DashboardController
{
    private Dashboard $dashboard;

    public function __construct()
    {
        // Existing helper checks auth.php before business queries or HTML.
        global $conn, $baseUrl;
        require_once dirname(__DIR__, 3) . '/admin/includes/order-status.php';
        $this->dashboard = new Dashboard($conn);
    }

    public function index(): void
    {
        $stats = [];
        $latestOrders = [];
        $error = '';
        try {
            $stats = $this->dashboard->statistics();
            $latestOrders = $this->dashboard->latestOrders();
        } catch (\Throwable $e) {
            http_response_code(503);
            $error = 'Không thể tải tổng quan. Vui lòng thử lại sau.';
        }
        $statusLabels = order_status_labels();
        $statusClasses = order_status_classes();
        $pageTitle = 'Tổng quan';
        View::admin('admin/dashboard/index', compact('stats', 'latestOrders', 'error', 'statusLabels', 'statusClasses', 'pageTitle'));
    }
}