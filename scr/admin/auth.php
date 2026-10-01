<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) define('MOTOPARTS_MVC_ENTRY', true);
require_once __DIR__ . '/../app/bootstrap.php';
$baseUrl = \MotoParts\App\Middleware\Authenticate::BASE_URL;
\MotoParts\App\Middleware\RequireRole::enforce('admin');
$conn = \MotoParts\App\Middleware\Authenticate::connection();
