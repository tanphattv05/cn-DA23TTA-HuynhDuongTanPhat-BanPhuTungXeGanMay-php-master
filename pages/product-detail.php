<?php
require_once __DIR__ . '/../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(404);
    $product = null;
} else {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT p.*, c.name AS category_name
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$product) {
        http_response_code(404);
    }
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container py-5">
    <?php if (!$product): ?>
        <h1>Không tìm thấy sản phẩm</h1>
        <a href="products.php">Quay lại danh sách</a>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-md-5">
                <?php if (!empty($product['image'])): ?>
                    <img class="img-fluid rounded"
                         src="../assets/images/products/<?= rawurlencode(basename($product['image'])) ?>"
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
                <a href="products.php" class="btn btn-outline-dark">Quay lại sản phẩm</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>