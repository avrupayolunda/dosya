<?php
require_once __DIR__ . '/includes/database.php';

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

global $conn;
if (!$conn) {
    if ($is_ajax) { http_response_code(500); echo 'Database connection error.'; exit; }
    else { die("Geen databaseverbinding: " . mysqli_connect_error()); }
}

require_once __DIR__ . '/post/helper/pagination.php';

function slugify($text) {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);
    $text = strtolower(trim($text, '-'));
    return $text;
}

function resolve_filter_value($conn, $column, $incoming) {
    if ($incoming === '' || $incoming === null) return '';
    $allowed = ['vehica_brand','vehica_type','vehica_fuel','vehica_transmission','vehica_color'];
    if (!in_array($column, $allowed, true)) return $incoming;
    $candidate = trim($incoming);
    $candidate_lower = mb_strtolower($candidate, 'UTF-8');
    $sql = "SELECT DISTINCT $column FROM cars WHERE $column IS NOT NULL AND $column != ''";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_row()) {
            $val = $row[0];
            if ($val === null) continue;
            $val_trim = trim($val);
            if ($val_trim === '') continue;
            if (mb_strtolower($val_trim, 'UTF-8') === $candidate_lower) return $val_trim;
            if (slugify($val_trim) === slugify($candidate)) return $val_trim;
            if (slugify($val_trim) === $candidate) return $val_trim;
        }
    }
    return $incoming;
}

$brand_in = isset($_GET['brand']) ? mb_strtolower(trim($_GET['brand']), 'UTF-8') : '';
$type_in  = isset($_GET['type']) ? mb_strtolower(trim($_GET['type']), 'UTF-8') : '';
$fuel_in  = isset($_GET['fuel']) ? mb_strtolower(trim($_GET['fuel']), 'UTF-8') : '';
$trans_in = isset($_GET['transmissie']) ? mb_strtolower(trim($_GET['transmissie']), 'UTF-8') : '';
$color_in = isset($_GET['kleur']) ? mb_strtolower(trim($_GET['kleur']), 'UTF-8') : '';

$brand = resolve_filter_value($conn, 'vehica_brand', $brand_in);
$type  = resolve_filter_value($conn, 'vehica_type', $type_in);
$fuel  = resolve_filter_value($conn, 'vehica_fuel', $fuel_in);
$trans = resolve_filter_value($conn, 'vehica_transmission', $trans_in);
$color = resolve_filter_value($conn, 'vehica_color', $color_in);

$min_price = isset($_GET['min_price']) ? preg_replace('/\D/', '', $_GET['min_price']) : '';
$max_price = isset($_GET['max_price']) ? preg_replace('/\D/', '', $_GET['max_price']) : '';
$min_km = isset($_GET['min_km']) ? preg_replace('/\D/', '', $_GET['min_km']) : '';
$max_km = isset($_GET['max_km']) ? preg_replace('/\D/', '', $_GET['max_km']) : '';
$min_bouwjaar = $_GET['min_bouwjaar'] ?? '';
$max_bouwjaar = $_GET['max_bouwjaar'] ?? '';
$keyword = $_GET['keyword'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;

$brand_info = null;
if (!empty($brand) && empty($keyword)) {
    $slug_from_url = slugify($brand_in);
    $stmt_brand = $conn->prepare("SELECT name, content, logo_url, seo_title, seo_description FROM car_brands WHERE name = ? OR slug = ? LIMIT 1");
    $stmt_brand->bind_param('ss', $brand, $slug_from_url);
    $stmt_brand->execute();
    $result_brand = $stmt_brand->get_result();
    if ($result_brand && $result_brand->num_rows > 0) {
        $brand_info = $result_brand->fetch_assoc();
    }
    $stmt_brand->close();
}

if (!$is_ajax) {
    if ($brand_info) {
        $seo_title = trim($brand_info['seo_title'] ?? '');
        if ($seo_title === '') {
            $seo_title = trim($brand_info['name'] ?? '');
        }
        $seo_description = trim($brand_info['seo_description'] ?? '');
        if ($seo_description === '') {
            $plain = trim(strip_tags($brand_info['content'] ?? ''));
            if (mb_strlen($plain, 'UTF-8') > 160) {
                $seo_description = mb_substr($plain, 0, 157, 'UTF-8') . '...';
            } else {
                $seo_description = $plain;
            }
        }
    }

    // === CANONICAL URL FOR CARLIST ===
    if (!empty($brand_in) && !empty($type_in)) {
        $canonical_url = rtrim($base_url, '/') . '/zoek/' . urlencode(slugify($brand_in)) . '/' . urlencode(slugify($type_in));
    } elseif (!empty($brand_in)) {
        $canonical_url = rtrim($base_url, '/') . '/zoek/' . urlencode(slugify($brand_in));
    } else {
        $canonical_url = rtrim($base_url, '/') . '/zoek';
    }
    
    $cache_key_to_delete = "page_" . md5($_SERVER['REQUEST_URI']);
    if (class_exists('\Phpfastcache\CacheManager')) {
        try {
            $InstanceCache = \Phpfastcache\CacheManager::getInstance('files');
            $InstanceCache->deleteItem($cache_key_to_delete);
        } catch (Exception $e) {
            error_log('Cache could not be cleared: ' . $e->getMessage());
        }
    }
    
    include 'header.php';
}

$where = [];
$params = [];
$typestr = '';
if ($brand != '') { $where[] = 'vehica_brand = ?'; $params[] = $brand; $typestr .= 's'; }
if ($type != '') { $where[] = 'vehica_type = ?'; $params[] = $type; $typestr .= 's'; }
if ($fuel != '') { $where[] = 'vehica_fuel = ?'; $params[] = $fuel; $typestr .= 's'; }
if ($trans != '') { $where[] = 'vehica_transmission = ?'; $params[] = $trans; $typestr .= 's'; }
if ($color != '') { $where[] = 'vehica_color = ?'; $params[] = $color; $typestr .= 's'; }
if ($min_price != '') { $where[] = "CAST(REPLACE(REPLACE(REPLACE(vehica_price, '.', ''), ',', ''), ' ', '') AS UNSIGNED) >= ?"; $params[] = $min_price; $typestr .= 'i'; }
if ($max_price != '') { $where[] = "CAST(REPLACE(REPLACE(REPLACE(vehica_price, '.', ''), ',', ''), ' ', '') AS UNSIGNED) <= ?"; $params[] = $max_price; $typestr .= 'i'; }
if ($min_km != '') { $where[] = "CAST(REPLACE(REPLACE(REPLACE(vehica_mileage, '.', ''), ',', ''), ' ', '') AS UNSIGNED) >= ?"; $params[] = $min_km; $typestr .= 'i'; }
if ($max_km != '') { $where[] = "CAST(REPLACE(REPLACE(REPLACE(vehica_mileage, '.', ''), ',', ''), ' ', '') AS UNSIGNED) <= ?"; $params[] = $max_km; $typestr .= 'i'; }
if ($min_bouwjaar != '') { $where[] = "CAST(vehica_year AS UNSIGNED) >= ?"; $params[] = $min_bouwjaar; $typestr .= 'i'; }
if ($max_bouwjaar != '') { $where[] = "CAST(vehica_year AS UNSIGNED) <= ?"; $params[] = $max_bouwjaar; $typestr .= 'i'; }
if ($keyword != '') { $where[] = "(name LIKE CONCAT('%', ?, '%') OR description LIKE CONCAT('%', ?, '%'))"; $params[] = $keyword; $params[] = $keyword; $typestr .= 'ss'; }

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$count_sql = "SELECT COUNT(*) as total FROM cars $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($typestr) { $count_stmt->bind_param($typestr, ...$params); }
$count_stmt->execute();
$total_cars = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$correct_total_pages = ceil($total_cars / $per_page);
$page = min($page, $correct_total_pages > 0 ? $correct_total_pages : 1);
$offset = ($page - 1) * $per_page;

$order = "created_at DESC";
if (isset($_GET['sorteren-op'])) {
    $sort = $_GET['sorteren-op'];
    if ($sort == 'price_asc') $order = "CAST(REPLACE(REPLACE(REPLACE(vehica_price, '.', ''), ',', ''), ' ', '') AS UNSIGNED) ASC";
    if ($sort == 'price_desc') $order = "CAST(REPLACE(REPLACE(REPLACE(vehica_price, '.', ''), ',', ''), ' ', '') AS UNSIGNED) DESC";
    if ($sort == 'year_desc') $order = "vehica_year DESC";
    if ($sort == 'year_asc') $order = "vehica_year ASC";
}

$sql = "SELECT uuid, name, created_at, vehica_brand, vehica_model, vehica_type, vehica_year, vehica_mileage, vehica_transmission, vehica_fuel, vehica_price, thumbnail_url, slug, status FROM cars $where_sql ORDER BY $order LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$final_params = array_merge($params, [$per_page, $offset]);
$final_typestr = $typestr . 'ii';
$stmt->bind_param($final_typestr, ...$final_params);
$stmt->execute();
$result = $stmt->get_result();
$cars = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

if ($is_ajax) {
    ob_start();
} else {
    ?>
<div id="filter-form-container">
    <?php
    $filter_action = $_SERVER['REQUEST_URI'];
    require_once __DIR__.'/post/cars/filter.php';
    ?>
</div>
<button class="umtfilter-mobile-btn" id="umtfilterMobileBtn">Filtreer</button>
<div class="umtfilter-modal" id="umtfilterModal"><div class="umtfilter-modal-content"><button class="umtfilter-modal-close" id="umtfilterModalClose" type="button">×</button><div id="filterModalInner"></div></div></div>
<div id="inventory-results">
<?php
}
if ($brand_info && !empty($brand_info['content'])) {
    $for_sale_count = 0;
    $sold_count = 0;

    $stmt_fs = $conn->prepare("SELECT COUNT(*) FROM cars WHERE vehica_brand = ? AND status = 'te_koop'");
    $stmt_fs->bind_param('s', $brand);
    $stmt_fs->execute();
    $stmt_fs->bind_result($for_sale_count);
    $stmt_fs->fetch();
    $stmt_fs->close();

    $stmt_s = $conn->prepare("SELECT COUNT(*) FROM cars WHERE vehica_brand = ? AND status = 'verkocht'");
    $stmt_s->bind_param('s', $brand);
    $stmt_s->execute();
    $stmt_s->bind_result($sold_count);
    $stmt_s->fetch();
    $stmt_s->close();

    $full_description = $brand_info['content'];
?>

<div class="ubrand-stage" id="ubrand_stage">
    <div class="ubrand-stage__inner">
        <div class="ubrand-stage__left">
            <div class="ubrand-stage__logo-wrapper">
                <?php if (!empty($brand_info['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($brand_info['logo_url']) ?>" alt="<?= htmlspecialchars($brand_info['name']) ?> Logo" class="ubrand-stage__logo">
                <?php endif; ?>
            </div>
            <div class="ubrand-stage__brand-wrapper">
                <h1 class="ubrand-stage__title"><?= htmlspecialchars($brand_info['name']) ?></h1>
                <div class="ubrand-stage__info-btn" data-action="open-popup">
                    <span class="material-icons-round">info</span>
                </div>
            </div>
        </div>

        <div class="ubrand-stage__stats">
            <div class="ubrand-stage__stat">
                <div class="ubrand-stage__stat-icon">
                    <span class="material-icons-round">directions_car</span>
                </div>
                <div class="ubrand-stage__stat-info">
                    <div class="ubrand-stage__stat-value"><?= number_format($for_sale_count) ?></div>
                    <div class="ubrand-stage__stat-label">Beschikbaar voertuig</div>
                </div>
            </div>
            <div class="ubrand-stage__stat">
                <div class="ubrand-stage__stat-icon">
                    <span class="material-icons-round">inventory</span>
                </div>
                <div class="ubrand-stage__stat-info">
                    <div class="ubrand-stage__stat-value"><?= number_format($sold_count) ?></div>
                    <div class="ubrand-stage__stat-label">Verkocht voertuig</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ubrand-stage__frame"></div>
</div>

<div class="ubrand-overlay" id="ubrand_overlay"></div>
<div class="ubrand-popup" id="ubrand_popup">
    <div class="ubrand-popup__header">
        <div class="ubrand-popup__title"><?= htmlspecialchars($brand_info['name']) ?></div>
        <div class="ubrand-popup__close" data-action="close-popup">
            <span class="material-icons-round">close</span>
        </div>
    </div>
    <div class="ubrand-popup__content" id="ubrand_popup_content">
        <?= $full_description ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const popup = document.getElementById('ubrand_popup');
    const overlay = document.getElementById('ubrand_overlay');

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-action="open-popup"]')) {
            popup.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        if (e.target.closest('[data-action="close-popup"]') || e.target === overlay) {
            popup.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});
</script>
<?php
}
if ($cars && count($cars)): ?>
    <div class="vhc-cars-grid">
        <?php foreach ($cars as $car): ?>
        <div class="vhc-car-card<?php echo $car['status'] === 'verkocht' ? ' vhc-car-card--sold' : ''; ?>">
            <div class="vhc-car-card__img-block">
                <span class="vhc-car-card__brand"><?=htmlspecialchars($car['vehica_brand'])?></span>
                <div class="vhc-car-card__price"><?=number_format((float)str_replace(['.', ','], '', $car['vehica_price']), 0, '.', ',')?> €</div>
                <a href="/listing/<?=htmlspecialchars($car['slug'])?>">
                    <img src="<?=htmlspecialchars($car['thumbnail_url'] ?: '/default-car.jpg')?>" alt="<?=htmlspecialchars($car['name'])?>" class="vhc-car-card__img">
                    <?php if ($car['status'] === 'verkocht'): ?><div class="vhc-car-card__sold-overlay"><span class="vhc-car-card__sold-text">VERKOCHT</span></div><?php endif; ?>
                </a>
            </div>
            <div class="vhc-car-card__bottom">
                <div class="vhc-car-card__model-row"><span class="vhc-car-card__model"><?=htmlspecialchars($car['name'])?></span></div>
                <div class="vhc-car-card__type-year-row"><span class="vhc-car-card__type"><?=htmlspecialchars($car['vehica_type'])?></span><span class="vhc-car-card__dot"></span><span class="vhc-car-card__year"><?=htmlspecialchars($car['vehica_year'])?></span></div>
                <div class="vhc-car-card__features-row">
                    <div class="vhc-car-card__feature"><span class="material-icons-round">speed</span><span><?=htmlspecialchars($car['vehica_mileage'])?> km</span></div>
                    <div class="vhc-car-card__feature"><span class="material-icons-round">settings</span><span><?=htmlspecialchars($car['vehica_transmission'])?></span></div>
                    <div class="vhc-car-card__feature"><span class="material-icons-round">local_gas_station</span><span><?=htmlspecialchars($car['vehica_fuel'])?></span></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    if (function_exists('render_pagination')) {
        $query_params_for_pagination = $_GET;
        unset($query_params_for_pagination['page']);
        echo render_pagination($page, $correct_total_pages, $query_params_for_pagination);
    }
    ?>
<?php else: ?>
<div style="padding:32px 0;text-align:center;color:#b22323;font-size:21px;">Geen auto's gevonden die aan de criteria voldoen.</div><?php endif;

if ($is_ajax) {
    echo ob_get_clean();
    exit;
}

echo '</div>';
echo '<script src="/assets/js/filter.js?v=' . time() . '" defer></script>';

include 'footer.php';
?>
