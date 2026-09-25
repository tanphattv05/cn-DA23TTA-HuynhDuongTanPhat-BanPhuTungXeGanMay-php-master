<?php
define('MOTOPARTS_MVC_ENTRY', true);
require_once __DIR__ . '/../app/bootstrap.php';
(new \MotoParts\App\Controllers\Storefront\CheckoutController())->index();