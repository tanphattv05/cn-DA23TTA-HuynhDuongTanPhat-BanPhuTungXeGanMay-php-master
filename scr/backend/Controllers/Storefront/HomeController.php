<?php
namespace MotoParts\App\Controllers\Storefront;

use MotoParts\App\Core\View;

if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

final class HomeController
{
    public function index(): void
    {
        ini_set('display_errors', '0');
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user = $_SESSION['user'] ?? null;
        View::storefront('storefront/home/index', compact('user'));
    }
}
