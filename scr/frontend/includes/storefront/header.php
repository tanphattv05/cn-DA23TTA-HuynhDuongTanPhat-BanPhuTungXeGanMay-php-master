<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Content-addressed asset URL prevents an older storefront stylesheet being reused.
$storefrontCssFile = dirname(__DIR__, 3) . '/assets/css/style.css';
$storefrontCssVersion = is_file($storefrontCssFile) ? hash_file('sha256', $storefrontCssFile) : 'missing';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MotoParts - Phụ tùng xe gắn máy</title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- CSS của website -->
    <link
        rel="stylesheet"
        href="/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/assets/css/style.css?v=<?= htmlspecialchars($storefrontCssVersion, ENT_QUOTES, 'UTF-8') ?>">

</head>

<body class="storefront-body">
<a class="skip-link" href="#main-content">Bỏ qua điều hướng, tới nội dung</a>
