<?php
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
// Also protects helpers/templates when requested directly.
ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require_once dirname(__DIR__, 2) . '/Middleware/admin.php';
} catch (Throwable $e) {
    http_response_code(503);
    exit('Không thể kết nối hệ thống. Vui lòng thử lại sau.');
}
require_once __DIR__ . '/product-validation.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function product_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function product_redirect(string $path): void
{
    global $baseUrl;
    header('Location: ' . $baseUrl . '/admin/' . $path, true, 303);
    exit;
}

function product_image_url(string $filename): string
{
    global $baseUrl;
    return $baseUrl . '/assets/images/products/' . rawurlencode(basename($filename));
}
