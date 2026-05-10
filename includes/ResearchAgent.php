<?php
require_once __DIR__ . '/../log.php';

class ResearchAgent {
    private $ai;
    private $pdo;
    
    public function __construct($ai, $pdo) {
        $this->ai = $ai;
        $this->pdo = $pdo;
    }
    
    // GÜNCELLENDİ: Hangi ajanın araştırma (research) yapacağını ayarlardan okuruz
    private function getAgent() {
        if (method_exists($this->ai, 'getAgentPreference')) {
            $pref = $this->ai->getAgentPreference('research');
            // Eğer $this->ai nesnesi içinde agent değiştirecek yapı varsa, onu kullanıyoruz
            $this->ai->setAgent($pref); 
        }
        return $this->ai;
    }
    
    public function research($keyword) {
        debug_log("ResearchAgent araştırma: {$keyword}");
        
        $prompt = "Research the topic: {$keyword}

Provide a comprehensive research report with:
1. Key facts and statistics
2. Main subtopics to cover
3. Important terminology
4. Common questions people ask
5. Related keywords and phrases

Format your response as JSON:
{
  \"facts\": [\"fact 1\", \"fact 2\"],
  \"subtopics\": [\"subtopic 1\", \"subtopic 2\"],
  \"terminology\": [\"term 1\", \"term 2\"],
  \"questions\": [\"question 1\", \"question 2\"],
  \"keywords\": [\"keyword 1\", \"keyword 2\"]
}

Return ONLY valid JSON.";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are an expert researcher. Provide detailed, factual research in JSON format.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            // Araştırmayı yapan AI'yi tercih edilen ajana göre ayarla
            $researcherAI = $this->getAgent();
            $result = $researcherAI->chat($messages, 0.7, true);
            
            $clean = preg_replace('/```json\s*|\s*```/i', '', $result);
            $clean = trim($clean);
            
            if (($pos = strpos($clean, '{')) !== false) {
                $clean = substr($clean, $pos);
            }
            
            $data = json_decode($clean, true);
            
            if (is_array($data)) {
                return $data;
            }
        } catch (Exception $e) {
            debug_log("ResearchAgent hata: " . $e->getMessage());
        }
        
        return [
            'facts' => ["Research about: $keyword"],
            'subtopics' => ['Overview', 'Details', 'Conclusion'],
            'terminology' => [$keyword],
            'questions' => ["What is $keyword?", "Why is $keyword important?"],
            'keywords' => [$keyword]
        ];
    }
}