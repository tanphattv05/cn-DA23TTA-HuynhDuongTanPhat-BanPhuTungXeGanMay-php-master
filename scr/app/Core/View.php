<?php
namespace MotoParts\App\Core;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class View
{
    public static function admin(string $template, array $variables): void
    {
        // Template names are code-owned, never taken from a request.
        if (!preg_match('~\Aadmin/[a-z0-9/-]+\z~', $template) || strpos($template, '..') !== false) {
            throw new \InvalidArgumentException('Invalid view.');
        }
        $file = dirname(__DIR__) . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View unavailable.');
        }
        global $baseUrl;
        extract($variables, EXTR_SKIP);
        $adminDirectory = dirname(__DIR__, 2) . '/admin';
        require $adminDirectory . '/includes/header.php';
        require $file;
        require $adminDirectory . '/includes/footer.php';
    }

    public static function storefront(string $template, array $variables): void
    {
        if (!preg_match('~\Astorefront/[a-z0-9/-]+\z~', $template) || strpos($template, '..') !== false) {
            throw new \InvalidArgumentException('Invalid view.');
        }
        $file = dirname(__DIR__) . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View unavailable.');
        }
        extract($variables, EXTR_SKIP);
        $includesDirectory = dirname(__DIR__, 2) . '/includes';
        require $includesDirectory . '/header.php';
        require $includesDirectory . '/navbar.php';
        require $file;
        require $includesDirectory . '/footer.php';
    }
}
