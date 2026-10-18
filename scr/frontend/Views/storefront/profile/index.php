<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="container py-4">
    <h1 class="h3 mb-4">Hồ sơ của tôi</h1>
    <?php if ($flash): ?><div class="alert alert-<?= $escape($flash['type']) ?>" role="alert"><?= $escape($flash['message']) ?></div><?php endif; ?>
    <div class="row g-4">
        <section class="col-12 col-lg-6">
            <div class="card h-100"><div class="card-body">
                <h2 class="h5">Thông tin tài khoản</h2>
                <form method="post" action="../actions/profile.php">
                    <input type="hidden" name="operation" value="profile">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <?php foreach (['fullname' => ['Họ tên', 'text', 'name', 100], 'email' => ['Email', 'email', 'email', 100], 'phone' => ['Số điện thoại', 'tel', 'tel', 11]] as $field => [$label, $type, $autocomplete, $max]): ?>
                        <div class="mb-3"><label class="form-label" for="<?= $field ?>"><?= $label ?></label>
                        <input class="form-control" id="<?= $field ?>" name="<?= $field ?>" type="<?= $type ?>" autocomplete="<?= $autocomplete ?>" maxlength="<?= $max ?>" required value="<?= $escape($data[$field] ?? '') ?>"></div>
                    <?php endforeach; ?>
                    <button class="btn btn-danger" type="submit">Lưu thông tin</button>
                </form>
            </div></div>
        </section>
        <section class="col-12 col-lg-6">
            <div class="card h-100"><div class="card-body">
                <h2 class="h5">Đổi mật khẩu</h2>
                <form method="post" action="../actions/profile.php">
                    <input type="hidden" name="operation" value="password">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <?php foreach (['current_password' => 'Mật khẩu hiện tại', 'new_password' => 'Mật khẩu mới', 'password_confirm' => 'Xác nhận mật khẩu mới'] as $field => $label): ?>
                        <div class="mb-3"><label class="form-label" for="<?= $field ?>"><?= $label ?></label>
                        <input class="form-control" type="password" id="<?= $field ?>" name="<?= $field ?>" autocomplete="<?= $field === 'current_password' ? 'current-password' : 'new-password' ?>" required></div>
                    <?php endforeach; ?>
                    <p class="text-muted small">Mật khẩu mới từ 6 đến 72 byte, theo quy tắc đăng ký hiện tại.</p>
                    <button class="btn btn-danger" type="submit">Đổi mật khẩu</button>
                </form>
            </div></div>
        </section>
    </div>
</div>
