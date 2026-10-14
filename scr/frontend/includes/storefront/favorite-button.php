<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
$favoriteRole = $favoriteState['role'] ?? 'guest';
$isFavorite = in_array((int)$product['id'], $favoriteState['ids'] ?? [], true);
?>
<?php if ($favoriteRole === 'customer' && empty($favoriteState['error'])): ?>
<form method="post" action="../actions/toggle_favorite.php" class="favorite-control mt-2">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($favoriteState['token'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
    <input type="hidden" name="operation" value="<?= $isFavorite ? 'remove' : 'add' ?>">
    <input type="hidden" name="source" value="<?= ($favoriteSource ?? '') === 'favorites' ? 'favorites' : 'product' ?>">
    <button class="btn btn-outline-dark" type="submit" aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>">
        <i class="bi <?= $isFavorite ? 'bi-heart-fill text-danger' : 'bi-heart' ?>" aria-hidden="true"></i>
        <?= $isFavorite ? 'Bỏ yêu thích' : 'Yêu thích' ?>
    </button>
</form>
<?php elseif (!empty($favoriteState['error'])): ?>
<p class="text-muted small mt-2"><?= htmlspecialchars($favoriteState['error'], ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif ($favoriteRole === 'guest'): ?>
<a href="login.php" class="btn btn-outline-dark mt-2"><i class="bi bi-heart" aria-hidden="true"></i> Đăng nhập để yêu thích</a>
<?php endif; ?>
