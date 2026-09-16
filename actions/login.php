<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

$_SESSION['login_email'] = $email;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)
    || $password === '') {
    $_SESSION['login_error'] =
        'Email hoặc mật khẩu không hợp lệ.';

    header('Location: ../pages/login.php');
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, fullname, email, password, phone, role
     FROM users
     WHERE email = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);

$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['login_error'] =
        'Email hoặc mật khẩu không chính xác.';

    header('Location: ../pages/login.php');
    exit;
}

/* Đổi mã session sau khi đăng nhập để tăng bảo mật */
session_regenerate_id(true);

$_SESSION['user'] = [
    'id' => (int) $user['id'],
    'fullname' => $user['fullname'],
    'email' => $user['email'],
    'phone' => $user['phone'],
    'role' => $user['role']
];

unset($_SESSION['login_email'], $_SESSION['login_error']);

if ($user['role'] === 'admin') {
    header('Location: ../admin/index.php');
} else {
    header('Location: ../index.php');
}

exit;