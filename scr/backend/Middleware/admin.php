<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';
$baseUrl = \MotoParts\App\Middleware\Authenticate::BASE_URL;
\MotoParts\App\Middleware\RequireRole::enforce('admin');
$conn = \MotoParts\App\Middleware\Authenticate::connection();
