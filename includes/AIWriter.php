<?php
require_once __DIR__ . '/ResearchAgent.php';
require_once __DIR__ . '/CriticAgent.php';
require_once __DIR__ . '/../log.php';

class AIWriter {
    private $gemini;
    private $deepseek;
    private $pdo;
    
    public function __construct($gemini, $deepseek, $pdo) {
        $this->gemini = $gemini;
        $this->deepseek = $deepseek;
        $this->pdo = $pdo;
    }
    
    private function getAgent($role) {
        $pref = $this->gemini->getAgentPreference($role); 
        return ($pref === 'deepseek') ? $this->deepseek : $this->gemini;
    }
    
    public function getLanguageName($code) {
        $stmt = $this->pdo->prepare("SELECT language FROM websites WHERE id = ? LIMIT 1");
        $map = ['tr'=>'Turkce','en'=>'English','nl'=>'Nederlands','de'=>'Deutsch','fr'=>'Francais'];
        return $map[$code] ?? $code;
    }
    
    public function getGscKeywords($websiteId, $focusKeyword, $lang, $ai) {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT query, SUM(impressions) as total_impressions 
             FROM search_console_data 
             WHERE website_id = ? AND query IS NOT NULL AND LENGTH(query) > 4
             GROUP BY query ORDER BY total_impressions DESC LIMIT 50"
        );
        $stmt->execute([$websiteId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $pool = [];
        foreach ($rows as $row) {
            $q = strtolower(trim($row['query']));
            if ($focusKeyword && stripos($q, strtolower($focusKeyword)) !== false) continue;
            if (strlen($q) < 5) continue;
            $pool[] = $q;
        }
        
        if (empty($pool)) return [];
        if (count($pool) <= 5) return array_slice($pool, 0, 5);
        
        $poolList = implode(", ", $pool);
        $prompt = "You are an expert SEO assistant. The main topic of our article is \"{$focusKeyword}\". The language is {$lang}.
Here is a list of popular search queries from this specific website: [{$poolList}].
Your task is to select ONLY the 4 to 8 most semantically relevant keywords from this list that naturally fit into an article about \"{$focusKeyword}\".
Return ONLY a comma-separated list of the keywords you chose. Do not write anything else.";

        try {
            $messages = [['role' => 'user', 'content' => $prompt]];
            $response = $ai->chat($messages, 0.3, false); 
            
            $filtered = array_map('trim', explode(',', $response));
            $filtered = array_filter($filtered, function($k) { return strlen($k) > 3; });
            return array_slice(array_values($filtered), 0, 8);
        } catch (Exception $e) {
            return array_slice($pool, 0, 5);
        }
    }
    
    public function getUnappliedLessons($websiteId) {
        $criticAI = $this->getAgent('critic');
        $critic = new CriticAgent($criticAI, $this->pdo);
        $lessons = $critic->getUnappliedLessons($websiteId, 5);
        if (empty($lessons)) return '';
        $text = "CRITICAL FIXES FROM PREVIOUS ARTICLES:\n";
        foreach ($lessons as $i => $l) $text .= ($i+1) . ". " . $l . "\n";
        return $text . "\n";
    }
    
    public function writeFullArticle($plan, $website) {
        $websiteId = $website['id'] ?? $website['website_id'];
        $lang = $this->getLanguageName($website['language'] ?? 'en');
        
        $writerAI = $this->getAgent('writer');
        
        $keyword = $plan['focus_keyword'];
        $lessonsText = $this->getUnappliedLessons($websiteId);
        $siteFullContext = $website['site_full_context'] ?? $website['site_context'] ?? '';
        
        $gscKeywords = $this->getGscKeywords($websiteId, $keyword, $lang, $writerAI);
        $gscText = !empty($gscKeywords) ? "RELATED SEARCH KEYWORDS TO USE NATURALLY:\n- " . implode("\n- ", $gscKeywords) . "\n\n" : '';
        
        $targetWords = rand(1600, 2400);
        $minWords = 1200;
        $targetKeywordCount = rand(9, 14);
        $maxStrong = max(3, min(7, $targetKeywordCount));
        
        $prompt = $lessonsText . "
Act as an expert copywriter and SEO specialist. Write a highly engaging, natural, and comprehensive BUSINESS article in HTML following Google's \"People-First Content\" and E-E-A-T guidelines.

TITLE: {$plan['title']}
MAIN KEYWORD: \"{$keyword}\"
LANGUAGE: {$lang}
TARGET WORDS: {$targetWords} minimum. Write deeply and comprehensively.

SITE CONTEXT & STRATEGY:
{$siteFullContext}

{$gscText}IMPORTANT - READER-FOCUSED CONTENT:
- Write this article FROM THE PERSPECTIVE of the business described in SITE CONTEXT.
- The goal is to attract readers/potential customers to THIS business website.
- Use 'we/our' naturally when referring to the business services.
- Include persuasive elements that encourage readers to choose this business.
- Do NOT write generic encyclopedia-style content. Write as if this business is publishing the article on their own blog.

CRITICAL SEO & STRUCTURE RULES:
1. PEOPLE-FIRST & E-E-A-T: Write for humans, not search engines. Provide original, deep, and unique insights.
2. HTML WRAPPER: The output MUST start directly with a <section> tag and end with </section>. Do NOT output any conversational text.
3. INTRO PARAGRAPH:
   - The VERY FIRST element after <section> MUST be a <p> paragraph (NOT an H2).
   - The FIRST LETTER of this paragraph MUST be UPPERCASE.
   - Wrap the exact MAIN KEYWORD in <strong> within the first sentence.
4. HEADING STRUCTURE (4-6 H2s):
   - EXACT MATCH RULE: You MUST include the EXACT keyword \"{$keyword}\" in AT LEAST ONE <h2> heading, and AT LEAST TWO <h3> headings. This is mandatory for scoring.
   - Use EXACTLY 4 to 6 <h2> tags to divide the article logically. NEVER use more than 6 H2 headings.
   - Each H2 heading MUST be between 20 and 60 characters long. Not shorter, not longer.
   - The first letter of every word in headings MUST be capitalized (Title Case).
   - Each section MUST be comprehensive and detailed, containing around 150-250 words.
5. READABILITY & SCANNABILITY:
   - Break your text into very short paragraphs of maximum 3-4 sentences (about 40-80 words). NEVER write a paragraph longer than 80 words.
   - Make the article highly SCANNABLE by using bullet points (<ul>).
   - Use underlining (<u>) for 3-5 striking statistics or core facts.
6. KEYWORD DENSITY: Use the MAIN KEYWORD \"{$keyword}\" naturally throughout the text at least {$targetKeywordCount} times (aim for 0.7%-1.5% density). Spread it across intro, body paragraphs, headings, and conclusion.
7. RICH ELEMENTS: Naturally integrate a <table> or <blockquote> if it adds real educational value for the reader.
8. CONCLUSION & FAQ: Include a FAQ section with 3-4 Q&A pairs using <h3> tags. Conclude with a natural wrap-up paragraph and a clear CTA that encourages the reader to contact or engage with the business.
9. FORBIDDEN WORDS: NEVER use headings like 'Conclusie', 'Samenvatting', 'Tot slot', 'In short', or 'Conclusion'. 
10. NO external URLs, NO city/region names. Write entirely in {$lang}.
11. NO <h1>, <!DOCTYPE>, <html>, <head>, <body>, <style>, or <article> tags.
12. Do NOT write static/hardcoded statistics or fake percentages. All claims should be general and applicable to any business in this sector.

RETURN ONLY HTML.";

        $messages = [['role' => 'user', 'content' => $prompt]];
        $html = $writerAI->chat($messages, 0.8, false); 
        
        $html = preg_replace('/```html\s*|\s*```/i', '', trim($html));
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        
        $sectionStart = stripos($html, '<section');
        if ($sectionStart !== false) {
            $html = substr($html, $sectionStart);
        } else {
            if (preg_match('/<(p|h2|h3)\b[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
                $html = "<section>\n" . substr($html, $matches[0][1]);
            }
        }
        
        $html = preg_replace('/<p[^>]*>[^<]*$/i', '', $html);
        $html = preg_replace('/<h[23][^>]*>[^<]*$/i', '', $html);
        
        $sectionEnd = strripos($html, '</section>');
        if ($sectionEnd !== false) {
            $html = substr($html, 0, $sectionEnd + 10);
        } else {
            $html .= "\n</section>";
        }

        $html = preg_replace('/(<section\b[^>]*>)\s*[^<]+/i', '$1' . "\n", $html);
        
        $html = $this->fixFirstElement($html, $keyword, $lang, $siteFullContext, $writerAI);
        $html = $this->capitalizeFirstLetter($html);
        $html = $this->enforceStrongTags($html, $keyword, $maxStrong);
        $html = $this->enforceUnderlineTags($html);
        $html = $this->removeConclusionHeadings($html, $lang, $writerAI);
        $html = $this->limitH2Count($html);
        $html = $this->enforceKeywordInH2($html, $keyword, $lang, $writerAI);
        $html = $this->splitLongParagraphs($html);
        $html = $this->enforceHeadingLength($html);
        $html = $this->capitalizeHeadings($html);
        
        $html = $this->ensureElements($html, $keyword, $lang, $siteFullContext, $writerAI);
        
        $html = $this->ensureMinimumLength($html, $keyword, $minWords, $lang, $siteFullContext, $writerAI);
        $html = $this->boostKeywordDensity($html, $keyword, $targetKeywordCount);
        $html = $this->cleanHtml($html);
        return $html;
    }
    
    private function fixFirstElement($html, $keyword, $lang, $siteContext, $ai) {
        if (!preg_match('/^(.*?<section>\s*)\s*<(h[23])/is', $html)) return $html;
        
        $prompt = "Write ONE intro paragraph (40-55 words) in {$lang} about \"{$keyword}\" for a website with context: {$siteContext}. 
RULES:
- Start with <strong>{$keyword}</strong> placed naturally in the first sentence.
- The FIRST LETTER of the paragraph MUST be UPPERCASE.
- Write from the business perspective using 'we/our' where appropriate.
- Format: <p>text</p>. 
Return ONLY this HTML line. Do not write any conversational text.";
        $messages = [['role' => 'user', 'content' => $prompt]];
        $intro = $ai->chat($messages, 0.7, false);
        $intro = trim(preg_replace('/```html\s*|\s*
```/i', '', $intro));
        
        $firstP = stripos($intro, '<p');
        if ($firstP !== false) {
            $intro = substr($intro, $firstP);
        }
        
        if (stripos($intro, '<p>') === false) $intro = '<p>' . $intro . '</p>';
        return preg_replace('/^(.*?<section>\s*)/is', '$1' . "\n" . $intro . "\n", $html);
    }
    
    private function capitalizeFirstLetter($html) {
        $done = false;
        $html = preg_replace_callback('/<p>(\s*)([a-z\x{00E7}\x{011F}\x{0131}\x{00F6}\x{015F}\x{00FC}\x{00E2}\x{00EA}\x{00EE}\x{00F4}\x{00FB}\x{00E4}\x{00EB}\x{00EF}])/iu', function($m) use (&$done) {
            if ($done) return $m[0];
            $done = true;
            return '<p>' . $m[1] . mb_strtoupper($m[2], 'UTF-8');
        }, $html);
        
        if (!$done) {
            $html = preg_replace_callback('/<p>(\s*<strong>)([a-z\x{00E7}\x{011F}\x{0131}\x{00F6}\x{015F}\x{00FC}])/iu', function($m) use (&$done) {
                if ($done) return $m[0];
                $done = true;
                return '<p>' . $m[1] . mb_strtoupper($m[2], 'UTF-8');
            }, $html);
        }
        
        return $html;
    }
    
    private function capitalizeHeadings($html) {
        return preg_replace_callback('/<(h[23])>(.*?)<\/\1>/is', function($m) {
            $tag = $m[1];
            $content = $m[2];
            $plainText = strip_tags($content);
            
            if (stripos($content, '<strong>') !== false) {
                $content = preg_replace_callback('/<strong>(.*?)<\/strong>/is', function($sm) {
                    return '<strong>' . mb_convert_case($sm[1], MB_CASE_TITLE, 'UTF-8') . '</strong>';
                }, $content);
                $content = preg_replace_callback('/(?:^|>)([^<]+)(?:<|$)/u', function($tm) {
                    return str_replace($tm[1], mb_convert_case($tm[1], MB_CASE_TITLE, 'UTF-8'), $tm[0]);
                }, $content);
            } else {
                $content = mb_convert_case($content, MB_CASE_TITLE, 'UTF-8');
            }
            
            return "<{$tag}>{$content}</{$tag}>";
        }, $html);
    }
    
    private function enforceStrongTags($html, $keyword, $target) {
        $current = substr_count(strtolower($html), '<strong>' . strtolower($keyword) . '</strong>');
        if ($current >= $target) return $html;
        $needed = $target - $current;
        $pattern = '/(?<!<strong>)(' . preg_quote($keyword, '/') . ')(?!<\/strong>)/i';
        return preg_replace_callback($pattern, function($m) use (&$needed) {
            if ($needed <= 0) return $m[0];
            $needed--;
            return '<strong>' . $m[1] . '</strong>';
        }, $html, $needed);
    }
    
    private function enforceUnderlineTags($html) {
        $uCount = substr_count(strtolower($html), '<u>');
        if ($uCount >= 3) return $html;
        $needed = 3 - $uCount;
        $patterns = [
            '/\b(\d{1,3}[.,]?\d*\s*(?:%|procent|percent|jaar|year|keer|times|euro|\x{20AC}|dollar|\$|km|kilometer|miles?|maand|month|week|weken|dag|day|y\x{0131}l|ay|hafta|g\x{00FC}n))\b/iu',
            '/\b(meer dan \d+|minder dan \d+|gemiddeld \d+|minstens \d+|ongeveer \d+|more than \d+|less than \d+|at least \d+|approximately \d+|ortalama \d+|en az \d+|yakla\x{015F}\x{0131}k \d+)\b/iu',
        ];
        foreach ($patterns as $p) {
            if ($needed <= 0) break;
            $html = preg_replace_callback($p, function($m) use (&$needed) {
                if ($needed <= 0 || stripos($m[0], '<u>') !== false) return $m[0];
                $needed--;
                return '<u>' . $m[0] . '</u>';
            }, $html, 1);
        }
        return $html;
    }
    
    private function removeConclusionHeadings($html, $lang, $ai) {
        $prompt = "List all words in {$lang} that mean \"conclusion\", \"summary\", \"in short\", \"finally\", or \"tips\" (like Tot slot, Conclusie, Samenvatting, Tips in Dutch). Format: comma separated single words only.";
        $messages = [['role' => 'user', 'content' => $prompt]];
        $wordsStr = $ai->chat($messages, 0.3, false);
        $words = array_map('trim', explode(',', strtolower($wordsStr)));
        $words = array_filter($words, fn($w) => strlen($w) > 2);
        
        foreach ($words as $word) {
            $html = preg_replace('/<h[23]>[^<]*\b' . preg_quote($word, '/') . '\b[^<]*<\/h[23]>/iu', '', $html);
            $html = preg_replace('/(<p[^>]*>\s*)' . preg_quote($word, '/') . '[\s:]+/iu', '$1', $html);
        }
        return $html;
    }
    
    private function limitH2Count($html) {
        $agentName = $this->gemini->getAgentPreference('writer') === 'deepseek' ? 'DeepSeek' : 'Gemini';
        preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $html, $matches, PREG_OFFSET_CAPTURE);
        $h2Count = count($matches[0]);
        
        if ($h2Count <= 6) return $html;
        
        debug_log("[API: {$agentName}] H2 LIMIT: {$h2Count} H2 bulundu, fazla olanlar H3'e cevriliyor");
        
        for ($i = $h2Count - 1; $i >= 6; $i--) {
            $fullMatch = $matches[0][$i][0];
            $replacement = str_replace(['<h2', '</h2>'], ['<h3', '</h3>'], $fullMatch);
            $pos = $matches[0][$i][1];
            $html = substr_replace($html, $replacement, $pos, strlen($fullMatch));
        }
        
        return $html;
    }
    
    private function enforceKeywordInH2($html, $keyword, $lang, $ai) {
        $agentName = $this->gemini->getAgentPreference('writer') === 'deepseek' ? 'DeepSeek' : 'Gemini';
        preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $html, $matches);
        
        $keywordFound = false;
        foreach (($matches[1] ?? []) as $h2Text) {
            if (stripos(strip_tags($h2Text), $keyword) !== false) {
                $keywordFound = true;
                break;
            }
        }
        
        if ($keywordFound) return $html;
        
        debug_log("[API: {$agentName}] H2 KEYWORD: Keyword H2'de bulunamadi, ekleniyor");
        
        if (!empty($matches[0])) {
            $targetIdx = min(1, count($matches[0]) - 1);
            $oldH2 = $matches[0][$targetIdx];
            $oldH2Text = strip_tags($matches[1][$targetIdx]);
            
            $prompt = "Rewrite this heading to naturally include the keyword \"{$keyword}\": \"{$oldH2Text}\". Language: {$lang}. The heading MUST be between 20-60 characters. Return ONLY the heading text, no HTML tags, no quotes.";
            $messages = [['role' => 'user', 'content' => $prompt]];
            $newText = trim($ai->chat($messages, 0.5, false));
            $newText = strip_tags($newText);
            $newText = trim($newText, '"\'');
            
            $len = mb_strlen($newText, 'UTF-8');
            if ($len >= 15 && $len <= 65 && stripos($newText, $keyword) !== false) {
                $newH2 = '<h2>' . htmlspecialchars($newText) . '</h2>';
                $html = str_replace($oldH2, $newH2, $html);
            }
        }
        
        return $html;
    }
    
    private function splitLongParagraphs($html) {
        return preg_replace_callback('/<p>(.*?)<\/p>/is', function($m) {
            $content = $m[1];
            $plainText = strip_tags($content);
            $wordCount = str_word_count($plainText);
            
            if ($wordCount <= 80) return $m[0];
            
            $sentences = preg_split('/(?<=[.!?])\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY);
            if (count($sentences) < 2) return $m[0];
            
            $chunks = [];
            $currentChunk = '';
            $currentWordCount = 0;
            
            foreach ($sentences as $sentence) {
                $sentenceWords = str_word_count(strip_tags($sentence));
                if ($currentWordCount + $sentenceWords > 60 && !empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = $sentence;
                    $currentWordCount = $sentenceWords;
                } else {
                    $currentChunk .= ' ' . $sentence;
                    $currentWordCount += $sentenceWords;
                }
            }
            if (!empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
            }
            
            $newHtml = '';
            foreach ($chunks as $chunk) {
                if (!empty(trim(strip_tags($chunk)))) {
                    $newHtml .= '<p>' . $chunk . '</p>' . "\n";
                }
            }
            return $newHtml;
        }, $html);
    }
    
    private function enforceHeadingLength($html) {
        return preg_replace_callback('/<h2>(.*?)<\/h2>/is', function($m) {
            $text = strip_tags($m[1]);
            $len = mb_strlen($text, 'UTF-8');
            
            if ($len > 60) {
                $trimmed = mb_substr($text, 0, 57, 'UTF-8');
                $lastSpace = mb_strrpos($trimmed, ' ', 0, 'UTF-8');
                if ($lastSpace !== false && $lastSpace > 20) {
                    $text = mb_substr($text, 0, $lastSpace, 'UTF-8');
                }
                return '<h2>' . $text . '</h2>';
            }
            
            return $m[0];
        }, $html);
    }
    
    private function boostKeywordDensity($html, $keyword, $targetCount) {
        $agentName = $this->gemini->getAgentPreference('writer') === 'deepseek' ? 'DeepSeek' : 'Gemini';
        $text = strip_tags($html);
        $currentCount = substr_count(strtolower($text), strtolower($keyword));
        
        if ($currentCount >= $targetCount) return $html;
        
        $needed = $targetCount - $currentCount;
        debug_log("[API: {$agentName}] KEYWORD BOOST: Mevcut={$currentCount}, Hedef={$targetCount}, Eklenecek={$needed}");
        
        $html = preg_replace_callback('/<p>(.*?)<\/p>/is', function($m) use ($keyword, &$needed) {
            if ($needed <= 0) return $m[0];
            
            $content = $m[1];
            $plainText = strip_tags($content);
            $wordCount = str_word_count($plainText);
            
            if (stripos($plainText, $keyword) !== false) return $m[0];
            if ($wordCount < 40) return $m[0];
            
            $sentences = preg_split('/(?<=[.!?])\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY);
            if (count($sentences) < 2) return $m[0];
            
            $midIdx = (int)floor(count($sentences) / 2);
            $sentences[$midIdx] = rtrim($sentences[$midIdx], '.!? ');
            
            $detectedLang = 'nl';
            $langWords = [
                'nl' => ['het','van','een','voor','met'],
                'tr' => ['bir','olan','bu'],
                'en' => ['the','and','for','with','this'],
                'de' => ['der','die','das','und'],
                'fr' => ['les','des','une','pour','avec']
            ];
            foreach ($langWords as $l => $words) {
                foreach ($words as $w) {
                    if (stripos($plainText, " $w ") !== false) {
                        $detectedLang = $l;
                        break 2;
                    }
                }
            }
            
            $connectors = [
                'nl' => [
                    ", vooral als het gaat om <strong>{$keyword}</strong>.",
                    ", zeker in de wereld van <strong>{$keyword}</strong>.",
                    ", wat cruciaal is bij <strong>{$keyword}</strong>."
                ],
                'tr' => [
                    ", ozellikle <strong>{$keyword}</strong> konusunda.",
                    ", <strong>{$keyword}</strong> acisindan onemlidir.",
                    ", <strong>{$keyword}</strong> soz konusu oldugunda."
                ],
                'en' => [
                    ", especially when it comes to <strong>{$keyword}</strong>.",
                    ", particularly in the context of <strong>{$keyword}</strong>.",
                    ", which is essential for <strong>{$keyword}</strong>."
                ],
                'de' => [
                    ", besonders wenn es um <strong>{$keyword}</strong> geht.",
                    ", vor allem im Bereich <strong>{$keyword}</strong>.",
                    ", was bei <strong>{$keyword}</strong> entscheidend ist."
                ],
                'fr' => [
                    ", surtout en ce qui concerne <strong>{$keyword}</strong>.",
                    ", notamment dans le domaine de <strong>{$keyword}</strong>.",
                    ", ce qui est essentiel pour <strong>{$keyword}</strong>."
                ],
            ];
            
            $langConnectors = $connectors[$detectedLang] ?? $connectors['nl'];
            $connector = $langConnectors[array_rand($langConnectors)];
            $sentences[$midIdx] .= $connector;
            
            $needed--;
            return '<p>' . implode(' ', $sentences) . '</p>';
        }, $html);
        
        return $html;
    }
    
    private function ensureElements($html, $keyword, $lang, $siteContext, $ai) {
        $closing = '</section>';
        $hasClosing = (stripos($html, $closing) !== false);
        if ($hasClosing) $html = str_replace($closing, '', $html);
        
        $missing = [];
        if (!$this->hasCTA($html, $lang, $ai)) $missing[] = 'cta';
        
        if (!empty($missing)) {
            $prompt = "Generate a natural CTA paragraph for an article about \"{$keyword}\" in {$lang}. Site context: {$siteContext}.
Write from the business perspective - encourage the reader to contact or engage with the business.
Return ONLY the HTML <p> tag. Do not write conversational text.";
            $messages = [['role' => 'user', 'content' => $prompt]];
            $gen = $ai->chat($messages, 0.7, false);
            $gen = preg_replace('/```html\s*|\s*```/i', '', trim($gen));
            
            if (preg_match('/<(p|h3)\b[^>]*>/i', $gen, $m, PREG_OFFSET_CAPTURE)) {
                $gen = substr($gen, $m[0][1]);
            }
            
            $html .= "\n" . trim($gen);
        }
        return $hasClosing ? $html . "\n</section>" : $html;
    }
    
    private function hasCTA($html, $lang, $ai) {
        static $ctaCache = [];
        if (!isset($ctaCache[$lang])) {
            $prompt = "List 10 common call-to-action trigger words/phrases in {$lang} (like 'contact', 'call now', 'get a quote'). Format: comma separated lowercase words only.";
            $messages = [['role' => 'user', 'content' => $prompt]];
            $result = $ai->chat($messages, 0.3, false);
            $ctaCache[$lang] = array_map('trim', explode(',', strtolower($result)));
        }
        $stripped = strip_tags(strtolower($html));
        foreach ($ctaCache[$lang] as $word) {
            if (strlen($word) > 3 && stripos($stripped, $word) !== false) return true;
        }
        return false;
    }
    
    private function ensureMinimumLength($html, $keyword, $minWords, $lang, $siteContext, $ai) {
        $wc = str_word_count(strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8')));
        if ($wc >= $minWords) return $html;
        $closing = '</section>';
        $html = str_replace($closing, '', $html);
        $prompt = "Expand this article about \"{$keyword}\" to {$minWords}+ words. Keep all HTML structure. Write in {$lang}. Write from the business perspective. Return COMPLETE HTML. Do not write conversational text.\n\n{$html}";
        $messages = [['role' => 'user', 'content' => $prompt]];
        $expanded = $ai->chat($messages, 0.7, false);
        $expanded = preg_replace('/```html\s*|\s*```/i', '', trim($expanded));
        
        if (preg_match('/<(section|p|h2|h3|ul|table|blockquote)\b[^>]*>/i', $expanded, $m, PREG_OFFSET_CAPTURE)) {
            $expanded = substr($expanded, $m[0][1]);
        }
        
        $expanded = preg_replace('/<p[^>]*>[^<]*$/i', '', $expanded);
        $expanded = preg_replace('/<h[23][^>]*>[^<]*$/i', '', $expanded);
        
        return (str_word_count(strip_tags($expanded)) > $wc) ? $expanded . "\n</section>" : $html . "\n</section>";
    }
    
    private function cleanHtml($html) {
        $html = preg_replace('/<h1>/i', '<h2>', $html);
        $html = preg_replace('/<\/h1>/i', '</h2>', $html);
        $html = preg_replace('/<a\s+href="https?:\/\/[^"]+"/i', '', $html);
        $html = preg_replace('/<a\s*>\s*<\/a>/i', '', $html);
        $html = preg_replace('/<h[23]>\s*<\/h[23]>/i', '', $html);
        
        $html = preg_replace('/<\/?(?:html|head|body|title|meta|main|article)[^>]*>/i', '', $html);
        
        return $html;
    }
}