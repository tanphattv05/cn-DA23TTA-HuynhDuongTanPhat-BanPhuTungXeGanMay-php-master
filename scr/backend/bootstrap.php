<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

// Keep the existing namespace while modules move one at a time.
spl_autoload_register(static function (string $class): void {
    $prefix = 'MotoParts\\App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $relative = substr($class, strlen($prefix));
    if (!preg_match('/\A[A-Za-z0-9_\\\\]+\z/', $relative)) return;
    $path = str_replace('\\', '/', $relative) . '.php';
    foreach ([__DIR__, dirname(__DIR__) . '/app'] as $root) {
        if (is_file($root . '/' . $path)) {
            require_once $root . '/' . $path;
            return;
        }
    }
});
