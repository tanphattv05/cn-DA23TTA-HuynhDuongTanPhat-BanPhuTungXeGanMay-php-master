<?php
namespace MotoParts\App\Core;

if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

final class View
{
    private static function template(string $template): string
    {
        // Callers validate the template name before resolution. No request paths.
        $websiteRoot = dirname(__DIR__, 2);
        $file = $websiteRoot . '/frontend/Views/' . $template . '.php';
        if (is_file($file)) return $file;
        throw new \RuntimeException('View unavailable.');
    }

    public static function admin(string $template, array $variables): void
    {
        // Template names are code-owned, never taken from a request.
        if (!preg_match('~\Aadmin/[a-z0-9/-]+\z~', $template) || strpos($template, '..') !== false) {
            throw new \InvalidArgumentException('Invalid view.');
        }
        $file = self::template($template);
        if (!is_file($file)) {
            throw new \RuntimeException('View unavailable.');
        }
        global $baseUrl;
        extract($variables, EXTR_SKIP);
        $adminDirectory = dirname(__DIR__, 2) . '/frontend/includes/admin';
        require $adminDirectory . '/header.php';
        require $file;
        require $adminDirectory . '/footer.php';
    }

    public static function storefront(string $template, array $variables): void
    {
        if (!preg_match('~\Astorefront/[a-z0-9/-]+\z~', $template) || strpos($template, '..') !== false) {
            throw new \InvalidArgumentException('Invalid view.');
        }
        $file = self::template($template);
        if (!is_file($file)) {
            throw new \RuntimeException('View unavailable.');
        }
        extract($variables, EXTR_SKIP);
        $includesDirectory = dirname(__DIR__, 2) . '/frontend/includes/storefront';
        require $includesDirectory . '/header.php';
        require $includesDirectory . '/navbar.php';
        require $file;
        require $includesDirectory . '/footer.php';
    }
}
