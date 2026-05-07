<?php
require_once __DIR__ . '/../../../includes/database.php';

include __DIR__ . '/../../hatalar.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Gecersiz yazi ID'si.";
    header("Location: " . BASE_URL . "/admin/post");
    exit();
}

$post_id = (int)$_GET['id'];

$sql_check = "SELECT id FROM posts WHERE id = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "i", $post_id);
mysqli_stmt_execute($stmt_check);
mysqli_stmt_store_result($stmt_check);

if (mysqli_stmt_num_rows($stmt_check) === 0) {
    $_SESSION['error'] = "Bu ID'ye sahip bir yazi bulunamadi.";
    header("Location: " . BASE_URL . "/admin/post");
    exit();
}

$sql_delete = "DELETE FROM posts WHERE id = ?";
$stmt_delete = mysqli_prepare($conn, $sql_delete);
mysqli_stmt_bind_param($stmt_delete, "i", $post_id);

if (mysqli_stmt_execute($stmt_delete)) {
    $_SESSION['success'] = "Yazi basariyla silindi.";
} else {
    $_SESSION['error'] = "Yazi silinirken bir hata olustu.";
}

mysqli_stmt_close($stmt_check);
mysqli_stmt_close($stmt_delete);

header("Location: " . BASE_URL . "/admin/post");
exit();
?>
