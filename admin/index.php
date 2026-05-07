<?php
include __DIR__ . '/header.php';

$post_count = 0;
$page_count = 0;
$category_count = 0;
$tag_count = 0;

$r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM posts");
if ($r) { $post_count = mysqli_fetch_assoc($r)['cnt']; mysqli_free_result($r); }

$r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM pages");
if ($r) { $page_count = mysqli_fetch_assoc($r)['cnt']; mysqli_free_result($r); }

$r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM categories");
if ($r) { $category_count = mysqli_fetch_assoc($r)['cnt']; mysqli_free_result($r); }

$r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tags");
if ($r) { $tag_count = mysqli_fetch_assoc($r)['cnt']; mysqli_free_result($r); }

$recent_posts = [];
$r = mysqli_query($conn, "SELECT p.id, p.title, p.created_at, c.name AS category_name FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC LIMIT 5");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) { $recent_posts[] = $row; }
    mysqli_free_result($r);
}

$recent_pages = [];
$r = mysqli_query($conn, "SELECT id, title, created_at FROM pages ORDER BY created_at DESC LIMIT 5");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) { $recent_pages[] = $row; }
    mysqli_free_result($r);
}
?>

<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p>Hos geldiniz, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>! Sitenize genel bir bakis.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-pen-fancy"></i></div>
        <div class="stat-info">
            <h4><?php echo $post_count; ?></h4>
            <p>Toplam Yazi</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-file-alt"></i></div>
        <div class="stat-info">
            <h4><?php echo $page_count; ?></h4>
            <p>Toplam Sayfa</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-folder-open"></i></div>
        <div class="stat-info">
            <h4><?php echo $category_count; ?></h4>
            <p>Kategori</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-tags"></i></div>
        <div class="stat-info">
            <h4><?php echo $tag_count; ?></h4>
            <p>Etiket</p>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock" style="margin-right: 8px; color: var(--primary);"></i> Son Yazilar</h3>
            <a href="includes/post/add-post" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Yeni Yazi</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Baslik</th>
                            <th>Kategori</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_posts): ?>
                            <?php foreach ($recent_posts as $rp): ?>
                            <tr>
                                <td>
                                    <a href="includes/post/add-post?id=<?php echo $rp['id']; ?>" style="color: var(--primary); font-weight: 500;">
                                        <?php echo htmlspecialchars($rp['title']); ?>
                                    </a>
                                </td>
                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($rp['category_name'] ?? 'Yok'); ?></span></td>
                                <td style="white-space: nowrap;"><?php echo date('d.m.Y', strtotime($rp['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="table-empty"><i class="fas fa-inbox"></i>Henuz yazi yok</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($post_count > 5): ?>
        <div class="card-footer" style="text-align: center;">
            <a href="post" class="btn btn-ghost btn-sm">Tum Yazilari Gor <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt" style="margin-right: 8px; color: var(--success);"></i> Son Sayfalar</h3>
            <a href="includes/page/add-page" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Yeni Sayfa</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Baslik</th>
                            <th>Tarih</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_pages): ?>
                            <?php foreach ($recent_pages as $rpage): ?>
                            <tr>
                                <td>
                                    <a href="includes/page/add-page?id=<?php echo $rpage['id']; ?>" style="color: var(--success); font-weight: 500;">
                                        <?php echo htmlspecialchars($rpage['title']); ?>
                                    </a>
                                </td>
                                <td style="white-space: nowrap;"><?php echo date('d.m.Y', strtotime($rpage['created_at'])); ?></td>
                                <td>
                                    <a href="includes/page/add-page?id=<?php echo $rpage['id']; ?>" class="btn btn-ghost btn-sm btn-icon" title="Duzenle">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="table-empty"><i class="fas fa-inbox"></i>Henuz sayfa yok</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($page_count > 5): ?>
        <div class="card-footer" style="text-align: center;">
            <a href="page" class="btn btn-ghost btn-sm">Tum Sayfalari Gor <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 32px;">
            <div style="width: 56px; height: 56px; background: var(--primary-bg); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                <i class="fas fa-pen-fancy" style="font-size: 24px; color: var(--primary);"></i>
            </div>
            <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">Yeni Yazi Olustur</h4>
            <p style="font-size: 13px; color: var(--gray-500); margin-bottom: 16px;">Blog yazilarinizi olusturun ve yayinlayin.</p>
            <a href="includes/post/add-post" class="btn btn-primary">Yazi Ekle</a>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 32px;">
            <div style="width: 56px; height: 56px; background: var(--success-bg); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                <i class="fas fa-file-alt" style="font-size: 24px; color: var(--success);"></i>
            </div>
            <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">Yeni Sayfa Olustur</h4>
            <p style="font-size: 13px; color: var(--gray-500); margin-bottom: 16px;">Statik sayfalarinizi olusturun ve yonetin.</p>
            <a href="includes/page/add-page" class="btn btn-success">Sayfa Ekle</a>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 32px;">
            <div style="width: 56px; height: 56px; background: var(--warning-bg); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                <i class="fas fa-cog" style="font-size: 24px; color: var(--warning);"></i>
            </div>
            <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">Site Ayarlari</h4>
            <p style="font-size: 13px; color: var(--gray-500); margin-bottom: 16px;">Site basligini, logo ve SEO ayarlarini yapilandirin.</p>
            <a href="settings" class="btn btn-warning">Ayarlara Git</a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
