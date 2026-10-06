<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } ?>
<div class="container py-5">
    <h1 class="mb-4">Đơn hàng của tôi</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (!$orders): ?>
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
                    <?php foreach ($orders as $order): ?>
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
                                <span class="badge <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
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
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
