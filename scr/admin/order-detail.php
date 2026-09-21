<?php
require_once __DIR__ . '/includes/order-status.php';
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
        $order = product_query(
            "SELECT o.id, o.user_id, o.fullname, o.phone, o.address, o.note, o.total, o.status, o.created_at,
                    u.id AS customer_id
             FROM orders o LEFT JOIN users u ON u.id = o.user_id AND u.role = 'customer'
             WHERE o.id = ?", 'i', [$id]
        )->get_result()->fetch_assoc();
    }
    if (!$order) {
        http_response_code(404);
        $notFound = true;
        $error = 'Không tìm thấy đơn hàng.';
    } else {
        $items = product_query(
            'SELECT od.product_id, od.quantity, od.price, od.price * od.quantity AS line_total,
                    p.name, p.image
             FROM order_details od LEFT JOIN products p ON p.id = od.product_id
             WHERE od.order_id = ? ORDER BY od.id', 'i', [$id]
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        $totals = product_query(
            'SELECT COALESCE(SUM(price * quantity), 0) AS detail_total,
                    COALESCE(SUM(price * quantity), 0) <> CAST(? AS DECIMAL(12,2)) AS differs
             FROM order_details WHERE order_id = ?', 'si', [$order['total'], $id]
        )->get_result()->fetch_assoc();
    }
} catch (Throwable $e) {
    http_response_code(503);
    $error = 'Không thể tải đơn hàng. Vui lòng thử lại sau.';
}
$success = $_SESSION['admin_success'] ?? '';
$actionError = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);
$statusLabels = order_status_labels();
$allowed = $order ? (order_transitions()[$order['status']] ?? []) : [];
$pageTitle = $notFound ? '404 — Không tìm thấy đơn hàng' : 'Chi tiết đơn hàng';
include __DIR__ . '/includes/header.php';
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
            $hasImage = $image !== '' && is_file(__DIR__ . '/../assets/images/products/' . $image);
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
            <input type="hidden" name="csrf_token" value="<?= product_escape($_SESSION['csrf_token']) ?>">
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
<?php include __DIR__ . '/includes/footer.php'; ?>
