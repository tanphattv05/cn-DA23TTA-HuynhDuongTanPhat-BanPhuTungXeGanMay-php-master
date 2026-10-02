<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } ?>
<main>
    <section class="bg-light py-5">
        <div class="container text-center py-5">
            <h1 class="display-4 fw-bold">
                PHỤ TÙNG XE MÁY CHÍNH HÃNG
            </h1>

            <p class="lead text-muted">
                Sản phẩm chất lượng, giá cả hợp lý và giao hàng tận nơi
            </p>

            <?php if ($user !== null): ?>
                <div class="alert alert-success mx-auto mt-4"
                     style="max-width: 600px;">
                    Đăng nhập thành công. Xin chào
                    <strong>
                        <?= htmlspecialchars(
                            $user['fullname'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>!
                </div>
            <?php endif; ?>

            <a
                href="/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/pages/products.php"
                class="btn btn-danger btn-lg mt-3">
                Xem sản phẩm
            </a>
        </div>
    </section>
</main>
