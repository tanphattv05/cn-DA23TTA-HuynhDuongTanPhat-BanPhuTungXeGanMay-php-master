<?php
// Reuse auth.php and the existing query, input and HTML helpers.
require_once __DIR__ . '/product-bootstrap.php';

function customer_page(array $input): int
{
    return filter_var(product_text($input, 'page'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647]
    ]) ?: 1;
}

function customer_date(?string $value): string
{
    $timestamp = $value ? strtotime($value) : false;
    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
}

function customer_pagination(string $path, array $query, int $page, int $pages): void
{
    if ($pages <= 1) {
        return;
    }
    ?>
    <nav aria-label="Phân trang"><ul class="pagination flex-wrap">
        <?php foreach (array_unique([1, max(1, $page - 1), $page, min($pages, $page + 1), $pages]) as $number): ?>
        <li class="page-item <?= $number === $page ? 'active' : '' ?>">
            <a class="page-link" <?= $number === $page ? 'aria-current="page"' : '' ?>
               href="<?= product_escape($path . '?' . http_build_query(array_merge($query, ['page' => $number]))) ?>"><?= $number ?></a>
        </li>
        <?php endforeach; ?>
    </ul></nav>
    <?php
}
