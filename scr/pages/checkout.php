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

        if ($quantity < 1 || (int) $product['stock'] < 1) {
            continue;
        }

        $quantity = min($quantity, (int) $product['stock']);
        $subtotal = (float) $product['price'] * $quantity;

        $product['quantity'] = $quantity;
        $product['subtotal'] = $subtotal;

        $products[] = $product;
        $total += $subtotal;
    }
}

if (empty($products)) {
    header('Location: cart.php');
    exit;
}

$error = $_SESSION['checkout_error'] ?? '';
$old = $_SESSION['checkout_old'] ?? [];
if (isset($_SESSION['user'])) {
    $old['fullname'] = $old['fullname']
        ?? $_SESSION['user']['fullname'];

    $old['phone'] = $old['phone']
        ?? $_SESSION['user']['phone'];
}
unset($_SESSION['checkout_error'], $_SESSION['checkout_old']);
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container py-5">
    <h1 class="mb-4">Thanh toán</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="mb-4">Thông tin nhận hàng</h4>

                    <form action="../actions/checkout.php" method="post">
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
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>