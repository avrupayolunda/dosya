<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'your_database';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die('Veritabani baglanti hatasi: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $protocol . '://' . $host);
}
?>
