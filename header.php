<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/AI.php';
require_once 'log.php';

$page_title = isset($page_title) ? $page_title : 'AI Content System';
$page_desc = isset($page_desc) ? $page_desc : '';

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> | AI Content System</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
</head>
<body>
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <span class="material-icons-round">auto_awesome</span>
                    </div>
                    <div class="logo-text">
                        <span class="logo-title">AI Content</span>
                        <span class="logo-badge">System</span>
                    </div>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <span class="material-icons-round">menu_open</span>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                    <span class="material-icons-round">dashboard</span>
                    <span class="nav-text">Websitelerim</span>
                </a>
                <a href="settings.php" class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                    <span class="material-icons-round">settings</span>
                    <span class="nav-text">AI Ayarlari</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-card">
                    <div class="user-avatar">
                        <span class="material-icons-round">person</span>
                    </div>
                    <div class="user-info">
                        <div class="user-name">Admin</div>
                        <div class="user-role">Yonetici</div>
                    </div>
                    <button class="user-menu-btn" onclick="toggleUserMenu()">
                        <span class="material-icons-round">more_vert</span>
                    </button>
                </div>
                <div class="user-menu" id="userMenu">
                    <a href="settings.php"><span class="material-icons-round">settings</span> Ayarlar</a>
                    <a href="logout.php" class="text-danger"><span class="material-icons-round">logout</span> Cikis</a>
                </div>
            </div>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div class="page-header">
                        <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
                        <?php if($page_desc): ?>
                        <p class="page-desc"><?php echo htmlspecialchars($page_desc); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="search-bar">
                        <span class="material-icons-round">search</span>
                        <input type="text" placeholder="Ara..." id="globalSearch">
                        <span class="search-shortcut">⌘K</span>
                    </div>
                </div>
            </div>
            
            <div class="toast-container" id="toastContainer"></div>
            
            <div class="content-area">