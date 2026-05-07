<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/database.php';

$base_url = rtrim(BASE_URL, '/');
$admin_base_url = $base_url . '/admin';

$current_uri = $_SERVER['REQUEST_URI'] ?? '';
$current_file = basename($_SERVER['PHP_SELF'], '.php');

function isActive($page, $current) {
    return $page === $current ? 'active' : '';
}

function isSubmenuActive($pages, $uri) {
    foreach ($pages as $p) {
        if (strpos($uri, $p) !== false) return true;
    }
    return false;
}

$posts_active = isSubmenuActive(['post', 'category', 'tags', 'add-post'], $current_uri);
$pages_active = isSubmenuActive(['page', 'add-page'], $current_uri) && !$posts_active;
?>

<aside class="dashboard-sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-cube" style="font-size: 24px; color: var(--primary);"></i>
        <h2><?php echo htmlspecialchars($settings['site_name'] ?? 'Admin Panel'); ?></h2>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Ana Menu</div>

            <div class="nav-item">
                <a href="<?php echo $admin_base_url; ?>" class="nav-link <?php echo isActive('index', $current_file); ?>">
                    <i class="fas fa-th-large"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>

            <div class="nav-item">
                <button class="nav-link <?php echo $posts_active ? 'active expanded' : ''; ?>" onclick="toggleSubmenu(this)">
                    <i class="fas fa-pen-fancy"></i>
                    <span class="nav-text">Yazilar</span>
                    <i class="fas fa-chevron-right nav-arrow"></i>
                </button>
                <div class="nav-submenu <?php echo $posts_active ? 'show' : ''; ?>">
                    <a href="<?php echo $admin_base_url; ?>/post" class="nav-link <?php echo isActive('post', $current_file); ?>">Tum Yazilar</a>
                    <a href="<?php echo $admin_base_url; ?>/includes/post/add-post" class="nav-link <?php echo isActive('add-post', $current_file); ?>">Yeni Yazi Ekle</a>
                    <a href="<?php echo $admin_base_url; ?>/includes/post/category" class="nav-link <?php echo isActive('category', $current_file); ?>">Kategoriler</a>
                    <a href="<?php echo $admin_base_url; ?>/includes/post/tags" class="nav-link <?php echo isActive('tags', $current_file); ?>">Etiketler</a>
                </div>
            </div>

            <div class="nav-item">
                <button class="nav-link <?php echo $pages_active ? 'active expanded' : ''; ?>" onclick="toggleSubmenu(this)">
                    <i class="fas fa-file-alt"></i>
                    <span class="nav-text">Sayfalar</span>
                    <i class="fas fa-chevron-right nav-arrow"></i>
                </button>
                <div class="nav-submenu <?php echo $pages_active ? 'show' : ''; ?>">
                    <a href="<?php echo $admin_base_url; ?>/page" class="nav-link <?php echo isActive('page', $current_file); ?>">Tum Sayfalar</a>
                    <a href="<?php echo $admin_base_url; ?>/includes/page/add-page" class="nav-link <?php echo isActive('add-page', $current_file); ?>">Yeni Sayfa Ekle</a>
                </div>
            </div>

            <div class="nav-item">
                <a href="<?php echo $admin_base_url; ?>/menu" class="nav-link <?php echo isActive('menu', $current_file); ?>">
                    <i class="fas fa-bars"></i>
                    <span class="nav-text">Menu Yonetimi</span>
                </a>
            </div>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Ayarlar</div>

            <div class="nav-item">
                <a href="<?php echo $admin_base_url; ?>/settings" class="nav-link <?php echo isActive('settings', $current_file); ?>">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">Site Ayarlari</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="<?php echo $admin_base_url; ?>/mail" class="nav-link <?php echo isActive('mail', $current_file); ?>">
                    <i class="fas fa-envelope"></i>
                    <span class="nav-text">E-mail Ayarlari</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
                <div class="sidebar-user-role">Administrator</div>
            </div>
        </div>
    </div>
</aside>
