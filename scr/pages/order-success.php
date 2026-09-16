<?php
session_start();

$orderId = $_SESSION['completed_order_id'] ?? null;

/*
 * Chỉ hiển thị thông báo một lần sau khi đặt hàng.
 */
unset($_SESSION['completed_order_id']);

if (!$orderId) {
    header('Location: products.php');
    exit;
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container py-5">
    <div class="card shadow-sm mx-auto text-center"
         style="max-width: 650px;">
        <div class="card-body p-5">
            <i class="bi bi-check-circle-fill text-success"
               style="font-size: 70px;"></i>

            <h1 class="mt-3">Đặt hàng thành công!</h1>

            <p class="fs-5">
                Mã đơn hàng của bạn là:
                <strong>#<?= (int) $orderId ?></strong>
            </p>

            <p class="text-muted">
                Cửa hàng sẽ liên hệ với bạn để xác nhận đơn hàng.
            </p>

            <a href="products.php" class="btn btn-dark">
                Tiếp tục mua hàng
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>