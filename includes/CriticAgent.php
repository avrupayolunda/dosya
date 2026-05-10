<?php
require_once __DIR__ . '/../log.php';

class CriticAgent {
    private $ai;
    private $pdo;
    private $targetScore = 80;
    
    public function __construct($ai, $pdo) {
        $this->ai = $ai;
        $this->pdo = $pdo;
        $this->ensureTables();
    }
    
    private function ensureTables() {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS critic_memory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                plan_id INT NOT NULL,
                critique LONGTEXT,
                suggestions LONGTEXT,
                applied TINYINT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY plan_id (plan_id)
            )");
            
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS writer_lessons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                website_id INT NOT NULL,
                lesson TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            try {
                $this->pdo->exec("ALTER TABLE writer_lessons ADD UNIQUE KEY unique_lesson (website_id, lesson(200))");
            } catch (Exception $e) {}
        } catch (Exception $e) {
            debug_log("CriticAgent: Tablo hatasi", ['error' => $e->getMessage()]);
        }
    }
    
    public function critique($html, $keyword, $context, $planId, $useAI = true) {
        debug_log("===================================================");
        debug_log("CriticAgent BASLADI | Plan: {$planId} | Keyword: {$keyword} | AI: " . ($useAI ? 'Acik' : 'Kapali'));
        
        $text = strip_tags($html);
        $wordCount = str_word_count($text);
        $keywordCount = substr_count(strtolower($text), strtolower($keyword));
        $density = round(($keywordCount / max(1, $wordCount)) * 100, 2);
        
        $strongCount = substr_count($html, '<strong>');
        $uCount = substr_count($html, '<u>');
        $ulCount = substr_count($html, '<ul');
        $h2Count = substr_count($html, '<h2');
        $h3Count = substr_count($html, '<h3');
        $linkCount = substr_count($html, '<a href=');
        
        preg_match_all('/<p>(.*?)<\/p>/is', $html, $matches);
        $shortParas = 0;
        $longParas = 0;
        foreach ($matches[1] ?? [] as $p) {
            $pClean = strip_tags($p);
            $paraWordCount = str_word_count($pClean);
            if ($paraWordCount < 20) $shortParas++;
            if ($paraWordCount > 100) $longParas++;
        }
        
        $seoIssues = [];
        $seoSuggestions = [];
        $criticalErrors = [];
        
        $firstPara = '';
        if (!empty($matches[1])) {
            $firstPara = strip_tags($matches[1][0]);
        }
        $keywordInFirstPara = (stripos($firstPara, $keyword) !== false);
        if (!$keywordInFirstPara) {
            $criticalErrors[] = "Anahtar kelime ilk paragrafta bulunmuyor";
            $seoSuggestions[] = "Anahtar kelimeyi ilk paragrafa ekleyin - tercihen ilk cumlede <strong> ile";
        }
        
        $minKeywordCount = max(6, floor($wordCount * 0.005));
        if ($keywordCount < $minKeywordCount) {
            $criticalErrors[] = "Odak anahtar kelime {$keywordCount} kez geciyor (minimum {$minKeywordCount} olmali, ~%0.5-1.5 yogunluk)";
            $seoSuggestions[] = "Anahtar kelimeyi dogal sekilde {$minKeywordCount}-" . ($minKeywordCount + 5) . " kez kullanin";
        }
        if ($density > 2.5) {
            $seoIssues[] = "Anahtar kelime yogunlugu cok yuksek: %{$density}";
            $seoSuggestions[] = "Anahtar kelime kullanimini azaltin, es anlamlilara yonelin";
        }
        
        preg_match_all('/<h2>(.*?)<\/h2>/is', $html, $h2Matches);
        $keywordInH2 = false;
        foreach (($h2Matches[1] ?? []) as $h2Text) {
            if (stripos(strip_tags($h2Text), $keyword) !== false) {
                $keywordInH2 = true;
                break;
            }
        }
        if (!$keywordInH2) {
            $criticalErrors[] = "Anahtar kelime hicbir H2 basliginda bulunmuyor";
            $seoSuggestions[] = "Anahtar kelimeyi en az bir H2 basligina ekleyin";
        }
        
        preg_match_all('/<h3>(.*?)<\/h3>/is', $html, $h3Matches);
        $h3WithKeyword = 0;
        $totalH3 = count($h3Matches[0] ?? []);
        foreach (($h3Matches[1] ?? []) as $h3) {
            if (stripos(strip_tags($h3), $keyword) !== false) {
                $h3WithKeyword++;
            }
        }
        if ($totalH3 > 0 && $h3WithKeyword < 2) {
            $seoIssues[] = "Alt basliklarda (H3) anahtar kelime yeterince kullanilmamis ({$h3WithKeyword}/{$totalH3})";
            $seoSuggestions[] = "En az 2-3 H3 basligina anahtar kelime veya es anlamlisini ekleyin";
        }
        
        if ($h2Count > 6) {
            $criticalErrors[] = "H2 sayisi cok fazla: {$h2Count} (maksimum 6 olmali)";
            $seoSuggestions[] = "Fazla H2 basliklarini H3'e cevirin veya birlestirin";
        }
        if ($h2Count < 3) {
            $seoIssues[] = "H2 sayisi az: {$h2Count} (en az 4 olmali)";
            $seoSuggestions[] = "Daha fazla H2 basligi ekleyin";
        }
        
        if ($longParas > 0) {
            $criticalErrors[] = "{$longParas} paragraf cok uzun (100+ kelime), okunabilirlik dusuk";
            $seoSuggestions[] = "Uzun paragraflari 40-80 kelimelik kisa paragraflara bolun";
        }
        
        $headingLengthIssues = 0;
        foreach (($h2Matches[1] ?? []) as $h2Text) {
            $h2Plain = strip_tags($h2Text);
            $h2Len = mb_strlen($h2Plain, 'UTF-8');
            if ($h2Len > 60 || $h2Len < 15) {
                $headingLengthIssues++;
            }
        }
        if ($headingLengthIssues > 0) {
            $seoIssues[] = "{$headingLengthIssues} H2 basligi uygun uzunlukta degil (20-60 karakter olmali)";
            $seoSuggestions[] = "H2 basliklarini 20-60 karakter arasinda tutun";
        }
        
        $firstLetterIssue = false;
        if (!empty($matches[1])) {
            $firstParaRaw = trim($matches[1][0]);
            if (preg_match('/^<strong>([a-z])/u', $firstParaRaw)) {
                $firstLetterIssue = true;
            } elseif (preg_match('/^([a-z])/u', strip_tags($firstParaRaw))) {
                $firstLetterIssue = true;
            }
        }
        if ($firstLetterIssue) {
            $criticalErrors[] = "Giris paragrafinin ilk harfi kucuk yazilmis";
            $seoSuggestions[] = "Ilk paragrafin ilk harfini buyuk yapin";
        }
        
        $hasBlockquote = (stripos($html, '<blockquote') !== false);
        if (!$hasBlockquote) {
            $seoIssues[] = "Blockquote (alinti) eksik";
            $seoSuggestions[] = "Uzman gorusu, istatistik veya referans icin bir blockquote ekleyin";
        }
        
        $ctaWords = ['contact', 'offerte', 'afspraak', 'bel ons', 'neem contact', 'vraag aan', 'call', 'quote', 'appointment', 'randevu', 'teklif', 'iletisim', 'ara', 'demo', 'deneme'];
        $hasCTA = false;
        $stripped = strtolower(strip_tags($html));
        foreach ($ctaWords as $cta) {
            if (stripos($stripped, $cta) !== false) {
                $hasCTA = true;
                break;
            }
        }
        if (!$hasCTA) {
            $seoIssues[] = "CTA (cagri-action) eksik";
            $seoSuggestions[] = "Makale sonuna dogru dogal bir CTA ekleyin (iletisim, teklif, randevu vb.)";
        }
        
        $hasImages = (stripos($html, '<img ') !== false || stripos($html, '<figure') !== false);
        
        if ($wordCount < 1200) {
            $criticalErrors[] = "Kelime sayisi cok dusuk: {$wordCount} (minimum 1200 olmali)";
            $seoSuggestions[] = "Makaleyi en az 1200 kelimeye genisletin";
        }
        
        debug_log("METRIKLER", [
            'words' => $wordCount, 'density' => $density . '%',
            'keyword_count' => $keywordCount, 'min_required' => $minKeywordCount,
            'keyword_in_first_para' => $keywordInFirstPara ? 'Evet' : 'Hayir',
            'keyword_in_h2' => $keywordInH2 ? 'Evet' : 'Hayir',
            'h3_with_keyword' => "{$h3WithKeyword}/{$totalH3}",
            'strong' => $strongCount, 'u' => $uCount, 'ul' => $ulCount,
            'h2' => $h2Count, 'h3' => $h3Count, 'links' => $linkCount,
            'blockquote' => $hasBlockquote ? 'Var' : 'Yok',
            'cta' => $hasCTA ? 'Var' : 'Yok',
            'images' => $hasImages ? 'Var' : 'Yok',
            'short_paras' => $shortParas, 'long_paras' => $longParas,
            'critical_errors' => count($criticalErrors)
        ]);
        
        if (!empty($criticalErrors)) {
            debug_log("KRITIK HATALAR", $criticalErrors);
        }
        
        if ($useAI) {
            $aiAnalysis = $this->aiAnalyze($html, $keyword, $context, $wordCount, $density, $h2Count, $h3Count, $linkCount, $ulCount, $strongCount, $shortParas, $longParas);
            $score = $aiAnalysis['score'] ?? 80;
            $issues = array_merge($criticalErrors, $seoIssues, $aiAnalysis['issues'] ?? []);
            $suggestions = array_merge($seoSuggestions, $aiAnalysis['suggestions'] ?? []);
        } else {
            $score = 85;
            $issues = array_merge($criticalErrors, $seoIssues);
            $suggestions = $seoSuggestions;
            
            if (!$keywordInFirstPara) { $score -= 10; }
            if ($keywordCount < $minKeywordCount) { $score -= 12; }
            if ($density > 2.5) { $score -= 5; }
            if (!$keywordInH2) { $score -= 10; }
            if ($totalH3 > 0 && $h3WithKeyword < 2) { $score -= 5; }
            if ($h2Count > 6) { $score -= 8; }
            if ($longParas > 0) { $score -= 8; }
            if ($firstLetterIssue) { $score -= 5; }
            if ($wordCount < 1200) { $score -= 15; }
            
            if ($h2Count < 3) { $score -= 5; }
            if ($h3Count < 3) { $issues[] = "H3 sayisi az: {$h3Count} (en az 4 olmali)"; $suggestions[] = "Daha fazla H3 alt basligi ekleyin"; $score -= 5; }
            if ($linkCount < 1) { $issues[] = "Hic ic link yok"; $suggestions[] = "En az 1 ic link ekleyin"; $score -= 3; }
            if ($ulCount > 5) { $issues[] = "Cok fazla liste: {$ulCount}"; $suggestions[] = "Liste sayisini azaltin"; $score -= 3; }
            if ($shortParas > 3) { $issues[] = "{$shortParas} paragraf cok kisa"; $suggestions[] = "Kisa paragraflari birlestirin veya genisletin"; $score -= 3; }
            if ($strongCount < 1) { $issues[] = "Hic <strong> tag'i yok"; $suggestions[] = "Anahtar kelimeleri vurgulamak icin <strong> kullanin"; $score -= 5; }
            if ($strongCount > 30) { $issues[] = "Cok fazla strong tag: {$strongCount}"; $suggestions[] = "Strong kullanimini 20'nin altina dusurun"; $score -= 3; }
            if (!$hasBlockquote) { $score -= 3; }
            if (!$hasCTA) { $score -= 5; }
            if ($headingLengthIssues > 0) { $score -= 3; }
        }
        
        $score = max(0, min(100, $score));
        
        $hasCriticalErrors = !empty($criticalErrors);
        if ($hasCriticalErrors && $score >= 80) {
            $score = min($score, 75);
            debug_log("KRITIK HATA CEZASI: Puan 80'in altina dusuruldu -> {$score}");
        }
        
        debug_log("FINAL SCORE: {$score}/100");
        debug_log("SEO ISSUES", $issues);
        if ($hasCriticalErrors) {
            debug_log("!!! KRITIK HATALAR MEVCUT - MAKALE YAYINLANMAMALI !!!");
        }
        
        $critique = [
            'score' => $score,
            'issues' => $issues,
            'suggestions' => $suggestions,
            'should_rewrite' => $score < $this->targetScore,
            'has_critical_errors' => $hasCriticalErrors,
            'critical_errors' => $criticalErrors,
            'metrics' => compact('wordCount','density','keywordCount','h2Count','h3Count','h3WithKeyword','strongCount','uCount','linkCount','hasBlockquote','hasCTA','hasImages','longParas','headingLengthIssues')
        ];
        
        $this->saveCritique($planId, $critique);
        return $critique;
    }
    
    private function aiAnalyze($html, $keyword, $context, $wordCount, $density, $h2Count, $h3Count, $linkCount, $ulCount, $strongCount, $shortParas, $longParas) {
        $textSample = substr(strip_tags($html), 0, 2000);
        
        preg_match('/<p>(.*?)<\/p>/is', $html, $firstParaMatch);
        $firstPara = !empty($firstParaMatch[1]) ? strip_tags($firstParaMatch[1]) : '';
        $keywordInFirst = (stripos($firstPara, $keyword) !== false) ? 'YES' : 'NO';
        
        preg_match_all('/<h2>(.*?)<\/h2>/is', $html, $h2Matches);
        $keywordInH2 = 'NO';
        foreach (($h2Matches[1] ?? []) as $h2Text) {
            if (stripos(strip_tags($h2Text), $keyword) !== false) {
                $keywordInH2 = 'YES';
                break;
            }
        }
        
        $hasBlockquote = (stripos($html, '<blockquote') !== false) ? 'YES' : 'NO';
        $hasCTA = 'NO';
        $ctaWords = ['contact', 'offerte', 'afspraak', 'bel ons', 'neem contact', 'call', 'quote', 'appointment'];
        foreach ($ctaWords as $cta) {
            if (stripos(strip_tags($html), $cta) !== false) {
                $hasCTA = 'YES';
                break;
            }
        }
        
        $prompt = "You are an SEO expert. Score this article based on SEO metrics and content quality.

KEYWORD: {$keyword}

SEO METRICS:
- Word count: {$wordCount}
- Keyword density: {$density}%
- Keyword in first paragraph: {$keywordInFirst}
- Keyword in any H2 heading: {$keywordInH2}
- H2 count: {$h2Count} (ideal: 4-6, NEVER more than 6)
- H3 count: {$h3Count}
- Internal links: {$linkCount}
- Lists: {$ulCount}
- Strong tags: {$strongCount}
- Short paragraphs (<20 words): {$shortParas}
- Long paragraphs (>100 words): {$longParas}
- Blockquote present: {$hasBlockquote}
- CTA present: {$hasCTA}

ARTICLE TEXT SAMPLE:
{$textSample}

SCORE RULES:
- 90-100: Excellent SEO + great content
- 80-89: Good, minor improvements needed
- 70-79: Average, several issues
- Below 70: Needs significant improvement

CRITICAL CHECKS (these MUST reduce score below 80 if failed):
- Keyword MUST be in first paragraph (if NO, deduct 10+ points)
- Keyword MUST be in at least one H2 (if NO, deduct 10+ points)
- H2 count MUST be 4-6 (if more than 6, deduct 8+ points)
- Long paragraphs (100+ words) = CRITICAL readability issue (deduct 8+ points per long paragraph)
- Word count below 1200 = CRITICAL (deduct 15+ points)
- Keyword density below 0.5% = CRITICAL (deduct 12+ points)

NON-CRITICAL CHECKS:
- Blockquote missing = deduct 3-5 points
- CTA missing = deduct 3-5 points
- 1-2 internal links is ACCEPTABLE (no deduction unless zero)

Reply ONLY in this format:
SCORE: [number]
ISSUES:
- specific issue found
- another issue
SUGGESTIONS:
- specific fix
- another fix";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You score articles based on SEO and content metrics. Be strict about keyword placement, H2 count (max 6), paragraph length (max 80 words), and keyword density. If ANY critical check fails, the score MUST be below 80. Reply ONLY in the specified format.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            debug_log("AI istegi gonderiliyor...");
            $response = $this->ai->chat($messages, 0.3, false);
            debug_log("AI ham yanit", ['length' => strlen($response), 'preview' => substr($response, 0, 300)]);
            
            $score = 80;
            if (preg_match('/SCORE:\s*(\d+)/i', $response, $m)) {
                $score = max(0, min(100, (int)$m[1]));
            }
            
            $issues = [];
            if (preg_match('/ISSUES:\s*\n?(.*?)(?:SUGGESTIONS:|$)/is', $response, $m)) {
                foreach (explode("\n", trim($m[1])) as $line) {
                    $line = trim($line, "- \t\n\r\0\x0B");
                    if (!empty($line)) $issues[] = $line;
                }
            }
            
            $suggestions = [];
            if (preg_match('/SUGGESTIONS:\s*\n?(.*?)$/is', $response, $m)) {
                foreach (explode("\n", trim($m[1])) as $line) {
                    $line = trim($line, "- \t\n\r\0\x0B");
                    if (!empty($line)) $suggestions[] = $line;
                }
            }
            
            debug_log("AI analiz tamam", ['score' => $score]);
            return ['score' => $score, 'issues' => $issues, 'suggestions' => $suggestions];
            
        } catch (Exception $e) {
            debug_log("AI Analiz HATASI: " . $e->getMessage());
        }
        
        return ['score' => 80, 'issues' => [], 'suggestions' => []];
    }
    
    private function saveCritique($planId, $critique) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO critic_memory (plan_id, critique, suggestions) VALUES (?, ?, ?)");
            $stmt->execute([$planId, json_encode($critique), json_encode($critique['suggestions'])]);
        } catch (Exception $e) {
            debug_log("CriticAgent: Kayit hatasi", ['error' => $e->getMessage()]);
        }
    }
    
    public function saveLesson($websiteId, $lesson) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM writer_lessons WHERE website_id = ? AND lesson = ? LIMIT 1");
            $stmt->execute([$websiteId, $lesson]);
            if (!$stmt->fetch()) {
                $stmt = $this->pdo->prepare("INSERT INTO writer_lessons (website_id, lesson, created_at) VALUES (?, ?, NOW())");
                return $stmt->execute([$websiteId, $lesson]);
            }
        } catch (Exception $e) {
            debug_log("saveLesson hatasi", ['error' => $e->getMessage()]);
        }
        return false;
    }
    
    public function getUnappliedLessons($websiteId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("SELECT DISTINCT lesson FROM writer_lessons WHERE website_id = ? ORDER BY id DESC LIMIT " . (int)$limit);
            $stmt->execute([$websiteId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $lessons = [];
            foreach ($rows as $row) {
                $lesson = str_replace('HATA: ', '', $row['lesson']);
                if (strlen($lesson) > 20) {
                    $lessons[] = $lesson;
                }
            }
            return array_slice(array_unique($lessons), 0, $limit);
        } catch (Exception $e) {
            return [];
        }
    }
}