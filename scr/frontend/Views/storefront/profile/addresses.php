<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="container py-4">
    <h1 class="h3">Địa chỉ giao hàng</h1>
    <a href="profile.php">Quay lại hồ sơ của tôi</a>
    <?php if ($flash): ?><div class="alert alert-info mt-3" role="alert"><?= $e($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger mt-3" role="alert"><?= $e($error) ?></div><?php else: ?>
    <div class="row g-4 mt-1">
        <section class="col-12 col-lg-6">
            <h2 class="h5">Địa chỉ đã lưu</h2>
            <?php if (!$rows): ?><p>Bạn chưa lưu địa chỉ nào.</p><?php endif; ?>
            <?php foreach ($rows as $row): ?>
            <div class="card mb-3"><div class="card-body text-break">
                <strong><?= $e($row['fullname']) ?></strong>
                <?php if ($row['is_default']): ?><span class="badge bg-danger">Mặc định</span><?php endif; ?>
                <p><?= $e($row['phone']) ?><br><?= nl2br($e($row['address'])) ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-dark btn-sm" href="addresses.php?id=<?= (int) $row['id'] ?>">Sửa</a>
                    <?php foreach (['default'=>'Đặt mặc định', 'delete'=>'Xóa'] as $operation=>$label): ?>
                    <?php if ($operation === 'default' && $row['is_default']) continue; ?>
                    <form method="post" action="../actions/addresses.php">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <button class="btn btn-outline-danger btn-sm" name="operation" value="<?= $operation ?>"><?= $label ?></button>
                    </form>
                    <?php endforeach; ?>
                </div>
            </div></div>
            <?php endforeach; ?>
        </section>
        <section class="col-12 col-lg-6"><div class="card"><div class="card-body">
            <h2 class="h5"><?= empty($edit['id']) ? 'Thêm địa chỉ' : 'Sửa địa chỉ' ?></h2>
            <form method="post" action="../actions/addresses.php">
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="operation" value="save">
                <input type="hidden" name="id" value="<?= $e($edit['id'] ?? '') ?>">
                <?php foreach (['fullname'=>'Họ tên người nhận', 'phone'=>'Số điện thoại', 'address'=>'Địa chỉ'] as $field=>$label): ?>
                <div class="mb-3"><label class="form-label" for="<?= $field ?>"><?= $label ?></label>
                <?php if ($field === 'address'): ?>
                <textarea class="form-control" rows="3" id="address" name="address" maxlength="500" required><?= $e($edit['address'] ?? '') ?></textarea>
                <?php else: ?>
                <input class="form-control" id="<?= $field ?>" name="<?= $field ?>" type="<?= $field === 'phone' ? 'tel' : 'text' ?>" maxlength="<?= $field === 'phone' ? 20 : 100 ?>" value="<?= $e($edit[$field] ?? '') ?>" required>
                <?php endif; ?></div>
                <?php endforeach; ?>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="is-default" name="is_default" value="1" <?= !empty($edit['is_default']) ? 'checked' : '' ?>><label class="form-check-label" for="is-default">Đặt làm địa chỉ mặc định</label></div>
                <button class="btn btn-danger">Lưu địa chỉ</button> <a class="btn btn-outline-dark" href="addresses.php">Thêm mới</a>
            </form>
        </div></div></section>
    </div>
    <?php endif; ?>
</div>
