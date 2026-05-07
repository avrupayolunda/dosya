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

    $original_name = $file['name'];
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $filename = uniqid('post_') . '_' . time();
    $saved_path = '';
    $saved_type = $file['type'];
    $saved_size = $file['size'];
    $width = null;
    $height = null;

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
            $saved_path = 'uploads/' . $filename . '.webp';
            $saved_type = 'image/webp';
            $saved_size = filesize($webp_path);
            $original_name = pathinfo($original_name, PATHINFO_FILENAME) . '.webp';

            $info = @getimagesize($webp_path);
            if ($info) { $width = $info[0]; $height = $info[1]; }
        }
    }

    if (empty($saved_path)) {
        $dest_path = $upload_dir . $filename . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dest_path)) {
            $saved_path = 'uploads/' . $filename . '.' . $ext;
            $info = @getimagesize($dest_path);
            if ($info) { $width = $info[0]; $height = $info[1]; }
        } else {
            return ['success' => false, 'error' => 'Dosya yuklenirken bir hata olustu.'];
        }
    }

    // Media tablosuna kaydet
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = mysqli_prepare($conn, "INSERT INTO media (filename, filepath, filetype, filesize, width, height, alt_text, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, '', ?, NOW())");
    mysqli_stmt_bind_param($stmt, 'sssiiii', $original_name, $saved_path, $saved_type, $saved_size, $width, $height, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return ['success' => true, 'path' => $saved_path];
}
?>
