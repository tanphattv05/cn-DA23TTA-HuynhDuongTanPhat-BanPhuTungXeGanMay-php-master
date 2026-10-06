<?php
// Harmless HTTP probes only. No database, real user, order or uploaded image writes.
if (PHP_SAPI !== 'cli' || !in_array('--isolated', $argv, true)) {
    http_response_code(403);
    exit('Run: php tests/apache-security.php --isolated');
}
$root = dirname(__DIR__);
$website = realpath($root . '/scr');
$base = 'http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/';
$probeName = '_httpcheck_' . bin2hex(random_bytes(8));
$probe = $website . DIRECTORY_SEPARATOR . $probeName;
$checks = 0;
function apache_check(string $url, int $expected, ?string $content = null): void {
    global $checks;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($status !== $expected || ($content !== null && $body !== $content)) throw new RuntimeException('Unexpected HTTP response for ' . $url . ': ' . $status);
    $checks++;
    echo 'PASS: HTTP ' . $expected . ' ' . $url . PHP_EOL;
}
if (!mkdir($probe)) throw new RuntimeException('Cannot reserve probe directory');
try {
    if (dirname(realpath($probe)) !== $website) throw new RuntimeException('Probe outside website');
    mkdir($probe . '/upload');
    mkdir($probe . '/empty');
    copy($website . '/assets/images/products/.htaccess', $probe . '/upload/.htaccess');
    file_put_contents($probe . '/control.php', '<?php echo "probe-control";');
    file_put_contents($probe . '/upload/probe.php', '<?php echo "must-never-execute";');
    file_put_contents($probe . '/upload/probe.phtml', '<?php echo "must-never-execute";');
    file_put_contents($probe . '/upload/image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $url = $base . 'scr/' . $probeName;
    apache_check($url . '/control.php', 200, 'probe-control');
    apache_check($url . '/upload/probe.php', 403);
    apache_check($url . '/upload/probe.phtml', 403);
    apache_check($url . '/upload/image.png', 200);
    apache_check($url . '/upload/', 403);
    apache_check($url . '/empty/', 403);
    foreach (['.git/HEAD', 'docs/database-schema.sql', 'tests/product-management.php', 'scr/backend/config/database.php', 'scr/frontend/includes/admin/header.php', 'scr/assets/', 'scr/admin/assets/'] as $path) apache_check($base . $path, 403);
    echo 'Completed ' . $checks . ' Apache security checks. No database touched.' . PHP_EOL;
} finally {
    // Delete only explicitly known files/directories created by this process.
    foreach (['control.php','upload/probe.php','upload/probe.phtml','upload/image.png','upload/.htaccess'] as $file) if (is_file($probe . '/' . $file)) unlink($probe . '/' . $file);
    foreach (['upload','empty'] as $dir) if (is_dir($probe . '/' . $dir)) rmdir($probe . '/' . $dir);
    rmdir($probe);
}
