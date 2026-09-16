<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$error = $_SESSION['register_error'] ?? '';
$old = $_SESSION['register_old'] ?? [];

unset($_SESSION['register_error'], $_SESSION['register_old']);
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 550px;">
        <div class="card-body p-4">
            <h1 class="text-center mb-4">Đăng ký tài khoản</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form action="../actions/register.php" method="post">
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
                    <label for="email" class="form-label">
                        Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        maxlength="100"
                        value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
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
                        value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">
                        Mật khẩu
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        minlength="6"
                        required>

                    <div class="form-text">
                        Mật khẩu phải có ít nhất 6 ký tự.
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password_confirm" class="form-label">
                        Nhập lại mật khẩu
                    </label>

                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        class="form-control"
                        minlength="6"
                        required>
                </div>

                <button type="submit" class="btn btn-danger w-100">
                    Đăng ký
                </button>
            </form>

            <p class="text-center mt-3 mb-0">
                Đã có tài khoản?
                <a href="login.php">Đăng nhập</a>
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>