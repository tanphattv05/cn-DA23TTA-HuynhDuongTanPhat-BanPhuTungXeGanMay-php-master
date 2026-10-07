<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } ?>
<!-- Bootstrap JavaScript -->
</main>
<footer class="storefront-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <a class="footer-brand" href="<?= $baseUrl ?>/"><i class="bi bi-gear-wide-connected" aria-hidden="true"></i> MotoParts</a>
                <p class="mt-3">Phụ tùng cho chiếc xe bạn tin cậy.<br>Đồng hành cùng bạn trên mọi hành trình.</p>
            </div>
            <div class="col-sm-6 col-lg-3">
                <h2>Khám phá</h2>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= $baseUrl ?>/">Trang chủ</a></li>
                    <li><a href="<?= $baseUrl ?>/pages/products.php">Sản phẩm</a></li>
                    <li><a href="<?= $baseUrl ?>/pages/cart.php">Giỏ hàng</a></li>
                </ul>
            </div>
            <div class="col-sm-6 col-lg-4">
                <h2>Hỗ trợ mua hàng</h2>
                <p>Xem thông tin phụ tùng và tồn kho trước khi đặt. Bạn có thể để lại yêu cầu hỗ trợ trong ghi chú đơn hàng.</p>
                <p class="mb-0"><i class="bi bi-truck" aria-hidden="true"></i> Giao hàng tận nơi · Thanh toán khi nhận hàng.</p>
            </div>
        </div>
        <div class="footer-bottom">© <?= date('Y') ?> MotoParts. Phụ tùng xe gắn máy.</div>
    </div>
</footer>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

<script
    src="/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/assets/js/main.js">
</script>

</body>
</html>
