<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } ?>
<nav aria-label="Phân trang"><ul class="pagination flex-wrap">
    <?php foreach (array_unique([1, max(1, $page - 1), $page, min($pages, $page + 1), $pages]) as $number): ?>
    <li class="page-item <?= $number === $page ? 'active' : '' ?>">
        <a class="page-link" <?= $number === $page ? 'aria-current="page"' : '' ?>
           href="<?= product_escape($path . '?' . http_build_query(array_merge($query, ['page' => $number]))) ?>"><?= $number ?></a>
    </li>
    <?php endforeach; ?>
</ul></nav>
