<?php
require_once __DIR__ . '/../database.php';
include __DIR__ . '/../../hatalar.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Gecersiz sayfa ID'si.";
    header("Location: " . BASE_URL . "/admin/page");
    exit;
}

$page_id = (int)$_GET['id'];

$sql_check = "SELECT id, featured_image FROM pages WHERE id = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, 'i', $page_id);
mysqli_stmt_execute($stmt_check);
$result = mysqli_stmt_get_result($stmt_check);
$page = mysqli_fetch_assoc($result);

if (!$page) {
    $_SESSION['error'] = "Bu ID'ye sahip bir sayfa bulunamadi.";
    header("Location: " . BASE_URL . "/admin/page");
    exit;
}

if ($page['featured_image'] && file_exists(__DIR__ . '/../../../' . $page['featured_image'])) {
    unlink(__DIR__ . '/../../../' . $page['featured_image']);
}

$sql_delete = "DELETE FROM pages WHERE id = ?";
$stmt_delete = mysqli_prepare($conn, $sql_delete);
mysqli_stmt_bind_param($stmt_delete, 'i', $page_id);

if (mysqli_stmt_execute($stmt_delete)) {
    $_SESSION['success'] = "Sayfa basariyla silindi.";
} else {
    $_SESSION['error'] = "Sayfa silinirken bir hata olustu.";
}

mysqli_stmt_close($stmt_check);
mysqli_stmt_close($stmt_delete);

header("Location: " . BASE_URL . "/admin/page");
exit;
?>
