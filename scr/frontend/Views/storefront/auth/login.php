<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } ?>
<div class="container py-5">
    <div class="card auth-card shadow-sm mx-auto">
        <div class="card-body p-4">
            <div class="auth-mark"><i class="bi bi-person" aria-hidden="true"></i></div>
            <h1 class="text-center mb-3">Đăng nhập</h1>
            <p class="text-center text-muted mb-4">Chào mừng trở lại. Theo dõi đơn hàng và tiếp tục hành trình cùng MotoParts.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form action="../actions/login.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3">
                    <label for="email" class="form-label">
                        Email
                    </label>

                    <input
                        type="email"
                        autocomplete="email"
                        id="email"
                        name="email"
                        class="form-control"
                        maxlength="100"
                        value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">
                        Mật khẩu
                    </label>

                    <input
                        type="password"
                        autocomplete="current-password"
                        id="password"
                        name="password"
                        class="form-control"
                        required>
                </div>

                <button type="submit" class="btn btn-dark w-100">
                    Đăng nhập
                </button>
            </form>

            <p class="text-center mt-3 mb-0">
                Chưa có tài khoản?
                <a href="register.php">Đăng ký</a>
            </p>
        </div>
    </div>
</div>

