<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/register.php');
    exit;
}

$fullname = trim($_POST['fullname'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$phone = preg_replace('/\s+/', '', trim($_POST['phone'] ?? ''));
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

$_SESSION['register_old'] = [
    'fullname' => $fullname,
    'email' => $email,
    'phone' => $phone
];

if ($fullname === '' || mb_strlen($fullname) > 100) {
    $_SESSION['register_error'] = 'Họ và tên không hợp lệ.';
    header('Location: ../pages/register.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)
    || mb_strlen($email) > 100) {
    $_SESSION['register_error'] = 'Địa chỉ email không hợp lệ.';
    header('Location: ../pages/register.php');
    exit;
}

if (!preg_match('/^[0-9]{9,11}$/', $phone)) {
    $_SESSION['register_error'] =
        'Số điện thoại phải có từ 9 đến 11 chữ số.';

    header('Location: ../pages/register.php');
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['register_error'] =
        'Mật khẩu phải có ít nhất 6 ký tự.';

    header('Location: ../pages/register.php');
    exit;
}

if ($password !== $passwordConfirm) {
    $_SESSION['register_error'] =
        'Hai mật khẩu không trùng khớp.';

    header('Location: ../pages/register.php');
    exit;
}

/* Kiểm tra email đã tồn tại */
$checkEmail = mysqli_prepare(
    $conn,
    "SELECT id FROM users WHERE email = ? LIMIT 1"
);

mysqli_stmt_bind_param($checkEmail, 's', $email);
mysqli_stmt_execute($checkEmail);

$existingUser = mysqli_fetch_assoc(
    mysqli_stmt_get_result($checkEmail)
);

if ($existingUser) {
    $_SESSION['register_error'] =
        'Email này đã được sử dụng.';

    header('Location: ../pages/register.php');
    exit;
}

/* Mã hóa mật khẩu trước khi lưu */
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$role = 'customer';

$insertUser = mysqli_prepare(
    $conn,
    "INSERT INTO users
        (fullname, email, password, phone, role)
     VALUES (?, ?, ?, ?, ?)"
);

mysqli_stmt_bind_param(
    $insertUser,
    'sssss',
    $fullname,
    $email,
    $passwordHash,
    $phone,
    $role
);

if (!mysqli_stmt_execute($insertUser)) {
    $_SESSION['register_error'] =
        'Không thể đăng ký tài khoản. Vui lòng thử lại.';

    header('Location: ../pages/register.php');
    exit;
}

unset($_SESSION['register_old']);

$_SESSION['login_success'] =
    'Đăng ký thành công. Bạn có thể đăng nhập.';

header('Location: ../pages/login.php');
exit;