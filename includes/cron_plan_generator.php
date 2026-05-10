<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/AI.php';
require_once __DIR__ . '/../log.php';
require_once __DIR__ . '/analyzer.php';

set_time_limit(300);
ini_set('memory_limit', '512M');

$logFile = dirname(__DIR__) . '/cron_plan_log.txt';
$lockFile = __DIR__ . '/../cron_plan.lock';

if (file_exists($lockFile) && time() - filemtime($lockFile) < 120) exit;
file_put_contents($lockFile, date('Y-m-d H:i:s'));
file_put_contents($logFile, date('Y-m-d H:i:s') . "\n", FILE_APPEND);

try {
    $gemini = new AI($pdo);
    $agentType = $gemini->getAgentPreference('plan_creator'); 
    
    $planAgent = new AI($pdo);
    $planAgent->setAgent($agentType);
    
    $stmt = $pdo->prepare("SELECT * FROM plan_generator_jobs WHERE status IN ('pending','running') ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        unlink($lockFile);
        exit;
    }
    
    if ($job['status'] === 'running') {
        $stmt2 = $pdo->prepare("SELECT updated_at FROM plan_generator_jobs WHERE id = ?");
        $stmt2->execute([$job['id']]);
        $updated = $stmt2->fetchColumn();
        if ($updated && time() - strtotime($updated) < 60) {
            unlink($lockFile);
            exit;
        }
    }
    
    $pdo->prepare("UPDATE plan_generator_jobs SET status = 'running', updated_at = NOW() WHERE id = ?")->execute([$job['id']]);
    
    $website_id = $job['website_id'];
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
    $stmt->execute([$website_id]);
    $website = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$website) throw new Exception("Error");
    
    $currentDay = (int)$job['current_day'];
    $totalDays = (int)$job['total_days'];
    $dailyLimit = (int)($website['daily_post_limit'] ?? 3);
    $publishInterval = (int)($website['publish_interval'] ?? 240);
    $peakStart = $website['peak_time_start'] ?? '09:00:00';
    $peakEnd = $website['peak_time_end'] ?? '21:00:00';
    
    $todayDate = date('Y-m-d', strtotime($job['start_date'] . " +{$currentDay} days"));
    $dayOfWeek = (int)date('N', strtotime($job['start_date'])); 
    $daysToSunday = 7 - $dayOfWeek;
    $sundayDate = date('Y-m-d', strtotime($job['start_date'] . " +{$daysToSunday} days"));
    
    if ($todayDate > $sundayDate || $currentDay >= $totalDays) {
        $pdo->prepare("UPDATE plan_generator_jobs SET status = 'completed', updated_at = NOW() WHERE id = ?")->execute([$job['id']]);
        unlink($lockFile);
        exit;
    }

    $isToday = ($todayDate === date('Y-m-d'));
    $now = date('H:i:s');
    
    if ($isToday && timeToMinutes($now) >= timeToMinutes($peakEnd)) {
        moveToNextDay($pdo, $job, $currentDay, $sundayDate);
        unlink($lockFile);
        exit;
    }
    
    $maxFit = $dailyLimit;
    if ($isToday) {
        $remainingMin = timeToMinutes($peakEnd) - max(timeToMinutes($now), timeToMinutes($peakStart));
        if ($remainingMin <= 0) {
            moveToNextDay($pdo, $job, $currentDay, $sundayDate);
            unlink($lockFile);
            exit;
        }
        $maxFit = min($dailyLimit, floor($remainingMin / $publishInterval) + 1);
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM content_plans WHERE website_id = ? AND scheduled_date = ?");
    $stmt->execute([$website_id, $todayDate]);
    $existingCount = (int)$stmt->fetchColumn();
    
    if ($existingCount >= $maxFit) {
        moveToNextDay($pdo, $job, $currentDay, $sundayDate);
        unlink($lockFile);
        exit;
    }
    
    $needed = $maxFit - $existingCount;
    
    if ($needed > 0) {
        $lastCrawledAt = strtotime($website['last_crawled_at'] ?? '2000-01-01 00:00:00');
        if ((time() - $lastCrawledAt) > 3600) {
            try {
                $analyzer = new SiteAnalyzer($pdo, $gemini, $website_id);
                $analyzer->crawl();
            } catch (Exception $e) {}
        }
    }

    $inserted = generateDayPlan($website, $needed, $todayDate, $planAgent, $pdo, $publishInterval, $peakStart, $peakEnd, $isToday);
    
    moveToNextDay($pdo, $job, $currentDay, $sundayDate);
    unlink($lockFile);
    
} catch (Exception $e) {
    if (isset($job)) {
        $pdo->prepare("UPDATE plan_generator_jobs SET status = 'failed', error_message = ? WHERE id = ?")->execute([substr($e->getMessage(), 0, 500), $job['id']]);
    }
    if (file_exists($lockFile)) unlink($lockFile);
}

function moveToNextDay($pdo, $job, $currentDay, $sundayDate) {
    $nextDay = $currentDay + 1;
    $nextDate = date('Y-m-d', strtotime($job['start_date'] . " +{$nextDay} days"));
    
    if ($nextDate > $sundayDate || $nextDay >= $job['total_days']) {
        $pdo->prepare("UPDATE plan_generator_jobs SET status = 'completed', current_day = ?, updated_at = NOW() WHERE id = ?")->execute([$nextDay, $job['id']]);
    } else {
        $pdo->prepare("UPDATE plan_generator_jobs SET status = 'pending', current_day = ?, updated_at = NOW() WHERE id = ?")->execute([$nextDay, $job['id']]);
    }
}

function getTrendingTopics($website, $pdo) {
    $website_id = $website['id'];
    $stmt = $pdo->prepare("SELECT query FROM search_console_data WHERE website_id = ? AND date_retrieved >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND query IS NOT NULL AND query != '' GROUP BY query HAVING SUM(clicks) > 0 ORDER BY SUM(clicks) DESC LIMIT 15");
    $stmt->execute([$website_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateDayPlan($website, $needed, $date, $planAgent, $pdo, $publishInterval, $peakStart, $peakEnd, $isToday) {
    $website_id = $website['id'];
    $lang = $website['language'] ?? 'nl';
    $langName = $planAgent->getLanguageName($lang);
    
    if ($needed <= 0) return 0;
    
    $trends = getTrendingTopics($website, $pdo);
    
    $stmt = $pdo->prepare("SELECT title FROM crawled_pages WHERE website_id = ? AND title != '' ORDER BY id DESC LIMIT 50");
    $stmt->execute([$website_id]);
    $crawledPages = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT title FROM content_plans WHERE website_id = ? ORDER BY id DESC LIMIT 50");
    $stmt->execute([$website_id]);
    $planPages = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT seo_title FROM writer_memory WHERE website_id = ? ORDER BY id DESC LIMIT 50");
    $stmt->execute([$website_id]);
    $memoryPages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $allExistingTitles = array_unique(array_merge($crawledPages, $planPages, $memoryPages));
    
    $stmt = $pdo->prepare("SELECT focus_keyword FROM content_plans WHERE website_id = ?");
    $stmt->execute([$website_id]);
    $usedKeywords = array_unique(array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    
    $existingSection = "ALREADY WRITTEN TITLES (NEVER WRITE ABOUT THESE AGAIN):\n- " . implode("\n- ", array_slice($allExistingTitles, 0, 40)) . "\n";
    $trendSection = !empty($trends) ? "GSC KEYWORDS:\n" . implode(", ", array_column($trends, 'query')) . "\n" : "";
    
    $requestCount = $needed + 5;

    $prompt = "You are an elite SEO Content Strategist. Find completely NEW CONTENT GAPS based on the context.

SITE CONTEXT: {$website['site_context']}
LANGUAGE: {$langName}

{$trendSection}
{$existingSection}

CRITICAL RULES:
1. Generate EXACTLY {$requestCount} article ideas.
2. YOU MUST NEVER GENERATE A TOPIC OR TITLE SIMILAR TO THE 'ALREADY WRITTEN TITLES' LIST. FIND COMPLETELY NEW ANGLES.
3. GOLDILOCKS LENGTH: Titles MUST be exactly 6 or 7 words long. Total character count MUST be between 42 and 58 characters.
4. NO PUNCTUATION: Do NOT use colons, hyphens, or pipes.
5. TITLE CASE: Capitalize the first letter of EVERY word.
6. NO LOCATIONS.
7. NO CANNIBALIZATION: Each title must be a completely different sub-topic.

Return ONLY valid JSON:
{\"plan\":[{\"keyword\":\"...\",\"title\":\"...\",\"category\":\"...\"}]}";

    $result = $planAgent->chat([
        ['role' => 'system', 'content' => 'You are a strict JSON API. Return ONLY valid JSON.'],
        ['role' => 'user', 'content' => $prompt]
    ], 0.85, true);
    
    $clean = preg_replace('/```json\s*|\s*```/i', '', $result);
    $clean = trim($clean);
    
    if (($pos = strpos($clean, '{')) !== false) $clean = substr($clean, $pos);
    $clean .= str_repeat('}', max(0, substr_count($clean, '{') - substr_count($clean, '}')));
    $clean .= str_repeat(']', max(0, substr_count($clean, '[') - substr_count($clean, ']')));
    
    $data = json_decode($clean, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) return 0;
    
    $plan = $data['plan'] ?? $data['items'] ?? $data['articles'] ?? $data;
    if (isset($data['keyword'])) $plan = [$data];
    if (!is_array($plan) || !isset($plan[0])) return 0;
    
    $timeSlots = [];
    $ps = timeToMinutes($peakStart);
    $pe = timeToMinutes($peakEnd);
    
    if ($isToday) {
        $start = max(timeToMinutes(date('H:i:s')) + 5, $ps);
    } else {
        $maxOffset = max(0, ($pe - $ps) - (($needed - 1) * $publishInterval));
        $start = $ps + rand(0, $maxOffset);
    }
    
    for ($i = 0; $i < $needed; $i++) {
        $m = $start + ($i * $publishInterval) + rand(-10, 10);
        $m = max($ps, min($pe, $m));
        $timeSlots[] = sprintf('%02d:%02d:00', floor($m / 60), $m % 60);
    }
    
    $inserted = 0;
    foreach ($plan as $item) {
        if ($inserted >= $needed) break; 
        
        $kw = trim($item['keyword'] ?? '');
        $title = trim($item['title'] ?? '');
        $cat = trim($item['category'] ?? 'Genel');
        
        if (empty($kw) || empty($title)) continue;
        
        $title = preg_replace('/[:|\-]/', '', $title);
        $title = preg_replace('/\s+/', ' ', trim($title));
        $title = mb_convert_case($title, MB_CASE_TITLE, "UTF-8");
        
        $len = mb_strlen($title, 'UTF-8');
        if ($len < 42 || $len > 58) {
            continue; 
        }

        if (in_array(strtolower($kw), $usedKeywords)) continue;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM content_plans WHERE website_id = ? AND scheduled_date = ? AND focus_keyword = ?");
        $stmt->execute([$website_id, $date, $kw]);
        if ($stmt->fetchColumn() > 0) continue;

        $catSlug = createSlugForPlan($cat);
        if(empty($catSlug)) $catSlug = 'genel';
        
        $stmtCat = $pdo->prepare("SELECT id FROM blog_categories WHERE website_id = ? AND slug = ?");
        $stmtCat->execute([$website_id, $catSlug]);
        $catRow = $stmtCat->fetch(PDO::FETCH_ASSOC);

        if (!$catRow) {
            $stmtCheck = $pdo->prepare("SELECT COUNT(id) FROM blog_categories WHERE website_id = ?");
            $stmtCheck->execute([$website_id]);
            if($stmtCheck->fetchColumn() < 15) {
                $pdo->prepare("INSERT INTO blog_categories (website_id, name, slug) VALUES (?, ?, ?)")->execute([$website_id, $cat, $catSlug]);
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO content_plans (website_id, focus_keyword, title, category, scheduled_date, scheduled_time, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$website_id, $kw, $title, $cat, $date, $timeSlots[$inserted]]);
        $usedKeywords[] = strtolower($kw);
        $inserted++;
    }
    
    return $inserted;
}

function timeToMinutes($time) {
    $parts = explode(':', $time);
    return (int)$parts[0] * 60 + (int)$parts[1];
}

function createSlugForPlan($text) {
    $find = array('I', 'İ', 'Ü', 'Ö', 'Ş', 'Ç', 'Ğ', 'ı', 'u', 'o', 's', 'c', 'g');
    $replace = array('i', 'i', 'u', 'o', 's', 'c', 'g', 'i', 'u', 'o', 's', 'c', 'g');
    $text = str_replace($find, $replace, $text);
    $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
    return strtolower(trim(preg_replace('/\s+/', '-', $text)));
}
?>