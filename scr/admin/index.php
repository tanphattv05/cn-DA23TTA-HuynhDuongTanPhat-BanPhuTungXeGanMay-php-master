<?php
require_once __DIR__ . '/auth.php';

$sql = "
    SELECT
        (SELECT COUNT(*) FROM products) AS product_count,
        (SELECT COUNT(*) FROM categories) AS category_count,
        (SELECT COUNT(*) FROM users
         WHERE role = 'customer') AS customer_count,
        (SELECT COUNT(*) FROM orders) AS order_count,
        (SELECT COUNT(*) FROM orders
         WHERE status = 'pending') AS pending_count,
        (SELECT COALESCE(SUM(total), 0) FROM orders
         WHERE status = 'completed') AS revenue
";

$stats = mysqli_fetch_assoc(mysqli_query($conn, $sql));

$latestOrders = mysqli_query(
    $conn,
    "SELECT id, fullname, total, status, created_at
     FROM orders
     ORDER BY id DESC
     LIMIT 10"
);

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

<?php
$pageTitle = 'Tổng quan';
include __DIR__ . '/includes/header.php';
?>

<div class="py-3">

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted">Sản phẩm</p>
                    <h2><?= (int) $stats['product_count'] ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted">Danh mục</p>
                    <h2><?= (int) $stats['category_count'] ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted">Khách hàng</p>
                    <h2><?= (int) $stats['customer_count'] ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted">Tổng đơn hàng</p>
                    <h2><?= (int) $stats['order_count'] ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted">Đơn chờ xác nhận</p>
                    <h2 class="text-warning">
                        <?= (int) $stats['pending_count'] ?>
                    </h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted">
                        Doanh thu đơn đã hoàn thành
                    </p>
                    <h2 class="text-success">
                        <?= number_format(
                            (float) $stats['revenue'],
                            0,
                            ',',
                            '.'
                        ) ?> ₫
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>10 đơn hàng gần nhất</strong>
        </div>

        <div class="card-body">
            <?php if (mysqli_num_rows($latestOrders) === 0): ?>
                <p class="mb-0">Chưa có đơn hàng.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Người nhận</th>
                                <th>Ngày đặt</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while (
                                $order = mysqli_fetch_assoc($latestOrders)
                            ): ?>
                                <?php
                                $status = $order['status'];
                                $label = $statusLabels[$status] ?? $status;
                                $class = $statusClasses[$status]
                                    ?? 'bg-secondary';
                                ?>

                                <tr>
                                    <td>#<?= (int) $order['id'] ?></td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $order['fullname'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= date(
                                            'd/m/Y H:i',
                                            strtotime($order['created_at'])
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            (float) $order['total'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?> ₫
                                    </td>

                                    <td>
                                        <span class="badge <?= $class ?>">
                                            <?= htmlspecialchars(
                                                $label,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>