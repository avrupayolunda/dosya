<?php
require_once 'header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'page_title' => $_POST['page_title'] ?? '',
        'meta_description' => $_POST['meta_description'] ?? '',
        'og_image' => $_POST['og_image'] ?? '',
        'site_name' => $_POST['site_name'] ?? '',
        'google_verification' => $_POST['google_verification'] ?? '',
        'site_logo' => $settings['site_logo'] ?? '',
        'site_favicon' => $settings['site_favicon'] ?? ''
    ];

    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['site_logo'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . '/../uploads/images/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'site_logo_' . uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                if (!empty($settings['site_logo']) && file_exists(__DIR__ . '/../' . $settings['site_logo'])) {
                    unlink(__DIR__ . '/../' . $settings['site_logo']);
                }
                $settings['site_logo'] = 'uploads/images/' . $filename;
            }
        }
    }

    if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['site_favicon'];
        $allowed = ['ico', 'png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . '/../uploads/images/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'favicon_' . uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                if (!empty($settings['site_favicon']) && file_exists(__DIR__ . '/../' . $settings['site_favicon'])) {
                    unlink(__DIR__ . '/../' . $settings['site_favicon']);
                }
                $settings['site_favicon'] = 'uploads/images/' . $filename;
            }
        }
    }

    if (isset($_POST['remove_logo'])) {
        if (!empty($settings['site_logo']) && file_exists(__DIR__ . '/../' . $settings['site_logo'])) {
            unlink(__DIR__ . '/../' . $settings['site_logo']);
        }
        $settings['site_logo'] = '';
    }

    if (isset($_POST['remove_favicon'])) {
        if (!empty($settings['site_favicon']) && file_exists(__DIR__ . '/../' . $settings['site_favicon'])) {
            unlink(__DIR__ . '/../' . $settings['site_favicon']);
        }
        $settings['site_favicon'] = '';
    }

    foreach ($settings as $key => $value) {
        $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'sss', $key, $value, $value);
        mysqli_stmt_execute($stmt);
    }
    
    header('Location: settings.php?saved=1');
    exit;
}

$settings_query = "SELECT setting_key, setting_value FROM settings";
$settings_result = mysqli_query($conn, $settings_query);
$settings = [];
while ($row = mysqli_fetch_assoc($settings_result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="page-header">
    <div>
        <h1>Site Ayarlari</h1>
        <p>Genel site ayarlarinizi yapilandirin.</p>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Ayarlar basariyla kaydedildi.</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <div class="card mb-3">
        <div class="card-header">
            <h3><i class="fas fa-globe" style="margin-right: 8px; color: var(--primary);"></i> Genel Ayarlar</h3>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Sayfa Basligi <span class="required">*</span></label>
                    <input type="text" class="form-control" name="page_title" value="<?php echo htmlspecialchars($settings['page_title'] ?? ''); ?>" placeholder="Sayfa basligini girin" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Site Adi <span class="required">*</span></label>
                    <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" placeholder="Site adini girin" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Meta Aciklama</label>
                <textarea class="form-control" name="meta_description" rows="3" placeholder="Meta aciklamasini girin"><?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3><i class="fas fa-image" style="margin-right: 8px; color: var(--success);"></i> Gorseller</h3>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Site Logo</label>
                    <div class="file-upload" id="logoUpload">
                        <input type="file" name="site_logo" accept="image/png,image/jpeg,image/gif,image/svg+xml" onchange="updateFileName(this, 'logoName')">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Logo yuklemek icin tiklayin</p>
                        <span class="file-name" id="logoName"></span>
                    </div>
                    <?php if (!empty($settings['site_logo'])): ?>
                    <div class="file-preview mt-1">
                        <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($settings['site_logo']); ?>" alt="Logo">
                        <button type="submit" name="remove_logo" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Kaldir</button>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Site Favicon</label>
                    <div class="file-upload" id="faviconUpload">
                        <input type="file" name="site_favicon" accept="image/x-icon,image/png" onchange="updateFileName(this, 'faviconName')">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Favicon yuklemek icin tiklayin</p>
                        <span class="file-name" id="faviconName"></span>
                    </div>
                    <?php if (!empty($settings['site_favicon'])): ?>
                    <div class="file-preview mt-1">
                        <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($settings['site_favicon']); ?>" alt="Favicon" style="max-height: 32px;">
                        <button type="submit" name="remove_favicon" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Kaldir</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3><i class="fas fa-search" style="margin-right: 8px; color: var(--warning);"></i> SEO Ayarlari</h3>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">OG Gorsel URL</label>
                    <input type="text" class="form-control" name="og_image" value="<?php echo htmlspecialchars($settings['og_image'] ?? ''); ?>" placeholder="OG gorsel URL'si girin">
                </div>
                <div class="form-group">
                    <label class="form-label">Google Dogrulama Kodu</label>
                    <input type="text" class="form-control" name="google_verification" value="<?php echo htmlspecialchars($settings['google_verification'] ?? ''); ?>" placeholder="Google dogrulama kodu">
                </div>
            </div>
        </div>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 12px;">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Ayarlari Kaydet</button>
    </div>
</form>

<script>
function updateFileName(input, targetId) {
    var name = input.files[0] ? input.files[0].name : '';
    document.getElementById(targetId).textContent = name;
}
</script>

<?php require_once 'footer.php'; ?>
