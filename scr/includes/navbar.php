<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) define('MOTOPARTS_MVC_ENTRY', true);
require_once __DIR__ . '/../app/bootstrap.php';
$authCsrfToken = \MotoParts\App\Core\AuthCsrf::token();
$baseUrl =
    '/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr';

$cartCount = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $quantity) {
        $cartCount += (int) $quantity;
    }
}
?>

<nav class="navbar navbar-expand-lg bg-dark" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand fw-bold"
           href="<?= $baseUrl ?>/">
            MotoParts
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar"
            aria-controls="mainNavbar"
            aria-expanded="false"
            aria-label="Mở menu">

            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <div class="navbar-nav ms-auto align-items-lg-center">
                <a class="nav-link" href="<?= $baseUrl ?>/">
                    Trang chủ
                </a>

                <a class="nav-link"
                   href="<?= $baseUrl ?>/pages/products.php">
                    Sản phẩm
                </a>

                <a class="nav-link"
                   href="<?= $baseUrl ?>/pages/cart.php">
                    <i class="bi bi-cart3"></i>
                    Giỏ hàng

                    <?php if ($cartCount > 0): ?>
                        <span class="badge bg-danger">
                            <?= $cartCount ?>
                        </span>
                    <?php endif; ?>
                </a>

                    <?php if (isset($_SESSION['user'])): ?>
                        <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                <a class="nav-link"
                href="<?= $baseUrl ?>/admin/index.php">
                    Quản trị
                </a>
            <?php endif; ?>
                <a class="nav-link"
                href="<?= $baseUrl ?>/pages/my-orders.php">
                    <i class="bi bi-receipt"></i>
                    Đơn hàng của tôi
                </a>

                <span class="navbar-text mx-lg-2">
                    Xin chào,
                    <?= htmlspecialchars(
                        $_SESSION['user']['fullname'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

                <form action="<?= $baseUrl ?>/actions/logout.php" method="post"><input type="hidden" name="auth_csrf_token" value="<?= htmlspecialchars($authCsrfToken, ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="nav-link border-0 bg-transparent">
                    Đăng xuất
                </button></form>
            <?php else: ?>
                <a class="nav-link"
                href="<?= $baseUrl ?>/pages/login.php">
                    Đăng nhập
                </a>

                <a class="nav-link"
                href="<?= $baseUrl ?>/pages/register.php">
                    Đăng ký
                </a>
            <?php endif; ?>
            </div>
        </div>
    </div>
</nav>