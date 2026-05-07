<?php
ob_start();
require_once __DIR__ . '/../database.php';

if (!isset($_SESSION)) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_tag_suggestions') {
    ob_clean();
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Oturum gerekli']);
        exit;
    }
    $term = trim($_POST['term'] ?? '');
    if (strlen($term) < 2) {
        echo json_encode([]);
        exit;
    }

    $query = "SELECT name FROM tags WHERE name LIKE ? LIMIT 10";
    $stmt = mysqli_prepare($conn, $query);
    $search_term = "%$term%";
    mysqli_stmt_bind_param($stmt, 's', $search_term);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $tags = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tags[] = $row['name'];
    }
    mysqli_stmt_close($stmt);

    echo json_encode($tags);
    exit;
}

echo json_encode(['error' => 'Gecersiz istek']);
exit;
?>
