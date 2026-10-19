<?php
if (PHP_SAPI !== 'cli' || !in_array('--isolated', $argv, true)) exit("Use --isolated\n");
define('MOTOPARTS_MVC_ENTRY', true);
require dirname(__DIR__).'/scr/backend/config/database.php';
require dirname(__DIR__).'/scr/backend/Models/StorefrontProduct.php';
$live=$conn; $name='motoparts_test_'.bin2hex(random_bytes(6)); $created=false;
try {
    $live->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4"); $created=true;
    $db=new mysqli($host,$username,$password,$name); $db->set_charset('utf8mb4');
    foreach(['categories','products'] as $table) $db->query($live->query('SHOW CREATE TABLE '.$table)->fetch_row()[1]);
    $model=new \MotoParts\App\Models\StorefrontProduct($db);
    $check=static function($ok){if(!$ok) throw new RuntimeException('Bounds check failed');};
    $check($model->priceBounds()===['minimum'=>null,'maximum'=>null]);
    $db->query("INSERT INTO categories(id,name) VALUES(1,'Fixture')");
    $db->query("INSERT INTO products(category_id,name,price,stock) VALUES(1,'A',123.45,1)");
    $check($model->priceBounds()===['minimum'=>'123.45','maximum'=>'123.45']);
    $db->query("INSERT INTO products(category_id,name,price,stock) VALUES(1,'B',3500000.25,0)");
    $check($model->priceBounds()===['minimum'=>'123.45','maximum'=>'3500000.25']);
    echo "3/3 isolated database bounds checks PASS\n";
} finally { if($created) $live->query("DROP DATABASE `$name`"); }
