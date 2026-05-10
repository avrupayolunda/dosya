<?php
class AI {
    private $gemini_key;
    private $deepseek_key;
    private $gemini_model;
    private $deepseek_model;
    private $pdo;
    private $research_agent;
    private $writer_agent;
    private $critic_agent;
    private $plan_creator_agent;
    private $plan_critic_agent;
    
    // YENİ: Hangi ajanın aktif olduğunu bilmek için kimlik değişkeni
    public $current_agent = null; 
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }
    
    // YENİ: Ajanın kimliğini belirleyen fonksiyon
    public function setAgent($agent) {
        $this->current_agent = $agent;
    }
    
    private function loadSettings() {
        $stmt = $this->pdo->query("SELECT gemini_api_key, deepseek_api_key, gemini_model, deepseek_model, research_agent, writer_agent, critic_agent, plan_creator_agent, plan_critic_agent FROM ai_settings WHERE id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->gemini_key = $settings['gemini_api_key'] ?? '';
        $this->deepseek_key = $settings['deepseek_api_key'] ?? '';
        $this->gemini_model = $settings['gemini_model'] ?? 'gemini-2.0-flash';
        $this->deepseek_model = $settings['deepseek_model'] ?? 'deepseek-chat';
        $this->research_agent = $settings['research_agent'] ?? 'deepseek';
        $this->writer_agent = $settings['writer_agent'] ?? 'deepseek';
        $this->critic_agent = $settings['critic_agent'] ?? 'gemini';
        $this->plan_creator_agent = $settings['plan_creator_agent'] ?? 'gemini';
        $this->plan_critic_agent = $settings['plan_critic_agent'] ?? 'deepseek';
    }
    
    public function getAgentPreference($role) {
        if ($role === 'research') return $this->research_agent;
        if ($role === 'writer') return $this->writer_agent;
        if ($role === 'critic') return $this->critic_agent;
        if ($role === 'plan_creator') return $this->plan_creator_agent;
        if ($role === 'plan_critic') return $this->plan_critic_agent;
        return 'gemini';
    }
    
    public function chatWithAgent($messages, $temperature = 0.8, $json_mode = false, $agent = 'gemini') {
        if ($agent === 'deepseek' && !empty($this->deepseek_key)) {
            return $this->callDeepSeek($messages, $temperature, $json_mode);
        } elseif ($agent === 'gemini' && !empty($this->gemini_key)) {
            return $this->callGemini($messages, $temperature, $json_mode);
        }
        
        if (!empty($this->deepseek_key)) {
            return $this->callDeepSeek($messages, $temperature, $json_mode);
        } elseif (!empty($this->gemini_key)) {
            return $this->callGemini($messages, $temperature, $json_mode);
        }
        
        throw new Exception('Hiçbir API anahtarı yapılandırılmamış');
    }
    
    // GÜNCELLENDİ: Artık DeepSeek'i körü körüne zorlamaz, kimliği kontrol eder
    public function chat($messages, $temperature = 0.8, $json_mode = false) {
        // Eğer Gemini ajanı olarak ayarlandıysa ve key varsa Gemini'ye git
        if ($this->current_agent === 'gemini' && !empty($this->gemini_key)) {
            return $this->callGemini($messages, $temperature, $json_mode);
        }
        // Eğer DeepSeek ajanı olarak ayarlandıysa ve key varsa DeepSeek'e git
        if ($this->current_agent === 'deepseek' && !empty($this->deepseek_key)) {
            return $this->callDeepSeek($messages, $temperature, $json_mode);
        }
        
        // Kimlik atanmamışsa (Fallback - eski mantık)
        if (!empty($this->deepseek_key)) {
            return $this->callDeepSeek($messages, $temperature, $json_mode);
        } elseif (!empty($this->gemini_key)) {
            return $this->callGemini($messages, $temperature, $json_mode);
        }
        
        throw new Exception('Hiçbir API anahtarı yapılandırılmamış');
    }
    
    public function generateImage($prompt, $size = '1024x1024') {
        if (!empty($this->deepseek_key)) {
            return $this->generateImageDeepSeek($prompt, $size);
        } elseif (!empty($this->gemini_key)) {
            return $this->generateImageGemini($prompt, $size);
        }
        throw new Exception('Resim oluşturmak için API anahtarı bulunamadı');
    }
    
    private function generateImageDeepSeek($prompt, $size = '1024x1024') {
        if (empty($this->deepseek_key)) {
            throw new Exception('DeepSeek API anahtarı bulunamadı');
        }
        
        $ch = curl_init('https://api.deepseek.com/v1/images/generations');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->deepseek_key
        ]);
        
        $payload = [
            'model' => 'deepseek-janus-pro-7b',
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('DeepSeek Resim Hatası: ' . $error);
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            if (!empty($this->gemini_key)) {
                return $this->generateImageGemini($prompt, $size);
            }
            throw new Exception('DeepSeek Resim API Hatası HTTP ' . $httpCode . ': ' . substr($response, 0, 300));
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['data'][0]['url'])) {
            return $data['data'][0]['url'];
        } elseif (isset($data['data'][0]['b64_json'])) {
            return 'data:image/png;base64,' . $data['data'][0]['b64_json'];
        }
        
        throw new Exception('DeepSeek resim yanıtı beklenmeyen formatta');
    }
    
    private function generateImageGemini($prompt, $size = '1024x1024') {
        if (empty($this->gemini_key)) {
            throw new Exception('Gemini API anahtarı bulunamadı');
        }
        
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent?key=' . $this->gemini_key;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Generate an image: ' . $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE']
            ]
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Gemini Resim Hatası: ' . $error);
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Gemini Resim API Hatası HTTP ' . $httpCode . ': ' . substr($response, 0, 300));
        }
        
        $data = json_decode($response, true);
        
        // Gemini yanıtından görsel verisini çıkar
        $candidates = $data['candidates'] ?? [];
        if (!empty($candidates)) {
            $parts = $candidates[0]['content']['parts'] ?? [];
            foreach ($parts as $part) {
                if (isset($part['inlineData'])) {
                    $mimeType = $part['inlineData']['mimeType'] ?? 'image/png';
                    $base64 = $part['inlineData']['data'] ?? '';
                    if (!empty($base64)) {
                        return 'data:' . $mimeType . ';base64,' . $base64;
                    }
                }
            }
        }
        
        throw new Exception('Gemini resim yanıtı beklenmeyen formatta');
    }
    
    private function sanitizeMessages($messages) {
        foreach ($messages as &$msg) {
            $msg['content'] = str_replace("\x00", '', $msg['content']);
            $msg['content'] = mb_convert_encoding($msg['content'], 'UTF-8', 'UTF-8');
            $msg['content'] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $msg['content']);
        }
        return $messages;
    }
    
    private function callGemini($messages, $temperature, $json_mode) {
        if (empty($this->gemini_key)) {
            throw new Exception('Gemini API anahtarı bulunamadı');
        }

        $messages = $this->sanitizeMessages($messages);
        
        // OpenAI mesaj formatını Gemini formatına dönüştür
        $systemInstruction = null;
        $geminiContents = [];
        
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            
            if ($role === 'system') {
                $systemInstruction = ['parts' => [['text' => $content]]];
            } elseif ($role === 'assistant') {
                $geminiContents[] = [
                    'role' => 'model',
                    'parts' => [['text' => $content]]
                ];
            } else {
                $geminiContents[] = [
                    'role' => 'user',
                    'parts' => [['text' => $content]]
                ];
            }
        }
        
        // En az bir content olmalı
        if (empty($geminiContents)) {
            throw new Exception('Gemini: Mesaj listesi boş');
        }
        
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->gemini_model . ':generateContent?key=' . $this->gemini_key;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $payload = [
            'contents' => $geminiContents,
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => 8192
            ]
        ];
        
        if ($systemInstruction) {
            $payload['systemInstruction'] = $systemInstruction;
        }
        
        if ($json_mode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
            // Eğer system instruction yoksa JSON modu için ekle
            if (!$systemInstruction) {
                $payload['systemInstruction'] = [
                    'parts' => [['text' => 'You are a helpful assistant. Always respond in valid JSON.']]
                ];
            }
        }
        
        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonBody === false) {
            throw new Exception('Gemini JSON encode hatası: ' . json_last_error_msg());
        }
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Gemini Curl Hatası: ' . $error);
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Gemini API Hatası HTTP ' . $httpCode . ': ' . substr($response, 0, 500));
        }
        
        $data = json_decode($response, true);
        
        // Gemini yanıt formatı: candidates[0].content.parts[0].text
        $candidates = $data['candidates'] ?? [];
        if (!empty($candidates) && isset($candidates[0]['content']['parts'])) {
            $parts = $candidates[0]['content']['parts'];
            $textParts = [];
            foreach ($parts as $part) {
                if (isset($part['text'])) {
                    $textParts[] = $part['text'];
                }
            }
            return implode('', $textParts);
        }
        
        // Hata durumunda boş metin veya hata fırlat
        $blockReason = $data['promptFeedback']['blockReason'] ?? null;
        if ($blockReason) {
            throw new Exception('Gemini içerik engellendi: ' . $blockReason);
        }
        
        return '';
    }
    
    private function callDeepSeek($messages, $temperature, $json_mode) {
        if (empty($this->deepseek_key)) {
            throw new Exception('DeepSeek API anahtarı bulunamadı');
        }
        
        $messages = $this->sanitizeMessages($messages);
        
        $totalChars = 0;
        foreach ($messages as $msg) {
            $totalChars += strlen($msg['content'] ?? '');
        }
        
        $maxTokens = ($totalChars > 15000) ? 4000 : 8000;
        
        $payload = [
            'model' => $this->deepseek_model, 
            'messages' => $messages, 
            'temperature' => $temperature, 
            'max_tokens' => $maxTokens
        ];
        if ($json_mode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        
        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonBody === false) {
            throw new Exception('DeepSeek JSON encode hatası: ' . json_last_error_msg());
        }
        
        $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json', 
            'Authorization: Bearer ' . $this->deepseek_key
        ]);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('DeepSeek Curl Hatası: ' . $error);
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $errorBody = substr($response, 0, 500);
            
            if ($httpCode === 400 && $totalChars > 8000) {
                $lastIdx = count($messages) - 1;
                $originalLen = strlen($messages[$lastIdx]['content']);
                $messages[$lastIdx]['content'] = substr($messages[$lastIdx]['content'], 0, (int)($originalLen * 0.6));
                $messages = $this->sanitizeMessages($messages);
                
                $payload['messages'] = $messages;
                $payload['max_tokens'] = 4000;
                unset($payload['response_format']);
                
                $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($jsonBody === false) {
                    throw new Exception('DeepSeek JSON encode hatası (2. deneme): ' . json_last_error_msg());
                }
                
                $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json', 
                    'Authorization: Bearer ' . $this->deepseek_key
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $data = json_decode($response, true);
                    return $data['choices'][0]['message']['content'] ?? '';
                }
                
                $errorBody = substr($response, 0, 500);
            }
            
            throw new Exception('DeepSeek API Hatası: ' . $httpCode . ' - ' . $errorBody);
        }
        
        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }
    
    public function hasValidApiKey() {
        return !empty($this->gemini_key) || !empty($this->deepseek_key);
    }
    
    public function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        return strtolower(trim($text, '-'));
    }
    
    public function getLanguageName($code) {
        $map = ['tr'=>'Turkce','en'=>'English','nl'=>'Nederlands','de'=>'Deutsch','fr'=>'Francais'];
        return $map[$code] ?? 'English';
    }
}
