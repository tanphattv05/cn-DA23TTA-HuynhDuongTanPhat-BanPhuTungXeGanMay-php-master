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

    <div class="text-center mb-5">
        <h1 class="fw-bold">SẢN PHẨM PHỤ TÙNG XE GẮN MÁY</h1>
        <p class="text-muted">
            Các sản phẩm phụ tùng chất lượng dành cho xe gắn máy
        </p>
    </div>

    <div class="row">

        <?php if ($products): ?>

            <?php foreach ($products as $product): ?>

                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">

                    <div class="card h-100 shadow-sm">

                        <img
                            src="<?php echo htmlspecialchars($product['image_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                            class="card-img-top"
                            alt="<?php echo htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                            style="height: 220px; object-fit: cover;">

                        <div class="card-body d-flex flex-column">

                            <small class="text-muted">
                                <?php echo htmlspecialchars((string) ($product['category_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </small>

                            <h5 class="card-title mt-2">
                                <?php echo htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </h5>

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

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="col-12 text-center">

                <h4>Chưa có sản phẩm nào!</h4>

            </div>

        <?php endif; ?>

    </div>

</div>
<?php endif; ?>
