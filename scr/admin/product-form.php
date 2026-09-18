<?php
require_once __DIR__ . '/includes/product-bootstrap.php';
$id = null;
if (isset($_GET['id'])) {
    $id = is_string($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) : false;
    if (!$id) { http_response_code(404); exit('Sản phẩm không tồn tại.'); }
}
$data = array_fill_keys(['name', 'category_id', 'price', 'brand', 'stock', 'description', 'image'], '');
$data['stock'] = '0';
try {
    if ($id) {
        $data = product_query('SELECT * FROM products WHERE id = ?', 'i', [$id])->get_result()->fetch_assoc();
        if (!$data) { http_response_code(404); exit('Sản phẩm không tồn tại.'); }
    }
    $categories = product_query('SELECT id, name FROM categories ORDER BY name')->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    http_response_code(503);
    exit('Không thể tải dữ liệu sản phẩm. Vui lòng thử lại sau.');
}
$errors = [];
$flash = $_SESSION['product_form'] ?? null;
unset($_SESSION['product_form']);
if ($flash && $flash['id'] === $id) {
    $data = array_replace($data, $flash['data']);
    $errors = $flash['errors'];
}
$pageTitle = $id ? 'Sửa sản phẩm' : 'Thêm sản phẩm';
include __DIR__ . '/includes/header.php';
?>
<div class="card my-3"><div class="card-body">
<?php foreach ($errors as $error): ?>
<div class="alert alert-danger" role="alert"><?= product_escape($error) ?></div>
<?php endforeach; ?>
<?php if ($errors): ?><p>Vui lòng chọn lại ảnh nếu có tải ảnh mới.</p><?php endif; ?>
<form action="save-product.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= product_escape($_SESSION['csrf_token']) ?>">
<input type="hidden" name="id" value="<?= product_escape($id) ?>">
<div class="mb-3"><label for="name" class="form-label">Tên sản phẩm</label>
<input class="form-control" id="name" name="name" maxlength="255" required value="<?= product_escape($data['name']) ?>"></div>
<div class="mb-3"><label for="category_id" class="form-label">Danh mục</label>
<select class="form-select" id="category_id" name="category_id" required>
<option value="">Chọn danh mục</option>
<?php foreach ($categories as $category): ?>
<option value="<?= (int) $category['id'] ?>" <?= (string) $data['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= product_escape($category['name']) ?></option>
<?php endforeach; ?>
</select></div>
<div class="mb-3"><label for="price" class="form-label">Giá (₫)</label>
<input class="form-control" id="price" name="price" type="number" min="0" max="9999999999.99" step="0.01" required value="<?= product_escape($data['price']) ?>"></div>
<div class="mb-3"><label for="brand" class="form-label">Thương hiệu</label>
<input class="form-control" id="brand" name="brand" maxlength="100" value="<?= product_escape($data['brand']) ?>"></div>
<div class="mb-3"><label for="stock" class="form-label">Tồn kho</label>
<input class="form-control" id="stock" name="stock" type="number" min="0" max="2147483647" step="1" required value="<?= product_escape($data['stock']) ?>"></div>
<div class="mb-3"><label for="description" class="form-label">Mô tả</label>
<textarea class="form-control" id="description" name="description" rows="5"><?= product_escape($data['description']) ?></textarea></div>
<div class="mb-3"><label for="image" class="form-label">Ảnh JPEG, PNG hoặc WebP (tối đa 2 MB)</label>
<input class="form-control" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
<div class="form-text">Không chọn ảnh mới sẽ giữ ảnh hiện tại. Tối đa 20 triệu điểm ảnh.</div>
<img id="image-preview" class="mt-2 rounded" style="max-width:240px;max-height:200px" alt="Xem trước ảnh sản phẩm" <?php if ($data['image']): ?>src="<?= product_escape(product_image_url($data['image'])) ?>"<?php else: ?>hidden<?php endif; ?>>
</div>
<button class="btn btn-primary" type="submit">Lưu sản phẩm</button>
<a class="btn btn-outline-secondary" href="products.php">Quay lại</a>
</form>
</div></div>
<script src="assets/product-form.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
