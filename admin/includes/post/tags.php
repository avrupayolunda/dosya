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

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

include __DIR__ . '/../../hatalar.php';

$edit_mode = false;
$tag = ['id' => '', 'name' => '', 'slug' => ''];

if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_mode = true;
    $tag_id = (int)$_GET['edit_id'];
    $query = "SELECT id, name, slug FROM tags WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $tag_id);
    mysqli_stmt_execute($stmt);
    $result_tag = mysqli_stmt_get_result($stmt);
    $tag = mysqli_fetch_assoc($result_tag) ?: $tag;
    mysqli_stmt_close($stmt);
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');

    if (empty($name)) $errors[] = "Etiket adi zorunlu.";
    if (empty($slug)) {
        $slug = strtolower(str_replace([' ', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['-', 'i', 'g', 'u', 's', 'o', 'c'], $name));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/^-|-$/', '', $slug);
    }

    $query = "SELECT id FROM tags WHERE slug = ? AND id != ?";
    $stmt = mysqli_prepare($conn, $query);
    $check_id = $edit_mode ? $tag_id : 0;
    mysqli_stmt_bind_param($stmt, 'si', $slug, $check_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) $errors[] = "Bu slug zaten kullaniliyor.";
    mysqli_stmt_close($stmt);

    if (empty($errors)) {
        if ($edit_mode) {
            $query = "UPDATE tags SET name = ?, slug = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssi', $name, $slug, $tag_id);
        } else {
            $query = "INSERT INTO tags (name, slug, created_at) VALUES (?, ?, NOW())";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ss', $name, $slug);
        }
        if (mysqli_stmt_execute($stmt)) {
            $success = $edit_mode ? "Etiket guncellendi." : "Etiket olusturuldu.";
            $tag = ['id' => '', 'name' => '', 'slug' => ''];
            $edit_mode = false;
        } else {
            $errors[] = "Veritabani hatasi: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $query = "DELETE FROM post_tags WHERE tag_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $delete_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $query = "DELETE FROM tags WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $delete_id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Etiket basariyla silindi.";
    } else {
        $_SESSION['error'] = "Etiket silinirken bir hata olustu.";
    }
    mysqli_stmt_close($stmt);
    header("Location: " . BASE_URL . "/admin/includes/post/tags");
    exit;
}

$result = mysqli_query($conn, "SELECT id, name, slug, created_at FROM tags ORDER BY created_at DESC");

include __DIR__ . '/../../header.php';
?>

<div class="page-header">
    <div>
        <h1><?php echo $edit_mode ? 'Etiket Duzenle' : 'Etiketler'; ?></h1>
        <p>Blog etiketlerinizi yonetin.</p>
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
            <h3><i class="fas fa-<?php echo $edit_mode ? 'edit' : 'plus-circle'; ?>" style="margin-right: 8px; color: var(--info);"></i> <?php echo $edit_mode ? 'Etiket Duzenle' : 'Yeni Etiket'; ?></h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Etiket Adi <span class="required">*</span></label>
                    <input type="text" name="name" id="tagName" class="form-control" value="<?php echo htmlspecialchars($tag['name']); ?>" placeholder="Etiket adini girin" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" id="tagSlug" class="form-control" value="<?php echo htmlspecialchars($tag['slug']); ?>" placeholder="Otomatik olusturulur">
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> <?php echo $edit_mode ? 'Guncelle' : 'Etiket Ekle'; ?></button>
                <?php if ($edit_mode): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/includes/post/tags" class="btn btn-secondary btn-block mt-1">Iptal</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Mevcut Etiketler</h3>
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
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($row['slug']); ?></span></td>
                                <td style="white-space: nowrap;"><?php echo date('d.m.Y', strtotime($row['created_at'])); ?></td>
                                <td style="text-align: right;">
                                    <div class="btn-group">
                                        <a href="?edit_id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu etiketi silmek istediginizden emin misiniz?');"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="table-empty"><i class="fas fa-tags"></i>Henuz etiket yok</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('tagName').addEventListener('input', function() {
    var slugField = document.getElementById('tagSlug');
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
