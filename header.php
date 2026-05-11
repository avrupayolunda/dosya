<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'panel/config.php';
require_once 'log.php';

$current_lang = isset($_GET['lang']) ? $_GET['lang'] : 'nl';
if (!in_array($current_lang, ['nl','fr','en','tr'])) {
    $current_lang = 'nl';
}

$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
if ($slug === null) {
    $slug = '';
}
$slug = trim((string)$slug);
if ($slug === '' || $slug === '/') {
    $slug = '';
} else {
    $slug = rtrim($slug, '/');
}

$path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
if ($path === null || $path === false) {
    $path = '';
}
$is_portfolio = (strpos($path, '/portfolio/') !== false);
$is_blog_list = (rtrim($path, '/') === '/blog' || preg_match('/^\/(nl|fr|en|tr)\/blog$/', rtrim($path, '/')));
$is_blog_detail = (!$is_blog_list && strpos($path, '/blog/') !== false);

try {
    $settings = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    writeLog($e->getMessage());
    $settings = [];
}

$home_group_id = null;
try {
    $home_stmt = $pdo->prepare("SELECT group_id FROM page WHERE language = 'nl' AND slug = 'home' LIMIT 1");
    $home_stmt->execute();
    $home_group_id = $home_stmt->fetchColumn();
    if (!$home_group_id) {
        $first_page = $pdo->query("SELECT group_id FROM page LIMIT 1")->fetchColumn();
        $home_group_id = $first_page ?: 0;
    }
} catch (PDOException $e) {
    writeLog($e->getMessage());
}

$menu_items = [];
try {
    $menu_stmt = $pdo->prepare("SELECT id FROM menus WHERE language = ? AND position = 'header' LIMIT 1");
    $menu_stmt->execute([$current_lang]);
    $menu_id = $menu_stmt->fetchColumn();
    if ($menu_id) {
        $stmt = $pdo->prepare("SELECT mi.*, p.slug as page_slug, p.group_id as page_group_id FROM menu_items mi LEFT JOIN page p ON mi.related_id = p.id WHERE mi.menu_id = ? ORDER BY mi.sort_order ASC");
        $stmt->execute([$menu_id]);
        $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    writeLog($e->getMessage());
}

$langs = ['nl', 'fr', 'en', 'tr'];
$lang_links = [];
$current_group_id = null;
$page_data = [];

if ($is_blog_detail) {
    $table_name = 'blog';
    try {
        $stmt = $pdo->prepare("SELECT * FROM blog WHERE slug = ? AND language = ? LIMIT 1");
        $stmt->execute([$slug, $current_lang]);
        $page_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($page_data) {
            $blog_group_id = $page_data['group_id'] ?? null;
            
            foreach ($langs as $l) {
                if ($l === 'nl') {
                    $base_path = base_url;
                } else {
                    $base_path = base_url . $l . '/';
                }
                $target_slug = $slug;
                
                if ($blog_group_id) {
                    $slugStmt = $pdo->prepare("SELECT slug FROM blog WHERE group_id = ? AND language = ? LIMIT 1");
                    $slugStmt->execute([$blog_group_id, $l]);
                    $foundSlug = $slugStmt->fetchColumn();
                    if ($foundSlug) {
                        $target_slug = $foundSlug;
                    }
                }
                
                $lang_links[$l] = $base_path . 'blog/' . $target_slug;
            }
        }
    } catch (PDOException $e) {
        writeLog($e->getMessage());
    }
} else {
    $table_name = $is_portfolio ? 'product' : 'page';
    try {
        if ($slug !== '') {
            $group_stmt = $pdo->prepare("SELECT group_id, title, seo_title, meta_description, builder_content, content FROM $table_name WHERE slug = ? AND language = ? LIMIT 1");
            $group_stmt->execute([$slug, $current_lang]);
            $fetched = $group_stmt->fetch(PDO::FETCH_ASSOC);
            if ($fetched) {
                $current_group_id = $fetched['group_id'];
                $page_data = $fetched;
            }
        } else {
            $current_group_id = $home_group_id;
            $home_data_stmt = $pdo->prepare("SELECT title, seo_title, meta_description, builder_content, content FROM page WHERE group_id = ? AND language = ? LIMIT 1");
            $home_data_stmt->execute([$home_group_id, $current_lang]);
            $page_data = $home_data_stmt->fetch(PDO::FETCH_ASSOC);
        }

        foreach ($langs as $l) {
            $target_slug = '';
            if ($current_group_id) {
                $slug_stmt = $pdo->prepare("SELECT slug FROM $table_name WHERE group_id = ? AND language = ? LIMIT 1");
                $slug_stmt->execute([$current_group_id, $l]);
                $target_slug = $slug_stmt->fetchColumn();
                if ($target_slug === false || $target_slug === null) {
                    $target_slug = '';
                }
            }
            if ($l === 'nl') {
                $base_path = base_url;
            } else {
                $base_path = base_url . $l . '/';
            }
            if ($is_portfolio) {
                $lang_links[$l] = $base_path . 'portfolio/' . $target_slug;
            } else {
                if ($current_group_id && $current_group_id == $home_group_id) {
                    if ($l === 'nl') {
                        $lang_links[$l] = rtrim(base_url, '/') . '/';
                    } else {
                        $lang_links[$l] = rtrim(base_url, '/') . '/' . $l;
                    }
                } else {
                    // FIX: rtrim trailing slash for non-nl base path when target_slug is empty
                    $lang_links[$l] = rtrim($base_path . $target_slug, '/');
                    if ($lang_links[$l] === rtrim(base_url, '/')) {
                        $lang_links[$l] = base_url;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        writeLog($e->getMessage());
    }
}

if ($is_blog_list) {
    $page_data['title'] = 'Blog';
    $page_data['seo_title'] = 'Blog - ' . ($settings['site_title'] ?? '');
    $page_data['meta_description'] = $settings['site_description'] ?? '';
    foreach ($langs as $l) {
        if ($l === 'nl') {
            $lang_links[$l] = rtrim(base_url, '/') . '/blog';
        } else {
            $lang_links[$l] = rtrim(base_url, '/') . '/' . $l . '/blog';
        }
    }
}

$final_title = !empty($page_data['seo_title']) ? $page_data['seo_title'] : (!empty($page_data['title']) ? $page_data['title'] . ' - ' . ($settings['site_title'] ?? '') : ($settings['site_title'] ?? ''));
$final_desc = !empty($page_data['meta_description']) ? $page_data['meta_description'] : ($settings['site_description'] ?? '');

$canonical_from_lang_links = true;
$current_url = '';
if (isset($lang_links[$current_lang]) && !empty($lang_links[$current_lang])) {
    $current_url = $lang_links[$current_lang];
} else {
    $canonical_from_lang_links = false;
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base = $proto . '://' . $host . '/';
    if ($is_blog_detail) {
        $current_url = $base . ($current_lang === 'nl' ? '' : $current_lang . '/') . 'blog/' . $slug;
    } elseif ($is_blog_list) {
        $current_url = $base . ($current_lang === 'nl' ? '' : $current_lang . '/') . 'blog';
    } elseif ($is_portfolio) {
        $current_url = $base . ($current_lang === 'nl' ? '' : $current_lang . '/') . 'portfolio/' . $slug;
    } elseif ($slug === '' || $slug === 'home') {
        if ($current_lang === 'nl') {
            $current_url = $base;
        } else {
            $current_url = $base . $current_lang;
        }
    } else {
        if ($current_lang === 'nl') {
            $current_url = $base . $slug;
        } else {
            $current_url = $base . $current_lang . '/' . $slug;
        }
    }
}

// CSS version: use a fixed deploy version instead of time() to avoid cache-busting on every request
$css_version = '20260511';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($final_title) ?></title>
<meta name="description" content="<?= htmlspecialchars($final_desc) ?>">
<link rel="canonical" href="<?= htmlspecialchars($current_url) ?>">
<?php foreach ($lang_links as $lang_code => $link_url): if (!empty($link_url)): ?>
<link rel="alternate" hreflang="<?= $lang_code ?>" href="<?= htmlspecialchars($link_url) ?>" />
<?php endif; endforeach; ?>
<?php
// x-default: always use the NL version (default language)
$x_default_url = !empty($lang_links['nl']) ? $lang_links['nl'] : base_url;
?>
<link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($x_default_url) ?>" />
<meta property="og:type" content="website">
<meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
<meta property="og:title" content="<?= htmlspecialchars($final_title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($final_desc) ?>">
<meta property="og:image" content="<?= !empty($settings['logo']) ? base_url . ltrim($settings['logo'], '/') : (!empty($settings['favicon']) ? base_url . ltrim($settings['favicon'], '/') : '') ?>">
<meta property="og:site_name" content="BWCreative">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($final_title) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($final_desc) ?>">
<?php if (!empty($settings['favicon'])): ?>
<link rel="icon" type="image/png" href="<?= base_url . ltrim($settings['favicon'], '/') ?>">
<link rel="apple-touch-icon" href="<?= base_url . ltrim($settings['favicon'], '/') ?>">
<?php endif; ?>
<link rel="preload" href="<?= base_url ?>assets/css/fonts.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="<?= base_url ?>assets/css/fonts.css"></noscript>
<link rel="stylesheet" href="<?= base_url ?>assets/css/style.css?v=<?= $css_version ?>">
<link rel="stylesheet" href="<?= base_url ?>assets/css/blog.css?v=<?= $css_version ?>">
<link rel="preload" href="<?= base_url ?>assets/css/product.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="<?= base_url ?>assets/css/product.css"></noscript>
<link rel="preload" href="<?= base_url ?>assets/css/accessibility.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="<?= base_url ?>assets/css/accessibility.css"></noscript>
<link rel="stylesheet" href="<?= base_url ?>assets/css/cart.css?v=<?= $css_version ?>">
<link rel="stylesheet" href="<?= base_url ?>assets/css/custom.css?v=<?= $css_version ?>">
<style>
body { opacity: 1 !important; visibility: visible !important; }
</style>
<?php
if (isset($home_group_id) && isset($current_group_id) && $current_group_id == $home_group_id) {
    if (file_exists('includes/snippet/rich-snippets.php')) {
        include 'includes/snippet/rich-snippets.php';
    }
}
?>
<!-- Start cookieyes banner --> 
<script id="cookieyes" type="text/javascript" src="https://cdn-cookieyes.com/client_data/ebfecd950578dda6bca63e805587a1c5/script.js"></script> 
<!-- End cookieyes banner -->
</head>
<?php require_once 'body.php'; ?>
</html>
