<?php
require_once __DIR__ . '/../log.php';

class ImageManager {
    private $ai;
    private $pdo;
    
    public function __construct($ai, $pdo) {
        $this->ai = $ai;
        $this->pdo = $pdo;
    }
    
    public function generateArticleImages($planId, $keyword, $title, $lang, $articleHtml, $websiteId) {
        $images = [];
        
        $stmt = $this->pdo->prepare("SELECT id, images_generated FROM writer_memory WHERE topic = ? AND website_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$keyword, $websiteId]);
        $memory = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($memory && $memory['images_generated'] == 1) {
            return $this->getExistingImages($planId);
        }
        
        $text = strip_tags($articleHtml);
        $excerpt = substr($text, 0, 500);
        $inlineCount = rand(2, 4);
        
        // Öne Çıkan Görsel (Featured)
        $fp = $this->aiPrompt("Create an image generation prompt for a Belgian business article about '{$keyword}'. Title: {$title}.
        CRITICAL RULES: 1. Must look like an authentic, amateur documentary photo shot on an iPhone or 35mm film. 2. Real, ordinary people, candid moments, imperfect natural lighting. 3. NO plastic skin, NO 3D render, NO perfect studio lighting, NO text/logos. Return ONLY the prompt.");
        
        // ALT ETİKETİ: İngilizce prompt yerine Anahtar Kelime gönderiliyor
        $fr = $this->downloadAndSave($fp, $keyword . '-feat', $keyword);
        if ($fr) { $fr['type'] = 'featured'; $images[] = $fr; }
        
        // İçerik Görselleri (Inline)
        $tp = $this->aiPrompt("Based on this Belgian business article excerpt, list {$inlineCount} visual topics for inline images. Excerpt: {$excerpt}. Return ONLY one topic per line, no numbers.");
        $topics = array_values(array_filter(array_map('trim', explode("\n", $tp))));
        $topics = array_slice($topics, 0, $inlineCount);
        
        foreach ($topics as $i => $topic) {
            if (empty($topic)) continue;
            $ip = $this->aiPrompt("Create image generation prompt for a Belgian business topic: '{$topic}'. Context: {$keyword}.
            CRITICAL RULES: 1. Authentic, real-life documentary photography. 2. Real ordinary people, candid pose, imperfect natural lighting. 3. ABSOLUTELY NO CGI, NO plastic faces, NO studio lighting, NO text. Return ONLY prompt.");
            
            // ALT ETİKETİ: İngilizce prompt yerine İçerik Konusu (Topic) gönderiliyor
            $ir = $this->downloadAndSave($ip, $keyword . '-' . ($i + 1), $topic);
            if ($ir) { $ir['type'] = 'inline'; $ir['topic'] = $topic; $images[] = $ir; }
            if ($i < count($topics) - 1) sleep(1);
        }
        
        if (!empty($images)) {
            $this->saveToDb($websiteId, $planId, $images, $keyword);
            $stmt = $this->pdo->prepare("UPDATE writer_memory SET images_generated = 1, images_count = ? WHERE website_id = ? AND topic = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([count($images), $websiteId, $keyword]);
        }
        
        return $images;
    }
    
    private function aiPrompt($prompt) {
        $messages = [['role' => 'user', 'content' => $prompt]];
        return trim($this->ai->chat($messages, 0.7, false));
    }
    
    private function downloadAndSave($prompt, $prefix, $altText) {
        try {
            $prompt = trim($prompt, "'\" \t\n\r\0\x0B");
            $imageUrl = $this->ai->generateImage($prompt, '1024x1024');
            if (empty($imageUrl)) return false;
            
            if (strpos($imageUrl, 'data:image') === 0) {
                $parts = explode(',', $imageUrl);
                $data = !empty($parts[1]) ? base64_decode($parts[1]) : false;
            } else {
                $data = @file_get_contents($imageUrl);
            }
            
            if (!$data || empty($data)) return false;
            
            $year = date('Y');
            $month = date('m');
            $slug = $this->slug($prefix);
            $filename = $slug . '-' . uniqid() . '.jpg'; 
            $relPath = $year . '/' . $month . '/' . $filename;
            
            $dir = dirname(__DIR__) . '/uploads/' . $year . '/' . $month;
            
            // İZİN (PERMISSION) KALKANI EKLENDİ
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @chmod($dir, 0755); // Klasör yetkilerini zorla düzelt
            
            $fullPath = $dir . '/' . $filename;
            
            $imageResource = @imagecreatefromstring($data);
            if ($imageResource !== false) {
                $bg = imagecreatetruecolor(imagesx($imageResource), imagesy($imageResource));
                imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
                imagecopy($bg, $imageResource, 0, 0, 0, 0, imagesx($imageResource), imagesy($imageResource));
                
                // imagejpeg izin hatası verirse ekrana basma
                if(!@imagejpeg($bg, $fullPath, 85)) {
                    @file_put_contents($fullPath, $data);
                }
                
                imagedestroy($bg);
                imagedestroy($imageResource);
            } else {
                @file_put_contents($fullPath, $data);
            }
            
            @chmod($fullPath, 0644); // Dışarıdan okunabilmesi için yetki
            
            return ['url_path' => $relPath, 'prompt' => $altText];
        } catch (Exception $e) {
            debug_log("ImageManager: Error", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function injectImagesIntoHtml($html, $images) {
        if (empty($images)) return $html;
        
        $featured = null;
        $inlines = [];
        $base = BASE_URL . 'uploads/';
        
        foreach ($images as $img) {
            $t = is_array($img) ? ($img['type'] ?? $img['image_type'] ?? 'inline') : 'inline';
            if ($t === 'featured') $featured = $img;
            else $inlines[] = $img;
        }
        
        // ÖNE ÇIKAN GÖRSELİ (H2'den önceye) EKLE
        if ($featured) {
            $u = is_array($featured) ? ($featured['url_path'] ?? '') : '';
            $a = is_array($featured) ? ($featured['prompt'] ?? '') : '';
            $tag = '<figure class="featured-image" style="display: block; margin: 0 auto 30px; text-align: center; clear: both;"><img src="' . $base . $u . '" alt="' . htmlspecialchars($a) . '" loading="eager" style="max-width: 100%; height: auto; border-radius: 8px;"></figure>';
            $html = preg_replace('/<h2\b[^>]*>/i', $tag . "\n$0", $html, 1);
        }
        
        // İÇERİK GÖRSELLERİNİ (H3'lerden önceye) RASTGELE VE TERS SIRAYLA EKLE
        if (!empty($inlines)) {
            preg_match_all('/<h3\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE);
            if (!empty($m[0])) {
                $totalH3 = count($m[0]);
                
                // Mümkün olan h3 indexlerini bir diziye al ve karıştır (Rastgele dağılım için)
                $availableIndices = range(0, $totalH3 - 1);
                shuffle($availableIndices);
                
                $placements = [];
                foreach ($inlines as $img) {
                    // Eğer h3 sayısı resim sayısından azsa, index dizisini yeniden doldur
                    if (empty($availableIndices)) {
                        $availableIndices = range(0, $totalH3 - 1);
                        shuffle($availableIndices);
                    }
                    
                    // Rastgele bir h3 indexi seç
                    $targetIdx = array_pop($availableIndices);
                    
                    $placements[] = [
                        'img' => $img,
                        'idx' => $targetIdx
                    ];
                }
                
                // ÇOK KRİTİK: Pozisyon kaymasını önlemek için indexleri BÜYÜKTEN KÜÇÜĞE (Aşağıdan Yukarıya) sırala
                usort($placements, function($a, $b) {
                    return $b['idx'] <=> $a['idx'];
                });
                
                // Artık HTML'e en alttan başlayarak ekliyoruz, böylece üstteki h3'lerin offsetleri ASLA bozulmuyor
                foreach ($placements as $placement) {
                    $img = $placement['img'];
                    $idx = $placement['idx'];
                    $pos = $m[0][$idx][1];
                    
                    $u = is_array($img) ? ($img['url_path'] ?? '') : '';
                    $a = is_array($img) ? ($img['prompt'] ?? $img['topic'] ?? '') : '';
                    
                    $alignments = ['left', 'right', 'center'];
                    $align = $alignments[array_rand($alignments)];
                    
                    if ($align === 'center') {
                        $width = rand(70, 100);
                        $style = "display: block; margin: 30px auto; text-align: center; width: {$width}%; clear: both;";
                    } else {
                        $width = rand(35, 55);
                        $margin = ($align === 'left') ? '15px 25px 20px 0' : '15px 0 20px 25px';
                        $style = "float: {$align}; margin: {$margin}; width: {$width}%; max-width: 500px; clear: both;";
                    }
                    
                    $tag = '<figure class="inline-image" style="' . $style . '"><img src="' . $base . $u . '" alt="' . htmlspecialchars($a) . '" loading="lazy" style="width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"></figure>';
                    
                    // Tam o pozisyona resmi göm
                    $html = substr_replace($html, $tag . "\n", $pos, 0);
                }
            }
        }
        
        $html .= '<div style="clear: both;"></div>';
        
        return $html;
    }
    
    private function getExistingImages($planId) {
        $stmt = $this->pdo->prepare("SELECT * FROM article_images WHERE plan_id = ? ORDER BY image_type DESC, id ASC");
        $stmt->execute([$planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function saveToDb($websiteId, $planId, $images, $keyword) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO article_images (website_id, plan_id, keyword, url_path, prompt, image_type, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            foreach ($images as $img) {
                $stmt->execute([$websiteId, $planId, $keyword, $img['url_path'], $img['prompt'] ?? '', $img['type'] ?? 'inline']);
            }
        } catch (Exception $e) {}
    }
    
    private function slug($t) {
        $t = preg_replace('~[^\pL\d]+~u', '-', $t);
        $t = iconv('utf-8', 'us-ascii//TRANSLIT', $t);
        $t = preg_replace('~[^-\w]+~', '', $t);
        return strtolower(substr(trim($t, '-'), 0, 50));
    }
}