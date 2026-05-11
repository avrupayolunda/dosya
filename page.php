<?php
/**
 * page.php — Genel sayfa şablonu (catch-all)
 *
 * DEĞİŞİKLİK: Sayfa veritabanında yoksa, header.php'den ÖNCE 404 durum kodu
 * ayarlanıyor. Böylece Google bu sayfaları 200 olarak algılamaz.
 * Eski kodda header.php çıktı gönderdiği için http_response_code(404) geç kalıyordu.
 */

// Önce veritabanı bağlantısını al (header.php'den bağımsız)
require_once 'panel/config.php';

$slug = $_GET['slug'] ?? '';
$lang = $_GET['lang'] ?? 'nl';

// Sayfa var mı kontrol et — header.php herhangi bir çıktı göndermeden ÖNCE
$_pre_stmt = $pdo->prepare("SELECT id FROM page WHERE slug = ? AND language = ? LIMIT 1");
$_pre_stmt->execute([$slug, $lang]);
$_page_exists = $_pre_stmt->fetchColumn();

if (!$_page_exists) {
    http_response_code(404);
}

require_once 'header.php';

$stmt = $pdo->prepare("SELECT * FROM page WHERE slug = ? AND language = ?");
$stmt->execute([$slug, $lang]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    require_once '404.php';
    require_once 'footer.php';
    exit;
}

include 'includes/snippet/page-snippets.php';
?>

<?php if(isset($page['group_id']) && $page['group_id'] != 1): ?>
<div class="page-header">
    <div class="container">
        <h1 class="page-title"><?= htmlspecialchars($page['title']) ?></h1>
        <div class="breadcrumb">Home / <span><?= htmlspecialchars($page['title']) ?></span></div>
    </div>
</div>
<?php endif; ?>

<div class="page-content-wrapper">
    <?php
    if (!empty($page['builder_content'])) {
        $rows = json_decode($page['builder_content'], true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($rows)) {
            foreach ($rows as $row) {
                $rowStyle = !empty($row['backgroundColor']) ? 'style="background-color:'.$row['backgroundColor'].';"' : '';
                
                echo '<div class="builder-row" '.$rowStyle.'>';
                
                if (!empty($row['columns'])) {
                    echo '<div class="builder-row-columns" style="display:flex; flex-wrap:wrap;">';
                    
                    foreach ($row['columns'] as $column) {
                        $colWidth = !empty($column['width']) ? $column['width'] : '100%';
                        
                        echo '<div class="builder-col" style="width:' . $colWidth . '; box-sizing:border-box;">';
                        
                        if (!empty($column['components'])) {
                            foreach ($column['components'] as $comp) {
                                $type = $comp['type'] ?? '';
                                $themeFile = 'themes/' . $type . '.php';
                                if(file_exists($themeFile)) {
                                    include $themeFile;
                                }
                            }
                        }
                        echo '</div>'; 
                    }
                    echo '</div>'; 
                }
                echo '</div>'; 
            }
        }
    } else {
        echo '<div class="container">' . ($page['content'] ?? '') . '</div>';
    }
    ?>
</div>

<?php
require_once 'footer.php';
?>
