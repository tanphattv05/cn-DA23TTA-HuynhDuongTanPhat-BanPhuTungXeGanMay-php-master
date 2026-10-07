<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } ?>
<div class="home-page">
    <section class="home-hero">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <p class="eyebrow">MOTOPARTS / ĐỒNG HÀNH CÙNG CHIẾC XE CỦA BẠN</p>
                    <h1><span class="hero-title-line">PHỤ TÙNG XE MÁY</span> <span class="hero-title-line">CHÍNH HÃNG</span></h1>
                    <p class="hero-description">Chăm xe đúng cách. An tâm mỗi chặng đường. Khám phá phụ tùng chất lượng với giá cả hợp lý và giao hàng tận nơi.</p>
                    <?php if ($user !== null): ?>
                        <div class="hero-welcome">Xin chào <strong><?= htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8') ?></strong>, cùng tìm phụ tùng cho hành trình tiếp theo.</div>
                    <?php endif; ?>
                    <a href="<?= $baseUrl ?>/pages/products.php" class="btn btn-danger btn-lg">Xem sản phẩm <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    <div class="hero-caption"><i class="bi bi-shield-check" aria-hidden="true"></i> Chọn phụ tùng phù hợp. Giữ xe luôn sẵn sàng.</div>
                </div>
                <div class="col-lg-5">
                    <div class="hero-machine" aria-hidden="true">
                        <div class="machine-ring"><i class="bi bi-gear-wide-connected"></i></div>
                        <span class="machine-label">CHẤT LƯỢNG TỪNG CHI TIẾT</span>
                        <span class="machine-number">MP / 01</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="benefits-section" aria-labelledby="benefits-title">
        <div class="container">
            <h2 id="benefits-title" class="visually-hidden">Mua sắm cùng MotoParts</h2>
            <div class="row g-4">
                <?php foreach ([['patch-check','Phụ tùng chính hãng','Chất lượng cho chiếc xe của bạn.'],['tag','Giá cả hợp lý','Dễ dàng chọn theo nhu cầu.'],['truck','Giao hàng tận nơi','Thuận tiện trên mọi hành trình.'],['chat-dots','Hỗ trợ khách hàng','Lắng nghe nhu cầu chăm sóc xe.']] as [$icon,$title,$text]): ?>
                <div class="col-sm-6 col-lg-3"><div class="benefit"><i class="bi bi-<?= $icon ?>" aria-hidden="true"></i><div><h3><?= $title ?></h3><p><?= $text ?></p></div></div></div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <section class="home-categories container" aria-labelledby="explore-title">
        <div class="section-heading"><div><p class="eyebrow">CHĂM SÓC XE TỪ NHỮNG ĐIỀU NHỎ</p><h2 id="explore-title">Sẵn sàng cho chặng đường mới</h2></div><a href="<?= $baseUrl ?>/pages/products.php">Khám phá sản phẩm <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
        <div class="row g-4">
            <?php foreach ([['droplet','Bảo dưỡng định kỳ','Dầu nhớt và những chi tiết cần chăm sóc thường xuyên.'],['lightning-charge','Vận hành ổn định','Tìm hiểu phụ tùng điện, ắc quy và hệ thống đánh lửa.'],['disc','An tâm di chuyển','Chăm sóc lốp xe và các bộ phận phục vụ hành trình.']] as [$icon,$title,$text]): ?>
            <div class="col-md-4"><article class="category-teaser"><i class="bi bi-<?= $icon ?>" aria-hidden="true"></i><h3><?= $title ?></h3><p><?= $text ?></p><a href="<?= $baseUrl ?>/pages/products.php">Xem danh sách phụ tùng <i class="bi bi-arrow-right" aria-hidden="true"></i></a></article></div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
