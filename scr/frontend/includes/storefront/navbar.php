<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
require_once dirname(__DIR__, 3) . '/backend/bootstrap.php';
$authCsrfToken = \MotoParts\App\Core\AuthCsrf::token();
$baseUrl =
    '/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr';

$cartCount = 0;
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$navGroups = [
    'home' => ['index.php'],
    'products' => ['products.php', 'product-detail.php'],
    'cart' => ['cart.php', 'checkout.php', 'order-success.php'],
    'orders' => ['my-orders.php', 'order-detail.php'],
    'login' => ['login.php'],
    'register' => ['register.php'],
];
$activeNav = static fn(string $group): string => in_array($currentPage, $navGroups[$group], true)
    ? ' active" aria-current="page' : '';

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $quantity) {
        $cartCount += (int) $quantity;
    }
}
?>

<header class="storefront-header">
<div class="brand-strip"><div class="container">MOTOPARTS <span>Chăm xe mỗi ngày. Vững vàng mọi hành trình.</span></div></div>
<nav class="navbar navbar-expand-xl" data-bs-theme="dark" aria-label="Điều hướng chính">
    <div class="container">
        <a class="navbar-brand fw-bold"
           href="<?= $baseUrl ?>/">
            <i class="bi bi-gear-wide-connected brand-icon" aria-hidden="true"></i> Moto<span class="brand-accent">Parts</span>
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
            <div class="navbar-nav ms-auto align-items-xl-center">
                <a class="nav-link<?= $activeNav('home') ?>" href="<?= $baseUrl ?>/">
                    Trang chủ
                </a>

                <a class="nav-link<?= $activeNav('products') ?>"
                   href="<?= $baseUrl ?>/pages/products.php">
                    Sản phẩm
                </a>

                <a class="nav-link<?= $activeNav('cart') ?>"
                   href="<?= $baseUrl ?>/pages/cart.php">
                    <i class="bi bi-cart3" aria-hidden="true"></i>
                    Giỏ hàng

                    <?php if ($cartCount > 0): ?>
                        <span class="badge bg-danger" aria-label="Số lượng trong giỏ">
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
                <a class="nav-link<?= $activeNav('orders') ?>"
                href="<?= $baseUrl ?>/pages/my-orders.php">
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    Đơn hàng của tôi
                </a>

                <span class="navbar-text nav-greeting mx-xl-2">
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
                <a class="nav-link<?= $activeNav('login') ?>"
                href="<?= $baseUrl ?>/pages/login.php">
                    Đăng nhập
                </a>

                <a class="nav-link nav-register<?= $activeNav('register') ?>"
                href="<?= $baseUrl ?>/pages/register.php">
                    Đăng ký
                </a>
            <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
</header>
<main id="main-content" tabindex="-1">
