<?php
require_once "../config/database.php";

$sql = "
    SELECT products.*, categories.name AS category_name
    FROM products
    INNER JOIN categories
    ON products.category_id = categories.id
    ORDER BY products.id DESC
";

$result = mysqli_query($conn, $sql);
?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/navbar.php"; ?>

<div class="container py-5">

    <div class="text-center mb-5">
        <h1 class="fw-bold">SẢN PHẨM PHỤ TÙNG XE GẮN MÁY</h1>
        <p class="text-muted">
            Các sản phẩm phụ tùng chất lượng dành cho xe gắn máy
        </p>
    </div>

    <div class="row">

        <?php if (mysqli_num_rows($result) > 0): ?>

            <?php while ($product = mysqli_fetch_assoc($result)): ?>

                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">

                    <div class="card h-100 shadow-sm">

                        <img
                            src="../assets/images/products/<?php echo htmlspecialchars($product['image']); ?>"
                            class="card-img-top"
                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                            style="height: 220px; object-fit: cover;">

                        <div class="card-body d-flex flex-column">

                            <small class="text-muted">
                                <?php echo htmlspecialchars($product['category_name']); ?>
                            </small>

                            <h5 class="card-title mt-2">
                                <?php echo htmlspecialchars($product['name']); ?>
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
                                    <?php echo htmlspecialchars($product['brand']); ?>
                                </strong>
                            </p>

                            <a
                                href="product-detail.php?id=<?php echo $product['id']; ?>"
                                class="btn btn-dark mt-auto">

                                Xem chi tiết

                            </a>

                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="col-12 text-center">

                <h4>Chưa có sản phẩm nào!</h4>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php include "../includes/footer.php"; ?>