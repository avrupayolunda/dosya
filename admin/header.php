<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/hatalar.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');

$page_titles = [
    'index' => 'Dashboard',
    'post' => 'Blog Yazilari',
    'page' => 'Sayfalar',
    'settings' => 'Site Ayarlari',
    'mail' => 'Mail Ayarlari',
    'menu' => 'Menu Yonetimi',
];

$page_title = $page_titles[$current_page] ?? 'Dashboard';

$settings = [
    'page_title' => 'Admin Panel',
    'site_name' => 'Admin Panel'
];

$settings_query = "SELECT setting_key, setting_value FROM settings";
$settings_result = mysqli_query($conn, $settings_query);
if ($settings_result) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    mysqli_free_result($settings_result);
}

$username = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$user_initial = strtoupper(substr($username, 0, 1));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($settings['page_title'] ?? 'Admin Panel'); ?> - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="shortcut icon" type="image/png" href="<?php echo BASE_URL; ?>/admin/assets/images/icon/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/dashboard.css">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php include __DIR__ . '/sidebar.php'; ?>

<header class="dashboard-header">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu">
            <i class="fas fa-bars"></i>
        </button>
        <nav class="page-breadcrumb">
            <a href="index.php">Ana Sayfa</a>
            <span class="separator"><i class="fas fa-chevron-right"></i></span>
            <span class="current"><?php echo htmlspecialchars($page_title); ?></span>
        </nav>
    </div>
    <div class="header-right">
        <div class="header-user" id="headerUser">
            <div class="header-user-avatar"><?php echo $user_initial; ?></div>
            <span class="header-user-name"><?php echo $username; ?></span>
            <i class="fas fa-chevron-down" style="font-size: 11px; color: var(--gray-400);"></i>
            <div class="header-dropdown" id="headerDropdown">
                <a href="settings.php"><i class="fas fa-cog"></i> Ayarlar</a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="danger"><i class="fas fa-sign-out-alt"></i> Cikis Yap</a>
            </div>
        </div>
    </div>
</header>

<main class="dashboard-content">
