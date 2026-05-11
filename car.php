<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('ABSPATH', __DIR__);

require_once(__DIR__ . '/includes/database.php');
require_once(__DIR__ . '/includes/thumb.php');

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    include __DIR__ . '/404.php';
    exit;
}

$stmt = $conn->prepare("SELECT * FROM cars WHERE slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$car = $result->fetch_assoc();
$stmt->close();

if (!$car) {
    include __DIR__ . '/404.php';
    exit;
}

$seo_title = !empty($car['seo_title']) 
    ? $car['seo_title'] 
    : trim($car['vehica_brand'] . " " . $car['vehica_model'] . " " . $car['vehica_year'] . " | " . $car['name']);
$seo_description = !empty($car['seo_description']) 
    ? $car['seo_description'] 
    : (
        !empty($car['description']) 
            ? mb_substr(strip_tags($car['description']), 0, 155) 
            : "Bekijk deze {$car['vehica_brand']} {$car['vehica_model']} ({$car['vehica_year']}) te koop bij UMTCar. Prijs: {$car['vehica_price']}€."
    );

$canonical_url = rtrim($base_url, '/') . '/listing/' . urlencode($slug);

$meta_title = $seo_title;
$meta_description = $seo_description;
require_once __DIR__ . '/header.php';

?>
<title><?= htmlspecialchars($meta_title) ?></title>
<meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
<?php

$db_gallery = [];
if (!empty($car['uuid'])) {
    $galeri_stmt = $conn->prepare("SELECT * FROM car_gallery WHERE car_uuid = ? ORDER BY sort_order ASC, id ASC");
    $galeri_stmt->bind_param("s", $car['uuid']);
    $galeri_stmt->execute();
    $galeri_result = $galeri_stmt->get_result();
    if ($galeri_result) {
        $db_gallery = $galeri_result->fetch_all(MYSQLI_ASSOC);
    }
    $galeri_stmt->close();
}

// JS için optimize edilmiş resim URL'lerini hazırla
$image_data_for_js = [];
if (!empty($db_gallery)) {
    foreach ($db_gallery as $img) {
        $image_data_for_js[] = [
            'main' => get_thumbnail_url($img['image_url'], 842, 632),
            'thumb' => get_thumbnail_url($img['image_url'], 110, 90),
            'lightbox' => get_thumbnail_url($img['image_url'], 1920, 1080)
        ];
    }
} elseif (!empty($car['thumbnail_url'])) {
    $image_data_for_js[] = [
        'main' => get_thumbnail_url($car['thumbnail_url'], 842, 632),
        'thumb' => get_thumbnail_url($car['thumbnail_url'], 110, 90),
        'lightbox' => get_thumbnail_url($car['thumbnail_url'], 1920, 1080)
    ];
}
$first_image = $image_data_for_js[0] ?? null;

$features = [];
if (!empty($car['vehica_features'])) {
    $features = array_filter(array_map('trim', preg_split('/[,|，]/', $car['vehica_features'])));
}

function get_related_cars($car, $conn, $limit = 6) {
    $brand = trim($car['vehica_brand'] ?? '');
    $uuid = $car['uuid'];
    $query = "SELECT * FROM cars WHERE uuid != ? AND vehica_brand = ? AND status != 'verkocht' AND thumbnail_url IS NOT NULL AND thumbnail_url NOT LIKE '%VERKOCHT%' LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $uuid, $brand, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $related = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $related;
}

function create_breadcrumbs($car) {
    $brand = htmlspecialchars($car['vehica_brand']);
    $brand_slug = strtolower(str_replace(' ', '-', $brand));
    $name = htmlspecialchars($car['name']);
    return <<<HTML
<div class="vehica-breadcrumbs-wrapper">
    <div class="vehica-breadcrumbs">
        <div class="vehica-breadcrumbs__single"><a href="/" class="vehica-breadcrumbs__link" title="Home">Home</a><span class="vehica-breadcrumbs__separator"></span></div>
        <div class="vehica-breadcrumbs__single"><a href="/zoek" class="vehica-breadcrumbs__link" title="Zoeken">Zoeken</a><span class="vehica-breadcrumbs__separator"></span></div>
        <div class="vehica-breadcrumbs__single"><a href="/zoek/{$brand_slug}" class="vehica-breadcrumbs__link" title="{$brand}">{$brand}</a><span class="vehica-breadcrumbs__separator"></span></div>
        <span class="vehica-breadcrumbs__last">{$name}</span>
    </div>
</div>
HTML;
}
?>

<div class="bwsingle-mainwrap">
    <div class="mindle_breadcrumbs"><?= create_breadcrumbs($car); ?></div>
    <div class="bwsingle-contentrow">
        <div class="pro-gallery" id="my-gallery" data-images='<?= json_encode($image_data_for_js) ?>'>
            <div class="pro-gallery__main">
                <div class="swiper">
                    <div class="swiper-wrapper">
                        <?php if ($first_image): ?>
                            <div class="swiper-slide"><img src="<?= htmlspecialchars($first_image['main']) ?>" alt="<?= htmlspecialchars($car['name']) ?> - Afbeelding 1" fetchpriority="high"></div>
                        <?php endif; ?>
                    </div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>
                <div class="pro-gallery__counter"></div>
            </div>
            <div class="pro-gallery__thumbs">
                <div class="swiper">
                    <div class="swiper-wrapper">
                         <?php if ($first_image): ?>
                            <div class="swiper-slide"><img src="<?= htmlspecialchars($first_image['thumb']) ?>" alt="<?= htmlspecialchars($car['name']) ?> - Thumbnail 1" loading="lazy"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="pro-gallery-lightbox" id="my-gallery-lightbox">
            <span class="pro-gallery-lightbox__close">&times;</span>
            <div class="swiper">
                <div class="swiper-wrapper">
                     <?php if ($first_image): ?>
                        <div class="swiper-slide"><img src="<?= htmlspecialchars($first_image['lightbox']) ?>" alt="<?= htmlspecialchars($car['name']) ?> - Afbeelding 1 (groot)" loading="lazy"></div>
                    <?php endif; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-pagination"></div>
            </div>
        </div>

        <div class="bwsingle-info-panel">
            <div class="bwsingle-title"><?= htmlspecialchars($car['name']) ?></div>
            <div class="bwsingle-info-short">
                <?php if(!empty($car['vehica_year'])): ?><span><?= htmlspecialchars($car['vehica_year']) ?></span><?php endif; ?>
                <?php if(!empty($car['vehica_mileage'])): ?><span><?= number_format($car['vehica_mileage'], 0, ',', '.') ?> KM</span><?php endif; ?>
                <?php if(!empty($car['vehica_body'])): ?><span><?= htmlspecialchars($car['vehica_body']) ?></span><?php endif; ?>
                <?php if(!empty($car['vehica_fuel'])): ?><span><?= htmlspecialchars($car['vehica_fuel']) ?></span><?php endif; ?>
            </div>
            <div class="bwsingle-price"><?= htmlspecialchars($car['vehica_price']) ?>€</div>
            <div class="bwsingle-price-divider"></div>
            <div class="bwsingle-fav-row"><span class="material-icons-round">star</span> <span>Voeg toe aan favorieten</span></div>
            <div class="bwsingle-attrs-card">
                <div class="bwsingle-attrs-table">
                    <div class="bwsingle-attrs-left">
                        <div class="bwsingle-attr-row"><span class="material-icons-round attr-icon">badge</span><span class="bwsingle-attr-label">Merk:</span><span class="bwsingle-attr-val"><?= htmlspecialchars($car['vehica_brand'] ?? 'N/A') ?></span></div>
                        <div class="bwsingle-attr-row"><span class="material-icons-round attr-icon">local_car_wash</span><span class="bwsingle-attr-label">Type:</span><span class="bwsingle-attr-val"><?= htmlspecialchars($car['vehica_type'] ?? 'N/A') ?></span></div>
                        <div class="bwsingle-attr-row"><span class="material-icons-round attr-icon">local_gas_station</span><span class="bwsingle-attr-label">Brandstof:</span><span class="bwsingle-attr-val"><?= htmlspecialchars($car['vehica_fuel'] ?? 'N/A') ?></span></div>
                        <div class="bwsingle-attr-row"><span class="material-icons-round attr-icon">palette</span><span class="bwsingle-attr-label">Kleur:</span><span class="bwsingle-attr-val"><?= htmlspecialchars($car['vehica_color'] ?? 'N/A') ?></span></div>
                    </div>
                    <div class="bwsingle-attrs-right">
                        <div class="bwsingle-attr-row"><span class="material-icons-round attr-icon">label</span><span class="bwsingle-attr-label">Model:</span><span class="bwsingle-attr-val"><?= htmlspecialchars($car['vehica_model'] ?? 'N/A') ?></span></div>
                        <div class="bwsingle-attr-row"><span class="material-icons-round attr-icon">settings</span><span class="bwsingle-attr-label">Motor:</span><span class="bwsingle-attr-val"><?= htmlspecialchars($car['vehica_engine'] ?? 'N/A') ?></span></div>
                        <div class="bwsingle-attr-row"><span class="material-icons-round attr-icon">calendar_today</span><span class="bwsingle-attr-label">Bouwjaar:</span><span class="bwsingle-attr-val"><?= htmlspecialchars($car['vehica_year'] ?? 'N/A') ?></span></div>
                        <div class="bwsingle-attr-row"><span class="material-icons-round attr-icon">speed</span><span class="bwsingle-attr-label">PK:</span><span class="bwsingle-attr-val"><?= htmlspecialchars($car['vehica_hp'] ?? 'N/A') ?></span></div>
                    </div>
                </div>
                <div class="bwsingle-attrs-bottom">
                    <div class="bwsingle-attr-row"><span class="material-icons-round attr-icon">bolt</span><span class="bwsingle-attr-label">kW:</span><span class="bwsingle-attr-val"><?= htmlspecialchars($car['vehica_kw'] ?? 'N/A') ?></span></div>
                    <div class="bwsingle-attr-row"><span class="material-icons-round attr-icon">manage_history</span><span class="bwsingle-attr-label">Transmissie:</span><span class="bwsingle-attr-val"><?= htmlspecialchars($car['vehica_transmission'] ?? 'N/A') ?></span></div>
                    <div class="bwsingle-attr-row"><span class="material-icons-round attr-icon">speed</span><span class="bwsingle-attr-label">Kilometerstand:</span><span class="bwsingle-attr-val"><?= (!empty($car['vehica_mileage']) ? number_format($car['vehica_mileage'], 0, ',', '.') . ' ' : 'N/A') ?></span></div>
                </div>
            </div>
            <a href="tel:+32 487 57 05 23" class="bwsingle-btn-main call-btn" aria-label="Direct bellen"><span class="material-icons-round">call</span> +32 487 57 05 23</a>
            <a href="https://wa.me/32487570523?text=Ik%20ben%20geïnteresseerd%20in%20deze%20auto:%20<?= urlencode($car['name']) ?>" class="bwsingle-btn-main whatsapp-btn" aria-label="WhatsApp bericht sturen"><span class="material-icons-round">chat</span> WhatsApp bericht</a>
            <a href="/contact" class="bwsingle-btn-main message-btn" aria-label="Stuur bericht"><span class="material-icons-round">email</span> Stuur bericht</a>
        </div>
    </div>
    <div class="bwsingle-desc-features-row">
        <div class="bwsingle-desc">
            <h2>Achtergrond</h2>
            <div class="bwsingle-desc-inner"><?= !empty($car['description']) ? $car['description'] : '<p>Geen achtergrondinformatie beschikbaar.</p>' ?></div>
        </div>
        <div class="bwsingle-features">
            <h2>Belangrijke Eigenschappen</h2>
            <div class="bwsingle-features-list">
                <?php foreach ($features as $feature): ?>
                    <div class="bwsingle-feature"><span class="material-icons-round feature-icon">check_circle</span><?= htmlspecialchars($feature) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div style="clear: both;"></div>
    <div class="bwsingle-related-block">
        <h2>Gerelateerde Auto's</h2>
        <div class="bwsingle-related-list">
            <?php
            $related = get_related_cars($car, $conn, 4);
            if (empty($related)) {
                echo '<div class="bwsingle-norelated">Geen gerelateerde auto\'s gevonden.</div>';
            } else {
                foreach ($related as $item): ?>
                    <a href="/listing/<?= htmlspecialchars($item['slug']) ?>" class="bwsingle-related-item">
                        <div class="bwsingle-related-img" style="background-image:url('<?= htmlspecialchars($item['thumbnail_url']) ?>')"></div>
                        <div class="bwsingle-type-badge"><?= htmlspecialchars($item['vehica_year'] ?? 'Type') ?></div>
                        <div class="bwsingle-related-body">
                            <div class="bwsingle-related-price"><?= htmlspecialchars($item['vehica_price']) ?>€</div>
                            <div class="bwsingle-related-title"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="bwsingle-related-meta">
                                <?php if(!empty($item['vehica_fuel'])): ?><span><span class="material-icons-round">local_gas_station</span> <?= htmlspecialchars($item['vehica_fuel']) ?></span><?php endif; ?>
                                <?php if(!empty($item['vehica_mileage'])): ?><span><span class="material-icons-round">speed</span> <?= number_format($item['vehica_mileage'], 0, ',', '.') ?></span><?php endif; ?>
                                <?php if(!empty($item['vehica_transmission'])): ?><span><span class="material-icons-round">settings</span> <?= htmlspecialchars($item['vehica_transmission']) ?></span><?php endif; ?>
                            </div>
                        </div>
                        <button class="bwsingle-btn-details">BEKIJK DETAILS →</button>
                    </a>
                <?php endforeach; }
            ?>
        </div>
    </div>
</div>
<script src="/assets/js/gallery.js" defer></script>
<?php
require_once(__DIR__ . '/includes/schema/carschema.php');
include __DIR__ . '/footer.php'; 
?>
