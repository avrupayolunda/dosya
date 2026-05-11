# Google İndeksleme Sorunları — Derinlemesine Analiz ve Çözümler

**Site:** https://www.idealdesign.be/  
**Tarih:** 2026-05-11

---

## ÖZET: 3 Kök Neden Tespit Edildi

| # | Sorun | Etki | Düzeltilecek Dosya |
|---|-------|------|-------------------|
| 1 | **page-snippets.php İstikbal.com.tr verisi içeriyor** | TÜM sayfaları etkiliyor | `includes/snippet/page-snippets.php` |
| 2 | **.htaccess'te eksik/hatalı redirect kuralları** | 8+18 URL | `.htaccess` |
| 3 | **Varolmayan sayfalar 404 yerine 200 dönüyor** | Sınırsız sayıda sahte sayfa | `page.php` |

---

## SORUN 1: "Alternative page with proper canonical tag" (8 sayfa)

### Etkilenen URL'ler ve Durumları

| URL | Mevcut Durum | Sorun |
|-----|-------------|-------|
| `/tr/portfolio` | 200 — canonical: `/tr/` | **Redirect kuralı eksik!** en ve fr için var, tr için yok |
| `/nl/diensten` | 301 → `/diensten` | ✓ Düzgün çalışıyor |
| `/en/en` | 301 → `/en` | ✓ Düzgün çalışıyor |
| `/en/home` | 301 → `/en` | ✓ Düzgün çalışıyor |
| `/nl/startpagina` | 301 → `/startpagina` → 200 | **Yanlış hedefe yönleniyor!** `/` olmalı |
| `/en/portfolio` | 301 → `/en` | ✓ Düzgün çalışıyor |
| `/en/contact-2` | 301 → `/en/contact` | ✓ Düzgün çalışıyor |
| `/contactez` | 301 → `/fr/contactez` | ✓ Düzgün çalışıyor |

### Kök Neden: .htaccess kural sıralaması

**Problem A:** `/nl/startpagina` → .htaccess satır 12 (`^nl/(.+)$`) bu URL'i `/startpagina`'ya yönlendiriyor. Ama startpagina anasayfa slug'ı olduğu için `/` olması gerekiyor. Satır 19'daki home slug kuralı (`^(en|fr|tr|nl)/(home|...|startpagina)/?$`) HİÇBİR ZAMAN çalışmıyor çünkü satır 12 daha önce yakalıyor.

**Problem B:** `/tr/portfolio` için redirect kuralı yok. `en/portfolio → /en` ve `fr/portfolio → /fr` kuralları var ama `tr/portfolio` unutulmuş.

### Düzeltme (.htaccess):
```apache
# ÖNCE nl home slugları yakala (generic nl/ kuralından ÖNCE)
RewriteRule ^nl/(home|startpagina)/?$ / [R=301,L]

# tr/portfolio redirect ekle
RewriteRule ^tr/portfolio/?$ /tr [R=301,L]

# Root-level startpagina redirect ekle  
RewriteRule ^(home|startpagina|accueil|anasayfa|anaysayfa)/?$ / [R=301,L]
```

---

## SORUN 2: "Page with redirect" (18 sayfa)

### Etkilenen URL'ler — Çift Redirect Zinciri

| URL | Redirect Zinciri | Sorun |
|-----|-----------------|-------|
| `/nl/portfolio/bwagenda-afspraken-plannen/` | → `/portfolio/bwagenda-afspraken-plannen/` → `/portfolio/bwagenda-afspraken-plannen` | **Çift 301!** |
| `/nl/portfolio/cartag-printer-auto-prijslabelprinter/` | → `/portfolio/cartag-...printer/` → `/portfolio/cartag-...printer` | **Çift 301!** |
| `/tr/portfolio/` | → `/tr/portfolio` → 200 (redirect değil ama yanlış sayfa) | Eksik redirect |
| `/fr/portfolio/` | → `/fr` | ✓ Tek redirect |
| `/en/blog/` | → `/en/blog` | ✓ Tek redirect (trailing slash) |
| Diğerleri | Trailing slash removal | ✓ Tek redirect |

### Kök Neden: nl/ kuralları trailing slash'ı koruyuyor

**Problem:** `.htaccess` satır 8: `^nl/portfolio/(.+)$` → `(.+)` trailing slash'ı da yakalar, yani:
- `/nl/portfolio/slug/` → `/portfolio/slug/` (slash dahil) → sonra trailing slash kuralı tekrar redirect eder

### Düzeltme (.htaccess):
```apache
# (.+) yerine (.+?) kullan ve /?$ ile trailing slash'ı burada temizle
RewriteRule ^nl/portfolio/(.+?)/?$ /portfolio/$1 [R=301,L]
RewriteRule ^nl/blog/(.+?)/?$ /blog/$1 [R=301,L]
```

---

## SORUN 3: "Crawled - currently not indexed" (123 sayfa — ARTIYOR!)

### Trend: Sayı HIZLA artıyor
- Şubat 2026: 12 sayfa
- Mayıs 2026: **123 sayfa** (10 kat artış!)

### KRİTİK KÖK NEDEN: page-snippets.php İstikbal verisi

`includes/snippet/page-snippets.php` dosyası İstikbal.com.tr için yapısal veri (structured data) içeriyor:

```json
{
  "@type": "Organization",
  "name": "İstikbal",
  "alternateName": "Her Ev Güzel İstikbal'le",
  "url": "https://www.istikbal.com.tr/"
}
```

**Bu dosya `page.php` satır 18'de dahil ediliyor ve TÜM normal sayfalarda gösteriliyor!**

Sonuç: Google her sayfada "Bu site İstikbal'e ait" diyen yapısal veri görüyor. Bu:
- Site kimliğini karıştırıyor
- Google'ın güven skorunu düşürüyor  
- İndeksleme kalitesini olumsuz etkiliyor
- "Crawled not indexed" sayısının artmasının en büyük nedeni bu

### İKİNCİ KÖK NEDEN: Varolmayan sayfalar 200 dönüyor

Test: `curl -s -o /dev/null -w "%{http_code}" "https://www.idealdesign.be/en/totally-fake-page-xyz123"` → **200**

`page.php`'de `http_response_code(404)` çağrısı `header.php`'nin HTML çıktısı göndermesinden SONRA yapılıyor. PHP header'ları zaten gönderilmiş olduğu için 404 status kodu etkili olmuyor.

Bu demek ki:
- **Sınırsız sayıda sahte URL 200 status ile yanıt veriyor**
- Google bunları gerçek sayfalar olarak algılıyor
- Crawl bütçesi boşa harcanıyor
- "Crawled not indexed" sayısı artmaya devam ediyor

### Düzeltme (page.php):
```php
// header.php'den ÖNCE veritabanını kontrol et
require_once 'panel/config.php';
$_pre_stmt = $pdo->prepare("SELECT id FROM page WHERE slug = ? AND language = ? LIMIT 1");
$_pre_stmt->execute([$slug, $lang]);
if (!$_pre_stmt->fetchColumn()) {
    http_response_code(404);  // Bu artık header gönderilmeden ÖNCE çalışır
}
require_once 'header.php';
```

### Diğer Katkıda Bulunan Faktörler:

1. **`/en/get-an-offer`** → Bu slug veritabanında yok ama 200 dönüyor (redirect eksik)
2. **`/llms.txt`** → robots.txt'te engellenmiyor, Google gereksiz yere taranıyor
3. **Bazı eski URL'ler** hâlâ Google indeksinde (redirect zinciri temizlendikçe düzelecek)

---

## DÜZELTİLECEK DOSYALAR

### 1. `.htaccess` — Redirect düzeltmeleri

**Değişiklikler:**
- `nl/(home|startpagina)` → `/` kuralı generic nl/ kuralından ÖNCE eklendi
- `tr/portfolio` redirect eklendi (eksikti)
- `en/get-an-offer` → `/en/get-offer` redirect eklendi
- Root-level home slugları (`startpagina`, `home`, `accueil`, vb.) → `/` redirect eklendi
- nl/ portfolio/blog kurallarında `(.+)` → `(.+?)/?$` ile trailing slash düzeltildi
- Home slug redirect'ten `nl` kaldırıldı (ayrı kuralda zaten var)

### 2. `page-snippets.php` — İstikbal → BWCreative

**Değişiklikler:**
- İstikbal.com.tr yapısal verisi tamamen silindi
- BWCreative için doğru BreadcrumbList schema eklendi
- Sayfa başlığı ve URL'si dinamik olarak kullanılıyor

### 3. `page.php` — 404 status düzeltmesi

**Değişiklikler:**
- `header.php`'den ÖNCE veritabanı kontrolü yapılıyor
- Sayfa yoksa `http_response_code(404)` header gönderilmeden ÖNCE ayarlanıyor
- Böylece Google 404 sayfaları artık doğru HTTP status kodu alıyor

### 4. `header.php` — Hreflang trailing slash düzeltmesi

**Değişiklikler:**
- Anasayfa alternate link'lerinde `/fr/` → `/fr` (trailing slash kaldırıldı)
- `$lang_links` oluşturulurken `rtrim` ile tutarlılık sağlandı
- Blog list URL'lerinde tutarlı format
- css_version güncellendi

### 5. `sitemap.php` — Content-Type header eklendi

**Değişiklikler:**
- `header('Content-Type: application/xml')` eklendi
- XML hem dosyaya yazılıyor hem doğrudan tarayıcıya gönderiliyor
- Anasayfa alternate URL'lerinde trailing slash tutarlılığı

### 6. `robots.txt` — Gereksiz tarama engellendi

**Değişiklikler:**
- `Disallow: /llms.txt` eklendi

---

## UYGULAMADAN SONRA YAPILMASI GEREKENLER

1. **Dosyaları sunucuya yükleyin** (yukarıdaki 6 dosya)
2. **Sitemap'i yeniden oluşturun:** `php sitemap.php` (CLI'dan çalıştırın)
3. **Google Search Console'da:**
   - URL İnceleme → Her sorunlu URL için "Canlı URL'yi Test Et" yapın
   - Sitemap'ler → `sitemap.xml`'i yeniden gönderin
   - Her 3 sorun için "Düzeltmeyi Doğrula" isteyin
4. **2-3 hafta bekleyin** — Google'ın yeniden taraması ve doğrulaması zaman alır

---

## ÖNCELİK SIRASI

| Öncelik | Dosya | Etkisi |
|---------|-------|--------|
| 🔴 KRİTİK | `page-snippets.php` | 123+ "crawled not indexed" sayfayı etkiliyor |
| 🔴 KRİTİK | `page.php` | Sınırsız sahte 200 sayfayı düzeltiyor |
| 🟠 YÜKSEK | `.htaccess` | 8+18 redirect sorununu düzeltiyor |
| 🟡 ORTA | `header.php` | Hreflang tutarlılığı |
| 🟢 DÜŞÜK | `sitemap.php` | Edge case düzeltmesi |
| 🟢 DÜŞÜK | `robots.txt` | Gereksiz taramayı önlüyor |
