<nav class="navbar navbar-expand-lg bg-dark" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand fw-bold"
           href="<?= $baseUrl ?>/admin/index.php">
            MotoParts Admin
        </a>

        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#adminNavbar"
                aria-controls="adminNavbar"
                aria-expanded="false"
                aria-label="Mở menu quản trị">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <div class="navbar-nav ms-auto align-items-lg-center">
                <a class="nav-link"
                   href="<?= $baseUrl ?>/admin/index.php">
                    Tổng quan
                </a>
                <a class="nav-link"
                href="<?= $baseUrl ?>/admin/orders.php">
                    Đơn hàng
                </a>
                <a class="nav-link" href="<?= $baseUrl ?>/">
                    Xem website
                </a>

                <span class="navbar-text mx-lg-2">
                    <?= htmlspecialchars(
                        $_SESSION['user']['fullname'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

                <a class="nav-link"
                   href="<?= $baseUrl ?>/actions/logout.php">
                    Đăng xuất
                </a>
            </div>
        </div>
    </div>
</nav>