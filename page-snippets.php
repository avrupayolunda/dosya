<?php
/**
 * page-snippets.php — Sayfa seviyesi yapısal veri (structured data)
 * Dosya yolu: includes/snippet/page-snippets.php
 *
 * ÖNEMLİ: Eski dosyada yanlışlıkla İstikbal.com.tr verisi vardı.
 * Bu dosya BWCreative / idealdesign.be için doğru verileri içerir.
 */

if (!defined('base_url')) {
    define('base_url', 'https://www.idealdesign.be/');
}

global $settings, $page_data, $current_lang, $current_url;

$lang  = $current_lang ?? 'nl';
$base  = base_url;

$siteName = 'BWCreative';
$orgName  = $siteName;

$metaTitle = $page_data['seo_title'] ?? ($page_data['title'] ?? ($settings['site_title'] ?? $siteName));
$metaDesc  = $page_data['meta_description'] ?? ($settings['site_description'] ?? '');

$logo = $base . 'assets/img/logo.png';
if (!empty($settings['logo'])) {
    $logo = $base . ltrim($settings['logo'], '/');
} elseif (!empty($settings['favicon'])) {
    $logo = $base . ltrim($settings['favicon'], '/');
}

$pageUrl = !empty($current_url) ? $current_url : $base;

$breadcrumbItems = [
    [
        "@type"    => "ListItem",
        "position" => 1,
        "name"     => $siteName,
        "item"     => ($lang === 'nl') ? $base : $base . $lang
    ]
];

if (!empty($page_data['title']) && !empty($pageUrl) && $pageUrl !== $base && $pageUrl !== rtrim($base, '/') . '/' . $lang) {
    $breadcrumbItems[] = [
        "@type"    => "ListItem",
        "position" => 2,
        "name"     => $page_data['title'],
        "item"     => $pageUrl
    ];
}

$schema = [
    "@context" => "https://schema.org",
    "@type"    => "BreadcrumbList",
    "itemListElement" => $breadcrumbItems
];
?>
<script type="application/ld+json">
<?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
