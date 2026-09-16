<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user'])) {
    $_SESSION['login_error'] =
        'Vui lòng đăng nhập để xem đơn hàng.';

    header('Location: login.php');
    exit;
}

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$userId = (int) $_SESSION['user']['id'];

if (!$orderId || $orderId < 1) {
    http_response_code(404);
    $order = null;
} else {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, fullname, phone, address, note,
                total, status, created_at
         FROM orders
         WHERE id = ? AND user_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, 'ii', $orderId, $userId);
    mysqli_stmt_execute($stmt);

    $order = mysqli_fetch_assoc(
        mysqli_stmt_get_result($stmt)
    );
}

$orderDetails = null;

if ($order) {
    $detailStmt = mysqli_prepare(
        $conn,
        "SELECT od.product_id, od.quantity, od.price,
                p.name, p.image
         FROM order_details od
         INNER JOIN products p ON p.id = od.product_id
         WHERE od.order_id = ?
         ORDER BY od.id ASC"
    );

    mysqli_stmt_bind_param($detailStmt, 'i', $orderId);
    mysqli_stmt_execute($detailStmt);

    $orderDetails = mysqli_stmt_get_result($detailStmt);
} else {
    http_response_code(404);
}

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
    <?php if (!$order): ?>
        <div class="alert alert-danger">
            Không tìm thấy đơn hàng hoặc bạn không có quyền xem đơn này.
        </div>

        <a href="my-orders.php" class="btn btn-dark">
            Quay lại đơn hàng
        </a>
    <?php else: ?>
        <?php
        $status = $order['status'];
        $statusLabel = $statusLabels[$status] ?? $status;
        $statusClass = $statusClasses[$status] ?? 'bg-secondary';
        ?>

        <div class="d-flex justify-content-between
                    align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h1 class="mb-1">
                    Đơn hàng #<?= (int) $order['id'] ?>
                </h1>

                <span class="text-muted">
                    Đặt lúc:
                    <?= date(
                        'd/m/Y H:i',
                        strtotime($order['created_at'])
                    ) ?>
                </span>
            </div>

            <span class="badge <?= $statusClass ?> fs-6">
                <?= htmlspecialchars(
                    $statusLabel,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <strong>Sản phẩm trong đơn hàng</strong>
                    </div>

                    <div class="card-body">
                        <?php while (
                            $item = mysqli_fetch_assoc($orderDetails)
                        ): ?>
                            <?php
                            $subtotal =
                                (float) $item['price']
                                * (int) $item['quantity'];
                            ?>

                            <div class="d-flex align-items-center
                                        justify-content-between
                                        border-bottom py-3 gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <img
                                        src="../assets/images/products/<?= rawurlencode(basename($item['image'])) ?>"
                                        alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        width="90"
                                        height="90"
                                        style="object-fit: contain;">

                                    <div>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $item['name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>

                                        <div class="text-muted">
                                            <?= number_format(
                                                (float) $item['price'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?> ₫
                                            ×
                                            <?= (int) $item['quantity'] ?>
                                        </div>
                                    </div>
                                </div>

                                <strong>
                                    <?= number_format(
                                        $subtotal,
                                        0,
                                        ',',
                                        '.'
                                    ) ?> ₫
                                </strong>
                            </div>
                        <?php endwhile; ?>

                        <div class="d-flex justify-content-between pt-4">
                            <strong class="fs-5">Tổng cộng</strong>

                            <strong class="fs-5 text-danger">
                                <?= number_format(
                                    (float) $order['total'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> ₫
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <strong>Thông tin nhận hàng</strong>
                    </div>

                    <div class="card-body">
                        <p>
                            <strong>Người nhận:</strong><br>
                            <?= htmlspecialchars(
                                $order['fullname'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                        <p>
                            <strong>Số điện thoại:</strong><br>
                            <?= htmlspecialchars(
                                $order['phone'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                        <p>
                            <strong>Địa chỉ:</strong><br>
                            <?= nl2br(htmlspecialchars(
                                $order['address'],
                                ENT_QUOTES,
                                'UTF-8'
                            )) ?>
                        </p>

                        <?php if (!empty($order['note'])): ?>
                            <p class="mb-0">
                                <strong>Ghi chú:</strong><br>
                                <?= nl2br(htmlspecialchars(
                                    $order['note'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <a href="my-orders.php"
           class="btn btn-outline-dark mt-4">
            Quay lại danh sách đơn hàng
        </a>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>