<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/AI.php';
require_once __DIR__ . '/AIWriter.php';
require_once __DIR__ . '/LinkManager.php';
require_once __DIR__ . '/ResearchAgent.php';
require_once __DIR__ . '/CriticAgent.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/../log.php';

$log = __DIR__ . '/../cron_log.txt';

if (!function_exists('cronLog')) {
    function cronLog($message) {
        global $log;
        file_put_contents($log, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
    }
}

cronLog("=== Cron başladı ===");

// ai_settings'tan generate_images kontrolü
$generateImages = true;
try {
    $stmt = $pdo->query("SELECT generate_images FROM ai_settings WHERE id = 1");
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $generateImages = ($setting['generate_images'] ?? 1) == 1;
} catch (Exception $e) {
    $generateImages = true;
}

$lockFile = __DIR__ . '/../cron_writer.lock';
if (file_exists($lockFile) && time() - filemtime($lockFile) < 120) { exit; }
file_put_contents($lockFile, date('Y-m-d H:i:s'));

try {
    $gemini = new AI($pdo);
    $deepseek = new AI($pdo);
    $writer = new AIWriter($gemini, $deepseek, $pdo);
    $linkManager = new LinkManager($pdo, $deepseek);
    
    // === GÖRSEL İŞİ (generate_images = 0 ise ATLA) ===
    if ($generateImages) {
        $stmt = $pdo->prepare("SELECT * FROM writer_jobs WHERE status = 'images_generating' ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $imageJob = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $imageJob = null;
        // generate_images kapalıysa, images_generating durumundaki job'ları direkt completed yap
        $pdo->exec("UPDATE writer_jobs SET status = 'completed' WHERE status = 'images_generating'");
    }
    
    if ($imageJob) {
        cronLog("GORSEL: Job {$imageJob['id']} Plan {$imageJob['plan_id']}");
        
        $stmt = $pdo->prepare("SELECT cp.*, w.language, w.id as website_id FROM content_plans cp LEFT JOIN websites w ON cp.website_id = w.id WHERE cp.id = ?");
        $stmt->execute([$imageJob['plan_id']]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($plan) {
            $stmt = $pdo->prepare("SELECT id, content FROM writer_memory WHERE website_id = ? AND topic = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$plan['website_id'], $plan['focus_keyword']]);
            $memory = $stmt->fetch(PDO::FETCH_ASSOC);
            $html = $memory['content'] ?? '';
            $memoryId = $memory['id'] ?? 0;
            
            cronLog("GORSEL: HTML=" . strlen($html) . " Memory=$memoryId");
            
            if (!empty($html) && strlen(strip_tags($html)) > 100) {
                $imageManager = new ImageManager($deepseek, $pdo);
                $lang = $gemini->getLanguageName($plan['language'] ?? 'en');
                
                try {
                    $images = $imageManager->generateArticleImages($imageJob['plan_id'], $plan['focus_keyword'], $plan['title'], $lang, $html, $plan['website_id']);
                    cronLog("GORSEL: Uretildi=" . count($images));
                    
                    if (!empty($images)) {
                        $html = $imageManager->injectImagesIntoHtml($html, $images);
                        $stmt2 = $pdo->prepare("UPDATE writer_memory SET content = ?, images_generated = 1, images_count = ? WHERE id = ?");
                        $stmt2->execute([$html, count($images), $memoryId]);
                        cronLog("GORSEL: writer_memory guncellendi ID=$memoryId");
                    }
                } catch (Exception $e) {
                    cronLog("GORSEL HATA: " . $e->getMessage());
                }
            }
        }
        
        $pdo->prepare("UPDATE writer_jobs SET status = 'completed' WHERE id = ?")->execute([$imageJob['id']]);
        cronLog("GORSEL: Tamamlandi");
        if (file_exists($lockFile)) unlink($lockFile);
        exit;
    }
    
    // === NORMAL İŞ AKIŞI ===
    $stmt = $pdo->prepare("SELECT * FROM writer_jobs WHERE status IN ('pending','scoring') ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) { cronLog("Is yok"); if (file_exists($lockFile)) unlink($lockFile); exit; }
    
    cronLog("IS: Job {$job['id']} Plan {$job['plan_id']} Status {$job['status']}");
    
    $stmt = $pdo->prepare("SELECT cp.*, w.name as website_name, w.url as website_url, w.site_context, w.seo_strategy, w.language, w.id as website_id FROM content_plans cp LEFT JOIN websites w ON cp.website_id = w.id WHERE cp.id = ?");
    $stmt->execute([$job['plan_id']]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$plan) throw new Exception("Plan bulunamadi");
    
    $plan['site_full_context'] = trim(($plan['site_context'] ?? '') . "\n\nSEO Strategy: " . ($plan['seo_strategy'] ?? ''));
    
    if ($job['status'] == 'pending') {
        cronLog("YAZILIYOR...");
        $start = microtime(true);
        $html = $writer->writeFullArticle($plan, $plan);
        $elapsed = round(microtime(true) - $start, 1);
        $wordCount = str_word_count(strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8')));
        cronLog("YAZILDI: {$elapsed}s, {$wordCount} words");
        
        $retry = 0;
        while ($wordCount < 300 && $retry < 3) {
            $retry++;
            cronLog("DUSUK KELIME ({$wordCount}), retry {$retry}");
            sleep(2);
            $start = microtime(true);
            $html = $writer->writeFullArticle($plan, $plan);
            $elapsed = round(microtime(true) - $start, 1);
            $wordCount = str_word_count(strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8')));
            cronLog("RETRY: {$elapsed}s, {$wordCount} words");
        }
        
        if ($wordCount < 100) {
            cronLog("HATA: Dusuk kelime ({$wordCount})");
            $pdo->prepare("UPDATE writer_jobs SET status = 'failed', error_message = ? WHERE id = ?")->execute(["Dusuk kelime: {$wordCount}", $job['id']]);
            if (file_exists($lockFile)) unlink($lockFile);
            exit;
        }
        
        $progress = json_encode(['full_html' => $html, 'attempt' => 0]);
        $pdo->prepare("UPDATE writer_jobs SET status = 'scoring', progress_data = ? WHERE id = ?")->execute([$progress, $job['id']]);
        cronLog("PUANLAMA asamasina gecildi");
        if (file_exists($lockFile)) unlink($lockFile);
        exit;
    }
    
    if ($job['status'] == 'scoring') {
        $progress = json_decode($job['progress_data'] ?? '{}', true);
        if (!is_array($progress)) $progress = [];
        $html = $progress['full_html'] ?? '';
        $attempt = ($progress['attempt'] ?? 0) + 1;
        $wordCount = str_word_count(strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8')));
        
        if ($wordCount < 100 || empty(trim(strip_tags($html)))) {
            cronLog("HATA: Bos icerik, yeniden yaziliyor");
            $start = microtime(true);
            $html = $writer->writeFullArticle($plan, $plan);
            $wordCount = str_word_count(strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8')));
            
            if ($wordCount < 100) {
                $pdo->prepare("UPDATE writer_jobs SET status = 'failed', error_message = 'Bos icerik' WHERE id = ?")->execute([$job['id']]);
                if (file_exists($lockFile)) unlink($lockFile);
                exit;
            }
            
            $progress['full_html'] = $html;
            $progress['attempt'] = $attempt;
            $pdo->prepare("UPDATE writer_jobs SET progress_data = ? WHERE id = ?")->execute([json_encode($progress), $job['id']]);
            cronLog("Progress guncellendi");
            if (file_exists($lockFile)) unlink($lockFile);
            exit;
        }
        
        $ownSlug = $gemini->slugify($plan['focus_keyword']);
        $smartLinks = $linkManager->getSmartLinks($plan['website_id'], $html, $job['plan_id'], 3);
        cronLog("LINK: " . count($smartLinks) . " links found");
        if (!empty($smartLinks)) {
            $html = $linkManager->injectLinks($html, $smartLinks, $ownSlug);
        }
        
        cronLog("PUANLANIYOR... attempt {$attempt}, {$wordCount} words");
        
        $criticAI = ($gemini->getAgentPreference('critic') === 'deepseek') ? $deepseek : $gemini;
        $critic = new CriticAgent($criticAI, $pdo);
        $critique = $critic->critique($html, $plan['focus_keyword'], $plan['site_full_context'], $job['plan_id'], true);
        $score = $critique['score'];
        
        cronLog("PUAN: {$score}/100");
        
        if ($score >= 80 || $attempt >= 2) {
            cronLog(($score >= 80 ? "BASARILI" : "MAX DENEME") . " - YAYINLANIYOR");
            
// SEO Description için metni güvenlice temizle ve kes
            $cleanText = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8'))));
            $seoDesc = mb_substr($cleanText, 0, 155, 'UTF-8');

            $stmt = $pdo->prepare("INSERT INTO writer_memory (website_id, topic, word_count, section_count, success, content, seo_title, seo_description, slug, created_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, NOW())");
            $stmt->execute([$plan['website_id'], $plan['focus_keyword'], $wordCount, 6, $html, $plan['title'], $seoDesc, $ownSlug]);
            
            $pdo->prepare("UPDATE content_plans SET status = 'published' WHERE id = ?")->execute([$job['plan_id']]);
            $pdo->prepare("UPDATE writer_jobs SET status = 'completed' WHERE id = ?")->execute([$job['id']]);
            
            // generate_images = 1 ise görsel job'u oluştur
            if ($generateImages) {
                $pdo->prepare("INSERT INTO writer_jobs (plan_id, status, progress_data, created_at) VALUES (?, 'images_generating', ?, NOW())")->execute([$job['plan_id'], json_encode(['full_html' => $html])]);
                cronLog("YAYINLANDI: {$wordCount} words, {$score}/100, gorsel job OLUSTURULDU");
            } else {
                cronLog("YAYINLANDI: {$wordCount} words, {$score}/100, gorsel DEVRE DISI");
            }
            
            if (file_exists($lockFile)) unlink($lockFile);
            exit;
        }
        
        cronLog("DUSUK PUAN ({$score}), yeniden yaziliyor");
        foreach ($critique['suggestions'] as $s) {
            $critic->saveLesson($plan['website_id'], "S{$score}: {$s}");
        }
        
        $start = microtime(true);
        $html = $writer->writeFullArticle($plan, $plan);
        $elapsed = round(microtime(true) - $start, 1);
        
        $progress['full_html'] = $html;
        $progress['attempt'] = $attempt;
        $pdo->prepare("UPDATE writer_jobs SET progress_data = ? WHERE id = ?")->execute([json_encode($progress), $job['id']]);
        
        cronLog("Yeniden yazildi: {$elapsed}s");
        if (file_exists($lockFile)) unlink($lockFile);
        exit;
    }
    
} catch (Exception $e) {
    cronLog("HATA: " . $e->getMessage());
    if (isset($job)) {
        $pdo->prepare("UPDATE writer_jobs SET status = 'failed', error_message = ? WHERE id = ?")->execute([substr($e->getMessage(), 0, 500), $job['id']]);
    }
}

if (file_exists($lockFile)) unlink($lockFile);
cronLog("=== Cron bitti ===\n");