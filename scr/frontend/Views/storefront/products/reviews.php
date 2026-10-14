<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } ?>
<section id="product-reviews" class="mt-5" aria-labelledby="reviews-title">
    <h2 id="reviews-title">Đánh giá sản phẩm</h2>
    <?php if ($reviewState['error']): ?>
        <p class="text-muted"><?= htmlspecialchars($reviewState['error'],ENT_QUOTES,'UTF-8') ?></p>
    <?php else: ?>
        <?php if ($reviewState['flash']): ?><div class="alert alert-info" role="status"><?= htmlspecialchars($reviewState['flash'],ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
        <p class="review-summary" data-total="<?= (int)$reviewState['total'] ?>" data-average="<?= htmlspecialchars((string)$reviewState['average'],ENT_QUOTES,'UTF-8') ?>">
            <span aria-hidden="true" class="text-danger">★</span>
            <?= number_format((float)$reviewState['average'],1,',','.') ?>/5 · <?= (int)$reviewState['total'] ?> đánh giá
        </p>
        <?php if (!$reviewState['rows']): ?><p>Chưa có đánh giá cho sản phẩm này.</p><?php endif; ?>
        <?php foreach ($reviewState['rows'] as $review): ?>
        <article class="card mb-3 review-item"><div class="card-body">
            <h3><?= htmlspecialchars($review['fullname'],ENT_QUOTES,'UTF-8') ?></h3>
            <p><?= (int)$review['rating'] ?>/5 sao · <time><?= htmlspecialchars($review['created_at'],ENT_QUOTES,'UTF-8') ?></time></p>
            <p class="mb-0"><?= nl2br(htmlspecialchars($review['comment'],ENT_QUOTES,'UTF-8')) ?></p>
        </div></article>
        <?php endforeach; ?>
        <?php if ($reviewState['pages']>1): ?>
        <nav aria-label="Phân trang đánh giá" class="d-flex flex-wrap gap-3 mb-3">
            <?php if ($reviewState['page']>1): ?><a href="product-detail.php?id=<?= (int)$product['id'] ?>&amp;review_page=<?= $reviewState['page']-1 ?>#product-reviews">Trước</a><?php endif; ?>
            <span aria-current="page">Trang <?= (int)$reviewState['page'] ?>/<?= (int)$reviewState['pages'] ?></span>
            <?php if ($reviewState['page']<$reviewState['pages']): ?><a href="product-detail.php?id=<?= (int)$product['id'] ?>&amp;review_page=<?= $reviewState['page']+1 ?>#product-reviews">Sau</a><?php endif; ?>
        </nav>
        <?php endif; ?>
        <?php if ($reviewState['role']==='guest'): ?>
            <a href="login.php">Đăng nhập để đánh giá sản phẩm đã mua</a>
        <?php elseif ($reviewState['role']==='customer' && $reviewState['eligible']): ?>
        <form id="review-form" class="card mb-3" method="post" action="../actions/product_review.php"><div class="card-body">
            <h3><?= $reviewState['own'] ? 'Sửa đánh giá của bạn' : 'Viết đánh giá' ?></h3>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($reviewState['token'],ENT_QUOTES,'UTF-8') ?>">
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <input type="hidden" name="operation" value="save">
            <label for="review-rating" class="form-label">Số sao</label>
            <select id="review-rating" class="form-select mb-3" name="rating" required>
                <?php for ($star=1;$star<=5;$star++): ?><option value="<?= $star ?>" <?= (int)($reviewState['own']['rating'] ?? 5)===$star ? 'selected' : '' ?>><?= $star ?> sao</option><?php endfor; ?>
            </select>
            <label for="review-comment" class="form-label">Nhận xét (tối đa 1000 ký tự)</label>
            <textarea id="review-comment" class="form-control mb-3" name="comment" maxlength="1000" rows="4"><?= htmlspecialchars($reviewState['own']['comment'] ?? '',ENT_QUOTES,'UTF-8') ?></textarea>
            <button class="btn btn-danger" type="submit">Lưu đánh giá</button>
        </div></form>
        <?php elseif ($reviewState['role']==='customer'): ?>
            <p>Bạn chỉ có thể đánh giá sau khi đơn mua sản phẩm này đã hoàn thành.</p>
        <?php endif; ?>
        <?php if ($reviewState['role']==='customer' && $reviewState['own']): ?>
        <form id="review-delete" method="post" action="../actions/product_review.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($reviewState['token'],ENT_QUOTES,'UTF-8') ?>">
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <input type="hidden" name="operation" value="delete">
            <button type="submit" class="btn btn-outline-dark">Xóa đánh giá của tôi</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
