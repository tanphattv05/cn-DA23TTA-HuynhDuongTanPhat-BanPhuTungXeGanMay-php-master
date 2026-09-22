<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}
?>
<div class="py-3">
    <a class="btn btn-outline-secondary mb-3" href="orders.php">Quay lại danh sách đơn</a>
    <?php if ($success): ?><div class="alert alert-success" role="status"><?= product_escape($success) ?></div><?php endif; ?>
    <?php if ($actionError): ?><div class="alert alert-danger" role="alert"><?= product_escape($actionError) ?></div><?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= product_escape($error) ?></div>
    <?php else: ?>
    <div class="card mb-3"><div class="card-body">
        <h2 class="fs-5">Đơn hàng #<?= (int) $order['id'] ?></h2>
        <dl class="row mb-0">
            <dt class="col-sm-3">Ngày đặt</dt><dd class="col-sm-9"><?= product_escape(date('d/m/Y H:i', strtotime($order['created_at']))) ?></dd>
            <dt class="col-sm-3">Trạng thái</dt><dd class="col-sm-9"><?= product_escape($statusLabels[$order['status']] ?? 'Không xác định') ?></dd>
            <dt class="col-sm-3">Tổng tiền đã lưu</dt><dd class="col-sm-9"><?= product_escape(order_money($order['total'])) ?></dd>
            <dt class="col-sm-3">Tài khoản đặt hàng</dt>
            <dd class="col-sm-9">
                <?php if ($order['customer_id'] !== null): ?>
                    <a href="customer-detail.php?id=<?= (int) $order['customer_id'] ?>">Khách hàng #<?= (int) $order['customer_id'] ?></a>
                <?php elseif ($order['user_id'] === null): ?>
                    Khách vãng lai hoặc đơn không còn liên kết tài khoản.
                <?php else: ?>
                    Không còn tài khoản khách hàng để liên kết.
                <?php endif; ?>
            </dd>
        </dl>
    </div></div>
    <div class="card mb-3"><div class="card-body">
        <h2 class="fs-5">Thông tin người nhận trên đơn</h2>
        <dl class="row mb-0">
            <dt class="col-sm-3">Họ tên</dt><dd class="col-sm-9"><?= product_escape($order['fullname']) ?></dd>
            <dt class="col-sm-3">Điện thoại</dt><dd class="col-sm-9"><?= product_escape($order['phone']) ?></dd>
            <dt class="col-sm-3">Địa chỉ</dt><dd class="col-sm-9"><?= nl2br(product_escape($order['address'])) ?></dd>
            <dt class="col-sm-3">Ghi chú</dt><dd class="col-sm-9"><?= nl2br(product_escape($order['note'] ?? 'Không có ghi chú.')) ?></dd>
        </dl>
    </div></div>
    <h2 class="fs-5">Sản phẩm trong đơn</h2>
    <p class="text-muted">Đơn giá là giá đã lưu khi đặt hàng. Tên và ảnh hiển thị theo sản phẩm hiện còn trong hệ thống.</p>
    <div class="table-responsive"><table class="table table-bordered align-middle">
        <thead><tr><th>Ảnh</th><th>Sản phẩm</th><th>Đơn giá đã lưu</th><th>Số lượng</th><th>Thành tiền</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <?php
            $image = $item['image'] ? basename($item['image']) : '';
            $hasImage = $image !== '' && is_file(dirname(__DIR__, 4) . '/assets/images/products/' . $image);
            ?>
            <tr>
                <td><?php if ($hasImage): ?><img src="<?= product_escape(product_image_url($image)) ?>" alt="<?= product_escape($item['name'] ?? 'Sản phẩm') ?>" width="72" height="72" style="object-fit:contain"><?php else: ?>Không có ảnh<?php endif; ?></td>
                <td><?= product_escape($item['name'] ?? ('Sản phẩm không còn trong hệ thống (#' . (int) $item['product_id'] . ')')) ?></td>
                <td><?= product_escape(order_money($item['price'])) ?></td>
                <td><?= (int) $item['quantity'] ?></td>
                <td><?= product_escape(order_money($item['line_total'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="5">Đơn hàng chưa có dòng sản phẩm.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <p><strong>Tổng các dòng sản phẩm: <?= product_escape(order_money($totals['detail_total'])) ?></strong></p>
    <?php if ((int) $totals['differs']): ?>
        <div class="alert alert-warning" role="alert">Tổng các dòng sản phẩm khác tổng tiền đã lưu trên đơn. Vui lòng đối chiếu hồ sơ đơn hàng; hệ thống giữ nguyên số tiền đã lưu.</div>
    <?php endif; ?>
    <?php if ($allowed): ?>
    <div class="card"><div class="card-body">
        <h2 class="fs-5">Cập nhật trạng thái</h2>
        <form action="update-order.php" method="post" class="d-flex flex-wrap gap-2" onsubmit="return confirm('Xác nhận thay đổi trạng thái đơn hàng?');">
            <input type="hidden" name="csrf_token" value="<?= product_escape($csrfToken) ?>">
            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
            <input type="hidden" name="return_to" value="detail">
            <label for="status" class="visually-hidden">Trạng thái tiếp theo</label>
            <select name="status" id="status" class="form-select w-auto" required>
                <option value="">Chọn trạng thái</option>
                <?php foreach ($allowed as $next): ?>
                    <option value="<?= product_escape($next) ?>"><?= product_escape($statusLabels[$next]) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Lưu trạng thái</button>
        </form>
    </div></div>
    <?php endif; ?>
    <?php endif; ?>
</div>
