<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}
?>
<div class="card my-3"><div class="card-body">
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger" role="alert"><?= product_escape($error) ?></div>
    <?php endforeach; ?>
    <?php if (!$unavailable): ?>
    <form action="save-category.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= product_escape($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= product_escape($id) ?>">
        <div class="mb-3">
            <label for="name" class="form-label">Tên danh mục</label>
            <input class="form-control" id="name" name="name" maxlength="100" required value="<?= product_escape($data['name']) ?>">
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Mô tả</label>
            <textarea class="form-control" id="description" name="description" rows="5" maxlength="2000" aria-describedby="description-help"><?= product_escape($data['description']) ?></textarea>
            <div id="description-help" class="form-text">Không bắt buộc, tối đa 2.000 ký tự.</div>
        </div>
        <button class="btn btn-primary" type="submit">Lưu danh mục</button>
        <a class="btn btn-outline-secondary" href="categories.php">Quay lại</a>
    </form>
    <?php else: ?>
        <a class="btn btn-outline-secondary" href="categories.php">Quay lại danh sách</a>
    <?php endif; ?>
</div></div>

