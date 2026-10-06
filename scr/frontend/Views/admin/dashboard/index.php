<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}
?>
<?php if ($error): ?>
    <div class="alert alert-danger my-3" role="alert"><?= product_escape($error) ?></div>
<?php else: ?>
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
            <?php if (!$latestOrders): ?>
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
                            <?php foreach ($latestOrders as $order): ?>
                                <?php
                                $status = $order['status'];
                                $label = $statusLabels[$status] ?? $status;
                                $class = $statusClasses[$status]
                                    ?? 'bg-secondary';
                                ?>

                                <tr>
                                    <td>#<?= (int) $order['id'] ?><br><a class="btn btn-sm btn-outline-primary mt-1" href="order-detail.php?id=<?= (int) $order['id'] ?>">Xem chi tiết</a></td>

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
                                        <span class="badge <?= product_escape($class) ?>">
                                            <?= htmlspecialchars(
                                                $label,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
