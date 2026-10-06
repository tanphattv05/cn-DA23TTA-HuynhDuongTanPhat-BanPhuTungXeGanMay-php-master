<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }

// Preserve the public PHP namespace; all classes now live in backend.
spl_autoload_register(static function (string $class): void {
    $prefix = 'MotoParts\\App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $relative = substr($class, strlen($prefix));
    if (!preg_match('/\A[A-Za-z0-9_\\\\]+\z/', $relative)) return;
    $path = str_replace('\\', '/', $relative) . '.php';
    if (is_file(__DIR__ . '/' . $path)) require_once __DIR__ . '/' . $path;
});
