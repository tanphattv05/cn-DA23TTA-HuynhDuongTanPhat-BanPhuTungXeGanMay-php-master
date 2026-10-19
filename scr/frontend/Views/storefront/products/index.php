<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}
?>
<?php if ($error): ?>
<div class="container py-5">
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <a href="products.php">Quay lại danh sách</a>
</div>
<?php else: ?>
<div class="container py-5">

    <div class="section-heading">
        <div>
        <p class="eyebrow">DANH MỤC SẢN PHẨM / MOTOPARTS</p>
        <h1 class="fw-bold">SẢN PHẨM PHỤ TÙNG XE GẮN MÁY</h1>
        <p class="text-muted">
            Các sản phẩm phụ tùng chất lượng dành cho xe gắn máy
        </p>
        </div>
    </div>

    <?php
    $filtersExpanded = !empty($validationErrors)
        || $filters['q'] !== '' || $filters['category'] !== ''
        || $filters['min_price'] !== '' || $filters['max_price'] !== ''
        || $filters['stock'] !== 'all' || $filters['sort'] !== 'newest';
    ?>
    <div id="catalog-results" class="catalog-result-heading" data-total="<?= (int)$total ?>" data-page="<?= (int)$page ?>" data-pages="<?= (int)$totalPages ?>">
        <h2 class="form-section-title mb-0">Kết quả sản phẩm</h2>
        <div class="catalog-result-tools">
        <div class="catalog-filter-anchor">
        <button class="catalog-filter-toggle btn d-inline-flex align-items-center gap-2"
                type="button" data-bs-toggle="collapse" data-bs-target="#catalog-filter-panel"
                aria-expanded="<?= $filtersExpanded ? 'true' : 'false' ?>" aria-controls="catalog-filter-panel">
            <i class="bi bi-sliders" aria-hidden="true"></i>
            <span>Bộ lọc</span>
            <i class="bi bi-chevron-down catalog-filter-arrow ms-auto" aria-hidden="true"></i>
        </button>
    <div id="catalog-filter-panel" class="catalog-filter-panel collapse<?= $filtersExpanded ? ' show' : '' ?>" role="region" aria-labelledby="catalog-filter-title">
    <form id="catalog-filters" class="catalog-filters card mb-0" method="get" action="products.php">
        <div class="card-body">
            <div class="catalog-filter-panel-heading d-flex align-items-center justify-content-between gap-3 mb-3">
                <h3 class="h5 mb-0" id="catalog-filter-title">Lọc sản phẩm</h3>
                <button type="button" class="btn-close" data-bs-toggle="collapse" data-bs-target="#catalog-filter-panel"
                        aria-controls="catalog-filter-panel" aria-expanded="<?= $filtersExpanded ? 'true' : 'false' ?>" aria-label="Đóng bộ lọc"></button>
            </div>
            <div class="row g-3">
                <div class="col-12">
                    <label for="catalog-q" class="form-label">Tên sản phẩm hoặc thương hiệu</label>
                    <input id="catalog-q" class="form-control" type="search" name="q" maxlength="100" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Tìm phụ tùng cho xe của bạn">
                </div>
                <div class="col-12 col-sm-6">
                    <label for="catalog-category" class="form-label">Danh mục</label>
                    <select id="catalog-category" class="form-select" name="category">
                        <option value="">Tất cả danh mục</option>
                        <?php if (isset($validationErrors['category']) && $filters['category'] !== ''): ?><option selected value="<?= htmlspecialchars($filters['category'], ENT_QUOTES, 'UTF-8') ?>">Danh mục không hợp lệ</option><?php endif; ?>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>" <?= (string)$category['id'] === $filters['category'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6">
                    <label for="catalog-sort" class="form-label">Sắp xếp</label>
                    <select id="catalog-sort" class="form-select" name="sort">
                        <?php foreach (['newest'=>'Mới nhất','price_asc'=>'Giá thấp đến cao','price_desc'=>'Giá cao đến thấp','name_asc'=>'Tên A–Z'] as $value=>$label): ?>
                        <option value="<?= $value ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6">
                    <label for="catalog-min" class="form-label">Giá tối thiểu (₫)</label>
                    <input id="catalog-min" class="form-control" type="text" inputmode="numeric" name="min_price" maxlength="10" pattern="[0-9]{1,10}" value="<?= htmlspecialchars($filters['min_price'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Không giới hạn">
                </div>
                <div class="col-12 col-sm-6">
                    <label for="catalog-max" class="form-label">Giá tối đa (₫)</label>
                    <input id="catalog-max" class="form-control" type="text" inputmode="numeric" name="max_price" maxlength="10" pattern="[0-9]{1,10}" value="<?= htmlspecialchars($filters['max_price'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Không giới hạn">
                </div>
                <div class="col-12">
                    <label for="catalog-stock" class="form-label">Tình trạng hàng</label>
                    <select id="catalog-stock" class="form-select" name="stock">
                        <?php foreach (['all'=>'Tất cả sản phẩm','in_stock'=>'Còn hàng','out_of_stock'=>'Hết hàng'] as $value=>$label): ?>
                        <option value="<?= $value ?>" <?= $filters['stock'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="catalog-filter-actions mt-3">
                <button type="submit" class="btn btn-danger">Áp dụng</button>
                <a href="products.php" class="btn btn-outline-dark">Xóa bộ lọc</a>
            </div>
        </div>
    </form>
    </div>
        </div>
        <?php if (!$validationErrors): ?><p><?= (int)$total ?> sản phẩm · Trang <?= (int)$page ?>/<?= (int)$totalPages ?></p><?php endif; ?>
        </div>
    </div>
    <?php if ($validationErrors): ?>
        <div class="alert alert-danger" role="alert"><strong>Vui lòng kiểm tra bộ lọc:</strong><ul class="mb-0">
        <?php foreach ($validationErrors as $message): ?><li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <div class="row">

        <?php if ($products): ?>

            <?php foreach ($products as $product): ?>

                <div class="col-xl-3 col-lg-4 col-sm-6 mb-4">

                    <div class="card product-card h-100 shadow-sm">

                        <img
                            src="<?php echo htmlspecialchars($product['image_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                            class="card-img-top" loading="lazy"
                            alt="<?php echo htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                            >

                        <div class="card-body d-flex flex-column">

                            <small class="text-muted">
                                <?php echo htmlspecialchars((string) ($product['category_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </small>

                            <h3 class="card-title mt-2">
                                <?php echo htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </h3>
                            <?php if ((int)$product['stock'] <= 0): ?><p><span class="badge bg-secondary">Hết hàng</span></p><?php endif; ?>

                            <p class="text-danger fw-bold fs-5">
                                <?php
                                echo number_format(
                                    $product['price'],
                                    0,
                                    ',',
                                    '.'
                                );
                                ?> ₫
                            </p>

                            <p>
                                Thương hiệu:
                                <strong>
                                    <?php echo htmlspecialchars((string) ($product['brand'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                </strong>
                            </p>

                            <a
                                href="product-detail.php?id=<?php echo (int) $product['id']; ?>"
                                class="btn btn-dark mt-auto">

                                Xem chi tiết

                            </a>

                            <?php require dirname(__DIR__, 3).'/includes/storefront/favorite-button.php'; ?>
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php elseif (!$validationErrors): ?>

            <div class="col-12"><div class="empty-state">
                <i class="bi bi-box-seam" aria-hidden="true"></i>

                <h2>Chưa có sản phẩm nào!</h2>
                <p class="text-muted">Không tìm thấy sản phẩm phù hợp. Hãy thử thay đổi bộ lọc.</p>
                <a href="products.php" class="btn btn-outline-dark">Xem tất cả sản phẩm</a>

            </div></div>

        <?php endif; ?>

    </div>
    <?php if ($pagination): ?>
    <nav aria-label="Phân trang sản phẩm" class="catalog-pagination">
        <ul class="pagination flex-wrap gap-1">
            <?php foreach ($pagination as $link): ?>
            <li class="page-item <?= $link['current'] ? 'active' : '' ?>">
                <?php if ($link['current']): ?><span class="page-link" aria-current="page"><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php elseif ($link['url'] === null): ?><span class="page-link">…</span>
                <?php else: ?><a class="page-link" href="<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php endif; ?>
