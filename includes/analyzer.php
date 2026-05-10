<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/AI.php';
require_once __DIR__ . '/../log.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

if($action === 'start_analysis') {
    $website_id = (int)($input['website_id'] ?? 0);
    
    if($website_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz website ID']);
        exit;
    }
    
    $ai = new AI($pdo);
    
    if(!$ai->hasValidApiKey()) {
        echo json_encode(['success' => false, 'error' => 'API anahtarı ayarlanmamış']);
        exit;
    }
    
    try {
        $analyzer = new SiteAnalyzer($pdo, $ai, $website_id);
        $result = $analyzer->crawl();
        echo json_encode(['success' => true, 'message' => 'Site tarandı', 'crawled' => $result['crawled'], 'new_pages' => $result['new_pages'], 'skipped' => $result['skipped']]);
    } catch (Exception $e) {
        debug_log("ANALYZER HATASI: " . $e->getMessage(), ['website_id' => $website_id]);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if($action === 'get_crawled_count') {
    $website_id = (int)($_GET['website_id'] ?? $input['website_id'] ?? 0);
    if($website_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz website ID']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM crawled_pages WHERE website_id = ?");
    $stmt->execute([$website_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode(['success' => true, 'count' => (int)$count]);
    exit;
}

if($action === 'fetch_gsc') {
    $website_id = (int)($input['website_id'] ?? 0);
    
    if($website_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz website ID']);
        exit;
    }
    
    try {
        $ai = new AI($pdo);
        $analyzer = new SiteAnalyzer($pdo, $ai, $website_id);
        $result = $analyzer->fetchGSCData();
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

class SiteAnalyzer {
    private $pdo;
    private $ai;
    private $website;
    private $visited = [];
    private $pages = [];
    private $baseUrl;
    private $domain;
    private $existingUrls = [];
    private $newPages = 0;
    private $skippedPages = 0;
    
    public function __construct($pdo, $ai, $websiteId) {
        $this->pdo = $pdo;
        $this->ai = $ai;
        $this->loadWebsite($websiteId);
        $this->loadExistingUrls();
    }
    
    private function loadWebsite($websiteId) {
        $stmt = $this->pdo->prepare("SELECT * FROM websites WHERE id = ?");
        $stmt->execute([$websiteId]);
        $this->website = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$this->website) throw new Exception('Website bulunamadı');
        $this->baseUrl = rtrim($this->website['url'], '/');
        $this->domain = parse_url($this->baseUrl, PHP_URL_HOST);
    }
    
    private function loadExistingUrls() {
        $stmt = $this->pdo->prepare("SELECT url FROM crawled_pages WHERE website_id = ?");
        $stmt->execute([$this->website['id']]);
        $this->existingUrls = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function crawl() {
        $toVisit = [$this->baseUrl];
        $crawled = 0;
        $blogQueue = [];
        
        while(!empty($toVisit) && $crawled < 500) {
            $url = array_shift($toVisit);
            
            // Blog linklerine öncelik ver
            if(empty($toVisit) && !empty($blogQueue)) {
                $toVisit = array_merge($toVisit, $blogQueue);
                $blogQueue = [];
            }
            
            if(in_array($url, $this->visited)) continue;
            $this->visited[] = $url;
            
            $html = $this->fetchUrl($url);
            if(!$html) continue;
            
            $pageData = $this->extractPageData($url, $html);
            if($pageData) {
                $isNew = $this->savePageData($pageData);
                if($isNew) {
                    $this->pages[] = $pageData;
                    $this->newPages++;
                } else {
                    $this->skippedPages++;
                }
                $crawled++;
            }
            
            $links = $this->extractInternalLinks($html);
            foreach($links as $link) {
                if(!in_array($link, $this->visited) && !in_array($link, $toVisit)) {
                    // Blog/icerik sayfalarini blogQueue'ya ekle, digerlerini normal siraya
                    if($this->isBlogUrl($link)) {
                        $blogQueue[] = $link;
                    } else {
                        $toVisit[] = $link;
                    }
                }
            }
        }
        
        // Kalan blog linklerini isle
        while(!empty($blogQueue) && $crawled < 500) {
            $url = array_shift($blogQueue);
            if(in_array($url, $this->visited)) continue;
            $this->visited[] = $url;
            
            $html = $this->fetchUrl($url);
            if(!$html) continue;
            
            $pageData = $this->extractPageData($url, $html);
            if($pageData) {
                $isNew = $this->savePageData($pageData);
                if($isNew) {
                    $this->pages[] = $pageData;
                    $this->newPages++;
                } else {
                    $this->skippedPages++;
                }
                $crawled++;
            }
        }
        
        if($this->newPages > 0) {
            $this->updateSiteMemory();
            $this->generateSiteContextAndStrategy();
        }
        
        return ['crawled' => $crawled, 'new_pages' => $this->newPages, 'skipped' => $this->skippedPages, 'total_pages' => count($this->pages)];
    }
    
    private function isBlogUrl($url) {
        $urlPath = parse_url($url, PHP_URL_PATH);
        $urlPath = rtrim($urlPath, '/');
        
        // Blog gostergeleri
        $blogIndicators = ['/blog/', '/nieuws/', '/artikel/', '/gids/', '/tips/', '/faq/', '/checklist/'];
        foreach($blogIndicators as $indicator) {
            if(stripos($urlPath, $indicator) !== false) return true;
        }
        
        // URL yapisina gore blog tahmini
        $segments = explode('/', trim($urlPath, '/'));
        $lastSegment = end($segments);
        
        // Uzun, tireli slug (blog yazisi gostergesi)
        $slugWords = explode('-', $lastSegment);
        if(count($slugWords) > 4) return true;
        
        // Sayisal olmayan, ozel karakter icermeyen temiz URL
        if(!preg_match('/\d{3,}/', $lastSegment) && !preg_match('/\.(html|php|asp)/', $lastSegment)) {
            $wordCount = str_word_count(str_replace('-', ' ', $lastSegment));
            if($wordCount > 3) return true;
        }
        
        return false;
    }
    
    private function fetchUrl($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if($httpCode == 200 && $html) {
            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
            $html = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $html);
            return $html;
        }
        return null;
    }
    
    private function extractPageData($url, $html) {
        $title = '';
        if(preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            $title = trim(strip_tags($matches[1]));
            $title = mb_convert_encoding($title, 'UTF-8', 'UTF-8');
            $title = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $title);
        }
        
        $description = '';
        if(preg_match('/<meta name="description" content="([^"]+)"/i', $html, $matches)) {
            $description = trim($matches[1]);
            $description = mb_convert_encoding($description, 'UTF-8', 'UTF-8');
        }
        
        $h1s = [];
        if(preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            foreach($matches[1] as $h1) $h1s[] = trim(strip_tags($h1));
        }
        
        $h2s = [];
        if(preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $matches)) {
            foreach($matches[1] as $h2) $h2s[] = trim(strip_tags($h2));
        }
        
        $contentPreview = $this->extractContentPreview($html);
        
        return [
            'url' => $url, 'title' => $title, 'meta_description' => $description,
            'h1_tags' => implode(' | ', $h1s), 'h2_tags' => implode(' | ', $h2s),
            'content_preview' => $contentPreview
        ];
    }
    
    private function extractContentPreview($html) {
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        $text = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return substr(trim($text), 0, 1000);
    }
    
    private function extractInternalLinks($html) {
        $links = [];
        if(preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach($matches[1] as $link) {
                // Anchor'ları filtrele
                if(strpos($link, '#') !== false) continue;
                
                // Dosya uzantılarını filtrele
                if(preg_match('/\.(jpg|jpeg|png|gif|css|js|pdf|zip|xml|rss|ico)$/i', $link)) continue;
                
                // Dış linkleri filtrele (Facebook, Instagram vb.)
                if(preg_match('/facebook|instagram|twitter|linkedin|pinterest|youtube|whatsapp|telegram|sharer|share\?/i', $link)) continue;
                
                // Absolute URL'ye dönüştür
                $absolute = $this->toAbsoluteUrl($link);
                if(!$absolute) continue;
                
                // DOMAIN KONTROL - SADECE KENDİ DOMAIN'İ CRAWL ET
                $urlDomain = parse_url($absolute, PHP_URL_HOST);
                if($urlDomain !== $this->domain) {
                    continue; // Farklı domain'deki URL'i atla
                }
                
                $links[] = $absolute;
            }
        }
        return array_unique($links);
    }
    
    private function toAbsoluteUrl($link) {
            // Parametreleri temizle (fbclid, utm vb.)
            if(parse_url($link, PHP_URL_QUERY)) {
                $parsed = parse_url($link);
                parse_str($parsed['query'] ?? '', $params);
                
                // Takip parametrelerini kaldır
                $trackingParams = ['fbclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
                foreach($trackingParams as $param) {
                    unset($params[$param]);
                }
                
                $query = http_build_query($params);
                
                // YENİ: scheme ve host yoksa PHP'nin uyarı vermesini engelle
                $scheme = !empty($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
                $host   = $parsed['host'] ?? '';
                $path   = $parsed['path'] ?? '';
                
                $link = $scheme . $host . $path;
                
                if($query) $link .= '?' . $query;
            }
            
            if(preg_match('/^(https?:\/\/)/i', $link)) return $link;
            if(strpos($link, '/') === 0) return $this->baseUrl . $link;
            if(strpos($link, './') === 0) return $this->baseUrl . substr($link, 1);
            if(strpos($link, '../') === 0) {
                $parts = explode('/', rtrim($this->baseUrl, '/'));
                $count = substr_count($link, '../');
                for($i = 0; $i < $count; $i++) array_pop($parts);
                return implode('/', $parts) . '/' . ltrim(str_replace('../', '', $link), '/');
            }
            if(parse_url($link, PHP_URL_HOST) === null && !preg_match('/^(https?:\/\/)/i', $link)) {
                return $this->baseUrl . '/' . ltrim($link, '/');
            }
            return null;
        }
        
    private function savePageData($data) {
        try {
            // URL zaten var mi kontrol et (Mükerrer URL Atlanır)
            if(in_array($data['url'], $this->existingUrls)) {
                return false; // Zaten var, atla
            }
            
            $stmt = $this->pdo->prepare("INSERT INTO crawled_pages (website_id, url, title, content_preview) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                title = VALUES(title), content_preview = VALUES(content_preview)");
            $stmt->execute([
                $this->website['id'], 
                $data['url'], 
                substr($data['title'], 0, 500),
                substr($data['content_preview'], 0, 1000)
            ]);
            
            // Existing URLs listesine ekle
            $this->existingUrls[] = $data['url'];
            return true;
        } catch (PDOException $e) {
            error_log("Save page error for URL {$data['url']}: " . $e->getMessage());
            return false;
        }
    }
    
    private function updateSiteMemory() {
        $topics = []; $links = [];
        foreach($this->pages as $page) {
            if($page['title']) $topics[] = $page['title'];
            $links[] = $page['url'];
        }
        $stmt = $this->pdo->prepare("INSERT INTO site_memory (website_id, memory_type, memory_data) VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE memory_data = VALUES(memory_data), updated_at = NOW()");
        $stmt->execute([$this->website['id'], 'keywords', json_encode([], JSON_UNESCAPED_UNICODE)]);
        $stmt->execute([$this->website['id'], 'topics', json_encode($topics, JSON_UNESCAPED_UNICODE)]);
        $stmt->execute([$this->website['id'], 'links', json_encode($links, JSON_UNESCAPED_UNICODE)]);
    }
    
    private function generateSiteContextAndStrategy() {
        $selectedLanguage = $this->website['language'] ?? 'tr';
        $langName = $this->getLanguageName($selectedLanguage);
        $samplePages = array_slice($this->pages, 0, 20);
        $pagesText = '';
        foreach($samplePages as $p) {
            $pagesText .= "URL: {$p['url']}\nTitle: {$p['title']}\nH1s: {$p['h1_tags']}\nH2s: {$p['h2_tags']}\nMeta Desc: {$p['meta_description']}\nPreview: " . substr($p['content_preview'], 0, 200) . "\n\n";
        }
        
        $prompt = "Analyze this website and provide TWO plain text strings.\n\nWEBSITE: {$this->website['url']}\nLANGUAGE: $langName\n\nPAGES:\n$pagesText\n\nReturn ONLY this JSON format (NO nested objects):\n{\n  \"site_context\": \"One paragraph describing: business type, target audience, country, industry, main topics\",\n  \"seo_strategy\": \"One paragraph with: content gaps to fill, keywords to target, best content formats, recommended tone\"\n}\n\nCRITICAL: Both values MUST be simple strings, NOT arrays or objects.\nCRITICAL: Write in $langName language.";
        
        $messages = [['role' => 'user', 'content' => $prompt]];
        $result = $this->ai->chat($messages, 0.7, true);
        $clean = preg_replace('/```json\s*|\s*```/i', '', $result);
        $clean = trim($clean);
        $data = json_decode($clean, true);
        
        if(json_last_error() !== JSON_ERROR_NONE) {
            if(preg_match('/"site_context":\s*"([^"]+)"/s', $clean, $m)) $data['site_context'] = $m[1];
            if(preg_match('/"seo_strategy":\s*"([^"]+)"/s', $clean, $m)) $data['seo_strategy'] = $m[1];
        }
        
        if($data && !empty($data['site_context']) && !empty($data['seo_strategy'])) {
            $siteContext = is_string($data['site_context']) ? $data['site_context'] : json_encode($data['site_context']);
            $seoStrategy = is_string($data['seo_strategy']) ? $data['seo_strategy'] : json_encode($data['seo_strategy']);
            $stmt = $this->pdo->prepare("UPDATE websites SET site_context = ?, seo_strategy = ?, last_crawled_at = NOW() WHERE id = ?");
            $stmt->execute([$siteContext, $seoStrategy, $this->website['id']]);
        }
    }
    
    public function fetchGSCData() {
        $stmt = $this->pdo->prepare("SELECT gsc_refresh_token FROM websites WHERE id = ?");
        $stmt->execute([$this->website['id']]);
        $site = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $refreshToken = $site['gsc_refresh_token'] ?? null;
        if(!$refreshToken) {
            return ['success' => false, 'error' => 'Google hesabı bağlı değil. Önce Connect with Google butonuna tıklayın.'];
        }
        
        $stmt = $this->pdo->query("SELECT google_client_id, google_client_secret FROM ai_settings WHERE id = 1");
        $apiSettings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $apiSettings['google_client_id'],
            'client_secret' => $apiSettings['google_client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $token = json_decode($response, true);
        curl_close($ch);
        
        if(!isset($token['access_token'])) {
            return ['success' => false, 'error' => 'Token yenilenemedi. Lütfen tekrar bağlanın.'];
        }
        
        return $this->fetchGSCDataWithToken($token['access_token']);
    }
    
    public function fetchGSCDataWithToken($accessToken) {
        $siteUrl = $this->website['gsc_site_url'] ?? $this->website['url'];
        
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-30 days'));
        
        $ch = curl_init('https://www.googleapis.com/webmasters/v3/sites/' . urlencode($siteUrl) . '/searchAnalytics/query');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'startDate' => $startDate, 'endDate' => $endDate,
            'dimensions' => ['query'], 'rowLimit' => 100
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        $this->pdo->prepare("DELETE FROM search_console_data WHERE website_id = ?")->execute([$this->website['id']]);
        
        $insertedCount = 0;
        $language = $this->website['language'] ?? 'nl';
        
        foreach(($data['rows'] ?? []) as $row) {
            $query = $row['keys'][0] ?? '';
            
            $this->pdo->prepare("INSERT INTO search_console_data 
                (website_id, query, clicks, impressions, ctr, position, date_retrieved) 
                VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([
                $this->website['id'], $query,
                $row['clicks'] ?? 0, $row['impressions'] ?? 0,
                round(($row['ctr'] ?? 0) * 100, 2),
                round($row['position'] ?? 0, 1),
                date('Y-m-d')
            ]);
            
            if(!empty($query) && strlen($query) > 3) {
                try {
                    $this->pdo->prepare("INSERT IGNORE INTO content_keywords 
                        (website_id, keyword, search_volume, type, language) 
                        VALUES (?, ?, ?, 'search_console', ?)")->execute([
                        $this->website['id'], $query, $row['impressions'] ?? 0, $language
                    ]);
                } catch(Exception $e) {}
            }
            $insertedCount++;
        }
        
        $this->pdo->prepare("UPDATE websites SET gsc_connected = 1, gsc_last_sync = NOW() WHERE id = ?")
            ->execute([$this->website['id']]);
        
        return ['success' => true, 'keywords_found' => $insertedCount];
    }
    
    private function getLanguageName($code) {
        $map = ['tr'=>'Türkçe','en'=>'English','nl'=>'Nederlands','de'=>'Deutsch','fr'=>'Français','es'=>'Español','it'=>'Italiano'];
        return $map[$code] ?? 'English';
    }

    // --- AKILLI KATEGORİ YÖNETİMİ ---
    public function getOrCreateSmartCategory($categoryName) {
        $categoryName = trim($categoryName);
        if(empty($categoryName)) {
            $categoryName = 'Genel';
        }
        
        $slug = $this->createSlug($categoryName);

        // 1. Zaten aynı slug ile kayıtlı kategori var mı?
        $stmt = $this->pdo->prepare("SELECT id FROM blog_categories WHERE website_id = ? AND slug = ?");
        $stmt->execute([$this->website['id'], $slug]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return $existing['id']; // Varsa tekrar oluşturma, mevcut ID'yi dön
        }

        // 2. Kategori sayısını kontrol et (Örn: Maksimum 15 Kategori Sınırı)
        $stmt = $this->pdo->prepare("SELECT COUNT(id) as total FROM blog_categories WHERE website_id = ?");
        $stmt->execute([$this->website['id']]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        if ($count >= 15) {
            // Sınır dolmuşsa, ilk/en genel kategorinin ID'sini bul ve oraya ekle
            $stmt = $this->pdo->prepare("SELECT id FROM blog_categories WHERE website_id = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$this->website['id']]);
            $fallback = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fallback) {
                return $fallback['id'];
            }
        }

        // 3. Sınır dolmamış ve kategori benzersiz ise yeni oluştur
        $stmt = $this->pdo->prepare("INSERT INTO blog_categories (website_id, name, slug) VALUES (?, ?, ?)");
        $stmt->execute([$this->website['id'], $categoryName, $slug]);
        return $this->pdo->lastInsertId();
    }

    public function createSlug($text) {
        $find = array('I', 'İ', 'Ü', 'Ö', 'Ş', 'Ç', 'Ğ', 'ı', 'u', 'o', 's', 'c', 'g');
        $replace = array('i', 'i', 'u', 'o', 's', 'c', 'g', 'i', 'u', 'o', 's', 'c', 'g');
        $text = str_replace($find, $replace, $text);
        $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
        $text = strtolower(trim(preg_replace('/\s+/', '-', $text)));
        return $text ?: 'genel';
    }
}
?>