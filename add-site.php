<?php
$page_title = 'Domain Ekle';
$page_desc = 'Yeni bir domain ekleyin';
require_once 'header.php';

$site_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $site_id > 0;
$site = null;

if($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
    $stmt->execute([$site_id]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);
    if($site) {
        $page_title = $site['name'] . ' - Domain Düzenle';
        $page_desc = 'Domain bilgilerini düzenleyin';
    } else {
        $is_edit = false;
    }
}

$languages = [
    'tr' => 'Turkce', 'en' => 'English', 'nl' => 'Dutch',
    'de' => 'Deutsch', 'fr' => 'Francais', 'es' => 'Espanol', 'it' => 'Italiano'
];

$gsc_success = isset($_GET['gsc']) && $_GET['gsc'] == 'success';
$analysis_done = $is_edit && !empty($site['site_context']);
$gsc_connected = $is_edit && !empty($site['gsc_connected']);
?>

<style>
.spin-icon { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.analysis-modal {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.7); z-index: 9999;
    display: flex; align-items: center; justify-content: center;
}
.analysis-modal-content {
    background: white; border-radius: 20px; padding: 30px;
    max-width: 400px; text-align: center;
}
.loading-spinner {
    width: 50px; height: 50px; border: 4px solid #e2e8f0;
    border-top-color: #6366f1; border-radius: 50%;
    animation: spin 1s linear infinite; margin: 0 auto 20px;
}
.step-indicator {
    display: flex; align-items: center; gap: 8px;
    padding: 16px; background: #f8fafc; border-radius: 12px; margin-bottom: 24px;
}
.step {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: #94a3b8;
}
.step.active { color: #6366f1; font-weight: 600; }
.step.done { color: #10b981; }
.step-arrow { color: #cbd5e1; }
</style>

<?php if($gsc_success): ?>
<div class="alert alert-success" style="margin-bottom: 16px;">
    <span class="material-icons-round">check_circle</span>
    Google Search Console bağlantısı başarılı! Verileriniz çekildi.
</div>
<?php endif; ?>

<!-- Adım Göstergesi -->
<div class="step-indicator">
    <div class="step <?php echo $is_edit ? 'done' : 'active'; ?>">
        <span class="material-icons-round"><?php echo $is_edit ? 'check_circle' : 'public'; ?></span>
        <span>1. Domain Ekle</span>
    </div>
    <span class="step-arrow">→</span>
    <div class="step <?php echo $gsc_connected ? 'done' : ($is_edit ? 'active' : ''); ?>">
        <span class="material-icons-round"><?php echo $gsc_connected ? 'check_circle' : 'search'; ?></span>
        <span>2. Google Bağlantısı</span>
    </div>
    <span class="step-arrow">→</span>
    <div class="step <?php echo $analysis_done ? 'done' : ''; ?>">
        <span class="material-icons-round"><?php echo $analysis_done ? 'check_circle' : 'auto_awesome'; ?></span>
        <span>3. Site Analizi</span>
    </div>
</div>

<form id="websiteForm" class="premium-form">
    <input type="hidden" name="id" value="<?php echo $site_id; ?>">
    
    <div class="form-layout">
        <div class="form-main">
            <!-- ADIM 1: Domain Bilgileri -->
            <div class="form-card">
                <div class="card-header">
                    <div class="card-icon"><span class="material-icons-round">language</span></div>
                    <div class="card-title">
                        <h3>1. Domain Bilgileri</h3>
                        <p><?php echo $is_edit ? 'Domain bilgilerini güncelleyin' : 'Eklemek istediginiz domain adresini girin'; ?></p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Domain Adi</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><span class="material-icons-round">public</span></span>
                            <input type="text" name="domain" class="form-input" required 
                                   value="<?php echo htmlspecialchars($site['name'] ?? ''); ?>"
                                   placeholder="ornek.com veya www.ornek.com">
                        </div>
                        <small>www'li veya www'siz olarak girin, oldugu gibi kaydedilir</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Site Dili</label>
                            <div class="select-wrapper">
                                <span class="select-icon"><span class="material-icons-round">translate</span></span>
                                <select name="language" class="form-select" required>
                                    <option value="">Seciniz</option>
                                    <?php foreach($languages as $key => $name): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($site['language'] ?? 'tr') == $key ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small>Bu site icin icerikler hangi dilde olusturulsun?</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Durum</label>
                            <div class="status-switch">
                                <label class="switch">
                                    <input type="checkbox" name="status_switch" value="active" 
                                           <?php echo ($site['status'] ?? 'active') == 'active' ? 'checked' : ''; ?>>
                                    <span class="switch-slider"></span>
                                </label>
                                <span class="status-label">Aktif / Pasif</span>
                            </div>
                            <input type="hidden" name="status" value="<?php echo ($site['status'] ?? 'active') == 'active' ? 'active' : 'inactive'; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Günlük Makale Limiti</label>
                            <div class="input-wrapper">
                                <span class="input-icon"><span class="material-icons-round">article</span></span>
                                <input type="number" name="daily_post_limit" class="form-input" min="1" max="20" 
                                       value="<?php echo $site['daily_post_limit'] ?? 3; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Yayın Aralığı (dakika)</label>
                            <div class="input-wrapper">
                                <span class="input-icon"><span class="material-icons-round">timelapse</span></span>
                                <input type="number" name="publish_interval" class="form-input" min="30" max="720" step="30"
                                       value="<?php echo $site['publish_interval'] ?? 240; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>API Anahtarı <?php if($is_edit): ?><span style="font-size:11px;color:#94a3b8;">(Otomatik oluşturulur)</span><?php endif; ?></label>
                        <div class="input-wrapper">
                            <span class="input-icon"><span class="material-icons-round">key</span></span>
                            <input type="text" name="api_key" class="form-input" readonly 
                                   value="<?php echo htmlspecialchars($site['api_key'] ?? ''); ?>"
                                   placeholder="Kaydedince otomatik oluşur">
                        </div>
                    </div>
                    
                    <div style="margin-top: 16px;">
                        <button type="submit" class="btn-primary" id="submitBtn">
                            <span class="material-icons-round"><?php echo $is_edit ? 'save' : 'add'; ?></span>
                            <?php echo $is_edit ? 'Guncelle' : 'Domain Ekle ve Devam Et'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="form-side">
            <!-- ADIM 2: Google Search Console -->
            <div class="form-card" style="<?php echo !$is_edit ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                <div class="card-header">
                    <div class="card-icon"><span class="material-icons-round">search</span></div>
                    <div class="card-title">
                        <h3>2. Google Search Console</h3>
                        <p><?php echo $is_edit ? 'Google hesabınızla bağlanın' : 'Önce domain ekleyin'; ?></p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if($gsc_connected): ?>
                    <div class="alert alert-success" style="font-size: 13px; margin-bottom: 16px;">
                        <span class="material-icons-round">check_circle</span>
                        ✅ Bağlantı aktif
                        <?php if(!empty($site['gsc_last_sync'])): ?>
                        <br><small>Son: <?php echo $site['gsc_last_sync']; ?></small>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="gscFetchBtn" class="btn-secondary" style="width: 100%;">
                        <span class="material-icons-round">refresh</span> Verileri Güncelle
                    </button>
                    <?php elseif($is_edit): ?>
                    <a href="includes/gsc_auth.php?website_id=<?php echo $site_id; ?>" 
                       class="btn-primary" style="width: 100%; display: block; text-align: center; text-decoration: none;">
                        <span class="material-icons-round">link</span> Connect with Google
                    </a>
                    <small style="display: block; margin-top: 8px; text-align: center; color: #64748b;">
                        Read-only access. We only read your search performance.
                    </small>
                    <?php else: ?>
                    <div class="info-box" style="background: #f8fafc; padding: 16px; border-radius: 12px; text-align: center;">
                        <span class="material-icons-round" style="color: #94a3b8; font-size: 32px;">lock</span>
                        <p style="font-size: 13px; color: #64748b; margin-top: 8px;">Domaini kaydettikten sonra kullanılabilir</p>
                    </div>
                    <?php endif; ?>
                    
                    <div id="gscResult" style="margin-top: 16px; display: none;">
                        <div class="alert alert-success" style="font-size: 12px;">
                            <span class="material-icons-round">check_circle</span>
                            <span id="gscMessage"></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ADIM 3: Site Analizi -->
            <div class="form-card" style="margin-top: 16px; <?php echo !$is_edit ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                <div class="card-header">
                    <div class="card-icon"><span class="material-icons-round">auto_awesome</span></div>
                    <div class="card-title">
                        <h3>3. Site Analizi</h3>
                        <p><?php echo $is_edit ? 'Siteyi tara ve SEO stratejisi oluştur' : 'Önce domain ekleyin'; ?></p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if($analysis_done): ?>
                    <div class="alert alert-success" style="font-size: 13px; margin-bottom: 16px;">
                        <span class="material-icons-round">check_circle</span>
                        ✅ Site analiz edildi
                    </div>
                    <button type="button" id="analyzeBtn" class="btn-secondary" style="width: 100%;">
                        <span class="material-icons-round">refresh</span> Tekrar Analiz Et
                    </button>
                    <?php elseif($is_edit): ?>
                    <button type="button" id="analyzeBtn" class="btn-primary" style="width: 100%;">
                        <span class="material-icons-round">psychology</span> Siteyi Analiz Et
                    </button>
                    <?php else: ?>
                    <div class="info-box" style="background: #f8fafc; padding: 16px; border-radius: 12px; text-align: center;">
                        <span class="material-icons-round" style="color: #94a3b8; font-size: 32px;">lock</span>
                        <p style="font-size: 13px; color: #64748b; margin-top: 8px;">Domaini kaydettikten sonra kullanılabilir</p>
                    </div>
                    <?php endif; ?>
                    
                    <div id="analyzeResult" style="margin-top: 16px; display: none;">
                        <div class="alert alert-success" style="font-size: 12px;">
                            <span class="material-icons-round">check_circle</span>
                            <span id="analyzeMessage"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
let currentSiteId = <?php echo $site_id ?: 0; ?>;

// ADIM 1: Domain Kaydet
document.getElementById('websiteForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    let domain = data.domain.trim().replace(/^https?:\/\//i, '').replace(/\/$/, '');
    
    const sendData = {
        action: 'save_website', id: data.id, name: domain,
        url: 'https://' + domain, language: data.language,
        description: '', status: data.status, api_key: data.api_key || '',
        daily_post_limit: parseInt(data.daily_post_limit) || 3,
        publish_interval: parseInt(data.publish_interval) || 240
    };
    
    const btn = document.getElementById('submitBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-icons-round spin-icon">sync</span> Kaydediliyor...';
    btn.disabled = true;
    
    try {
        const response = await fetch('includes/save.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(sendData)
        });
        const result = await response.json();
        
        if(result.success) {
            if(result.is_new) {
                currentSiteId = result.id;
                document.querySelector('input[name="id"]').value = currentSiteId;
                if(result.api_key) document.querySelector('input[name="api_key"]').value = result.api_key;
                showToast('✅ Domain kaydedildi! Şimdi Google bağlantısı yapabilirsiniz.', 'success');
                setTimeout(() => window.location.href = 'add-site.php?id=' + currentSiteId, 500);
            } else {
                showToast('✅ Güncellendi!', 'success');
                setTimeout(() => window.location.reload(), 500);
            }
        } else {
            showToast('❌ ' + result.error, 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch(error) {
        showToast('❌ Hata: ' + error.message, 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
});

// ADIM 3: Site Analizi
document.getElementById('analyzeBtn')?.addEventListener('click', async function() {
    if(!currentSiteId) { showToast('Önce domain ekleyin', 'warning'); return; }
    
    const btn = this;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-icons-round spin-icon">sync</span> Analiz ediliyor...';
    btn.disabled = true;
    
    const modal = document.createElement('div');
    modal.className = 'analysis-modal';
    modal.innerHTML = `<div class="analysis-modal-content">
        <div class="loading-spinner"></div>
        <h3>Site Analiz Ediliyor</h3>
        <p id="analysisStatus" style="color:#64748b;margin-top:10px;">Sayfalar taranıyor...</p>
        <p id="analysisCount" style="font-size:12px;color:#94a3b8;margin-top:10px;">0 sayfa tarandi</p>
    </div>`;
    document.body.appendChild(modal);
    
    let pageCount = 0;
    const countEl = document.getElementById('analysisCount');
    const interval = setInterval(async () => {
        try {
            const checkResponse = await fetch('includes/analyzer.php?action=get_crawled_count&website_id=' + currentSiteId);
            const checkResult = await checkResponse.json();
            if(checkResult.success && countEl && checkResult.count > pageCount) {
                pageCount = checkResult.count;
                countEl.innerHTML = pageCount + ' sayfa tarandi';
            }
        } catch(e) {}
    }, 2000);
    
    try {
        const response = await fetch('includes/analyzer.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'start_analysis', website_id: currentSiteId})
        });
        const result = await response.json();
        clearInterval(interval);
        
        if(result.success) {
            document.getElementById('analysisStatus').innerHTML = '✅ Analiz tamamlandi!';
            countEl.innerHTML = result.crawled + ' sayfa tarandi';
            setTimeout(() => {
                modal.remove();
                showToast('✅ Site analiz edildi! ' + result.crawled + ' sayfa tarandi.', 'success');
                setTimeout(() => window.location.reload(), 1000);
            }, 1500);
        } else {
            clearInterval(interval); modal.remove();
            showToast('❌ ' + result.error, 'error');
            btn.innerHTML = originalText; btn.disabled = false;
        }
    } catch(error) {
        clearInterval(interval); modal.remove();
        showToast('❌ ' + error.message, 'error');
        btn.innerHTML = originalText; btn.disabled = false;
    }
});

document.querySelector('input[name="status_switch"]')?.addEventListener('change', function(e) {
    document.querySelector('input[name="status"]').value = e.target.checked ? 'active' : 'inactive';
});

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;bottom:20px;right:20px;padding:12px 24px;border-radius:8px;color:white;z-index:9999;background:${type==='success'?'#10b981':type==='error'?'#ef4444':'#f59e0b'};box-shadow:0 4px 12px rgba(0,0,0,0.15);`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity='0'; toast.style.transition='opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
}
</script>

<?php require_once 'footer.php'; ?>