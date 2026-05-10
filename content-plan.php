<?php
$website_id = isset($_GET['website_id']) ? (int)$_GET['website_id'] : 0;
if(!$website_id) {
    header("Location: index.php");
    exit;
}

require_once 'header.php';
require_once 'log.php';

$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
$stmt->execute([$website_id]);
$website = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$website) {
    header("Location: index.php");
    exit;
}

$page_title = $website['name'] . ' - Icerik Plani';
$page_desc = 'AI ile icerik plani olusturun ve yönetin';

// --- AY FİLTRESİ (Arşiv Gösterimi İçin Kalıyor) ---
$selected_month = (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) ? $_GET['month'] : date('Y-m');
$start_date = $selected_month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

$monthStmt = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(scheduled_date, '%Y-%m') as m FROM content_plans WHERE website_id = ? ORDER BY m DESC");
$monthStmt->execute([$website_id]);
$available_months = $monthStmt->fetchAll(PDO::FETCH_COLUMN);

$current_ym = date('Y-m');
if (!in_array($current_ym, $available_months)) {
    $available_months[] = $current_ym;
    rsort($available_months); 
}

$tr_months = [
    '01' => 'Ocak', '02' => 'Şubat', '03' => 'Mart', '04' => 'Nisan',
    '05' => 'Mayıs', '06' => 'Haziran', '07' => 'Temmuz', '08' => 'Ağustos',
    '09' => 'Eylül', '10' => 'Ekim', '11' => 'Kasım', '12' => 'Aralık'
];

$stmt = $pdo->prepare("SELECT * FROM content_plans WHERE website_id = ? AND scheduled_date BETWEEN ? AND ? ORDER BY scheduled_date ASC, scheduled_time ASC, id DESC");
$stmt->execute([$website_id, $start_date, $end_date]);
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM content_plans WHERE website_id = ? AND scheduled_date BETWEEN ? AND ?");
$stmt->execute([$website_id, $start_date, $end_date]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$dailyLimit = (int)($website['daily_post_limit'] ?? 3);
$publishInterval = (int)($website['publish_interval'] ?? 240);

// --- YENİ HAFTALIK HESAPLAMA MANTIĞI ---
// Hafta sonuna (Pazar gününe) kadar kaç gün var?
$today = new DateTime();
$dayOfWeek = (int)$today->format('N'); // 1 (Pazartesi) ile 7 (Pazar) arası
$remainingDays = 7 - $dayOfWeek + 1; // Eğer Pazar ise 1 gün, Pazartesi ise 7 gün döner.

// Aktif plan job'ı var mı?
$stmt = $pdo->prepare("SELECT * FROM plan_generator_jobs WHERE website_id = ? AND status IN ('pending','running') ORDER BY id DESC LIMIT 1");
$stmt->execute([$website_id]);
$activeJob = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="filters-bar" style="margin-bottom: 24px;">
    <div class="filters-left">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div class="stat-icon purple" style="width: 48px; height: 48px;">
                <span class="material-icons-round">public</span>
            </div>
            <div>
                <h1 style="font-size: 24px; margin: 0;"><?php echo htmlspecialchars($website['name']); ?></h1>
                <a href="<?php echo htmlspecialchars($website['url']); ?>" target="_blank" style="font-size: 13px; color: var(--primary);">
                    <?php echo htmlspecialchars($website['url']); ?>
                </a>
                <div style="display: flex; gap: 16px; margin-top: 4px;">
                    <span style="font-size: 12px; color: var(--gray);">
                        <span class="material-icons-round" style="font-size: 14px; vertical-align: middle;">article</span>
                        Günlük: <?php echo $dailyLimit; ?> makale
                    </span>
                    <span style="font-size: 12px; color: var(--gray);">
                        <span class="material-icons-round" style="font-size: 14px; vertical-align: middle;">calendar_view_week</span>
                        Hafta sonuna: <?php echo $remainingDays; ?> gün
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="filters-right" style="display: flex; gap: 10px; align-items: center;">
        <?php if($activeJob): ?>
        <div id="planProgress" style="display: flex; align-items: center; gap: 8px; margin-right: 10px;">
            <div style="width: 150px; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                <div id="progressBar" style="width: <?php echo round(($activeJob['current_day'] / $activeJob['total_days']) * 100); ?>%; height: 100%; background: #6366f1; transition: width 0.3s;"></div>
            </div>
            <span style="font-size: 12px; color: #64748b; white-space: nowrap;">
                <span id="progressText"><?php echo $activeJob['current_day']; ?>/<?php echo $activeJob['total_days']; ?></span> gün
            </span>
        </div>
        <?php endif; ?>
        
        <button class="btn-primary" id="generatePlanBtn" data-website-id="<?php echo $website_id; ?>">
            <span class="material-icons-round">auto_awesome</span>
            <?php echo $activeJob ? 'Plan Oluşturuluyor...' : "Plan Oluştur (Hafta Sonuna Kadar $remainingDays Gün)"; ?>
        </button>
        <a href="add-site.php?id=<?php echo $website_id; ?>" class="btn-secondary">
            <span class="material-icons-round">settings</span>
        </a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon blue"><span class="material-icons-round">description</span></div>
        <div class="stat-info"><div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div><div class="stat-label">Toplam</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><span class="material-icons-round">pending</span></div>
        <div class="stat-info"><div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div><div class="stat-label">Bekleyen</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><span class="material-icons-round">check_circle</span></div>
        <div class="stat-info"><div class="stat-value"><?php echo $stats['published'] ?? 0; ?></div><div class="stat-label">Yayınlanan</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><span class="material-icons-round">error</span></div>
        <div class="stat-info"><div class="stat-value"><?php echo $stats['failed'] ?? 0; ?></div><div class="stat-label">Başarısız</div></div>
    </div>
</div>

<div class="filters-bar">
    <div class="filters-left">
        <div class="filter-group">
            <select id="monthFilter" class="filter-select" onchange="window.location.href='?website_id=<?php echo $website_id; ?>&month='+this.value;">
                <?php 
                foreach($available_months as $m) {
                    list($y, $m_num) = explode('-', $m);
                    $display_name = $tr_months[$m_num] . ' ' . $y;
                    $isSelected = ($m === $selected_month) ? 'selected' : '';
                    echo "<option value=\"{$m}\" {$isSelected}>{$display_name}</option>";
                }
                ?>
            </select>
        </div>

        <div class="search-wrapper">
            <span class="material-icons-round">search</span>
            <input type="text" id="searchInput" placeholder="Icerik ara..." class="search-input">
        </div>
        
        <div class="filter-group">
            <select id="statusFilter" class="filter-select">
                <option value="">Tum Durumlar</option>
                <option value="pending">Bekleyen</option>
                <option value="generating">Olusturuluyor</option>
                <option value="published">Yayinlandi</option>
                <option value="failed">Basarisiz</option>
            </select>
        </div>
    </div>
    <div class="filters-right">
        <button class="btn-secondary" onclick="location.reload()">
            <span class="material-icons-round">refresh</span>
        </button>
    </div>
</div>

<div class="orders-table-container">
    <table class="orders-table">
        <thead>
            <tr><th>Baslik</th><th>Anahtar Kelime</th><th>Kategori</th><th>Tarih & Saat</th><th>Durum</th><th>Islemler</th></tr>
        </thead>
        <tbody>
            <?php if(empty($plans)): ?>
            <tr><td colspan="6" class="empty-row">
                <span class="material-icons-round">event_note</span>
                <p>Seçilen ay için henüz içerik planı yok.</p>
            </td></tr>
            <?php else: foreach($plans as $plan): 
                $status_class = match($plan['status']) {
                    'published' => 'status-paid', 'generating' => 'status-pending',
                    'failed' => 'status-cancelled', default => 'status-pending'
                };
                $status_text = match($plan['status']) {
                    'published' => 'Yayinlandi', 'generating' => 'Olusturuluyor',
                    'failed' => 'Basarisiz', default => 'Bekliyor'
                };
                $scheduledTime = !empty($plan['scheduled_time']) ? date('H:i', strtotime($plan['scheduled_time'])) : '09:00';
            ?>
            <tr data-title="<?php echo strtolower(htmlspecialchars($plan['title'])); ?>" data-status="<?php echo $plan['status']; ?>">
                <td data-label="Baslik" class="order-number"><?php echo htmlspecialchars($plan['title']); ?></td>
                <td data-label="Anahtar Kelime"><span class="badge" style="background:#f1f5f9;"><?php echo htmlspecialchars($plan['focus_keyword']); ?></span></td>
                <td data-label="Kategori"><?php echo htmlspecialchars($plan['category'] ?? '-'); ?></td>
                <td data-label="Tarih"><?php echo date('d.m.Y', strtotime($plan['scheduled_date'])); ?> <span style="font-size: 11px; color: #94a3b8;"><?php echo $scheduledTime; ?></span></td>
                <td data-label="Durum"><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
<td data-label="Islemler">
    <div class="action-buttons">
        <?php if($plan['status'] == 'pending'): ?>
        <button class="action-btn edit" onclick="generateContent(<?php echo $plan['id']; ?>)" title="Icerigi Olustur">
            <span class="material-icons-round">auto_awesome</span>
        </button>
        <?php elseif($plan['status'] == 'published'): ?>
        <a href="preview.php?id=<?php echo $plan['id']; ?>" target="_blank" class="action-btn edit" title="Icerigi Goruntule">
            <span class="material-icons-round">visibility</span>
        </a>
        <?php elseif($plan['status'] == 'failed'): ?>
        <button class="action-btn edit" onclick="generateContent(<?php echo $plan['id']; ?>)" title="Tekrar Dene">
            <span class="material-icons-round">refresh</span>
        </button>
        <?php elseif($plan['status'] == 'generating'): ?>
        <button class="action-btn edit" disabled style="opacity:0.5;" title="Olusturuluyor...">
            <span class="material-icons-round spin-icon">sync</span>
        </button>
        <?php else: ?>
        <button class="action-btn edit" onclick="generateContent(<?php echo $plan['id']; ?>)" title="Icerigi Olustur">
            <span class="material-icons-round">auto_awesome</span>
        </button>
        <?php endif; ?>
        <button class="action-btn delete" onclick="deletePlan(<?php echo $plan['id']; ?>)" title="Sil">
            <span class="material-icons-round">delete</span>
        </button>
    </div>
</td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div id="generateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>AI Icerik Olusturuluyor</h3><button class="modal-close" onclick="closeModal()"><span class="material-icons-round">close</span></button></div>
        <div class="modal-body" id="generateLog"><div class="loading">Yukleniyor...</div></div>
    </div>
</div>

<style>
.spin-icon { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.stat-icon.red { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #dc2626; }
</style>

<?php 
$extra_js = '<script src="assets/js/plan.js"></script>';
require_once 'footer.php'; 
?>