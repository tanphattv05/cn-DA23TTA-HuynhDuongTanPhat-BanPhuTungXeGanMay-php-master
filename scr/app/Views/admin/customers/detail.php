<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}
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
                <td><a href="order-detail.php?id=<?= (int) $order['id'] ?>">#<?= (int) $order['id'] ?></a></td>
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

