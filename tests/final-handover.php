<?php
// Read-only audit, except the separately invoked isolated Apache probe suite.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$options = getopt('', ['isolated', 'package-root:']);
if (!isset($options['isolated'])) { fwrite(STDERR, "Use --isolated [--package-root=directory]\n"); exit(1); }
$root = isset($options['package-root']) ? realpath($options['package-root']) : dirname(__DIR__);
if (!$root) exit(1);
$packaged = isset($options['package-root']);
$count = 0;
function verify(bool $ok, string $label): void {
    global $count;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $count++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function files_under(string $directory): array {
    return iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)));
}
try {
    foreach (['README.md','.htaccess','index.php','scr/index.php','scr/backend/bootstrap.php',
        'scr/backend/Core/View.php','scr/backend/config/database.example.php',
        'scr/backend/.htaccess','scr/frontend/.htaccess','scr/assets/images/products/.htaccess',
        'docs/database-schema.sql','docs/ARCHITECTURE.md','docs/INSTALLATION-XAMPP.md',
        'docs/SECURITY.md','docs/TESTING.md','docs/PROJECT-STRUCTURE.md','docs/FINAL-HANDOVER.md',
        'tools/build-handover.ps1','tests/final-handover.php'] as $path) verify(is_file($root.'/'.$path), 'Required '.$path);
    foreach (['scr/app','scr/includes','scr/admin/includes','scr/config'] as $path) verify(!file_exists($root.'/'.$path), 'Retired '.$path);
    foreach (files_under($root.'/scr') as $file) {
        if ($file->getExtension() !== 'php') continue;
        $body = file_get_contents($file->getPathname());
        verify(!preg_match('~(?:[\x27\x22])[^\r\n\x27\x22]*(?:/app/|/admin/includes/|/scr/includes/|/scr/config/)~', $body), 'No legacy path '.$file->getFilename());
        if (str_contains(str_replace('\\','/',$file->getPathname()), '/frontend/')) {
            verify(!preg_match('/mysqli_|\b(?:SELECT .+ FROM|INSERT INTO|UPDATE .+ SET)\b|\$_POST/', $body), 'View boundary '.$file->getFilename());
        }
    }
    foreach (array_merge(glob($root.'/scr/pages/*.php'),glob($root.'/scr/actions/*.php'),glob($root.'/scr/admin/*.php'),[$root.'/scr/index.php']) as $path) {
        $body = file_get_contents($path);
        verify(strlen($body)<400 && str_contains($body,'backend/bootstrap.php') && !preg_match('/mysqli_|\$_POST|SELECT /',$body), 'Thin entry '.basename($path));
    }
    $schema = preg_replace('/^--[^\r\n]*/m','',file_get_contents($root.'/docs/database-schema.sql'));
    preg_match_all('/CREATE TABLE `(categories|users|products|orders|order_details)` \([\s\S]*?\) ENGINE=InnoDB[^;]*;/', $schema, $tables);
    verify($tables[1] === ['categories','users','products','orders','order_details'] && trim(str_replace($tables[0],'',$schema)) === '', 'Schema is five DDL statements only');
    $example = file_get_contents($root.'/scr/backend/config/database.example.php');
    verify(str_contains($example, "\$username = '';") && str_contains($example, "\$password = '';"), 'Sample has no account credentials');
    $docs = array_merge([$root.'/README.md'], files_under($root.'/docs'), files_under($root.'/tests'));
    foreach ($docs as $file) {
        $path = (string)$file;
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'md') continue;
        preg_match_all('/\[[^\]]+\]\(([^)]+)\)/',file_get_contents($path),$links);
        foreach ($links[1] as $link) {
            if (preg_match('~^(?:https?://|#|mailto:)~',$link)) continue;
            $target = rawurldecode(explode('#',$link)[0]);
            verify(file_exists(dirname($path).'/'.$target), 'Document link '.basename($path).' -> '.$target);
        }
    }
    if ($packaged) {
        verify(!file_exists($root.'/scr/backend/config/database.php'), 'Local config excluded');
        foreach (files_under($root) as $file) {
            $path = str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
            verify(!preg_match('~(?:^|/)(?:\.git|dist|logs?|cache|sessions?|thesis|\.env[^/]*|sess_[^/]*|motoparts_(?:test|schema)[^/]*)(?:/|$)|\.(?:log|tmp|bak|zip|sqlite|db)$~i',$path)
                && ($file->getExtension() !== 'sql' || $path === 'docs/database-schema.sql')
                && (!str_starts_with($path,'scr/assets/images/products/') || basename($path)==='.htaccess'), 'Clean package '.$path);
        }
    } else {
        $base = 'http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/';
        $cases = ['scr/'=>200,'scr/pages/products.php'=>200,'scr/pages/login.php'=>200,
            'scr/pages/register.php'=>200,'scr/pages/cart.php'=>200,'scr/pages/my-orders.php'=>302,
            'scr/pages/checkout.php'=>303,'scr/pages/order-success.php'=>303,
            'scr/assets/css/style.css'=>200,'scr/assets/js/main.js'=>200,
            'scr/admin/assets/adminlte/css/adminlte.min.css'=>200,'scr/admin/assets/adminlte/js/adminlte.min.js'=>200,
            'scr/backend/config/database.php'=>403,'scr/backend/config/database.example.php'=>403,
            'scr/frontend/includes/storefront/header.php'=>403,'scr/app/bootstrap.php'=>404,
            'tools/build-handover.ps1'=>403,'dist/'=>403];
        foreach (glob($root.'/scr/admin/*.php') as $path) $cases['scr/admin/'.basename($path)] = 302;
        foreach ($cases as $path=>$expected) {
            $ch=curl_init($base.$path);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>10]);
            $response=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
            verify($response !== false && $status === $expected, 'Apache '.$expected.' '.$path);
            if ($expected === 302) verify((bool)preg_match('~Location: [^\r\n]*/pages/login\.php~i',$response), 'Redirect to login '.$path);
        }
        // Reuse actual upload protection probes, never write into real uploads.
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/apache-security.php').' --isolated', $status);
        verify($status === 0, 'Apache upload and directory protections (13 separate assertions)');
    }
    echo 'Completed '.$count.' final handover checks'.($packaged?' (package)':' (workspace)').'.'.PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); }
