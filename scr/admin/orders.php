<?php
require_once __DIR__ . '/includes/order-status.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = $_SESSION['admin_success'] ?? '';
$error = $_SESSION['admin_error'] ?? '';

unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$orders = mysqli_query(
    $conn,
    "SELECT id, fullname, phone, total, status, created_at
     FROM orders
     ORDER BY id DESC"
);

$statusLabels = order_status_labels();

$transitions = order_transitions();
?>

<?php
$pageTitle = 'Quản lý đơn hàng';
include __DIR__ . '/includes/header.php';
?>

<div class="py-3">

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (mysqli_num_rows($orders) === 0): ?>
        <div class="alert alert-info">
            Chưa có đơn hàng.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Mã đơn</th>
                        <th>Khách hàng</th>
                        <th>Ngày đặt</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Cập nhật</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                        <?php
                        $status = $order['status'];
                        $allowed = $transitions[$status] ?? [];
                        ?>

                        <tr>
                            <td>#<?= (int) $order['id'] ?><br><a class="btn btn-sm btn-outline-primary mt-1" href="order-detail.php?id=<?= (int) $order['id'] ?>">Xem chi tiết</a></td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $order['fullname'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <div class="text-muted">
                                    <?= htmlspecialchars(
                                        $order['phone'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>
                            </td>

                            <td>
                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime($order['created_at'])
                                ) ?>
                            </td>

                            <td class="fw-bold">
                                <?= number_format(
                                    (float) $order['total'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> ₫
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $statusLabels[$status] ?? $status,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?php if ($allowed): ?>
                                    <form
                                        action="update-order.php"
                                        method="post"
                                        class="d-flex gap-2"
                                        onsubmit="return confirm('Xác nhận thay đổi trạng thái đơn hàng?');">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= htmlspecialchars(
                                                   $_SESSION['csrf_token'],
                                                   ENT_QUOTES,
                                                   'UTF-8'
                                               ) ?>">

                                        <input type="hidden"
                                               name="order_id"
                                               value="<?= (int) $order['id'] ?>">

                                        <select name="status"
                                                class="form-select"
                                                required>
                                            <option value="">
                                                Chọn trạng thái
                                            </option>

                                            <?php foreach ($allowed as $next): ?>
                                                <option value="<?= $next ?>">
                                                    <?= $statusLabels[$next] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="submit"
                                                class="btn btn-dark">
                                            Lưu
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">
                                        Đã kết thúc
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>