<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}
?>
<div class="py-3">
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= product_escape($error) ?></div><?php endif; ?>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-8">
            <label for="q" class="form-label">Họ tên, email hoặc điện thoại</label>
            <input class="form-control" id="q" name="q" maxlength="100" value="<?= product_escape($q) ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end gap-2">
            <button class="btn btn-dark" type="submit">Tìm kiếm</button>
            <a class="btn btn-outline-secondary" href="customers.php">Bỏ tìm kiếm</a>
        </div>
    </form>
    <?php if (!$error): ?>
    <p><?= $total ?> khách hàng — Trang <?= $page ?>/<?= $pages ?></p>
    <div class="table-responsive"><table class="table table-bordered align-middle">
        <thead><tr><th>Mã</th><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Ngày đăng ký</th><th>Tổng số đơn</th><th>Tổng tiền hoàn thành</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $customer): ?>
            <tr>
                <td><?= (int) $customer['id'] ?></td>
                <td><?= product_escape($customer['fullname']) ?></td>
                <td><?= product_escape($customer['email']) ?></td>
                <td><?= product_escape($customer['phone'] ?: '—') ?></td>
                <td><?= product_escape(customer_date($customer['created_at'])) ?></td>
                <td><?= (int) $customer['order_count'] ?></td>
                <td><?= number_format((float) $customer['completed_total'], 2, ',', '.') ?> ₫</td>
                <td><a class="btn btn-sm btn-outline-primary" href="customer-detail.php?id=<?= (int) $customer['id'] ?>">Xem chi tiết</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8">Không tìm thấy khách hàng.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php customer_pagination('customers.php', ['q' => $q], $page, $pages); ?>
    <?php endif; ?>
</div>

