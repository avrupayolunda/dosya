<?php
$page_title = 'AI Ayarlari';
$page_desc = 'Gemini ve DeepSeek API ayarlarini yonetin';
require_once 'header.php';

$stmt = $pdo->query("SELECT * FROM ai_settings WHERE id = 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$settings) {
    $pdo->exec("INSERT INTO ai_settings (id) VALUES (1)");
    $stmt = $pdo->query("SELECT * FROM ai_settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gemini_key = trim($_POST['gemini_api_key'] ?? '');
    $deepseek_key = trim($_POST['deepseek_api_key'] ?? '');
    $gemini_model = $_POST['gemini_model'] ?? 'gemini-2.0-flash';
    $deepseek_model = $_POST['deepseek_model'] ?? 'deepseek-chat';
    $generate_images = isset($_POST['generate_images']) ? 1 : 0;
    $research_agent = $_POST['research_agent'] ?? 'deepseek';
    $writer_agent = $_POST['writer_agent'] ?? 'gemini';
    $critic_agent = $_POST['critic_agent'] ?? 'deepseek';
    $plan_creator_agent = $_POST['plan_creator_agent'] ?? 'gemini';
    $plan_critic_agent = $_POST['plan_critic_agent'] ?? 'deepseek';
    $google_client_id = trim($_POST['google_client_id'] ?? '');
    $google_client_secret = trim($_POST['google_client_secret'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE ai_settings SET 
        gemini_api_key = ?, deepseek_api_key = ?,
        gemini_model = ?, deepseek_model = ?, generate_images = ?,
        research_agent = ?, writer_agent = ?, critic_agent = ?,
        plan_creator_agent = ?, plan_critic_agent = ?,
        google_client_id = ?, google_client_secret = ?
        WHERE id = 1");
    
    if($stmt->execute([$gemini_key, $deepseek_key, $gemini_model, $deepseek_model, $generate_images,
        $research_agent, $writer_agent, $critic_agent, $plan_creator_agent, $plan_critic_agent,
        $google_client_id, $google_client_secret])) {
        $success = 'Ayarlar basariyla kaydedildi.';
        $stmt = $pdo->query("SELECT * FROM ai_settings WHERE id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = 'Kayit sirasinda bir hata olustu.';
    }
}

$gemini_models = [];
$deepseek_models = [];
$stmt = $pdo->query("SELECT model_id, model_name FROM available_models WHERE api_type = 'gemini' AND is_active = 1 ORDER BY model_id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $gemini_models[$row['model_id']] = $row['model_name'];
$stmt = $pdo->query("SELECT model_id, model_name FROM available_models WHERE api_type = 'deepseek' AND is_active = 1 ORDER BY model_id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $deepseek_models[$row['model_id']] = $row['model_name'];

if (empty($gemini_models)) $gemini_models = ['gemini-2.0-flash' => 'Gemini 2.0 Flash', 'gemini-2.5-pro-preview-05-06' => 'Gemini 2.5 Pro', 'gemini-2.5-flash-preview-04-17' => 'Gemini 2.5 Flash'];
if (empty($deepseek_models)) $deepseek_models = ['deepseek-chat' => 'DeepSeek Chat', 'deepseek-reasoner' => 'DeepSeek Reasoner'];

$isGscConnected = !empty($settings['google_refresh_token']);
$hasApiKeys = !empty($settings['google_client_id']) && !empty($settings['google_client_secret']);
?>

<?php if($success): ?>
<div class="alert alert-success"><span class="material-icons-round">check_circle</span> <?php echo $success; ?></div>
<?php endif; ?>
<?php if($error): ?>
<div class="alert alert-error"><span class="material-icons-round">error</span> <?php echo $error; ?></div>
<?php endif; ?>
<?php if(isset($_GET['gsc']) && $_GET['gsc'] == 'success'): ?>
<div class="alert alert-success"><span class="material-icons-round">check_circle</span> Google Search Console bağlantısı başarılı!</div>
<?php endif; ?>

<div class="settings-container" style="max-width: 800px; margin: 0 auto;">
    <form method="POST" class="settings-form">
        
        <!-- API Anahtarları -->
        <div class="form-card">
            <div class="card-header">
                <div class="card-icon"><span class="material-icons-round">api</span></div>
                <div class="card-title"><h3>API Anahtarlari</h3><p>Gemini ve DeepSeek API bilgilerinizi girin</p></div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Gemini API Key</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><span class="material-icons-round">key</span></span>
                        <input type="password" name="gemini_api_key" class="form-input" value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" placeholder="AIza...">
                    </div>
                </div>
                <div class="form-group">
                    <label>DeepSeek API Key</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><span class="material-icons-round">key</span></span>
                        <input type="password" name="deepseek_api_key" class="form-input" value="<?php echo htmlspecialchars($settings['deepseek_api_key'] ?? ''); ?>" placeholder="sk-...">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end;">
                    <button type="button" id="updateModelsBtn" class="btn-secondary">
                        <span class="material-icons-round">refresh</span> Modelleri Güncelle
                    </button>
                </div>
                <div id="updateModelsResult" style="margin-top: 10px; display: none;"></div>
            </div>
        </div>
        
        <!-- Google API Bilgileri -->
        <div class="form-card">
            <div class="card-header">
                <div class="card-icon"><span class="material-icons-round">cloud</span></div>
                <div class="card-title"><h3>Google API Bilgileri</h3><p>Google Cloud Console'dan aldığınız Client ID ve Client Secret</p></div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Google Client ID</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><span class="material-icons-round">fingerprint</span></span>
                        <input type="text" name="google_client_id" class="form-input" 
                               value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>" 
                               placeholder="....apps.googleusercontent.com">
                    </div>
                </div>
                <div class="form-group">
                    <label>Google Client Secret</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><span class="material-icons-round">password</span></span>
                        <input type="password" name="google_client_secret" class="form-input" 
                               value="<?php echo htmlspecialchars($settings['google_client_secret'] ?? ''); ?>" 
                               placeholder="GOCSPX-...">
                    </div>
                </div>
                <small>Google Cloud Console → APIs & Services → Credentials bölümünden alabilirsiniz.</small>
            </div>
        </div>
        
        <!-- Google Search Console Bağlantısı -->
        <div class="form-card">
            <div class="card-header">
                <div class="card-icon"><span class="material-icons-round">search</span></div>
                <div class="card-title"><h3>Google Search Console Bağlantısı</h3><p>Google hesabınızı bağlayın (site sahiplerinin yetki vermesi gerekmez)</p></div>
            </div>
            <div class="card-body">
                <?php if(!$hasApiKeys): ?>
                <div class="info-box" style="background: #fef3c7; padding: 16px; border-radius: 12px; margin-bottom: 16px;">
                    <span class="material-icons-round" style="color: #f59e0b;">warning</span>
                    <div style="margin-top: 8px;">
                        <strong>Önce yukarıdaki Google API bilgilerini girin.</strong>
                        <p style="font-size: 12px; color: #64748b; margin-top: 4px;">Client ID ve Client Secret olmadan bağlantı yapılamaz.</p>
                    </div>
                </div>
                <?php elseif($isGscConnected): ?>
                <div class="alert alert-success" style="font-size: 13px; margin-bottom: 16px;">
                    <span class="material-icons-round">check_circle</span> Google hesabı bağlı.
                </div>
                <a href="includes/gsc_auth.php" class="btn-secondary" style="width: 100%;">
                    <span class="material-icons-round">refresh</span> Google Hesabını Yeniden Bağla
                </a>
                <?php else: ?>
                <a href="includes/gsc_auth.php" class="btn-primary" style="width: 100%; display: block; text-align: center; text-decoration: none;">
                    <span class="material-icons-round">link</span> Connect with Google
                </a>
                <small style="display: block; margin-top: 8px; text-align: center; color: #64748b;">Read-only access. We only read your search performance.</small>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Model Ayarları -->
        <div class="form-card">
            <div class="card-header">
                <div class="card-icon"><span class="material-icons-round">settings</span></div>
                <div class="card-title"><h3>Model Ayarlari</h3><p>Hangi modeller kullanılsın?</p></div>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Gemini Modeli</label>
                        <div class="select-wrapper">
                            <span class="select-icon"><span class="material-icons-round">smart_toy</span></span>
                            <select name="gemini_model" class="form-select">
                                <?php foreach($gemini_models as $key => $name): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($settings['gemini_model'] ?? 'gemini-2.0-flash') == $key ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>DeepSeek Modeli</label>
                        <div class="select-wrapper">
                            <span class="select-icon"><span class="material-icons-round">smart_toy</span></span>
                            <select name="deepseek_model" class="form-select">
                                <?php foreach($deepseek_models as $key => $name): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($settings['deepseek_model'] ?? 'deepseek-chat') == $key ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- AI Rolleri -->
        <div class="form-card">
            <div class="card-header">
                <div class="card-icon"><span class="material-icons-round">smart_toy</span></div>
                <div class="card-title"><h3>AI Rolleri (Agent Seçimleri)</h3><p>Hangi işlemi hangi AI yapsın?</p></div>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Araştırmacı</label>
                        <div class="select-wrapper">
                            <span class="select-icon"><span class="material-icons-round">search</span></span>
                            <select name="research_agent" class="form-select">
                                <option value="deepseek" <?php echo ($settings['research_agent'] ?? 'deepseek') == 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                                <option value="gemini" <?php echo ($settings['research_agent'] ?? '') == 'gemini' ? 'selected' : ''; ?>>Gemini</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Yazar</label>
                        <div class="select-wrapper">
                            <span class="select-icon"><span class="material-icons-round">edit</span></span>
                            <select name="writer_agent" class="form-select">
                                <option value="gemini" <?php echo ($settings['writer_agent'] ?? 'gemini') == 'gemini' ? 'selected' : ''; ?>>Gemini</option>
                                <option value="deepseek" <?php echo ($settings['writer_agent'] ?? '') == 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Eleştirmen</label>
                        <div class="select-wrapper">
                            <span class="select-icon"><span class="material-icons-round">rate_review</span></span>
                            <select name="critic_agent" class="form-select">
                                <option value="deepseek" <?php echo ($settings['critic_agent'] ?? 'deepseek') == 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                                <option value="gemini" <?php echo ($settings['critic_agent'] ?? '') == 'gemini' ? 'selected' : ''; ?>>Gemini</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Plan Oluşturma -->
        <div class="form-card">
            <div class="card-header">
                <div class="card-icon"><span class="material-icons-round">psychology</span></div>
                <div class="card-title"><h3>Plan Oluşturma Agent'ları</h3><p>İçerik planı oluşturma ve eleştirme</p></div>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Plan Oluşturucu</label>
                        <div class="select-wrapper">
                            <span class="select-icon"><span class="material-icons-round">auto_awesome</span></span>
                            <select name="plan_creator_agent" class="form-select">
                                <option value="gemini" <?php echo ($settings['plan_creator_agent'] ?? 'gemini') == 'gemini' ? 'selected' : ''; ?>>Gemini</option>
                                <option value="deepseek" <?php echo ($settings['plan_creator_agent'] ?? '') == 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Plan Eleştirmen</label>
                        <div class="select-wrapper">
                            <span class="select-icon"><span class="material-icons-round">checklist</span></span>
                            <select name="plan_critic_agent" class="form-select">
                                <option value="deepseek" <?php echo ($settings['plan_critic_agent'] ?? 'deepseek') == 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                                <option value="gemini" <?php echo ($settings['plan_critic_agent'] ?? '') == 'gemini' ? 'selected' : ''; ?>>Gemini</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Görsel -->
        <div class="form-card">
            <div class="card-header">
                <div class="card-icon"><span class="material-icons-round">image</span></div>
                <div class="card-title"><h3>Görsel Ayarları</h3></div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="switch-label">
                        <input type="checkbox" name="generate_images" value="1" <?php echo ($settings['generate_images'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="switch-slider"></span>
                        <span>DeepSeek ile gorsel olusturulsun</span>
                    </label>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <span class="material-icons-round">save</span> Ayarlari Kaydet
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('updateModelsBtn').addEventListener('click', async function() {
    const btn = this;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-icons-round spin-icon">sync</span> Güncelleniyor...';
    btn.disabled = true;
    const resultDiv = document.getElementById('updateModelsResult');
    resultDiv.style.display = 'none';
    
    try {
        const response = await fetch('includes/update_models.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'}
        });
        const result = await response.json();
        resultDiv.style.display = 'block';
        resultDiv.className = result.success ? 'alert alert-success' : 'alert alert-error';
        resultDiv.innerHTML = '<span class="material-icons-round">' + (result.success ? 'check_circle' : 'error') + '</span> ' + result.message;
        if(result.success) setTimeout(() => location.reload(), 2000);
        else { btn.innerHTML = originalText; btn.disabled = false; }
    } catch(error) {
        resultDiv.style.display = 'block';
        resultDiv.className = 'alert alert-error';
        resultDiv.innerHTML = '<span class="material-icons-round">error</span> Bağlantı hatası';
        btn.innerHTML = originalText; btn.disabled = false;
    }
});
</script>

<style>
.spin-icon { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

<?php require_once 'footer.php'; ?>