<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../log.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $stmt = $pdo->query("SELECT gemini_api_key FROM ai_settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!empty($settings['gemini_api_key'])) {
        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?key=' . $settings['gemini_api_key']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($res, true);
            
            $pdo->exec("DELETE FROM available_models WHERE api_type = 'gemini'");
            
            $stmt2 = $pdo->prepare("INSERT INTO available_models 
                (api_type, model_id, model_name, is_active) 
                VALUES ('gemini', ?, ?, 1)");
            
            $added = 0;
            $seen = [];
            
            foreach ($data['models'] ?? [] as $model) {
                $fullName = $model['name'] ?? '';
                $id = str_replace('models/', '', $fullName);
                if (empty($id)) continue;

                // Sadece gemini modelleri
                if (stripos($id, 'gemini') === false) continue;
                
                // İstemediğimiz modelleri atla
                if (stripos($id, 'embedding') !== false) continue;
                if (stripos($id, 'aqa') !== false) continue;
                if (stripos($id, 'vision') !== false) continue;
                if (stripos($id, 'imagen') !== false) continue;
                if (stripos($id, 'legacy') !== false) continue;
                
                // Duplikat engelle
                if (isset($seen[$id])) continue;
                $seen[$id] = true;

                // Model adı
                $modelName = $model['displayName'] ?? ucwords(str_replace('-', ' ', $id));

                $stmt2->execute([$id, $modelName]);
                $added++;
            }
            
            $response['success'] = true;
            $response['message'] = "Gemini: $added model güncellendi.";
        } else {
            $response['message'] = "Gemini API hatası: HTTP $httpCode";
        }
    } else {
        $response['message'] = "Gemini API anahtarı bulunamadı.";
    }

    // DeepSeek modelleri
    $pdo->exec("DELETE FROM available_models WHERE api_type = 'deepseek'");
    $stmt2 = $pdo->prepare("INSERT INTO available_models (api_type, model_id, model_name, is_active) VALUES ('deepseek', ?, ?, 1)");
    $stmt2->execute(['deepseek-chat', 'DeepSeek Chat']);
    $stmt2->execute(['deepseek-reasoner', 'DeepSeek Reasoner']);
    
    $response['success'] = true;
    $response['message'] .= " DeepSeek: 2 model güncellendi.";

    debug_log("Modeller güncellendi", ['gemini_added' => $added ?? 0]);

} catch (Exception $e) {
    $response['message'] = 'Hata: ' . $e->getMessage();
    debug_log("Model güncelleme hatası", ['error' => $e->getMessage()]);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
