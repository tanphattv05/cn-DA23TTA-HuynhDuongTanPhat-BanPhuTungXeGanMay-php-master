<?php if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; } ?>
<div class="container py-5">
    <h1>Không thể tiếp tục</h1>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <a class="btn btn-outline-dark" href="<?= $baseUrl ?>/pages/login.php">Quay lại đăng nhập</a>
</div>
