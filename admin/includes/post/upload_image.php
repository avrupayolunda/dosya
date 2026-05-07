<?php
ob_start();
require_once __DIR__ . '/../database.php';

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Oturum gerekli']);
    exit;
}

$upload_dir = __DIR__ . '/../../../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// GET: Gorselleri listele (media tablosundan)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'list') {
    ob_clean();
    header('Content-Type: application/json');

    $images = [];
    $result = mysqli_query($conn, "SELECT * FROM media ORDER BY created_at DESC");

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $images[] = [
                'id' => (int)$row['id'],
                'name' => $row['filename'],
                'url' => BASE_URL . '/' . $row['filepath'],
                'filepath' => $row['filepath'],
                'size' => (int)$row['filesize'],
                'width' => $row['width'] ? (int)$row['width'] : null,
                'height' => $row['height'] ? (int)$row['height'] : null,
                'alt_text' => $row['alt_text'] ?? '',
                'filetype' => $row['filetype'],
                'date' => $row['created_at']
            ];
        }
        mysqli_free_result($result);
    }

    echo json_encode($images);
    exit;
}

// POST: Gorsel sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    ob_clean();
    header('Content-Type: application/json');

    $media_id = (int)($_POST['media_id'] ?? 0);
    if ($media_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Gecersiz media ID']);
        exit;
    }

    $stmt = mysqli_prepare($conn, "SELECT filepath FROM media WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $media_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $media = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$media) {
        http_response_code(404);
        echo json_encode(['error' => 'Gorsel bulunamadi']);
        exit;
    }

    $file_path = __DIR__ . '/../../../' . $media['filepath'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM media WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $media_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true, 'message' => 'Gorsel silindi']);
    exit;
}

// POST: Alt text guncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_alt') {
    ob_clean();
    header('Content-Type: application/json');

    $media_id = (int)($_POST['media_id'] ?? 0);
    $alt_text = trim($_POST['alt_text'] ?? '');

    if ($media_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Gecersiz media ID']);
        exit;
    }

    $stmt = mysqli_prepare($conn, "UPDATE media SET alt_text = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $alt_text, $media_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true]);
    exit;
}

// POST: Gorsel yukle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Dosya yuklenemedi.']);
        exit;
    }

    $file = $_FILES['file'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $max_size = 10 * 1024 * 1024;

    if (!in_array($file['type'], $allowed_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Gecersiz dosya turu.']);
        exit;
    }

    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['error' => 'Dosya boyutu 10MB sinirini asiyor.']);
        exit;
    }

    $original_name = $file['name'];
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $filename = uniqid('img_') . '_' . time();
    $saved_path = '';
    $saved_type = $file['type'];
    $saved_size = $file['size'];
    $width = null;
    $height = null;

    // WebP donusturme
    if (function_exists('imagewebp') && in_array($ext, ['jpg', 'jpeg', 'png'])) {
        $source = null;
        switch ($file['type']) {
            case 'image/jpeg':
                $source = @imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $source = @imagecreatefrompng($file['tmp_name']);
                break;
        }

        if ($source) {
            $webp_path = $upload_dir . $filename . '.webp';
            imagewebp($source, $webp_path, 85);
            imagedestroy($source);

            $saved_path = 'uploads/' . $filename . '.webp';
            $saved_type = 'image/webp';
            $saved_size = filesize($webp_path);
            $original_name = pathinfo($original_name, PATHINFO_FILENAME) . '.webp';

            $info = @getimagesize($webp_path);
            if ($info) {
                $width = $info[0];
                $height = $info[1];
            }
        }
    }

    // WebP donusum yapilmadiysa orijinal kaydet
    if (empty($saved_path)) {
        $dest_path = $upload_dir . $filename . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dest_path)) {
            $saved_path = 'uploads/' . $filename . '.' . $ext;

            $info = @getimagesize($dest_path);
            if ($info) {
                $width = $info[0];
                $height = $info[1];
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Dosya kaydedilemedi.']);
            exit;
        }
    }

    // Veritabanina kaydet
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = mysqli_prepare($conn, "INSERT INTO media (filename, filepath, filetype, filesize, width, height, alt_text, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, '', ?, NOW())");
    mysqli_stmt_bind_param($stmt, 'sssiiii', $original_name, $saved_path, $saved_type, $saved_size, $width, $height, $user_id);
    mysqli_stmt_execute($stmt);
    $media_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    echo json_encode([
        'location' => BASE_URL . '/' . $saved_path,
        'success' => true,
        'media_id' => $media_id,
        'filepath' => $saved_path
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Gecersiz istek metodu.']);
exit;
?>
