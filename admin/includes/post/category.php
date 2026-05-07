<?php
ob_start();
require_once __DIR__ . '/../../../includes/database.php';

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

include __DIR__ . '/../../hatalar.php';

$edit_mode = false;
$category = ['id' => '', 'name' => '', 'slug' => '', 'description' => ''];

if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_mode = true;
    $cat_id = (int)$_GET['edit_id'];
    $query = "SELECT id, name, slug, description FROM categories WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $cat_id);
    mysqli_stmt_execute($stmt);
    $result_cat = mysqli_stmt_get_result($stmt);
    $category = mysqli_fetch_assoc($result_cat) ?: $category;
    mysqli_stmt_close($stmt);
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) $errors[] = "Kategori adi zorunlu.";
    if (empty($slug)) {
        $slug = strtolower(str_replace([' ', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['-', 'i', 'g', 'u', 's', 'o', 'c'], $name));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/^-|-$/', '', $slug);
    }

    $query = "SELECT id FROM categories WHERE slug = ? AND id != ?";
    $stmt = mysqli_prepare($conn, $query);
    $check_id = $edit_mode ? $cat_id : 0;
    mysqli_stmt_bind_param($stmt, 'si', $slug, $check_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) $errors[] = "Bu slug zaten kullaniliyor.";
    mysqli_stmt_close($stmt);

    if (empty($errors)) {
        if ($edit_mode) {
            $query = "UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssi', $name, $slug, $description, $cat_id);
        } else {
            $query = "INSERT INTO categories (name, slug, description, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sss', $name, $slug, $description);
        }
        if (mysqli_stmt_execute($stmt)) {
            $success = $edit_mode ? "Kategori guncellendi." : "Kategori olusturuldu.";
            $category = ['id' => '', 'name' => '', 'slug' => '', 'description' => ''];
            $edit_mode = false;
        } else {
            $errors[] = "Veritabani hatasi: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $query = "DELETE FROM categories WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $delete_id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Kategori basariyla silindi.";
    } else {
        $_SESSION['error'] = "Kategori silinirken bir hata olustu.";
    }
    mysqli_stmt_close($stmt);
    header("Location: " . BASE_URL . "/admin/includes/post/category");
    exit;
}

$result = mysqli_query($conn, "SELECT id, name, slug, description, created_at FROM categories ORDER BY created_at DESC");

include __DIR__ . '/../../header.php';
?>

<div class="page-header">
    <div>
        <h1><?php echo $edit_mode ? 'Kategori Duzenle' : 'Kategoriler'; ?></h1>
        <p>Blog kategorilerinizi yonetin.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/admin/post" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Yazilara Don</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i>
        <?php foreach ($errors as $error): ?><span><?php echo htmlspecialchars($error); ?></span> <?php endforeach; ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-<?php echo $edit_mode ? 'edit' : 'plus-circle'; ?>" style="margin-right: 8px; color: var(--primary);"></i> <?php echo $edit_mode ? 'Kategori Duzenle' : 'Yeni Kategori'; ?></h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Kategori Adi <span class="required">*</span></label>
                    <input type="text" name="name" id="catName" class="form-control" value="<?php echo htmlspecialchars($category['name']); ?>" placeholder="Kategori adini girin" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" id="catSlug" class="form-control" value="<?php echo htmlspecialchars($category['slug']); ?>" placeholder="Otomatik olusturulur">
                </div>
                <div class="form-group">
                    <label class="form-label">Aciklama</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Kategori aciklamasi"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> <?php echo $edit_mode ? 'Guncelle' : 'Kategori Ekle'; ?></button>
                <?php if ($edit_mode): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/includes/post/category" class="btn btn-secondary btn-block mt-1">Iptal</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Mevcut Kategoriler</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ad</th>
                            <th>Slug</th>
                            <th>Tarih</th>
                            <th style="text-align: right;">Islemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td style="font-weight: 500;"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['slug']); ?></span></td>
                                <td style="white-space: nowrap;"><?php echo date('d.m.Y', strtotime($row['created_at'])); ?></td>
                                <td style="text-align: right;">
                                    <div class="btn-group">
                                        <a href="?edit_id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu kategoriyi silmek istediginizden emin misiniz?');"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="table-empty"><i class="fas fa-folder-open"></i>Henuz kategori yok</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('catName').addEventListener('input', function() {
    var slugField = document.getElementById('catSlug');
    if (!slugField.value || slugField.value === slugField.dataset.lastGenerated) {
        var slug = this.value.toLowerCase()
            .replace(/[ı]/g, 'i').replace(/[ğ]/g, 'g').replace(/[ü]/g, 'u')
            .replace(/[ş]/g, 's').replace(/[ö]/g, 'o').replace(/[ç]/g, 'c')
            .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        slugField.value = slug;
        slugField.dataset.lastGenerated = slug;
    }
});
</script>

<?php
mysqli_free_result($result);
include __DIR__ . '/../../footer.php';
ob_end_flush();
?>
