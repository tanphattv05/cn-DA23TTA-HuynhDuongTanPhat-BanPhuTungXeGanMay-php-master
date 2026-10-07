<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } ?>
<div class="container py-5">
    <div class="card auth-card shadow-sm mx-auto">
        <div class="card-body p-4">
            <div class="auth-mark"><i class="bi bi-person-plus" aria-hidden="true"></i></div>
            <h1 class="text-center mb-3">Đăng ký tài khoản</h1>
            <p class="text-center text-muted mb-4">Lưu lại hành trình mua sắm và theo dõi các đơn hàng của bạn.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form action="../actions/register.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3">
                    <label for="fullname" class="form-label">
                        Họ và tên
                    </label>

                    <input
                        type="text"
                        autocomplete="name"
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
                        autocomplete="email"
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
                        autocomplete="tel"
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
                        autocomplete="new-password"
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
                        autocomplete="new-password"
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

