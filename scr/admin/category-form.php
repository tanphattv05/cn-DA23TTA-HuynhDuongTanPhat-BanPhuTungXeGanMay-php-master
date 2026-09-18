<?php
require_once __DIR__ . '/includes/category-input.php';
$id = category_id($_GET);
$data = ['name' => '', 'description' => ''];
$errors = [];
$unavailable = $id === false || (array_key_exists('id', $_GET) && $id === null);
if ($unavailable) {
    http_response_code(404);
    $errors[] = 'Mã danh mục không hợp lệ.';
} else {
    try {
        if ($id !== null) {
            $category = product_query('SELECT name, description FROM categories WHERE id = ?', 'i', [$id])->get_result()->fetch_assoc();
            if (!$category) {
                http_response_code(404);
                $unavailable = true;
                $errors[] = 'Danh mục không tồn tại.';
            } else {
                $data = $category;
            }
        }
    } catch (Throwable $e) {
        http_response_code(503);
        $unavailable = true;
        $errors[] = 'Không thể tải danh mục. Vui lòng thử lại sau.';
    }
}
$flash = $_SESSION['category_form'] ?? null;
if ($flash && $flash['id'] === $id) {
    unset($_SESSION['category_form']);
    if (!$unavailable) {
        $data = $flash['data'];
        $errors = $flash['errors'];
    }
}
$pageTitle = $id ? 'Sửa danh mục' : 'Thêm danh mục';
include __DIR__ . '/includes/header.php';
?>
<div class="card my-3"><div class="card-body">
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger" role="alert"><?= product_escape($error) ?></div>
    <?php endforeach; ?>
    <?php if (!$unavailable): ?>
    <form action="save-category.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= product_escape($_SESSION['csrf_token']) ?>">
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
<?php include __DIR__ . '/includes/footer.php'; ?>
