<?php
require_once __DIR__ . '/includes/database.php';

if (!$conn) {
    die("Bağlantı hatası: " . mysqli_connect_error());
}

$sitemapDir = __DIR__ . '/sitemap/';

if (isset($_SERVER['REQUEST_URI']) && preg_match('/\/sitemap\.xml$/', $_SERVER['REQUEST_URI'])) {
    $sitemapFile = __DIR__ . '/sitemap.xml';
    if (file_exists($sitemapFile)) {
        header('Content-Type: application/xml');
        readfile($sitemapFile);
        exit;
    } else {
        header('HTTP/1.0 404 Not Found');
        echo "Sitemap dosyası bulunamadı!";
        exit;
    }
}

function createXmlHeader() {
    return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
           '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
}

function createXmlFooter() {
    return '</urlset>';
}

function writeSitemap($filename, $content) {
    global $sitemapDir;
    if ($filename === 'sitemap.xml') {
        file_put_contents(__DIR__ . '/' . $filename, $content);
    } else {
        if (!is_dir($sitemapDir)) {
            mkdir($sitemapDir, 0755, true);
        }
        file_put_contents($sitemapDir . $filename, $content);
    }
}

function generateStaticPagesSitemap($conn, $base_url) {
    $xml = createXmlHeader();
    $staticPages = ['', 'zoek', 'blog'];
    foreach ($staticPages as $page) {
        $loc = rtrim($base_url, '/');
        if ($page !== '') {
            $loc .= '/' . $page;
        }
        $xml .= "<url>\n";
        $xml .= "  <loc>" . $loc . "</loc>\n";
        $xml .= "  <lastmod>" . date('c') . "</lastmod>\n";
        $xml .= "  <changefreq>daily</changefreq>\n";
        $xml .= "  <priority>1.0</priority>\n";
        $xml .= "</url>\n";
    }

    $result = mysqli_query($conn, "SELECT slug, created_at FROM pages");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $url = rtrim($base_url, '/') . '/' . urlencode($row['slug']);
            $lastmod = date('c', strtotime($row['created_at']));
            $xml .= "<url>\n";
            $xml .= "  <loc>$url</loc>\n";
            $xml .= "  <lastmod>$lastmod</lastmod>\n";
            $xml .= "  <changefreq>monthly</changefreq>\n";
            $xml .= "  <priority>0.8</priority>\n";
            $xml .= "</url>\n";
        }
    }
    $xml .= createXmlFooter();
    writeSitemap('pages-sitemap.xml', $xml);
}

function generatePostsSitemap($conn, $base_url) {
    $xml = createXmlHeader();
    $result = mysqli_query($conn, "SELECT slug, updated_at, created_at FROM blogs");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $url = rtrim($base_url, '/') . '/' . urlencode($row['slug']);
            $lastmod = !empty($row['updated_at']) ? date('c', strtotime($row['updated_at'])) : date('c', strtotime($row['created_at']));
            $xml .= "<url>\n";
            $xml .= "  <loc>$url</loc>\n";
            $xml .= "  <lastmod>$lastmod</lastmod>\n";
            $xml .= "  <changefreq>weekly</changefreq>\n";
            $xml .= "  <priority>0.7</priority>\n";
            $xml .= "</url>\n";
        }
    }
    $xml .= createXmlFooter();
    writeSitemap('posts-sitemap.xml', $xml);
}

function generateCategoriesSitemap($conn, $base_url) {
    $xml = createXmlHeader();
    $result = mysqli_query($conn, "SELECT slug, created_at FROM blog_categories");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $url = rtrim($base_url, '/') . '/category/' . urlencode($row['slug']);
            $lastmod = date('c', strtotime($row['created_at']));
            $xml .= "<url>\n";
            $xml .= "  <loc>$url</loc>\n";
            $xml .= "  <lastmod>$lastmod</lastmod>\n";
            $xml .= "  <changefreq>weekly</changefreq>\n";
            $xml .= "  <priority>0.6</priority>\n";
            $xml .= "</url>\n";
        }
    }
    $xml .= createXmlFooter();
    writeSitemap('categories-sitemap.xml', $xml);
}

function generateTagsSitemap($conn, $base_url) {
    $xml = createXmlHeader();
    $result = mysqli_query($conn, "SELECT slug, created_at FROM blog_tags");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $url = rtrim($base_url, '/') . '/tag/' . urlencode($row['slug']);
            $lastmod = date('c', strtotime($row['created_at']));
            $xml .= "<url>\n";
            $xml .= "  <loc>$url</loc>\n";
            $xml .= "  <lastmod>$lastmod</lastmod>\n";
            $xml .= "  <changefreq>weekly</changefreq>\n";
            $xml .= "  <priority>0.5</priority>\n";
            $xml .= "</url>\n";
        }
    }
    $xml .= createXmlFooter();
    writeSitemap('tags-sitemap.xml', $xml);
}

function generateCarsSitemap($conn, $base_url) {
    $xml = createXmlHeader();
    $result = mysqli_query($conn, "SELECT slug, updated_at, created_at FROM cars WHERE status = 'te_koop'");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $url = rtrim($base_url, '/') . '/listing/' . urlencode($row['slug']);
            $lastmod = !empty($row['updated_at']) ? date('c', strtotime($row['updated_at'])) : date('c', strtotime($row['created_at']));
            $xml .= "<url>\n";
            $xml .= "  <loc>$url</loc>\n";
            $xml .= "  <lastmod>$lastmod</lastmod>\n";
            $xml .= "  <changefreq>daily</changefreq>\n";
            $xml .= "  <priority>0.9</priority>\n";
            $xml .= "</url>\n";
        }
    }
    $xml .= createXmlFooter();
    writeSitemap('cars-sitemap.xml', $xml);
}

function generateCarBrandsSitemap($conn, $base_url) {
    $xml = createXmlHeader();
    $result = mysqli_query($conn, "SELECT DISTINCT vehica_brand FROM cars WHERE vehica_brand IS NOT NULL AND vehica_brand != ''");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $brand_slug = strtolower(str_replace(' ', '-', $row['vehica_brand']));
            $url = rtrim($base_url, '/') . '/zoek/' . urlencode($brand_slug);
            $xml .= "<url>\n";
            $xml .= "  <loc>$url</loc>\n";
            $xml .= "  <lastmod>" . date('c') . "</lastmod>\n";
            $xml .= "  <changefreq>daily</changefreq>\n";
            $xml .= "  <priority>0.8</priority>\n";
            $xml .= "</url>\n";
        }
    }
    $xml .= createXmlFooter();
    writeSitemap('car_brands-sitemap.xml', $xml);
}

function generateSitemapIndex($base_url) {
    global $sitemapDir;
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
           '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    $sitemaps = [
        'pages-sitemap.xml',
        'posts-sitemap.xml',
        'categories-sitemap.xml',
        'tags-sitemap.xml',
        'cars-sitemap.xml',
        'car_brands-sitemap.xml'
    ];
    foreach ($sitemaps as $sitemap) {
        $filePath = $sitemapDir . $sitemap;
        if (file_exists($filePath)) {
            $url = rtrim($base_url, '/') . '/sitemap/' . $sitemap;
            $lastmod = date('c', filemtime($filePath));
            $xml .= "<sitemap>\n";
            $xml .= "  <loc>$url</loc>\n";
            $xml .= "  <lastmod>$lastmod</lastmod>\n";
            $xml .= "</sitemap>\n";
        }
    }
    $xml .= '</sitemapindex>';
    writeSitemap('sitemap.xml', $xml);
}

generateStaticPagesSitemap($conn, $base_url);
generatePostsSitemap($conn, $base_url);
generateCategoriesSitemap($conn, $base_url);
generateTagsSitemap($conn, $base_url);
generateCarsSitemap($conn, $base_url);
generateCarBrandsSitemap($conn, $base_url);
generateSitemapIndex($base_url);

mysqli_close($conn);

echo "Sitemap dosyaları başarıyla oluşturuldu!";
?>
