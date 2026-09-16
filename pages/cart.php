<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$cart = $_SESSION['cart'] ?? [];
$products = [];
$total = 0;

if (!empty($cart)) {
    $productIds = array_map('intval', array_keys($cart));
    $idList = implode(',', $productIds);

    $sql = "
        SELECT id, name, price, image, stock
        FROM products
        WHERE id IN ($idList)
    ";

    $result = mysqli_query($conn, $sql);

    while ($product = mysqli_fetch_assoc($result)) {
        $productId = (int) $product['id'];
        $quantity = (int) ($cart[$productId] ?? 0);

        if ($quantity < 1) {
            continue;
        }

        $quantity = min($quantity, (int) $product['stock']);
        $_SESSION['cart'][$productId] = $quantity;

        $product['quantity'] = $quantity;
        $product['subtotal'] = (float) $product['price'] * $quantity;

        $total += $product['subtotal'];
        $products[] = $product;
    }
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container py-5">
    <h1 class="mb-4">Giỏ hàng</h1>

    <?php if (empty($products)): ?>
        <div class="alert alert-info">
            Giỏ hàng của bạn đang trống.
        </div>

        <a href="products.php" class="btn btn-dark">
            Tiếp tục mua hàng
        </a>
    <?php else: ?>
        <form action="../actions/update_cart.php" method="post">
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
                                            src="../assets/images/products/<?= rawurlencode(basename($product['image'])) ?>"
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
                                        type="number"
                                        class="form-control"
                                        name="quantities[<?= (int) $product['id'] ?>]"
                                        value="<?= (int) $product['quantity'] ?>"
                                        min="1"
                                        max="<?= (int) $product['stock'] ?>">
                                </td>

                                <td class="fw-bold text-danger">
                                    <?= number_format((float) $product['subtotal'], 0, ',', '.') ?> ₫
                                </td>

                                <td>
                                    <a
                                        href="../actions/remove_cart.php?id=<?= (int) $product['id'] ?>"
                                        class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Bạn muốn xóa sản phẩm này?')">
                                        Xóa
                                    </a>
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
                <button type="submit" class="btn btn-dark">
                    Cập nhật giỏ hàng
                </button>

                <a href="checkout.php" class="btn btn-danger">
                    Tiến hành thanh toán
                </a>
            </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>