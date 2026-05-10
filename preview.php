<?php
$plan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$plan_id) {
    header("Location: index.php");
    exit;
}

require_once 'includes/config.php';
require_once 'log.php';

// Plan bilgilerini al
$stmt = $pdo->prepare("SELECT cp.*, w.name as website_name, w.url as website_url FROM content_plans cp LEFT JOIN websites w ON cp.website_id = w.id WHERE cp.id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$plan) {
    die("Icerik bulunamadi.");
}

// İçeriği al
$generatedContent = '';
$wordCount = 0;
$seoTitle = $plan['title'];
$seoDesc = '';
$slug = '';
$imagesGenerated = false;
$imagesCount = 0;

// 1. writer_memory'de ara
$stmt = $pdo->prepare("SELECT * FROM writer_memory WHERE topic = ? AND website_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$plan['focus_keyword'], $plan['website_id']]);
$memory = $stmt->fetch(PDO::FETCH_ASSOC);

if ($memory && !empty($memory['content'])) {
    $generatedContent = $memory['content'];
    $wordCount = $memory['word_count'] ?? 0;
    $seoTitle = $memory['seo_title'] ?? $plan['title'];
    $seoDesc = $memory['seo_description'] ?? '';
    $slug = $memory['slug'] ?? '';
    $imagesGenerated = $memory['images_generated'] ?? false;
    $imagesCount = $memory['images_count'] ?? 0;
}

// 2. writer_jobs progress_data'dan al
if (empty($generatedContent)) {
    $stmt = $pdo->prepare("SELECT progress_data FROM writer_jobs WHERE plan_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$plan_id]);
    $jobData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($jobData && !empty($jobData['progress_data'])) {
        $progress = json_decode($jobData['progress_data'], true);
        $generatedContent = $progress['full_html'] ?? '';
        if (!empty($generatedContent)) {
            $wordCount = str_word_count(strip_tags($generatedContent));
        }
    }
}

// 3. Eski generated_content kolonu (backward compatibility)
if (empty($generatedContent) && isset($plan['generated_content'])) {
    $generatedContent = $plan['generated_content'];
    $wordCount = str_word_count(strip_tags($generatedContent));
}

// === GÖRSEL PATH'LERİNİ DÜZELT ===
// Panel: https://mailfly.be/content/
// Görseller: https://mailfly.be/content/uploads/ 
// src="/uploads/..." → src="/content/uploads/..."
$generatedContent = str_replace(
    ['src="/uploads/', "src='/uploads/"],
    ['src="/content/uploads/', "src='/content/uploads/"],
    $generatedContent
);

$page_title = $plan['title'] . ' - Icerik Önizleme';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link href="assets/css/preview.css" rel="stylesheet">
    <style>
        figure.featured-image,
        figure.inline-image {
            margin: 20px 0;
            text-align: center;
        }
        figure.featured-image img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        figure.inline-image img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        figure figcaption {
            margin-top: 8px;
            font-size: 13px;
            color: #666;
            font-style: italic;
        }
        .images-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .no-images-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fff3e0;
            color: #e65100;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <div class="preview-header">
            <a href="content-plan.php?website_id=<?php echo $plan['website_id']; ?>" class="back-link">
                <span class="material-icons-round" style="font-size: 18px;">arrow_back</span> Icerik Planina Don
            </a>
            <h1><?php echo htmlspecialchars($seoTitle); ?></h1>
            <div class="preview-meta">
                <span><span class="material-icons-round" style="font-size: 16px;">vpn_key</span> <?php echo htmlspecialchars($plan['focus_keyword']); ?></span>
                <span><span class="material-icons-round" style="font-size: 16px;">category</span> <?php echo htmlspecialchars($plan['category'] ?? 'Genel'); ?></span>
                <span><span class="material-icons-round" style="font-size: 16px;">calendar_today</span> <?php echo date('d F Y', strtotime($plan['scheduled_date'])); ?></span>
                <?php if($wordCount > 0): ?>
                <span><span class="material-icons-round" style="font-size: 16px;">text_fields</span> <?php echo $wordCount; ?> kelime</span>
                <?php endif; ?>
                <?php if($imagesGenerated): ?>
                <span class="images-badge"><span class="material-icons-round" style="font-size: 14px;">image</span> <?php echo $imagesCount; ?> görsel</span>
                <?php else: ?>
                <span class="no-images-badge"><span class="material-icons-round" style="font-size: 14px;">hide_image</span> Görsel yok</span>
                <?php endif; ?>
            </div>
            <div style="margin-top: 15px;">
                <span class="badge <?php echo $plan['status'] == 'published' ? 'badge-published' : 'badge-pending'; ?>">
                    <?php echo $plan['status'] == 'published' ? 'Yayinlandi' : 'Bekliyor'; ?>
                </span>
            </div>
            <?php if($seoDesc): ?>
            <div class="seo-info">
                <strong>SEO Meta Açıklama:</strong>
                <span><?php echo htmlspecialchars($seoDesc); ?></span>
                <?php if($slug): ?>
                <span style="margin-top: 6px;"><strong>Slug:</strong> <?php echo htmlspecialchars($slug); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="preview-content">
            <?php if(!empty($generatedContent)): ?>
                <article><?php echo $generatedContent; ?></article>
            <?php else: ?>
                <div class="empty-state">
                    <span class="material-icons-round">description</span>
                    <h3>Icerik henuz olusturulmamis</h3>
                    <p>Bu icerigi olusturmak icin icerik planina donup "Olustur" butonuna tiklayin.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="preview-footer">
            <a href="content-plan.php?website_id=<?php echo $plan['website_id']; ?>" class="btn btn-secondary">
                <span class="material-icons-round">arrow_back</span>
                Icerik Planina Don
            </a>
            <button onclick="window.print();" class="btn btn-secondary">
                <span class="material-icons-round">print</span>
                Yazdir
            </button>
        </div>
    </div>
</body>
</html>