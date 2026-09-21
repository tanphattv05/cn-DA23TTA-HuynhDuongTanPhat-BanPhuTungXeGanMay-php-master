<?php
$pageTitle = $pageTitle ?? 'Quản trị MotoParts';
$currentPage = basename($_SERVER['SCRIPT_NAME']);

$adminUrl = $baseUrl . '/admin';
$assetUrl = $adminUrl . '/assets/adminlte';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>
        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
        - MotoParts
    </title>

    <!-- AdminLTE đã bao gồm CSS Bootstrap -->
    <link rel="stylesheet"
          href="<?= $assetUrl ?>/css/adminlte.css">
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">

<div class="app-wrapper">

    <!-- Thanh phía trên -->
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <button class="nav-link border-0 bg-transparent"
                            type="button"
                            data-lte-toggle="sidebar"
                            aria-label="Ẩn hoặc hiện menu">
                        ☰
                    </button>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>/">
                        Xem website
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item d-flex align-items-center">
                    <span class="me-3">
                        Xin chào,
                        <strong>
                            <?= htmlspecialchars(
                                $_SESSION['user']['fullname'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>
                    </span>
                </li>

                <li class="nav-item">
                    <a class="nav-link"
                       href="<?= $baseUrl ?>/actions/logout.php">
                        Đăng xuất
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Menu bên trái -->
    <aside class="app-sidebar bg-body-secondary shadow"
           data-bs-theme="dark">

        <div class="sidebar-brand">
            <a href="<?= $adminUrl ?>/index.php"
               class="brand-link">
                <span class="brand-text fw-bold">
                    MotoParts Admin
                </span>
            </a>
        </div>

        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column">

                    <li class="nav-header">
                        QUẢN LÝ CỬA HÀNG
                    </li>

                    <li class="nav-item">
                        <a href="<?= $adminUrl ?>/index.php"
                           class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                            <p>Tổng quan</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= $adminUrl ?>/orders.php"
                           class="nav-link <?= in_array($currentPage, ['orders.php', 'order-detail.php'], true) ? 'active' : '' ?>">
                            <p>Đơn hàng</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= $adminUrl ?>/products.php"
                           class="nav-link <?= in_array($currentPage, ['products.php', 'product-form.php', 'save-product.php'], true) ? 'active' : '' ?>">
                            <p>Sản phẩm</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $adminUrl ?>/categories.php"
                           class="nav-link <?= in_array($currentPage, ['categories.php', 'category-form.php', 'save-category.php'], true) ? 'active' : '' ?>">
                            <p>Danh mục</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $adminUrl ?>/customers.php"
                           class="nav-link <?= in_array($currentPage, ['customers.php', 'customer-detail.php'], true) ? 'active' : '' ?>">
                            <p>Khách hàng</p>
                        </a>
                    </li>
                    <li class="nav-header">
                        WEBSITE
                    </li>

                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/"
                           class="nav-link">
                            <p>Trang bán hàng</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/actions/logout.php"
                           class="nav-link">
                            <p>Đăng xuất</p>
                        </a>
                    </li>

                </ul>
            </nav>
        </div>
    </aside>

    <!-- Nội dung chính -->
    <main class="app-main">

        <div class="app-content-header">
            <div class="container-fluid">
                <h1 class="fs-3 mb-0">
                    <?= htmlspecialchars(
                        $pageTitle,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h1>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">