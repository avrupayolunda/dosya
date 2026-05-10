<?php
$page_title = 'Websitelerim';
$page_desc = 'Eklediğiniz siteleri görüntüleyin ve yönetin';
require_once 'header.php';

$stmt = $pdo->query("SELECT w.*, COUNT(cp.id) as content_count 
                     FROM websites w 
                     LEFT JOIN content_plans cp ON w.id = cp.website_id 
                     GROUP BY w.id 
                     ORDER BY w.created_at DESC");
$websites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <span class="material-icons-round">language</span>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo count($websites); ?></div>
            <div class="stat-label">Toplam Website</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <span class="material-icons-round">description</span>
        </div>
        <div class="stat-info">
            <div class="stat-value" id="totalContent">0</div>
            <div class="stat-label">Toplam İçerik</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <span class="material-icons-round">pending</span>
        </div>
        <div class="stat-info">
            <div class="stat-value" id="pendingContent">0</div>
            <div class="stat-label">Bekleyen İçerik</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <span class="material-icons-round">check_circle</span>
        </div>
        <div class="stat-info">
            <div class="stat-value" id="publishedContent">0</div>
            <div class="stat-label">Yayınlanan</div>
        </div>
    </div>
</div>

<div class="filters-bar">
    <div class="filters-left">
        <div class="search-wrapper">
            <span class="material-icons-round">search</span>
            <input type="text" id="searchInput" placeholder="Site ara..." class="search-input">
        </div>
    </div>
    <div class="filters-right">
        <a href="add-site.php" class="btn-primary">
            <span class="material-icons-round">add</span>
            Yeni Website Ekle
        </a>
    </div>
</div>

<div class="events-container" id="websitesGrid">
    <?php if(count($websites) > 0): ?>
        <?php foreach($websites as $site): 
            $domain = parse_url($site['url'], PHP_URL_HOST);
        ?>
        <div class="event-card" data-name="<?php echo strtolower(htmlspecialchars($site['name'])); ?>">
            <div class="event-card-image" style="background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100px; display: flex; align-items: center; justify-content: center;">
                <div class="event-type-badge" style="position: relative; bottom: auto; right: auto;">
                    <span class="material-icons-round">public</span>
                    <?php echo htmlspecialchars($domain); ?>
                </div>
            </div>
            <div class="event-card-body">
                <h3 class="event-title"><?php echo htmlspecialchars($domain); ?></h3>
                <div class="event-meta-grid">
                    <div class="meta-item">
                        <span class="material-icons-round">description</span>
                        <span><?php echo $site['content_count']; ?> içerik</span>
                    </div>
                    <div class="meta-item">
                        <span class="material-icons-round">schedule</span>
                        <span><?php echo date('d.m.Y', strtotime($site['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            <div class="event-card-footer">
                <div class="event-actions">
                    <a href="content-plan.php?website_id=<?php echo $site['id']; ?>" class="action-btn edit" title="İçerik Planı">
                        <span class="material-icons-round">event_note</span>
                    </a>
                    <a href="add-site.php?id=<?php echo $site['id']; ?>" class="action-btn map" title="Düzenle">
                        <span class="material-icons-round">edit</span>
                    </a>
                    <button onclick="deleteWebsite(<?php echo $site['id']; ?>)" class="action-btn delete" title="Sil">
                        <span class="material-icons-round">delete</span>
                    </button>
                </div>
                <div class="event-status <?php echo $site['status'] == 'active' ? 'active' : 'inactive'; ?>">
                    <span class="status-dot"></span>
                    <span class="status-text"><?php echo $site['status'] == 'active' ? 'Aktif' : 'Pasif'; ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state" style="grid-column: 1/-1; text-align: center; padding: 60px;">
            <div class="empty-icon">
                <span class="material-icons-round" style="font-size: 64px; color: #cbd5e1;">language</span>
            </div>
            <h3>Henüz domain eklenmemiş</h3>
            <p>İlk domaini eklemek için aşağıdaki butona tıklayın.</p>
            <a href="add-site.php" class="btn-primary btn-large" style="margin-top: 16px;">
                <span class="material-icons-round">add</span>
                Domain Ekle
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('searchInput').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.event-card').forEach(card => {
        const name = card.dataset.name;
        card.style.display = name.includes(term) ? 'flex' : 'none';
    });
});

async function loadStats() {
    try {
        const response = await fetch('ajax.php?action=get_stats');
        const text = await response.text();
        console.log('Raw response:', text);
        const data = JSON.parse(text);
        if(data.success) {
            document.getElementById('totalContent').textContent = data.total_content || 0;
            document.getElementById('pendingContent').textContent = data.pending_content || 0;
            document.getElementById('publishedContent').textContent = data.published_content || 0;
        }
    } catch (error) {
        console.error('loadStats error:', error);
        document.getElementById('totalContent').textContent = '0';
        document.getElementById('pendingContent').textContent = '0';
        document.getElementById('publishedContent').textContent = '0';
    }
}

async function deleteWebsite(id) {
    if(!confirm('Bu websiteyi silmek istediğinize emin misiniz?')) return;
    
    try {
        const response = await fetch('includes/save.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id, action: 'delete_website'})
        });
        
        const result = await response.json();
        
        if(result.success) {
            if(typeof showToast === 'function') {
                showToast('Website silindi', 'success');
            } else {
                alert('Website silindi');
            }
            setTimeout(() => location.reload(), 1000);
        } else {
            if(typeof showToast === 'function') {
                showToast(result.error, 'error');
            } else {
                alert(result.error);
            }
        }
    } catch(error) {
        console.error('Delete error:', error);
        alert('Silme işlemi sırasında hata oluştu: ' + error.message);
    }
}

loadStats();
</script>

<?php require_once 'footer.php'; ?>