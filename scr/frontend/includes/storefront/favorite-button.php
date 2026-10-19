<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
$favoriteRole = $favoriteState['role'] ?? 'guest';
$isFavorite = in_array((int)$product['id'], $favoriteState['ids'] ?? [], true);
$favoriteIconOnly = $favoriteIconOnly ?? false;
$favoriteLabel = $isFavorite ? 'Bỏ yêu thích' : 'Yêu thích';
?>
<?php if ($favoriteRole === 'customer' && empty($favoriteState['error'])): ?>
<form method="post" action="../actions/toggle_favorite.php" class="favorite-control<?= $favoriteIconOnly ? '' : ' mt-2' ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($favoriteState['token'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
    <input type="hidden" name="operation" value="<?= $isFavorite ? 'remove' : 'add' ?>">
    <input type="hidden" name="source" value="<?= ($favoriteSource ?? '') === 'favorites' ? 'favorites' : 'product' ?>">
    <button class="<?= $favoriteIconOnly ? 'favorite-icon-button' : 'btn btn-outline-dark' ?>" type="submit" aria-label="<?= $favoriteLabel ?>" title="<?= $favoriteLabel ?>" aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>">
        <i class="bi <?= $isFavorite ? 'bi-heart-fill text-danger' : 'bi-heart' ?>" aria-hidden="true"></i>
        <?php if (!$favoriteIconOnly): ?><?= $favoriteLabel ?><?php endif; ?>
    </button>
</form>
<?php elseif (!empty($favoriteState['error'])): ?>
<?php if ($favoriteIconOnly): ?>
<button class="favorite-icon-button" type="button" disabled aria-label="Yêu thích tạm thời chưa khả dụng" title="<?= htmlspecialchars($favoriteState['error'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-heart" aria-hidden="true"></i></button>
<?php else: ?>
<p class="text-muted small mt-2"><?= htmlspecialchars($favoriteState['error'], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php elseif ($favoriteRole === 'guest'): ?>
<a href="login.php" class="<?= $favoriteIconOnly ? 'favorite-icon-button' : 'btn btn-outline-dark mt-2' ?>" aria-label="Đăng nhập để yêu thích" title="Đăng nhập để yêu thích"><i class="bi bi-heart" aria-hidden="true"></i><?php if (!$favoriteIconOnly): ?> Đăng nhập để yêu thích<?php endif; ?></a>
<?php endif; ?>
