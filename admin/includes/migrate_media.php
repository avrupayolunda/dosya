<?php
require_once __DIR__ . '/database.php';

$sql = "CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    filetype VARCHAR(100) NOT NULL DEFAULT '',
    filesize BIGINT NOT NULL DEFAULT 0,
    width INT DEFAULT NULL,
    height INT DEFAULT NULL,
    alt_text VARCHAR(500) DEFAULT '',
    uploaded_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_filetype (filetype)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($conn, $sql)) {
    echo "media tablosu basariyla olusturuldu.\n";
} else {
    echo "Hata: " . mysqli_error($conn) . "\n";
}

$check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM media");
$row = mysqli_fetch_assoc($check);

if ($row['cnt'] == 0) {
    $upload_dir = __DIR__ . '/../../uploads/';
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $imported = 0;

    $scan_dirs = [$upload_dir, $upload_dir . 'images/'];

    foreach ($scan_dirs as $dir) {
        if (!is_dir($dir)) continue;
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_extensions)) continue;

            $full_path = $dir . $file;
            $relative = str_replace(__DIR__ . '/../../', '', $full_path);
            $filesize = filesize($full_path);
            $filetype = mime_content_type($full_path) ?: '';
            $date = date('Y-m-d H:i:s', filemtime($full_path));

            $width = null;
            $height = null;
            $info = @getimagesize($full_path);
            if ($info) {
                $width = $info[0];
                $height = $info[1];
            }

            $stmt = mysqli_prepare($conn, "INSERT INTO media (filename, filepath, filetype, filesize, width, height, alt_text, created_at) VALUES (?, ?, ?, ?, ?, ?, '', ?)");
            mysqli_stmt_bind_param($stmt, 'sssiiss', $file, $relative, $filetype, $filesize, $width, $height, $date);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $imported++;
        }
    }

    echo "Mevcut $imported gorsel media tablosuna aktarildi.\n";
} else {
    echo "media tablosunda zaten " . $row['cnt'] . " kayit var, aktarim yapilmadi.\n";
}

echo "\nTamamlandi.\n";
?>
