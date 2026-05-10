<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../log.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'error' => 'Geçersiz işlem'];

try {
    switch($action) {
        
        case 'save_website':
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            $name = trim($input['name'] ?? '');
            $url = trim($input['url'] ?? '');
            $language = trim($input['language'] ?? 'tr');
            $description = trim($input['description'] ?? '');
            $status = $input['status'] ?? 'active';
            $dailyLimit = isset($input['daily_post_limit']) ? (int)$input['daily_post_limit'] : 3;
            $publishInterval = isset($input['publish_interval']) ? (int)$input['publish_interval'] : 240;
            
            if(empty($name) || empty($url)) {
                throw new Exception('Domain adı zorunludur');
            }
            
            if($id > 0) {
                $stmt = $pdo->prepare("SELECT api_key FROM websites WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                $api_key = $existing['api_key'] ?? '';
                
                if (!empty($input['api_key'])) {
                    $api_key = trim($input['api_key']);
                }
                
                $stmt = $pdo->prepare("UPDATE websites SET 
                    name = ?, url = ?, language = ?, description = ?, status = ?, 
                    daily_post_limit = ?, publish_interval = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $url, $language, $description, $status, $dailyLimit, $publishInterval, $id]);
                
                $response = ['success' => true, 'message' => 'Domain güncellendi', 'is_new' => false];
            } else {
                $api_key = bin2hex(random_bytes(16));
                
                $stmt = $pdo->prepare("INSERT INTO websites (name, url, language, description, status, api_key, daily_post_limit, publish_interval, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $url, $language, $description, $status, $api_key, $dailyLimit, $publishInterval]);
                
                $newId = $pdo->lastInsertId();
                if(!$newId) throw new Exception('Veritabanına ekleme yapılamadı');
                
                $response = [
                    'success' => true, 
                    'message' => 'Domain eklendi', 
                    'is_new' => true, 
                    'id' => (int)$newId, 
                    'api_key' => $api_key
                ];
            }
            break;
        
        case 'delete_website':
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if($id <= 0) throw new Exception('Geçersiz ID');
            
            $pdo->prepare("DELETE FROM content_plans WHERE website_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM crawled_pages WHERE website_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM site_memory WHERE website_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM writer_lessons WHERE website_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM websites WHERE id = ?")->execute([$id]);
            
            $response = ['success' => true, 'message' => 'Website silindi'];
            break;
            
        case 'get_crawled_count':
            $website_id = isset($_GET['website_id']) ? (int)$_GET['website_id'] : (isset($input['website_id']) ? (int)$input['website_id'] : 0);
            if($website_id <= 0) throw new Exception('Geçersiz website ID');
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM crawled_pages WHERE website_id = ?");
            $stmt->execute([$website_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $response = ['success' => true, 'count' => (int)$count];
            break;
        
        default:
            $response = ['success' => false, 'error' => 'Bilinmeyen işlem'];
    }
    
} catch (Exception $e) {
    debug_log("SAVE.PHP HATASI", ['action' => $action, 'error' => $e->getMessage()]);
    $response = ['success' => false, 'error' => $e->getMessage()];
}

if (ob_get_length()) ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>