<?php
require_once __DIR__ . '/../database.php';

function uploadFeaturedImage($file, $conn) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 10 * 1024 * 1024;

    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Gecersiz dosya turu. Sadece JPG, PNG, GIF ve WebP dosyalari kabul edilir.'];
    }

    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Dosya boyutu 10MB sinirini asiyor.'];
    }

    $upload_dir = __DIR__ . '/../../../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('post_') . '_' . time();

    if (function_exists('imagewebp') && $ext !== 'gif') {
        $source = null;
        switch ($file['type']) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $source = imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($file['tmp_name']);
                break;
        }

        if ($source) {
            $webp_path = $upload_dir . $filename . '.webp';
            imagewebp($source, $webp_path, 85);
            imagedestroy($source);
            return ['success' => true, 'path' => 'uploads/' . $filename . '.webp'];
        }
    }

    $dest_path = $upload_dir . $filename . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        return ['success' => true, 'path' => 'uploads/' . $filename . '.' . $ext];
    }

    return ['success' => false, 'error' => 'Dosya yuklenirken bir hata olustu.'];
}
?>
