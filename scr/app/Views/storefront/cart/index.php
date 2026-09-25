<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
?>
<div class="container py-5">
    <h1 class="mb-4">Giỏ hàng</h1>
    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars($flash['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($notice): ?><div class="alert alert-warning"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php else: ?>

    <?php if (empty($products)): ?>
        <div class="alert alert-info">
            Giỏ hàng của bạn đang trống.
        </div>

        <a href="products.php" class="btn btn-dark">
            Tiếp tục mua hàng
        </a>
    <?php else: ?>
        <form id="cart-update" action="../actions/update_cart.php" method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
</form>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Đơn giá</th>
                            <th style="width: 140px;">Số lượng</th>
                            <th>Thành tiền</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img
                                            src="../assets/images/products/<?= rawurlencode(basename((string) ($product['image'] ?? ''))) ?>"
                                            alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                                            width="90"
                                            height="90"
                                            style="object-fit: contain;">

                                        <strong>
                                            <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </strong>
                                    </div>
                                </td>

                                <td>
                                    <?= number_format((float) $product['price'], 0, ',', '.') ?> ₫
                                </td>

                                <td>
                                    <input
                                        type="number" form="cart-update"
                                        class="form-control"
                                        name="quantities[<?= (int) $product['id'] ?>]"
                                        value="<?= (int) $product['quantity'] ?>"
                                        min="0"
                                        max="<?= (int) $product['stock'] ?>">
                                </td>

                                <td class="fw-bold text-danger">
                                    <?= number_format((float) $product['subtotal'], 0, ',', '.') ?> ₫
                                </td>

                                <td>
                                    <form action="../actions/remove_cart.php" method="post" onsubmit="return confirm('Bạn muốn xóa sản phẩm này?')">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
    <button type="submit" class="btn btn-outline-danger btn-sm">Xóa</button>
</form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">
                                Tổng cộng:
                            </th>
                            <th class="text-danger fs-5">
                                <?= number_format($total, 0, ',', '.') ?> ₫
                            </th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-between flex-wrap gap-2">
                <a href="products.php" class="btn btn-outline-dark">
                    Tiếp tục mua hàng
                </a>

            <div class="d-flex gap-2">
                <button type="submit" form="cart-update" class="btn btn-dark">
                    Cập nhật giỏ hàng
                </button>

                <a href="checkout.php" class="btn btn-danger">
                    Tiến hành thanh toán
                </a>
            </div>
            </div>

    <?php endif; ?>
<?php endif; ?>
</div>
