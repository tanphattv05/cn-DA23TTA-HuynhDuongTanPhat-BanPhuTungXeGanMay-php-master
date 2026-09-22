<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) {
    http_response_code(403);
    exit;
}

// Local namespace-to-file mapping; no dependency manager or rewrite required.
spl_autoload_register(static function (string $class): void {
    $prefix = 'MotoParts\\App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    if (!preg_match('/\A[A-Za-z0-9_\\\\]+\z/', $relative)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
