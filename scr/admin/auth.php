<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$baseUrl =
    '/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr';

if (!isset($_SESSION['user']['id'])) {
    $_SESSION['login_error'] =
        'Vui lòng đăng nhập bằng tài khoản quản trị.';

    header('Location: ' . $baseUrl . '/pages/login.php');
    exit;
}

$userId = (int) $_SESSION['user']['id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, fullname, role
     FROM users
     WHERE id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);

$currentUser = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

if (!$currentUser || $currentUser['role'] !== 'admin') {
    http_response_code(403);
    exit('Bạn không có quyền truy cập trang quản trị.');
}

$_SESSION['user']['role'] = $currentUser['role'];
$_SESSION['user']['fullname'] = $currentUser['fullname'];