<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/AI.php';
require_once 'includes/AIWriter.php';
require_once 'log.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'error' => 'Geçersiz işlem'];

// Plan işlemlerini includes/plan.php'ye yönlendir
$planActions = ['generate_plan', 'delete_plan', 'generate_content', 'check_job_status', 'get_lessons'];
if (in_array($action, $planActions)) {
    require_once __DIR__ . '/includes/plan.php';
    exit;
}

try {
    $gemini = new AI($pdo);
    $deepseek = new AI($pdo);
    $writer = new AIWriter($gemini, $deepseek, $pdo);
    
    switch($action) {
        
        case 'get_stats':
            $total = $pdo->query("SELECT COUNT(*) as total FROM content_plans")->fetch(PDO::FETCH_ASSOC)['total'];
            $pending = $pdo->query("SELECT COUNT(*) as total FROM content_plans WHERE status = 'pending'")->fetch(PDO::FETCH_ASSOC)['total'];
            $published = $pdo->query("SELECT COUNT(*) as total FROM content_plans WHERE status = 'published'")->fetch(PDO::FETCH_ASSOC)['total'];
            $response = ['success' => true, 'total_content' => (int)$total, 'pending_content' => (int)$pending, 'published_content' => (int)$published];
            break;
        
        case 'rate_content':
            $input = json_decode(file_get_contents('php://input'), true);
            $plan_id = (int)($input['plan_id'] ?? 0);
            $rating = (int)($input['rating'] ?? 0);
            $feedback = trim($input['feedback'] ?? '');
            if($plan_id <= 0 || $rating < 1 || $rating > 5) throw new Exception('Geçersiz değerler');
            
            $stmt = $pdo->prepare("SELECT * FROM content_plans WHERE id = ?");
            $stmt->execute([$plan_id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("UPDATE content_plans SET user_rating = ?, user_feedback = ? WHERE id = ?");
            $stmt->execute([$rating, $feedback, $plan_id]);
            
            $stmt = $pdo->query("SELECT evolving_rules FROM ai_settings WHERE id = 1");
            $evo = json_decode($stmt->fetchColumn() ?: '{}', true);
            $success = ($rating >= 4);
            $evo['success_rate'] = round(($evo['success_rate'] ?? 0.7) * 0.9 + ($success ? 0.1 : 0), 2);
            if($success && isset($plan['generated_content'])) {
                $wc = str_word_count(strip_tags($plan['generated_content']));
                $evo['avg_word_count'] = round((($evo['avg_word_count'] ?? 1800) + $wc) / 2);
            }
            if($rating <= 2 && !empty($feedback)) {
                if(!isset($evo['past_mistakes'])) $evo['past_mistakes'] = [];
                $evo['past_mistakes'][] = ['topic' => $plan['focus_keyword'], 'lesson' => $feedback, 'timestamp' => date('Y-m-d H:i:s')];
                if(count($evo['past_mistakes']) > 20) array_shift($evo['past_mistakes']);
            }
            $stmt = $pdo->prepare("UPDATE ai_settings SET evolving_rules = ? WHERE id = 1");
            $stmt->execute([json_encode($evo)]);
            $response = ['success' => true, 'message' => 'Teşekkürler'];
            break;
        
        case 'get_website_settings':
            $website_id = (int)($_GET['website_id'] ?? 0);
            if($website_id <= 0) throw new Exception('Geçersiz website ID');
            
            $stmt = $pdo->prepare("SELECT daily_post_limit, publish_interval, api_key FROM websites WHERE id = ?");
            $stmt->execute([$website_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response = ['success' => true, 'settings' => $settings];
            break;
        
        default:
            $response = ['success' => false, 'error' => 'Bilinmeyen işlem: ' . $action];
    }
    
} catch (Exception $e) {
    debug_log("AJAX HATASI", ['action' => $action, 'error' => $e->getMessage()]);
    $response = ['success' => false, 'error' => $e->getMessage()];
}

if (ob_get_length()) ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>