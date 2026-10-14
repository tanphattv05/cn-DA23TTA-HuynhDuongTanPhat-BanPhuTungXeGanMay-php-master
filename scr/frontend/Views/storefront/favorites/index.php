<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } ?>
<div class="container py-5">
    <h1>Sản phẩm yêu thích</h1>
    <?php if ($flash): ?><div class="alert alert-info" role="status"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (!$products): ?>
    <div class="empty-state"><p>Bạn chưa có sản phẩm yêu thích.</p><a href="products.php" class="btn btn-danger">Khám phá sản phẩm</a></div>
    <?php else: ?>
    <div class="row g-4">
    <?php $favoriteSource = 'favorites'; foreach ($products as $product): ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <div class="card product-card h-100">
                <?php if ($product['image']): ?><img class="card-img-top" src="<?= htmlspecialchars($product['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                <div class="card-body d-flex flex-column">
                    <h2 class="card-title"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="text-danger fw-bold"><?= number_format((float)$product['price'], 0, ',', '.') ?> ₫</p>
                    <p><?= (int)$product['stock'] > 0 ? 'Còn hàng: '.(int)$product['stock'] : 'Hết hàng' ?></p>
                    <a class="btn btn-dark mt-auto" href="product-detail.php?id=<?= (int)$product['id'] ?>">Xem chi tiết</a>
                    <?php require dirname(__DIR__, 3).'/includes/storefront/favorite-button.php'; ?>
                    <?php if ((int)$product['stock'] > 0): ?>
                    <form action="../actions/add_cart.php" method="post" class="mt-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" class="btn btn-danger">Thêm vào giỏ hàng</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
