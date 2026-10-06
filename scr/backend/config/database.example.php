<?php
// Copy to database.php and configure locally. Never distribute that local file.
if (!defined('MOTOPARTS_MVC_ENTRY')) { http_response_code(403); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$host = 'localhost';
$username = '';
$password = '';
$database = 'phutung_xemay';
$conn = mysqli_connect($host, $username, $password, $database);
mysqli_set_charset($conn, 'utf8mb4');
