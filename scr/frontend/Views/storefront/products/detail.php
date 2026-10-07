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
    <?php if (!$product): ?>
        <h1>Không tìm thấy sản phẩm</h1>
        <a href="products.php">Quay lại danh sách</a>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-md-5">
                <div class="product-photo">
                <?php if (!empty($product['image'])): ?>
                    <img class="img-fluid rounded"
                         src="<?= htmlspecialchars($product['image_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                    <div class="text-center text-muted"><i class="bi bi-image fs-1" aria-hidden="true"></i><p>Ảnh sản phẩm đang cập nhật</p></div>
                <?php endif; ?>
                </div>
            </div>
            <div class="col-md-7">
                <div class="product-copy">
                <p class="eyebrow"><?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?></p>
                <h1><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="product-price">
                    <?= number_format((float) $product['price'], 0, ',', '.') ?> ₫
                </p>
                <p>Thương hiệu:
                    <?= htmlspecialchars($product['brand'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p><span class="badge <?= $canAddToCart ? 'bg-success' : 'bg-secondary' ?>">Còn hàng: <?= (int) $product['stock'] ?></span>
                <?php if ($canAddToCart && (int) $product['stock'] <= 5): ?><span class="badge bg-warning text-dark">Sắp hết hàng</span><?php endif; ?></p>
                <div class="product-description"><h2 class="form-section-title">Thông tin sản phẩm</h2><p><?= nl2br(htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p></div>
                <?php if ($canAddToCart): ?>
    <form action="../actions/add_cart.php" method="post" class="mb-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="product_id"
               value="<?= (int) $product['id'] ?>">

        <div class="mb-3 quantity-field">
            <label for="quantity" class="form-label">Số lượng</label>

            <input type="number"
                   id="quantity"
                   name="quantity"
                   class="form-control"
                   value="1"
                   min="1"
                   max="<?= (int) $product['stock'] ?>"
                   required>
        </div>

        <button type="submit" class="btn btn-danger">
            <i class="bi bi-cart-plus" aria-hidden="true"></i>
            Thêm vào giỏ hàng
        </button>
    </form>
<?php else: ?>
    <div class="alert alert-warning">
        Sản phẩm hiện đã hết hàng.
    </div>
    <button type="button" class="btn btn-secondary mb-3" disabled>Hết hàng — chưa thể thêm vào giỏ</button>
<?php endif; ?>

<a href="products.php" class="btn btn-outline-dark">
    Quay lại sản phẩm
</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
