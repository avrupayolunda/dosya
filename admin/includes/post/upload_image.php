<?php
ob_start();
require_once __DIR__ . '/../../../includes/database.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'list') {
    ob_clean();
    header('Content-Type: application/json');

    $images = [];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    if (is_dir($upload_dir)) {
        $files = scandir($upload_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_extensions)) {
                $images[] = [
                    'name' => $file,
                    'url' => BASE_URL . '/uploads/' . $file,
                    'size' => filesize($upload_dir . $file),
                    'date' => date('Y-m-d H:i:s', filemtime($upload_dir . $file))
                ];
            }
        }

        $images_subdir = $upload_dir . 'images/';
        if (is_dir($images_subdir)) {
            $subfiles = scandir($images_subdir);
            foreach ($subfiles as $file) {
                if ($file === '.' || $file === '..') continue;
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed_extensions)) {
                    $images[] = [
                        'name' => $file,
                        'url' => BASE_URL . '/uploads/images/' . $file,
                        'size' => filesize($images_subdir . $file),
                        'date' => date('Y-m-d H:i:s', filemtime($images_subdir . $file))
                    ];
                }
            }
        }
    }

    usort($images, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    echo json_encode($images);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('img_') . '_' . time();

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

            echo json_encode([
                'location' => BASE_URL . '/uploads/' . $filename . '.webp',
                'success' => true
            ]);
            exit;
        }
    }

    $dest_path = $upload_dir . $filename . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        echo json_encode([
            'location' => BASE_URL . '/uploads/' . $filename . '.' . $ext,
            'success' => true
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode(['error' => 'Dosya kaydedilemedi.']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Gecersiz istek metodu.']);
exit;
?>
