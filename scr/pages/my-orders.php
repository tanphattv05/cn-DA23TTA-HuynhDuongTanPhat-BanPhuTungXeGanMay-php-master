<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user'])) {
    $_SESSION['login_error'] =
        'Vui lòng đăng nhập để xem đơn hàng.';

    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user']['id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, fullname, phone, address, total, status, created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY id DESC"
);

mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);

$orders = mysqli_stmt_get_result($stmt);

$statusLabels = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'shipping' => 'Đang giao hàng',
    'completed' => 'Đã hoàn thành',
    'cancelled' => 'Đã hủy'
];

$statusClasses = [
    'pending' => 'bg-warning text-dark',
    'confirmed' => 'bg-primary',
    'shipping' => 'bg-info text-dark',
    'completed' => 'bg-success',
    'cancelled' => 'bg-danger'
];
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container py-5">
    <h1 class="mb-4">Đơn hàng của tôi</h1>

    <?php if (mysqli_num_rows($orders) === 0): ?>
        <div class="alert alert-info">
            Bạn chưa có đơn hàng nào.
        </div>

        <a href="products.php" class="btn btn-dark">
            Mua sắm ngay
        </a>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Mã đơn</th>
                        <th>Ngày đặt</th>
                        <th>Người nhận</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                        <?php
                        $status = $order['status'];

                        $statusLabel =
                            $statusLabels[$status] ?? $status;

                        $statusClass =
                            $statusClasses[$status] ?? 'bg-secondary';
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    #<?= (int) $order['id'] ?>
                                </strong>
                            </td>

                            <td>
                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime($order['created_at'])
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $order['fullname'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td class="fw-bold text-danger">
                                <?= number_format(
                                    (float) $order['total'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> ₫
                            </td>

                            <td>
                                <span class="badge <?= $statusClass ?>">
                                    <?= htmlspecialchars(
                                        $statusLabel,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    href="order-detail.php?id=<?= (int) $order['id'] ?>"
                                    class="btn btn-outline-dark btn-sm">
                                    Xem chi tiết
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>