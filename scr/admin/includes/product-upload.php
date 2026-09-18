<?php
require_once __DIR__ . '/product-bootstrap.php';

function product_upload(array $file): ?string
{
    if (!isset($file['error']) || !is_int($file['error'])) {
        throw new RuntimeException('Dữ liệu tải ảnh không hợp lệ.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Không tải được ảnh. Chỉ nhận ảnh tối đa 2 MB; vui lòng chọn lại.');
    }
    $tmp = $file['tmp_name'] ?? null;
    if (!is_string($tmp) || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Tệp tải lên không hợp lệ.');
    }
    $size = filesize($tmp);
    if ($size === false || $size < 1 || $size > 2 * 1024 * 1024) {
        throw new RuntimeException('Ảnh phải có dung lượng từ 1 byte đến 2 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info = @getimagesize($tmp);
    if (!isset($extensions[$mime]) || !$info || $info['mime'] !== $mime || $info[0] * $info[1] > 20000000) {
        throw new RuntimeException('Chỉ nhận ảnh JPEG, PNG hoặc WebP hợp lệ, tối đa 20 triệu điểm ảnh.');
    }
    // When GD is available, decode pixels in addition to MIME/header inspection.
    if (function_exists('imagecreatefromstring')) {
        $decoded = @imagecreatefromstring(file_get_contents($tmp));
        if ($decoded === false) {
            throw new RuntimeException('Nội dung ảnh bị hỏng hoặc không được hỗ trợ.');
        }
        imagedestroy($decoded);
    }
    $directory = __DIR__ . '/../../assets/images/products/';
    // Reserve exclusively so even a name collision cannot overwrite an image.
    do {
        $name = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
        $path = $directory . $name;
        $handle = @fopen($path, 'x');
        if ($handle === false && !file_exists($path)) {
            throw new RuntimeException('Không thể ghi ảnh vào thư mục sản phẩm.');
        }
    } while ($handle === false);
    fclose($handle);
    if (!move_uploaded_file($tmp, $path)) {
        @unlink($path);
        throw new RuntimeException('Không thể lưu ảnh tải lên.');
    }
    return $name;
}
