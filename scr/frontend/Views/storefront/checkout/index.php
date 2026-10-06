<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
?>
<div class="container py-5">
    <h1 class="mb-4">Thanh toán</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$unavailable): ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="mb-4">Thông tin nhận hàng</h4>

                    <form action="../actions/checkout.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="submit_token" value="<?= htmlspecialchars($submitToken, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label for="fullname" class="form-label">
                                Họ và tên
                            </label>

                            <input
                                type="text"
                                id="fullname"
                                name="fullname"
                                class="form-control"
                                maxlength="100"
                                value="<?= htmlspecialchars($old['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                required>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">
                                Số điện thoại
                            </label>

                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                class="form-control"
                                maxlength="20"
                                pattern="[0-9]{9,11}"
                                placeholder="Ví dụ: 0912345678"
                                value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                required>

                            <div class="form-text">
                                Nhập từ 9 đến 11 chữ số.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">
                                Địa chỉ nhận hàng
                            </label>

                            <textarea
                                id="address"
                                name="address"
                                class="form-control"
                                rows="4"
                                maxlength="500"
                                required><?= htmlspecialchars($old['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="note" class="form-label">
                                Ghi chú
                            </label>

                            <textarea
                                id="note"
                                name="note"
                                class="form-control"
                                rows="3"
                                maxlength="500"
                                placeholder="Không bắt buộc"><?= htmlspecialchars($old['note'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-danger">
                            Xác nhận đặt hàng
                        </button>

                        <a href="cart.php" class="btn btn-outline-dark">
                            Quay lại giỏ hàng
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="mb-4">Đơn hàng của bạn</h4>

                    <?php foreach ($products as $product): ?>
                        <div class="d-flex justify-content-between border-bottom py-3">
                            <div>
                                <strong>
                                    <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                                </strong>

                                <div class="text-muted">
                                    Số lượng: <?= (int) $product['quantity'] ?>
                                </div>
                            </div>

                            <span>
                                <?= number_format((float) $product['subtotal'], 0, ',', '.') ?> ₫
                            </span>
                        </div>
                    <?php endforeach; ?>

                    <div class="d-flex justify-content-between pt-3">
                        <strong class="fs-5">Tổng cộng</strong>

                        <strong class="text-danger fs-5">
                            <?= number_format($total, 0, ',', '.') ?> ₫
                        </strong>
                    </div>

                    <p class="text-muted mt-3 mb-0">
                        Phương thức thanh toán: Thanh toán khi nhận hàng.
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <a href="cart.php" class="btn btn-outline-dark">Quay lại giỏ hàng</a>
<?php endif; ?></div>
