<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}
?>
<div class="py-3">
<?php if ($success): ?><div class="alert alert-success"><?= product_escape($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= product_escape($error) ?></div><?php endif; ?>
<a class="btn btn-primary mb-3" href="product-form.php">Thêm sản phẩm</a>
<form method="get" class="row g-2 mb-3">
<div class="col-md-5"><label for="q" class="form-label">Tên hoặc thương hiệu</label>
<input class="form-control" id="q" name="q" maxlength="255" value="<?= product_escape($q) ?>"></div>
<div class="col-md-4"><label for="category" class="form-label">Danh mục</label>
<select class="form-select" id="category" name="category"><option value="">Tất cả danh mục</option>
<?php foreach ($categories as $category): ?>
<option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= product_escape($category['name']) ?></option>
<?php endforeach; ?>
</select></div>
<div class="col-md-3 d-flex align-items-end gap-2"><button class="btn btn-dark">Tìm kiếm</button><a class="btn btn-outline-secondary" href="products.php">Bỏ lọc</a></div>
</form>
<p><?= $total ?> sản phẩm — Trang <?= $page ?>/<?= $pages ?></p>
<div class="table-responsive"><table class="table table-bordered align-middle">
<thead><tr><th>Ảnh</th><th>Tên</th><th>Danh mục</th><th>Thương hiệu</th><th>Giá</th><th>Tồn kho</th><th>Thao tác</th></tr></thead>
<tbody>
<?php foreach ($rows as $product): ?>
<tr>
<td><?php if ($product['image']): ?><img src="<?= product_escape(product_image_url($product['image'])) ?>" alt="<?= product_escape($product['name']) ?>" width="72" height="72" style="object-fit:contain" loading="lazy"><?php else: ?>Chưa có ảnh<?php endif; ?></td>
<td><?= product_escape($product['name']) ?></td><td><?= product_escape($product['category_name'] ?? 'Chưa có danh mục') ?></td>
<td><?= product_escape($product['brand']) ?></td><td><?= number_format((float) $product['price'], 2, ',', '.') ?> ₫</td>
<td><?= (int) $product['stock'] ?></td><td><a class="btn btn-sm btn-outline-primary" href="product-form.php?id=<?= (int) $product['id'] ?>">Sửa</a></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows && !$error): ?><tr><td colspan="7">Không tìm thấy sản phẩm.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($pages > 1): ?>
<nav aria-label="Phân trang sản phẩm"><ul class="pagination flex-wrap">
<?php
$links = array_unique(array_merge([1, max(1, $page - 1)], range(max(1, $page - 2), min($pages, $page + 2)), [min($pages, $page + 1), $pages]));
sort($links);
foreach ($links as $number):
$url = 'products.php?' . http_build_query(['q' => $q, 'category' => $categoryId ?: '', 'page' => $number]);
?>
<li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" <?= $number === $page ? 'aria-current="page"' : '' ?> href="<?= product_escape($url) ?>"><?= $number ?></a></li>
<?php endforeach; ?>
</ul></nav>
<?php endif; ?>
</div>

