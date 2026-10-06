<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } ?>
<div class="container py-5">
    <?php if (!$order): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error !== '' ? $error : 'Không tìm thấy đơn hàng hoặc bạn không có quyền xem đơn này.', ENT_QUOTES, 'UTF-8') ?>
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

            <span class="badge <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?> fs-6">
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
                        <?php foreach ($orderDetails as $item): ?>

                            <div class="d-flex align-items-center
                                        justify-content-between
                                        border-bottom py-3 gap-3 flex-wrap">
                                <div class="d-flex align-items-center gap-3">
                                    <?php if ($item['imageUrl'] !== null): ?>
                                    <img
                                        src="<?= htmlspecialchars($item['imageUrl'], ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        width="90"
                                        height="90"
                                        style="object-fit: contain;">
                                    <?php endif; ?>

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
                                        (float) $item['line_total'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?> ₫
                                </strong>
                            </div>
                        <?php endforeach; ?>

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
