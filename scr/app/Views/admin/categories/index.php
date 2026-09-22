<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}
?>
<div class="py-3">
    <?php if ($success): ?><div class="alert alert-success" role="status"><?= product_escape($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= product_escape($error) ?></div><?php endif; ?>
    <a class="btn btn-primary mb-3" href="category-form.php">Thêm danh mục</a>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-8">
            <label for="q" class="form-label">Tên danh mục</label>
            <input class="form-control" id="q" name="q" maxlength="100" value="<?= product_escape($q) ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end gap-2">
            <button class="btn btn-dark" type="submit">Tìm kiếm</button>
            <a class="btn btn-outline-secondary" href="categories.php">Bỏ tìm kiếm</a>
        </div>
    </form>
    <p><?= $total ?> danh mục — Trang <?= $page ?>/<?= $pages ?></p>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead><tr><th>Mã</th><th>Tên</th><th>Mô tả</th><th>Số sản phẩm</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $category): ?>
                <tr>
                    <td><?= (int) $category['id'] ?></td>
                    <td><?= product_escape($category['name']) ?></td>
                    <td><?= product_escape(mb_strimwidth($category['description'] ?? '', 0, 120, '…', 'UTF-8')) ?></td>
                    <td><?= (int) $category['product_count'] ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="category-form.php?id=<?= (int) $category['id'] ?>">Sửa</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows && !$error): ?><tr><td colspan="5">Không tìm thấy danh mục.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <nav aria-label="Phân trang danh mục"><ul class="pagination flex-wrap">
        <?php foreach (array_unique([1, max(1, $page - 1), $page, min($pages, $page + 1), $pages]) as $number): ?>
        <li class="page-item <?= $number === $page ? 'active' : '' ?>">
            <a class="page-link" <?= $number === $page ? 'aria-current="page"' : '' ?>
               href="<?= product_escape('categories.php?' . http_build_query(['q' => $q, 'page' => $number])) ?>"><?= $number ?></a>
        </li>
        <?php endforeach; ?>
    </ul></nav>
    <?php endif; ?>
</div>

