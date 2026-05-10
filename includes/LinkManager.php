<?php
require_once __DIR__ . '/../log.php';

class LinkManager {
    private $pdo;
    private $ai;

    public function __construct($pdo, $ai = null) {
        $this->pdo = $pdo;
        $this->ai = $ai;
    }

    public function getSmartLinks($websiteId, $articleContent, $planId = null, $limit = 3) {
        debug_log("LinkManager: AI SMART LINK START", ['website_id' => $websiteId, 'plan_id' => $planId]);

        if (!$this->ai) {
            debug_log("LinkManager: AI objesi bulunamadi!");
            return [];
        }

        // Makalenin Odak Anahtar Kelimesini Veritabanından Öğren
        $focusKeyword = '';
        if ($planId) {
            $stmt = $this->pdo->prepare("SELECT focus_keyword FROM content_plans WHERE id = ?");
            $stmt->execute([$planId]);
            $focusKeyword = $stmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare("SELECT url, title FROM crawled_pages WHERE website_id = ?");
        $stmt->execute([$websiteId]);
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pages)) {
            debug_log("LinkManager: Sitede taranmis URL bulunamadi.");
            return [];
        }

        $availableUrls = [];
        $validUrlsMap = []; 
        foreach ($pages as $p) {
            $u = rtrim($p['url'], '/');
            $availableUrls[] = "- URL: " . $u . " | BASLIK: " . ($p['title'] ?? 'Bilinmiyor');
            $validUrlsMap[$u] = true;
        }
        $urlsListStr = implode("\n", $availableUrls);

        $plainText = strip_tags(html_entity_decode($articleContent, ENT_QUOTES, 'UTF-8'));
        $plainText = preg_replace('/\s+/u', ' ', $plainText);
        $plainText = mb_substr($plainText, 0, 6000, 'UTF-8'); 

        $prompt = "Sen uzman bir SEO ve İçerik yöneticisisin. Sana bir makale metni ve kullanabileceğin hedef URL'lerin bir listesini vereceğim.
Görevin: Makalenin anlamsal bağlamını okuyarak, makale içinden DOĞAL ve TAM eşleşen kelime öbekleri (anchor text) seçmek ve bunları listedeki en alakalı URL'ler ile eşleştirmektir.

BU MAKALENİN ODAK ANAHTAR KELİMESİ: \"{$focusKeyword}\"

HEDEF URL LISTESI:
{$urlsListStr}

MAKALE METNI:
{$plainText}

KURALLAR:
1. SADECE makale metninde birebir geçen kelime öbeklerini 'anchor' olarak kullan.
2. Maksimum {$limit} adet anlamsal olarak en güçlü eşleşmeyi bul.
3. KESİN KURAL: 'zonder gedoe', 'snel', 'klik hier', 'vraag', 'antwoord', 'hier' gibi jenerik sıfatlar, zarflar veya soru kelimelerini ASLA anchor text olarak kullanma!
4. Anchor text DAİMA 'tweedehands auto verkopen', 'autowaardebepaling' gibi net isimler (noun), sektörel terimler veya spesifik hizmet adlarından oluşmalıdır.
5. SEO İÇİN KRİTİK KURAL: Makalenin kendi odak anahtar kelimesi olan \"{$focusKeyword}\" kelimesini veya buna çok benzeyen varyasyonlarını KESİNLİKLE anchor text olarak KULLANMA! Kendi anahtar kelimesiyle başka sayfaya link vermek SEO'yu mahveder. 
6. Çıktın KESİNLİKLE geçerli bir JSON dizisi (array of objects) olmalıdır.

JSON FORMATI ŞU ŞEKİLDE OLMALI:
[
  {
    \"anchor\": \"makaleden secilen tam kelime obegi\",
    \"url\": \"eslesen_hedef_url\"
  }
]";

        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        try {
            debug_log("LinkManager: AI'a link bulmasi icin istek atiliyor...");
            $jsonResponse = $this->ai->chat($messages, 0.3, true);
            
            $cleanJson = preg_replace('/```json\s*|\s*```/i', '', trim($jsonResponse));
            $linksData = json_decode($cleanJson, true);

            if (is_array($linksData)) {
                $finalLinks = [];
                $usedUrls = [];
                
                foreach ($linksData as $item) {
                    if (count($finalLinks) >= $limit) break;
                    
                    $anchor = trim($item['anchor'] ?? '');
                    $url = rtrim($item['url'] ?? '', '/');
                    
                    // Anahtar kelimenin birebir linklenmesini son bir güvenlik duvarıyla engelle
                    $isFocusKeyword = (!empty($focusKeyword) && stripos($anchor, $focusKeyword) !== false);

                    if (!empty($anchor) && !empty($url) && isset($validUrlsMap[$url]) && !in_array($url, $usedUrls) && !$isFocusKeyword) {
                        $finalLinks[] = [
                            'keyword' => $anchor,
                            'url' => $url
                        ];
                        $usedUrls[] = $url;
                    }
                }
                
                debug_log("LinkManager: AI basariyla " . count($finalLinks) . " link buldu.", $finalLinks);
                return $finalLinks;
            } else {
                debug_log("LinkManager: AI gecersiz JSON dondu.", ['raw' => $jsonResponse]);
            }
        } catch (Exception $e) {
            debug_log("LinkManager AI Hata: " . $e->getMessage());
        }

        return [];
    }

    public function injectLinks($html, $links, $ownSlug = null) {
        if (empty($links)) {
            debug_log("LinkManager: INJECT - eklenecek link yok");
            return $html;
        }

        $html = preg_replace('/<a\s+href=["\'][^"\']*["\'][^>]*>(.*?)<\/a>/is', '$1', $html);

        preg_match_all('/<(p|li)\b[^>]*>(.*?)<\/\1>/is', $html, $elements, PREG_SET_ORDER);
        $totalElements = count($elements);

        if ($totalElements < 2) {
            debug_log("LinkManager: INJECT - yeterli metin blogu yok");
            return $html;
        }

        $added = 0;
        $usedPositions = [];

        foreach ($links as $link) {
            if ($ownSlug && stripos($link['url'], $ownSlug) !== false) continue;

            $anchor = $this->normalizeAnchor($link['keyword']);
            $pattern = $this->anchorRegex($anchor);

            for ($p = 0; $p < $totalElements; $p++) {
                if (isset($usedPositions[$p])) continue;
                
                $tag = $elements[$p][1]; // p veya li
                $pc = $elements[$p][2];  // içeriği

                $strongPattern = '/<strong\b[^>]*>\s*' . $pattern . '\s*<\/strong>/iu';
                if (preg_match($strongPattern, $pc, $m)) {
                    $newPara = preg_replace(
                        $strongPattern,
                        '<a href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '"><strong>' . $m[1] . '</strong></a>',
                        $pc,
                        1
                    );
                    if ($newPara !== $pc) {
                        $html = $this->replaceFirst($html, $elements[$p][0], '<' . $tag . '>' . $newPara . '</' . $tag . '>');
                        $added++;
                        $usedPositions[$p] = true;
                        break;
                    }
                }

                $textPattern = '/' . $pattern . '(?![^<]*>)/iu';
                if (preg_match($textPattern, $pc, $m)) {
                    $newPara = preg_replace(
                        $textPattern,
                        '<a href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '">' . $m[1] . '</a>',
                        $pc,
                        1
                    );
                    if ($newPara !== $pc) {
                        $html = $this->replaceFirst($html, $elements[$p][0], '<' . $tag . '>' . $newPara . '</' . $tag . '>');
                        $added++;
                        $usedPositions[$p] = true;
                        break;
                    }
                }
            }
        }

        debug_log("LinkManager: INJECT DONE", ['added' => $added, 'total_links' => count($links)]);
        return $html;
    }

    private function anchorRegex($anchor) {
        $parts = preg_split('/[\s\-]+/u', $anchor);
        $parts = array_values(array_filter($parts, fn($part) => $part !== ''));
        $quotedParts = array_map(fn($part) => preg_quote($part, '/'), $parts);
        $phrase = implode('[\s\-]+', $quotedParts);
        return '(?<![\p{L}\p{N}])(' . $phrase . ')(?![\p{L}\p{N}])';
    }

    private function replaceFirst($subject, $search, $replace) {
        $pos = strpos($subject, $search);
        if ($pos === false) return $subject;
        return substr_replace($subject, $replace, $pos, strlen($search));
    }

    private function normalizeAnchor($text) {
        $text = html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($this->lower($text));
    }

    private function lower($text) {
        return function_exists('mb_strtolower') ? mb_strtolower((string)$text, 'UTF-8') : strtolower((string)$text);
    }
}