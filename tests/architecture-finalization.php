<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!isset($app, $db)) {
    if (!in_array('--isolated', $argv, true)) exit('Run: php tests/architecture-finalization.php --isolated');
    require __DIR__ . '/product-management.php';
    exit;
}
$architectureStart=$checks;
foreach (['app/bootstrap.php','app/Core/View.php','includes/header.php','includes/navbar.php','includes/footer.php','admin/auth.php','admin/navbar.php','test_database.php','config/database.php'] as $old) {
    check(!is_file($app.'/'.$old), 'Retired runtime removed: '.$old);
    check(request($old)[0]===404, 'Retired URL no longer executes: '.$old);
}
check(!str_contains(file_get_contents($app.'/backend/bootstrap.php'),"'/app'"), 'Autoload has no legacy fallback');
check(!str_contains(file_get_contents($app.'/backend/Core/View.php'),'/app/Views'), 'Renderer frontend only');
foreach (['backend/config/database.php','backend/Support/Admin/product-upload.php','backend/Support/Admin/product-validation.php'] as $path) check(request($path)[0]===403,'Final internal path protected: '.$path);
foreach (array_merge(glob($app.'/pages/*.php'),glob($app.'/actions/*.php'),glob($app.'/admin/*.php'),[$app.'/index.php']) as $entry) {
    $source=file_get_contents($entry);
    check(strlen($source)<400 && str_contains($source,'backend/bootstrap.php') && str_contains($source,'Controller') && !preg_match('/SELECT |mysqli_|<html|<form/', $source),'Uniform public entry: '.basename($entry));
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app.'/frontend',FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension()!=='php') continue;
    check(!preg_match('/mysqli_|\b(?:SELECT .+ FROM|INSERT INTO|UPDATE .+ SET)\b|\$_POST/',file_get_contents($file->getPathname())),'Frontend no SQL/POST: '.$file->getFilename());
}
// Verify the install artifact before executing anything, then import only into
// a uniquely named empty database. Never run its DDL on the application DB.
$schemaSql = file_get_contents($root . '/docs/database-schema.sql');
$schemaBody = preg_replace('/^--[^\r\n]*(?:\r?\n|$)/m', '', $schemaSql);
preg_match_all('/CREATE TABLE `(categories|users|products|orders|order_details)` \([\s\S]*?\) ENGINE=InnoDB[^;]*;/',$schemaBody,$schemaParts,PREG_SET_ORDER);
$schemaOrder = array_column($schemaParts, 1);
check($schemaOrder === ['categories','users','products','orders','order_details'], 'Schema has only five dependency-ordered tables');
$schemaRemainder = $schemaBody;
foreach ($schemaParts as $part) $schemaRemainder = str_replace($part[0], '', $schemaRemainder);
check(trim($schemaRemainder) === '', 'Schema has no data, extra statements or credentials');
$normalizeSchema = static fn(string $sql): string => preg_replace('/\s+/', ' ', trim(preg_replace('/ AUTO_INCREMENT=\d+/', '', rtrim($sql, ';'))));
foreach ($schemaParts as $part) {
    $actual = $live->query('SHOW CREATE TABLE `' . $part[1] . '`')->fetch_row()[1];
    check($normalizeSchema($actual) === $normalizeSchema($part[0]), 'Install schema matches real columns keys enums collation: ' . $part[1]);
}
$schemaDb = 'motoparts_schema_' . bin2hex(random_bytes(6));
$schemaConnection = null;
$live->query('CREATE DATABASE `' . $schemaDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
try {
    $schemaConnection = new mysqli($host, $username, $password, $schemaDb);
    foreach ($schemaParts as $part) $schemaConnection->query($part[0]);
    check($schemaConnection->query('SHOW TABLES')->num_rows === 5, 'Schema imports successfully in empty isolated database');
    foreach ($schemaOrder as $table) check((int)$schemaConnection->query('SELECT COUNT(*) FROM `' . $table . '`')->fetch_row()[0] === 0, 'Install schema contains no records: ' . $table);
} finally {
    if ($schemaConnection) $schemaConnection->close();
    $live->query('DROP DATABASE `' . $schemaDb . '`');
}
echo 'Final architecture checks completed: '.($checks-$architectureStart).PHP_EOL;
