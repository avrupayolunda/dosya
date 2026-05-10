/**
 * Content Plan Management (İçerik Planı Yönetimi)
 * Bu dosya, hem genel sitenin içerik planlarının oluşturulmasını hem de
 * tekil makalelerin yapay zekaya yazdırılması ve bu sürecin ekranda anlık takibini (polling) yönetir.
 */

// --- GLOBAL DURUM (STATE) DEĞİŞKENLERİ ---
let generating = false; // Sitenin genel planı oluşturulurken mükerrer tıklamayı önler
let progressInterval = null; // Genel plan oluşturma işleminin anlık takibi için setInterval ID'si
let contentGenerating = false; // Tek bir makale yazdırılırken mükerrer tıklamayı önler
let contentPollingInterval = null; // Tekil makale yazım aşamalarının anlık takibi için setInterval ID'si

// Sayfa yüklendiğinde tetiklenecek başlangıç işlemleri
document.addEventListener('DOMContentLoaded', function() {
    // Sayfa açıldığında devam eden bir "Plan Oluşturma" işi varsa ilerleme çubuğu takibini başlat
    if (document.getElementById('planProgress')) {
        startProgressPolling();
    }
    // Sayfa açıldığında arkaplanda yazılmaya devam eden makaleler varsa butonları güncelle
    checkGeneratingContents();
});

/**
 * Güvenli Fetch İşlemi (safeFetch)
 * Standart fetch() fonksiyonunu sarar. Sunucudan boş, hatalı veya JSON olmayan 
 * bir yanıt geldiğinde sayfanın çökmesini (JS hatası vermesini) engeller.
 */
async function safeFetch(url, options = {}) {
    try {
        const response = await fetch(url, options);
        const text = await response.text();
        
        if (!text || text.trim() === '') {
            console.error('Boş yanıt alındı:', url);
            return { success: false, error: 'Boş yanıt' };
        }
        
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse hatası:', url, text.substring(0, 200));
            return { success: false, error: 'JSON parse hatası' };
        }
    } catch (error) {
        console.error('Fetch hatası:', url, error);
        return { success: false, error: error.message };
    }
}

/**
 * Üretimi Devam Eden Makaleleri Kontrol Et
 * Sayfa F5 ile yenilendiğinde, arkaplanda "generating" (üretiliyor) durumunda 
 * olan makaleleri bularak tablo butonlarını doğru duruma getirir.
 */
async function checkGeneratingContents() {
    try {
        const result = await safeFetch('includes/plan.php?action=get_generating_plans');
        if (result && result.success && result.plans && result.plans.length > 0) {
            for (const plan of result.plans) {
                updateGenerateButton(plan.plan_id, true, plan.message);
            }
        }
    } catch(e) {
        console.error('Check generating contents error:', e);
    }
}

/**
 * Buton Durumu Güncelleyici
 * Bir makale üretilmeye başlandığında buton ikonunu döndürür (spinner) ve kilitler.
 * İşlem bittiğinde ise "Göz" ikonuna (Önizleme) dönüştürür.
 */
function updateGenerateButton(planId, isGenerating, message = null) {
    const buttons = document.querySelectorAll(`.action-btn.edit[onclick*="generateContent(${planId})"]`);
    buttons.forEach(btn => {
        if (isGenerating) {
            // Üretim aşamasında butonu kilitle
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'wait';
            btn.title = message || 'İçerik oluşturuluyor...';
            const icon = btn.querySelector('.material-icons-round');
            if (icon && !icon.classList.contains('spin-icon')) {
                icon.classList.add('spin-icon');
            }
        } else {
            // Üretim bittiğinde veya iptal olduğunda butonu aç
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
            btn.title = 'İçeriği Görüntüle / Düzenle';
            const icon = btn.querySelector('.material-icons-round');
            if (icon) {
                icon.classList.remove('spin-icon');
                icon.textContent = 'visibility';
            }
            // Artık butona tıklandığında içeriği önizleme sayfasını aç
            btn.onclick = () => viewContent(planId);
        }
    });
}

/**
 * İçeriği Önizleme Sayfasında Açar
 */
function viewContent(planId) {
    window.open(`preview.php?id=${planId}`, '_blank');
}

/**
 * Tekil Makale (İçerik) Oluşturma İşlemini Başlatır
 * Tablodaki sihirli değnek ikonuna (generate) basıldığında tetiklenir.
 */
async function generateContent(planId) {
    if (contentGenerating) {
        showToast('Zaten bir içerik oluşturuluyor, lütfen bekleyin', 'warning');
        return;
    }
    
    const modal = document.getElementById('generateModal');
    const logDiv = document.getElementById('generateLog');
    
    // UI Güncellemesi
    updateGenerateButton(planId, true, 'İçerik oluşturuluyor...');
    contentGenerating = true;
    
    // Modalı aç ve yükleniyor animasyonunu göster
    if (modal && logDiv) {
        modal.style.display = 'flex';
        logDiv.innerHTML = '<div class="loading"><span class="material-icons-round spin-icon">sync</span> İçerik oluşturma başlatılıyor...</div>';
    } else {
        showToast('İçerik oluşturma başlatılıyor...', 'info');
    }
    
    // İşlem durumu takibini (polling) başlat
    startContentPolling(planId);
    
    try {
        // Sunucuya içeriği oluşturması için POST isteği at
        const result = await safeFetch('includes/plan.php?action=generate_content', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({plan_id: planId})
        });
        
        if (result && result.success) {
            if (modal && logDiv) {
                logDiv.innerHTML = '<div class="alert alert-success">✅ ' + result.message + '<br><span style="font-size: 13px;">İçerik hazırlanıyor, lütfen bekleyin...</span></div>';
            }
        } else {
            // Hata olursa işlemi geri al
            updateGenerateButton(planId, false);
            contentGenerating = false;
            const errorMsg = result?.error || 'Bilinmeyen hata';
            if (modal && logDiv) {
                logDiv.innerHTML = '<div class="alert alert-error">❌ ' + errorMsg + '</div>';
            } else {
                showToast('Hata: ' + errorMsg, 'error');
            }
        }
    } catch(error) {
        console.error('Generate error:', error);
        updateGenerateButton(planId, false);
        contentGenerating = false;
        if (modal && logDiv) {
            logDiv.innerHTML = '<div class="alert alert-error">❌ Bağlantı hatası: ' + error.message + '</div>';
        } else {
            showToast('Hata: ' + error.message, 'error');
        }
    }
}

/**
 * İçerik Üretim Sürecini Anlık Takip Eder (Polling)
 * Makalenin yazım sürecini (örneğin "başlık yazılıyor", "bölüm 2/5 tamamlandı")
 * 3 saniyede bir sunucuya sorarak kullanıcıya gösterir.
 */
function startContentPolling(planId) {
    if (contentPollingInterval) {
        clearInterval(contentPollingInterval);
    }
    
    contentPollingInterval = setInterval(async () => {
        try {
            const result = await safeFetch(`includes/plan.php?action=check_content_status&plan_id=${planId}`);
            
            if (!result || !result.success) {
                return;
            }
            
            // Makale tamamen bittiyse
            if (result.status === 'completed') {
                clearInterval(contentPollingInterval);
                contentGenerating = false;
                updateGenerateButton(planId, false);
                
                const modal = document.getElementById('generateModal');
                if (modal) modal.style.display = 'none';
                
                showToast('✅ İçerik başarıyla oluşturuldu!', 'success');
                updateTableRow(planId, result.content);
                setTimeout(() => location.reload(), 1500); // UI tam oturması için sayfayı yenile
                
            } 
            // Makale üretiminde hata çıktıysa
            else if (result.status === 'failed') {
                clearInterval(contentPollingInterval);
                contentGenerating = false;
                updateGenerateButton(planId, false);
                
                const logDiv = document.getElementById('generateLog');
                if (logDiv) {
                    logDiv.innerHTML = '<div class="alert alert-error">❌ ' + (result.error || 'Oluşturma hatası') + '</div>';
                }
                showToast('Hata: ' + (result.error || 'Oluşturma hatası'), 'error');
                
            } 
            // Makale yazılıyor (Bölüm bölüm yazım ilerlemesi)
            else if (result.status === 'writing' && result.progress) {
                const logDiv = document.getElementById('generateLog');
                if (logDiv) {
                    const pct = Math.round((result.progress.current_section / result.progress.total_sections) * 100);
                    logDiv.innerHTML = `
                        <div class="loading">
                            <span class="material-icons-round spin-icon">sync</span>
                            İçerik yazılıyor... (${result.progress.current_section}/${result.progress.total_sections})
                            <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; margin-top: 10px;">
                                <div style="width: ${pct}%; height: 100%; background: #6366f1; border-radius: 3px;"></div>
                            </div>
                        </div>
                    `;
                }
            } 
            // Taslak (İskelet/Alt Başlıklar) çıkartılıyor
            else if (result.status === 'outline') {
                const logDiv = document.getElementById('generateLog');
                if (logDiv) {
                    logDiv.innerHTML = '<div class="loading"><span class="material-icons-round spin-icon">sync</span> Outline oluşturuluyor...</div>';
                }
            }
        } catch(e) {
            console.error('Polling error:', e);
        }
    }, 3000);
}

/**
 * Tablo Satırını Günceller
 * Sayfayı yenilemeye gerek kalmadan tabloda duran makalenin "Bekliyor" durumunu 
 * "Yayınlandı" olarak değiştirir ve "Görüntüle" butonunu ekler.
 */
function updateTableRow(planId, content) {
    const oldBtn = document.querySelector(`.action-btn.edit[onclick*="generateContent(${planId})"]`);
    if (!oldBtn) return;
    
    const row = oldBtn.closest('tr');
    if (row) {
        // Durum etiketini (Badge) güncelle
        const statusCell = row.querySelector('td[data-label="Durum"] .status-badge');
        if (statusCell) {
            statusCell.className = 'status-badge status-paid';
            statusCell.textContent = 'Yayinlandi';
        }
        
        row.dataset.status = 'published';
        
        // İşlem butonunu Göz (Önizleme) ikonuna çevir
        const newLink = document.createElement('a');
        newLink.href = `preview.php?id=${planId}`;
        newLink.target = '_blank';
        newLink.className = 'action-btn edit';
        newLink.title = 'Icerigi Goruntule';
        newLink.innerHTML = '<span class="material-icons-round">visibility</span>';
        oldBtn.replaceWith(newLink);
        
        // Eğer o an farklı bir durum filtresi açıksa (örn: Bekleyenler) bu satırı gizle
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter && statusFilter.value && statusFilter.value !== 'published') {
            row.style.display = 'none';
        }
    }
}

/**
 * Toplu / Genel Plan Oluşturma İşlemini Başlatır (Haftalık Planlama)
 * "Plan Oluştur" butonuna basıldığında tetiklenir.
 */
document.getElementById('generatePlanBtn')?.addEventListener('click', async function() {
    if (generating) {
        showToast('Plan oluşturma işlemi zaten devam ediyor', 'warning');
        return;
    }
    
    const websiteId = this.dataset.websiteId;
    if (!websiteId) {
        showToast('Website ID bulunamadı', 'error');
        return;
    }
    
    generating = true;
    const btn = this;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-icons-round spin-icon">sync</span> Başlatılıyor...';
    btn.disabled = true;
    
    try {
        // Sunucuda plan generator (cron job kuyruğu) başlat
        const result = await safeFetch('includes/plan.php?action=start_plan_generator', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({website_id: websiteId})
        });
        
        if (result && result.success) {
            showToast(result.message, 'success');
            btn.innerHTML = '<span class="material-icons-round spin-icon">sync</span> Plan Oluşturuluyor...';
            
            // Eğer UI'da ilerleme çubuğu (progress bar) yoksa anında yarat ve göster
            const progressDiv = document.getElementById('planProgress');
            if (!progressDiv) {
                const btnParent = btn.parentElement;
                const newProgress = document.createElement('div');
                newProgress.id = 'planProgress';
                newProgress.style.cssText = 'display: flex; align-items: center; gap: 8px; margin-right: 10px;';
                newProgress.innerHTML = `
                    <div style="width: 150px; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                        <div id="progressBar" style="width: 0%; height: 100%; background: #6366f1; transition: width 0.3s;"></div>
                    </div>
                    <span style="font-size: 12px; color: #64748b; white-space: nowrap;">
                        <span id="progressText">0/${result.total_days || '?'}</span> gün
                    </span>
                `;
                btnParent.insertBefore(newProgress, btn);
            }
            
            // Oluşturma sürecini (gün gün) izlemeye başla
            startProgressPolling();
        } else {
            showToast(result?.error || 'Bir hata oluştu', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
            generating = false;
        }
    } catch(error) {
        console.error('Plan error:', error);
        showToast('Hata: ' + error.message, 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
        generating = false;
    }
});

/**
 * Genel Plan Oluşturma Sürecini İzleme (Polling)
 * Arka planda (Cron ile) plan kaçıncı gün için oluşturuluyor verisini çekerek ilerleme çubuğunu doldurur.
 */
function startProgressPolling() {
    const websiteId = document.getElementById('generatePlanBtn')?.dataset.websiteId;
    if (!websiteId) return;
    
    if (progressInterval) clearInterval(progressInterval);
    
    progressInterval = setInterval(async () => {
        try {
            const result = await safeFetch(`includes/plan.php?action=check_plan_job&website_id=${websiteId}`);
            
            if (result && result.success && result.job) {
                const progressBar = document.getElementById('progressBar');
                const progressText = document.getElementById('progressText');
                
                // İlerleme yüzdesini hesapla ve UI'ı güncelle
                if (progressBar && progressText) {
                    const pct = Math.round((result.job.current_day / result.job.total_days) * 100);
                    progressBar.style.width = pct + '%';
                    progressText.textContent = result.job.current_day + '/' + result.job.total_days;
                }
                
                // İşlem tamamen bittiğinde
                if (result.job.status === 'completed') {
                    clearInterval(progressInterval);
                    const btn = document.getElementById('generatePlanBtn');
                    if (btn) {
                        btn.innerHTML = '<span class="material-icons-round">auto_awesome</span> Plan Oluştur';
                        btn.disabled = false;
                    }
                    generating = false;
                    showToast('✅ Tüm plan oluşturuldu!', 'success');
                    setTimeout(() => location.reload(), 2000); // Tablonun yenilenmesi için sayfayı tazele
                }
            }
        } catch(e) {
            console.error('Progress polling error:', e);
        }
    }, 3000); // Her 3 saniyede bir sorar
}

/**
 * Oluşturulmuş Planı / Taslağı Siler
 * Çöp kutusu ikonuna basıldığında tetiklenir.
 */
async function deletePlan(id) {
    if (!confirm('Bu icerigi silmek istediginize emin misiniz?')) return;
    
    try {
        const result = await safeFetch('includes/plan.php?action=delete_plan', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        });
        
        if (result && result.success) {
            showToast('Icerik silindi', 'success');
            setTimeout(() => location.reload(), 500); // Satır silinince sayfayı yenile
        } else {
            showToast(result?.error || 'Silme hatası', 'error');
        }
    } catch(error) {
        console.error('Delete error:', error);
        showToast('Hata: ' + error.message, 'error');
    }
}

/**
 * Tablo İçi Metin Araması (Client-Side Search)
 * Arama kutusuna bir şey yazıldığında satırların data-title özelliğine bakarak filtreler.
 */
document.getElementById('searchInput')?.addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.orders-table tbody tr').forEach(row => {
        const title = row.dataset.title;
        // Eğer başlık aranan kelimeyi içermiyorsa satırı gizle (display: none)
        row.style.display = (title && !title.includes(term)) ? 'none' : '';
    });
});

/**
 * Tablo İçi Durum Filtrelemesi (Client-Side Filter)
 * Dropdown (Seçiniz) üzerinden Bekleyen/Yayınlanan filtresi uygulandığında satırları ayarlar.
 */
document.getElementById('statusFilter')?.addEventListener('change', function(e) {
    const status = e.target.value;
    document.querySelectorAll('.orders-table tbody tr').forEach(row => {
        row.style.display = (status && row.dataset.status !== status) ? 'none' : '';
    });
});

/**
 * Makale Yazım Modalı'nı (Açılır Pencere) Kapatır
 */
function closeModal() {
    const modal = document.getElementById('generateModal');
    if (modal) modal.style.display = 'none';
}

// Modal dışındaki siyah (overlay) alana tıklanırsa pencereyi kapat
document.getElementById('generateModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

/**
 * Kullanıcıya Uyarı / Bilgi Mesajı (Toast) Gösterir
 * Sağ alt köşede animasyonlu olarak çıkar ve 4 saniye sonra kendiliğinden kaybolur.
 * type: 'success' (Yeşil), 'error' (Kırmızı), 'warning' (Turuncu) vs.
 */
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed; bottom: 20px; right: 20px; padding: 12px 24px;
        border-radius: 8px; color: white; z-index: 9999;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#f59e0b'};
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: fadeIn 0.3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    // 4 Saniye sonra yavaşça silinmesini sağla
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}