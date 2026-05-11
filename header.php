<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === CACHE CONTROL INITIALIZATION ===

require_once __DIR__ . '/vendor/autoload.php';

use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;

CacheManager::setDefaultConfig(new ConfigurationOption([
    'path' => __DIR__ . '/cache',
]));

$InstanceCache = CacheManager::getInstance('files');

$key = "page_" . md5($_SERVER['REQUEST_URI']);
$CachedString = $InstanceCache->getItem($key);

if ($CachedString->isHit()) {
    // 1 AY cache başlığı gönder
    header("Cache-Control: public, max-age=2592000, immutable");
    header("Expires: " . gmdate('D, d M Y H:i:s', time() + 2592000) . " GMT");
    echo $CachedString->get();
    exit();
} else {
    ob_start();
}

// === CACHE CHECK END ===


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/database.php';
require_once 'log.php';
require_once 'includes/optimize.php';

if (!isset($base_url) || empty($base_url)) {
    $base_url = 'https://www.umtcar.be/';
    error_log('$base_url is not defined, default value used: ' . $base_url);
}

$settings_query = "SELECT site_title, site_description, site_logo, site_favicon, site_mail, telefon, adres, organization, structured_logo, structured_url, structured_desc, structured_facebook, structured_twitter, structured_instagram 
                   FROM settings WHERE id = 1";
$settings_result = mysqli_query($conn, $settings_query);
if ($settings_result && mysqli_num_rows($settings_result) > 0) {
    $settings = mysqli_fetch_assoc($settings_result);
    $site_title = $settings['site_title'];
    $site_description = $settings['site_description'];
    $site_logo = $settings['site_logo'];
    $site_favicon = $settings['site_favicon'];
    $site_mail = $settings['site_mail'] ?? 'info@umtcar.be';
    $telefon = $settings['telefon'] ?? '+32 487 57 05 23';
    $adres = $settings['adres'] ?? 'Bazelstraat 250, 9150 Kruibeke, België';
    $organization = $settings['organization'];
    $structured_logo = $settings['structured_logo'];
    $structured_url = $settings['structured_url'];
    $structured_desc = $settings['structured_desc'];
    $structured_facebook = $settings['structured_facebook'];
    $structured_twitter = $settings['structured_twitter'];
    $structured_instagram = $settings['structured_instagram'];
} else {
    error_log("Settings query failed or no data found: " . mysqli_error($conn));
    $site_title = 'Umt Car Auto Opkoper / Verkoper Belgie';
    $site_description = 'UMT-CAR koopt uw tweedehands wagen aan een eerlijke prijs. Wij focussen op klant gerichtheid, service en een correcte prijs.';
    $site_logo = 'uploads/img/logo_1751405092.webp';
    $site_favicon = 'uploads/img/favicon_1751405092.webp';
    $site_mail = 'info@umtcar.be';
    $telefon = '+32 487 57 05 23';
    $adres = 'Bazelstraat 250, 9150 Kruibeke, Belgium';
    $organization = 'UMTCAR';
    $structured_logo = '/admin/uploads/img/logo.jpg';
    $structured_url = $base_url;
    $structured_desc = 'Your trusted auto partner!';
    $structured_facebook = $base_url;
    $structured_twitter = $base_url;
    $structured_instagram = $base_url;
}
mysqli_free_result($settings_result);

$menu_query = "SELECT mi.id, mi.title, mi.url, mi.sort_order, mi.type, mi.ref_id 
               FROM menu_items mi 
               WHERE mi.menu_id = 3 AND mi.parent_id IS NULL 
               ORDER BY mi.sort_order ASC";
$menu_result = mysqli_query($conn, $menu_query);
if (!$menu_result) {
    error_log("Menu query failed: " . mysqli_error($conn));
    $menu_items = [];
} else {
    $menu_items = [];
    while ($row = mysqli_fetch_assoc($menu_result)) {
        if (is_null($row['url']) && $row['type'] === 'page' && !is_null($row['ref_id'])) {
            $row['url'] = '/page/' . $row['ref_id'];
        }
        $menu_items[] = $row;
    }
    mysqli_free_result($menu_result);
}
if (empty($menu_items)) {
    error_log("Menu items empty: no data found for menu_id=3");
}

// === CANONICAL URL NORMALIZATION ===
if (!isset($canonical_url) || empty($canonical_url)) {
    if ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/index.php') {
        $canonical_url = $base_url;
    } else {
        $parsed = parse_url($_SERVER['REQUEST_URI']);
        $path = $parsed['path'] ?? '/';
        $path = strtolower($path);
        $path = rtrim($path, '/');
        if ($path === '') $path = '/';
        $canonical_url = rtrim($base_url, '/') . $path;
    }
} elseif (strpos($canonical_url, 'http') !== 0) {
    $canonical_url = rtrim($base_url, '/') . '/' . ltrim($canonical_url, '/');
}

// === NOINDEX DETECTION ===
$noindex_page = false;
$request_uri = $_SERVER['REQUEST_URI'];
$query_string = $_SERVER['QUERY_STRING'] ?? '';

$noindex_params = ['s', 'brandstof', 'sorteren-op', 'current-page', 'kleur', 'transmissie', 'type', 'post_type', 'kenmerken', 'min_price', 'max_price', 'min_km', 'max_km', 'keyword'];
foreach ($noindex_params as $param) {
    if (isset($_GET[$param]) && $_GET[$param] !== '') {
        $noindex_page = true;
        break;
    }
}

if (preg_match('#/zoek/.+/page/\d+#', $request_uri)) {
    $noindex_page = true;
}
if (preg_match('#/zoek/page/\d+#', $request_uri) && !empty($query_string)) {
    $noindex_page = true;
}
if (preg_match('#/blog/?\?page=\d+#', $request_uri)) {
    $noindex_page = true;
}
if (preg_match('#/category/.+/\?pagination=#', $request_uri)) {
    $noindex_page = true;
}

// SEO for each page: Use $seo_title and $seo_description if set, otherwise defaults
$page_title = isset($seo_title) && !empty($seo_title) ? $seo_title : $site_title;
$page_description = isset($seo_description) && !empty($seo_description) ? $seo_description : $site_description;
?>
<!DOCTYPE html>
<html lang="nl">
<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
<?php if ($noindex_page): ?>
  <meta name="robots" content="noindex, follow" />
<?php else: ?>
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
<?php endif; ?>
  <title><?= htmlspecialchars($page_title) ?></title>
  <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
  <link rel="icon" href="<?= htmlspecialchars($site_favicon) ?>">
  <link rel="canonical" href="<?= htmlspecialchars($canonical_url) ?>">

  <!-- Open Graph Meta Tags -->
  <meta property="og:locale" content="en_US" />
  <meta property="og:type" content="website" />
  <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($page_description) ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($canonical_url) ?>" />
  <meta property="og:site_name" content="<?= htmlspecialchars($organization) ?>" />
  <meta property="article:modified_time" content="<?= gmdate('Y-m-d\TH:i:s+00:00') ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($base_url . $structured_logo) ?>" />
  <meta name="twitter:card" content="summary_large_image" />



<style>
.cnvs-main-title{font-size:2rem;font-weight:900;color:#fff;margin:0 0 10px;line-height:1.2}.cnvs-main-title .cnvs-highlight{color:#A80202;font-weight:900}.cnvs-subtitle{font-size:1.1rem;color:#e3e3e3;margin-bottom:16px}.cnvs-tab-group{display:flex;background:#23242a;border-radius:8px 8px 0 0;margin-bottom:16px;overflow:hidden}.cnvs-tab{padding:10px 20px;background:#23242a;color:#b7b8c6;font-weight:600;font-size:1rem;border:none}.cnvs-tab.cnvs-active{background:#fff;color:#23242a}.cnvs-divider{height:1px;background:linear-gradient(to right,#333,#444,#333);margin:10px 0 16px;opacity:0.5}.cnvs-form-row{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:12px}.cnvs-form-col{flex:1 1 100%;min-width:0}label{display:flex;align-items:center;gap:6px;font-size:1rem;font-weight:500;margin-bottom:6px;color:#e3e3e3}.cnvs-required{color:#A80202;font-size:1rem}select,input[type="text"],input[type="number"],input[type="email"],input[type="tel"],textarea{width:100%;padding:12px;border-radius:5px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.5);color:#fff;font-size:14px;min-height:40px}.cnvs-form-panel label .material-icons-round{font-size:20px;color:#e3e3e3;vertical-align:middle}.cnvs-submit-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px;background:#A80202;color:#fff;font-size:1rem;font-weight:600;border:none;border-radius:6px;margin-top:12px}.material-icons-round{font-family:'Material Icons Round';font-size:20px;color:#e3e3e3;font-weight:400;font-style:normal;-webkit-font-smoothing:antialiased;vertical-align:middle}.cnvs-desc-big.cnvs-desc-verybig{font-size:21px;color:#fff;margin:20px 0;font-weight:500;line-height:45px;letter-spacing:0.01em}.cnvs-desc-big.cnvs-desc-verybig strong{color:#fff;font-weight:800}.cnvs-desc-big.cnvs-desc-verybig .umtcar-red{color:#A80202;font-weight:900}.umtcar-red{color:#A80202;font-weight:900;letter-spacing:0.01em}.cnvs-car-list.cnvs-car-list-double{display:grid;grid-template-columns:1fr 1fr;gap:16px}.cnvs-car-list.cnvs-car-list-double .cnvs-car-item{margin-bottom:0}@media(max-width:700px){.cnvs-car-list.cnvs-car-list-double{grid-template-columns:1fr}}.cnvs-car-item{display:flex;align-items:center;gap:12px;background:#18191d;border-radius:8px;padding:12px;margin-bottom:10px}.cnvs-car-item img.cnvs-car-item__image{width:90px;height:65px;object-fit:cover;border-radius:6px;flex-shrink:0}.cnvs-car-item .cnvs-car-item__details{display:flex;flex-direction:column;gap:2px}.cnvs-car-item .cnvs-car-item__name{font-weight:700;color:#fff;font-size:1rem}.cnvs-car-item .cnvs-car-item__price{color:#A80202;font-weight:700;font-size:0.98rem}.cnvs-car-item .cnvs-car-item__year,.cnvs-car-item .cnvs-car-item__fuel{font-size:0.92rem;color:#aaa}
</style>

<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/custom.css">
<link rel="stylesheet" href="/assets/css/modul.css">
<link rel="stylesheet" href="/assets/css/mobile.css">
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-H9F45CWDS7"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-H9F45CWDS7');
</script>

<script id="cookieyes" type="text/javascript" src="https://cdn-cookieyes.com/client_data/93db2db1a58fd572ebab6a4b/script.js"></script>

</head>
<body>
  <header class="umtcaruni-header">
    <div class="umtcaruni-header__container">
      <button class="umtcaruni-header__hamburger" id="umtcaruniHamburger" aria-label="Menüyü Aç/Kapat" type="button">
        <span></span>
        <span></span>
        <span></span>
      </button>
      <div class="umtcaruni-header__logo">
        <a href="/" title="<?= htmlspecialchars($site_title) ?>">
          <img src="<?= htmlspecialchars($site_logo) ?>" alt="<?= htmlspecialchars($site_title) ?>" fetchpriority="high">
        </a>
      </div>
      <nav class="umtcaruni-header__nav" id="umtcaruniDesktopNav">
        <ul>
          <?php if (!empty($menu_items)): ?>
            <?php foreach ($menu_items as $item): ?>
              <li>
                <a href="<?= htmlspecialchars($item['url'] ?? '#') ?>">
                  <span class="umtcaruni-header__arrow material-icons-round">chevron_right</span>
                  <?= htmlspecialchars($item['title']) ?>
                </a>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li><a href="#">Menü Boş</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
    <nav class="umtcaruni-header__mobile-nav" id="umtcaruniMobileMenu">
      <div class="umtcaruni-header__mobile-menu-inner">
        <div class="umtcaruni-header__mobile-logo">
          <a href="/" title="<?= htmlspecialchars($site_title) ?>">
            <img src="<?= htmlspecialchars($site_logo) ?>" alt="<?= htmlspecialchars($site_title) ?>">
          </a>
        </div>
        <button class="umtcaruni-header__mobile-close" id="umtcaruniCloseMobileMenu" aria-label="Menüyü Kapat" type="button">
          <span class="black material-icons-round">close</span>
        </button>
        <ul>
          <?php if (!empty($menu_items)): ?>
            <?php foreach ($menu_items as $item): ?>
              <li>
                <a href="<?= htmlspecialchars($item['url'] ?? '#') ?>">
                  <span class="umtcaruni-header__arrow material-icons-round">chevron_right</span>
                  <?= htmlspecialchars($item['title']) ?>
                </a>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li><a href="#">Menu Leeg</a></li>
          <?php endif; ?>
        </ul>
        <div class="umtcaruni-header__mobile-contact">
          <a href="tel:<?= htmlspecialchars($telefon) ?>"><span class="material-icons-round">phone</span> <?= htmlspecialchars($telefon) ?></a>
          <a href="mailto:<?= htmlspecialchars($site_mail) ?>"><span class="material-icons-round">mail</span> <?= htmlspecialchars($site_mail) ?></a>
        </div>
      </div>
      <div class="umtcaruni-header__mobile-nav-mask" id="umtcaruniMobileNavMask"></div>
    </nav>
  </header>
  <main>
