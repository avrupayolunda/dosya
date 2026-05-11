<?php
/**
 * sitemap.php — XML Sitemap oluşturucu
 *
 * DEĞİŞİKLİK: Content-Type header'ı eklendi ve XML doğrudan tarayıcıya gönderiliyor.
 * Eski versiyon sadece dosyaya yazıp mesaj gösteriyordu; Google /sitemap.xml istediğinde
 * XML yerine düz metin alıyordu (eğer sitemap.xml dosyası fiziksel olarak yoksa).
 */

require_once __DIR__ . '/panel/config.php';

$base = 'https://www.idealdesign.be/';
$langs = ['nl', 'fr', 'en', 'tr'];

function buildSitemapUrl($base, $lang, $slug = '', $prefix = '') {
    if ($lang === 'nl') {
        $url = $base;
    } else {
        $url = $base . $lang . '/';
    }
    if ($prefix) {
        $url .= $prefix . '/';
    }
    if ($slug) {
        $url .= $slug;
    }
    return rtrim($url, '/');
}

function addUrl(&$xml, $loc, $lastmod, $priority, $changefreq, $alternates = []) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
    if ($lastmod) {
        $xml .= "    <lastmod>" . date('Y-m-d', strtotime($lastmod)) . "</lastmod>\n";
    }
    $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
    $xml .= "    <priority>{$priority}</priority>\n";
    foreach ($alternates as $lang => $href) {
        $xml .= '    <xhtml:link rel="alternate" hreflang="' . $lang . '" href="' . htmlspecialchars($href) . '" />' . "\n";
    }
    if (!empty($alternates) && isset($alternates['nl'])) {
        $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($alternates['nl']) . '" />' . "\n";
    }
    $xml .= "  </url>\n";
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
$xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

try {
    // --- ANASAYFA ---
    $homeAlts = [];
    foreach ($langs as $l) {
        $homeAlts[$l] = ($l === 'nl') ? $base : rtrim($base, '/') . '/' . $l;
    }
    addUrl($xml, $base, date('Y-m-d'), '1.00', 'daily', $homeAlts);
    foreach (['fr', 'en', 'tr'] as $l) {
        addUrl($xml, rtrim($base, '/') . '/' . $l, date('Y-m-d'), '0.90', 'daily', $homeAlts);
    }

    // --- SAYFALAR ---
    $pageGroups = $pdo->query("SELECT DISTINCT group_id FROM page WHERE group_id != 1 ORDER BY group_id")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($pageGroups as $gid) {
        $stmt = $pdo->prepare("SELECT language, slug, updated_at FROM page WHERE group_id = ? ORDER BY language");
        $stmt->execute([$gid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $alternates = [];
        $lastmod = null;
        foreach ($rows as $r) {
            $alternates[$r['language']] = buildSitemapUrl($base, $r['language'], $r['slug']);
            if (!$lastmod || strtotime($r['updated_at']) > strtotime($lastmod)) {
                $lastmod = $r['updated_at'];
            }
        }

        foreach ($rows as $r) {
            $url = buildSitemapUrl($base, $r['language'], $r['slug']);
            $pri = ($r['language'] === 'nl') ? '0.80' : '0.64';
            addUrl($xml, $url, $lastmod, $pri, 'weekly', $alternates);
        }
    }

    // --- PORTFOLIO ---
    $prodGroups = $pdo->query("SELECT DISTINCT group_id FROM product ORDER BY group_id")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($prodGroups as $gid) {
        $stmt = $pdo->prepare("SELECT language, slug, updated_at FROM product WHERE group_id = ? ORDER BY language");
        $stmt->execute([$gid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $alternates = [];
        $lastmod = null;
        foreach ($rows as $r) {
            $alternates[$r['language']] = buildSitemapUrl($base, $r['language'], $r['slug'], 'portfolio');
            if (!$lastmod || strtotime($r['updated_at']) > strtotime($lastmod)) {
                $lastmod = $r['updated_at'];
            }
        }

        foreach ($rows as $r) {
            $url = buildSitemapUrl($base, $r['language'], $r['slug'], 'portfolio');
            $pri = ($r['language'] === 'nl') ? '0.80' : '0.64';
            addUrl($xml, $url, $lastmod, $pri, 'monthly', $alternates);
        }
    }

    // --- BLOG LISTE SAYFALARI ---
    $blogListAlts = [];
    foreach ($langs as $l) {
        $blogListAlts[$l] = buildSitemapUrl($base, $l, 'blog');
    }
    foreach ($langs as $l) {
        $url = buildSitemapUrl($base, $l, 'blog');
        $pri = ($l === 'nl') ? '0.80' : '0.64';
        addUrl($xml, $url, date('Y-m-d'), $pri, 'daily', $blogListAlts);
    }

    // --- BLOG YAZILARI ---
    $blogGroups = $pdo->query("SELECT DISTINCT group_id FROM blog ORDER BY group_id")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($blogGroups as $gid) {
        $stmt = $pdo->prepare("SELECT language, slug, updated_at, created_at FROM blog WHERE group_id = ? ORDER BY language");
        $stmt->execute([$gid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $alternates = [];
        $lastmod = null;
        foreach ($rows as $r) {
            $alternates[$r['language']] = buildSitemapUrl($base, $r['language'], $r['slug'], 'blog');
            $date = $r['updated_at'] ?? $r['created_at'];
            if (!$lastmod || strtotime($date) > strtotime($lastmod)) {
                $lastmod = $date;
            }
        }

        foreach ($rows as $r) {
            $url = buildSitemapUrl($base, $r['language'], $r['slug'], 'blog');
            $pri = ($r['language'] === 'nl') ? '0.70' : '0.56';
            addUrl($xml, $url, $lastmod, $pri, 'monthly', $alternates);
        }
    }

} catch (PDOException $e) {
    echo "HATA: Sitemap olusturulamadi - " . $e->getMessage() . "\n";
    exit(1);
}

$xml .= '</urlset>';

// Sitemap.xml dosyasını ana dizine yaz (cache olarak)
$sitemapPath = __DIR__ . '/sitemap.xml';
file_put_contents($sitemapPath, $xml);

// Doğrudan XML olarak çıktı ver (Content-Type header ile)
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/xml; charset=UTF-8');
    echo $xml;
} else {
    $urlCount = substr_count($xml, '<url>');
    echo "Sitemap basariyla olusturuldu!\n";
    echo "Dosya: {$sitemapPath}\n";
    echo "Toplam URL: {$urlCount}\n";
    echo "Boyut: " . round(strlen($xml) / 1024, 1) . " KB\n";
}
