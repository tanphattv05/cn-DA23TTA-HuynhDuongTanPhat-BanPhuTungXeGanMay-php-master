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
                <?php if (!empty($product['image'])): ?>
                    <img class="img-fluid rounded"
                         src="<?= htmlspecialchars($product['image_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-7">
                <p><?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?></p>
                <h1><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                <h3 class="text-danger">
                    <?= number_format((float) $product['price'], 0, ',', '.') ?> ₫
                </h3>
                <p>Thương hiệu:
                    <?= htmlspecialchars($product['brand'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p>Còn hàng: <?= (int) $product['stock'] ?></p>
                <p><?= nl2br(htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p>
                <?php if ($canAddToCart): ?>
    <form action="../actions/add_cart.php" method="post" class="mb-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="product_id"
               value="<?= (int) $product['id'] ?>">

        <div class="mb-3" style="max-width: 150px;">
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
            <i class="bi bi-cart-plus"></i>
            Thêm vào giỏ hàng
        </button>
    </form>
<?php else: ?>
    <div class="alert alert-warning">
        Sản phẩm hiện đã hết hàng.
    </div>
<?php endif; ?>

<a href="products.php" class="btn btn-outline-dark">
    Quay lại sản phẩm
</a>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
