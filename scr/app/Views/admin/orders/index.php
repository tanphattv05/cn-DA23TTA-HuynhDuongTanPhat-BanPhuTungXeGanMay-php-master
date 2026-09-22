<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}
?>
<div class="py-3">
    <?php if ($success): ?><div class="alert alert-success" role="status"><?= product_escape($success) ?></div><?php endif; ?>
    <?php foreach (array_filter(array_merge([$error, $loadError], $filterErrors)) as $message): ?>
        <div class="alert alert-danger" role="alert"><?= product_escape($message) ?></div>
    <?php endforeach; ?>
    <form method="get" action="orders.php" class="row g-3 mb-3">
        <div class="col-12 col-lg-4">
            <label for="q" class="form-label">Mã đơn, tên hoặc điện thoại người nhận</label>
            <input class="form-control" id="q" name="q" maxlength="100" value="<?= product_escape($filters['q']) ?>" aria-describedby="search-help">
            <div id="search-help" class="form-text">Chỉ nhập số hoặc #mã để tìm đúng mã đơn; chuỗi khác tìm theo tên/điện thoại.</div>
        </div>
        <div class="col-12 col-sm-4 col-lg-2">
            <label for="filter-status" class="form-label">Trạng thái</label>
            <select class="form-select" id="filter-status" name="status">
                <option value="">Tất cả</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= product_escape($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= product_escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php foreach (['from' => 'Từ ngày', 'to' => 'Đến ngày'] as $key => $label): ?>
        <div class="col-6 col-sm-4 col-lg-2">
            <label for="<?= $key ?>" class="form-label"><?= $label ?></label>
            <input class="form-control" type="date" id="<?= $key ?>" name="<?= $key ?>" value="<?= product_escape($filters[$key]) ?>">
        </div>
        <?php endforeach; ?>
        <div class="col-12 col-lg-2 d-flex align-items-start align-self-end gap-2 flex-wrap">
            <button class="btn btn-dark" type="submit">Lọc</button>
            <a class="btn btn-outline-secondary" href="orders.php">Xóa bộ lọc</a>
        </div>
    </form>
    <?php if (!$filterErrors && !$loadError): ?>
    <p><?= $total ?> đơn hàng — Trang <?= $page ?>/<?= $pages ?></p>
    <?php if (!$orders): ?>
        <div class="alert alert-info">Không tìm thấy đơn hàng phù hợp.</div>
    <?php else: ?>
    <div class="table-responsive"><table class="table table-bordered align-middle">
        <thead class="table-dark"><tr><th>Mã đơn</th><th>Khách hàng</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Trạng thái</th><th>Cập nhật</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <?php $allowed = $transitions[$order['status']] ?? []; ?>
            <tr>
                <td>#<?= (int) $order['id'] ?><br><a class="btn btn-sm btn-outline-primary mt-1" href="order-detail.php?id=<?= (int) $order['id'] ?>">Xem chi tiết</a></td>
                <td><strong><?= product_escape($order['fullname']) ?></strong><div class="text-muted"><?= product_escape($order['phone']) ?></div></td>
                <td><?= product_escape(customer_date($order['created_at'])) ?></td>
                <td class="fw-bold"><?= number_format((float) $order['total'], 0, ',', '.') ?> ₫</td>
                <td><?= product_escape($statusLabels[$order['status']] ?? 'Không xác định') ?></td>
                <td>
                    <?php if ($allowed): ?>
                    <form action="update-order.php" method="post" class="d-flex gap-2" onsubmit="return confirm('Xác nhận thay đổi trạng thái đơn hàng?');">
                        <input type="hidden" name="csrf_token" value="<?= product_escape($csrfToken) ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <input type="hidden" name="return_to" value="list">
                        <?php foreach ($returnContext as $key => $value): ?>
                            <input type="hidden" name="list_context[<?= product_escape($key) ?>]" value="<?= product_escape($value) ?>">
                        <?php endforeach; ?>
                        <select name="status" class="form-select" aria-label="Trạng thái tiếp theo cho đơn #<?= (int) $order['id'] ?>" required>
                            <option value="">Chọn trạng thái</option>
                            <?php foreach ($allowed as $next): ?>
                                <option value="<?= product_escape($next) ?>"><?= product_escape($statusLabels[$next]) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-dark" type="submit">Lưu</button>
                    </form>
                    <?php else: ?><span class="text-muted">Đã kết thúc</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
    <?php customer_pagination('orders.php', $filters, $page, $pages); ?>
    <?php endif; ?>
</div>
