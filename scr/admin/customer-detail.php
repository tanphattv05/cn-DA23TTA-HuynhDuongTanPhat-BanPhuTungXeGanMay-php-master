<?php
require_once __DIR__ . '/includes/customer-view.php';
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
        $customer = product_query(
            "SELECT id, fullname, email, phone, created_at FROM users WHERE id = ? AND role = 'customer'",
            'i', [$id]
        )->get_result()->fetch_assoc();
    }
    if (!$customer) {
        http_response_code(404);
        $notFound = true;
        $error = 'Không tìm thấy khách hàng.';
    } else {
        $stats = product_query(
            "SELECT COUNT(*) AS order_count,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_count,
                    COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END), 0) AS completed_total
             FROM orders WHERE user_id = ?", 'i', [$id]
        )->get_result()->fetch_assoc();
        $pages = max(1, (int) ceil((int) $stats['order_count'] / 10));
        $page = min($page, $pages);
        $orders = product_query(
            'SELECT id, created_at, fullname, total, status FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?',
            'iii', [$id, 10, ($page - 1) * 10]
        )->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    http_response_code(503);
    $error = 'Không thể tải thông tin khách hàng. Vui lòng thử lại sau.';
}
$statusLabels = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'shipping' => 'Đang giao hàng',
    'completed' => 'Đã hoàn thành',
    'cancelled' => 'Đã hủy'
];
$pageTitle = $notFound ? '404 — Không tìm thấy khách hàng' : 'Chi tiết khách hàng';
include __DIR__ . '/includes/header.php';
?>
<div class="py-3">
    <a class="btn btn-outline-secondary mb-3" href="customers.php">Quay về danh sách khách hàng</a>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= product_escape($error) ?></div>
    <?php else: ?>
    <div class="card mb-3"><div class="card-body">
        <h2 class="fs-5">Thông tin khách hàng #<?= (int) $customer['id'] ?></h2>
        <dl class="row mb-0">
            <dt class="col-sm-3">Họ tên</dt><dd class="col-sm-9"><?= product_escape($customer['fullname']) ?></dd>
            <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= product_escape($customer['email']) ?></dd>
            <dt class="col-sm-3">Điện thoại</dt><dd class="col-sm-9"><?= product_escape($customer['phone'] ?: '—') ?></dd>
            <dt class="col-sm-3">Ngày đăng ký</dt><dd class="col-sm-9"><?= product_escape(customer_date($customer['created_at'])) ?></dd>
        </dl>
    </div></div>
    <div class="card mb-3"><div class="card-body">
        <h2 class="fs-5">Thống kê đơn hàng</h2>
        <dl class="row mb-0">
            <dt class="col-sm-3">Tổng số đơn</dt><dd class="col-sm-9"><?= (int) $stats['order_count'] ?></dd>
            <dt class="col-sm-3">Đã hoàn thành</dt><dd class="col-sm-9"><?= (int) $stats['completed_count'] ?></dd>
            <dt class="col-sm-3">Đã hủy</dt><dd class="col-sm-9"><?= (int) $stats['cancelled_count'] ?></dd>
            <dt class="col-sm-3">Tổng tiền hoàn thành</dt><dd class="col-sm-9"><?= number_format((float) $stats['completed_total'], 2, ',', '.') ?> ₫</dd>
        </dl>
    </div></div>
    <h2 class="fs-5">Lịch sử đơn hàng</h2>
    <?php if (!$orders): ?>
        <div class="alert alert-info">Khách hàng chưa có đơn hàng.</div>
    <?php else: ?>
    <p>Trang <?= $page ?>/<?= $pages ?></p>
    <div class="table-responsive"><table class="table table-bordered align-middle">
        <thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Người nhận</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td>#<?= (int) $order['id'] ?></td>
                <td><?= product_escape(customer_date($order['created_at'])) ?></td>
                <td><?= product_escape($order['fullname']) ?></td>
                <td><?= number_format((float) $order['total'], 2, ',', '.') ?> ₫</td>
                <td><?= product_escape($statusLabels[$order['status']] ?? 'Không xác định') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php customer_pagination('customer-detail.php', ['id' => $id], $page, $pages); ?>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
