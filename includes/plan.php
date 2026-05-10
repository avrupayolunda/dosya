<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

class AI {
    public function __construct($pdo) {}
    public function getAgentPreference($role) { return 'gemini'; }
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

$action = $_GET['action'] ?? '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['error' => 'Action parametresi gerekli']);
    exit;
}

$ai = new AI($pdo);

switch ($action) {

    case 'get_generating_plans':
        $website_id = $_GET['website_id'] ?? 0;
        
        if ($website_id) {
            $stmt = $pdo->prepare("
                SELECT cp.id as plan_id, cp.status, wj.status as job_status, wj.error_message
                FROM content_plans cp
                LEFT JOIN writer_jobs wj ON cp.id = wj.plan_id
                WHERE cp.website_id = ? AND cp.status IN ('generating','outline','writing')
                ORDER BY cp.id DESC
            ");
            $stmt->execute([$website_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT cp.id as plan_id, cp.status, wj.status as job_status, wj.error_message
                FROM content_plans cp
                LEFT JOIN writer_jobs wj ON cp.id = wj.plan_id
                WHERE cp.status IN ('generating','outline','writing')
                ORDER BY cp.id DESC
            ");
            $stmt->execute();
        }
        
        $plans = $stmt->fetchAll();
        $response = [];
        
        foreach ($plans as $plan) {
            $msg = 'İçerik oluşturuluyor...';
            if ($plan['job_status'] === 'failed') {
                $msg = $plan['error_message'] ?? 'Oluşturma başarısız';
            } elseif ($plan['status'] === 'outline') {
                $msg = 'Outline oluşturuluyor...';
            } elseif ($plan['status'] === 'writing') {
                $msg = 'İçerik yazılıyor...';
            }
            $response[] = [
                'plan_id' => (int)$plan['plan_id'],
                'status' => $plan['status'],
                'message' => $msg
            ];
        }
        
        echo json_encode(['success' => true, 'plans' => $response]);
        break;

    case 'start_plan_generator':
        $input = json_decode(file_get_contents('php://input'), true);
        $website_id = $input['website_id'] ?? $_GET['website_id'] ?? 0;
        $days = $input['days'] ?? $_GET['days'] ?? null;
        
        if (!$website_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Website ID gerekli']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id FROM plan_generator_jobs WHERE website_id = ? AND status IN ('pending','running')");
        $stmt->execute([$website_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Zaten devam eden bir plan oluşturma işlemi var']);
            exit;
        }
        
        // --- DEĞİŞTİRİLEN KISIM: HAFTALIK HESAPLAMA (Pazar Gününe Kadar) ---
        if (!$days) {
            $today = new DateTime();
            $dayOfWeek = (int)$today->format('N'); // 1 (Pazartesi) ile 7 (Pazar) arası
            $days = 7 - $dayOfWeek + 1; // Pazar gününe kadar kalan gün sayısı
        }
        // ------------------------------------------------------------------
        
        // Websites tablosundan günlük limiti al
        $dailyLimit = 3;
        $stmt = $pdo->prepare("SELECT daily_post_limit FROM websites WHERE id = ?");
        $stmt->execute([$website_id]);
        $web = $stmt->fetch();
        if ($web) $dailyLimit = (int)($web['daily_post_limit'] ?? 3);
        
        $startDate = date('Y-m-d');
        
        $stmt = $pdo->prepare("INSERT INTO plan_generator_jobs (website_id, total_days, current_day, daily_limit, start_date, status, created_at) VALUES (?, ?, 0, ?, ?, 'pending', NOW())");
        $stmt->execute([$website_id, $days, $dailyLimit, $startDate]);
        
        echo json_encode(['success' => true, 'message' => 'Plan oluşturma başlatıldı', 'total_days' => $days, 'daily_limit' => $dailyLimit]);
        break;

    case 'check_plan_job':
        $website_id = $_GET['website_id'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT * FROM plan_generator_jobs WHERE website_id = ? AND status IN ('pending','running','completed') ORDER BY id DESC LIMIT 1");
        $stmt->execute([$website_id]);
        $job = $stmt->fetch();
        
        if (!$job) {
            echo json_encode(['success' => false, 'error' => 'İş bulunamadı']);
            exit;
        }
        
        echo json_encode(['success' => true, 'job' => [
            'status' => $job['status'],
            'current_day' => (int)$job['current_day'],
            'total_days' => (int)$job['total_days']
        ]]);
        break;

    case 'generate_content':
        $input = json_decode(file_get_contents('php://input'), true);
        $plan_id = $input['plan_id'] ?? $_GET['plan_id'] ?? 0;
        
        if (!$plan_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'plan_id gerekli']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM content_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            echo json_encode(['success' => false, 'error' => 'Plan bulunamadı']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id, status FROM writer_jobs WHERE plan_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$plan_id]);
        $existingJob = $stmt->fetch();
        
        if ($existingJob && in_array($existingJob['status'], ['pending','outline','writing'])) {
            echo json_encode(['success' => false, 'error' => 'Bu içerik zaten oluşturuluyor']);
            exit;
        }
        
        $pdo->prepare("UPDATE content_plans SET status = 'generating' WHERE id = ?")->execute([$plan_id]);
        $pdo->prepare("INSERT INTO writer_jobs (plan_id, status, created_at) VALUES (?, 'pending', NOW())")->execute([$plan_id]);
        
        echo json_encode(['success' => true, 'plan_id' => (int)$plan_id, 'message' => 'İçerik oluşturma başlatıldı']);
        break;

    case 'check_content_status':
        $plan_id = $_GET['plan_id'] ?? 0;
        
        $stmt = $pdo->prepare("
            SELECT cp.id, cp.status as plan_status, cp.title, cp.generated_content, cp.focus_keyword,
                   wj.status as job_status, wj.progress_data, wj.error_message,
                   wj.total_sections, wj.current_section_index
            FROM content_plans cp
            LEFT JOIN writer_jobs wj ON cp.id = wj.plan_id
            WHERE cp.id = ?
            ORDER BY wj.id DESC LIMIT 1
        ");
        $stmt->execute([$plan_id]);
        $row = $stmt->fetch();
        
        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Plan bulunamadı']);
            exit;
        }
        
        if ($row['plan_status'] === 'published' && !empty($row['generated_content'])) {
            echo json_encode([
                'success' => true,
                'status' => 'completed',
                'content' => $row['generated_content'],
                'title' => $row['title'],
                'keyword' => $row['focus_keyword']
            ]);
            exit;
        }
        
        if ($row['plan_status'] === 'failed' || $row['job_status'] === 'failed') {
            echo json_encode([
                'success' => true,
                'status' => 'failed',
                'error' => $row['error_message'] ?? 'Oluşturma başarısız'
            ]);
            exit;
        }
        
        $response = ['success' => true, 'status' => $row['plan_status'] ?? 'pending'];
        
        if (in_array($row['plan_status'], ['generating','outline','writing'])) {
            $progress = null;
            if ($row['progress_data']) {
                $data = json_decode($row['progress_data'], true);
                if ($data) {
                    $progress = [
                        'current_section' => (int)($data['current_section'] ?? $row['current_section_index'] ?? 0),
                        'total_sections' => (int)($data['total_sections'] ?? $row['total_sections'] ?? 1)
                    ];
                }
            }
            $response['progress'] = $progress;
        }
        
        echo json_encode($response);
        break;

    case 'get_plans':
        $website_id = $_GET['website_id'] ?? 0;
        
        $stmt = $pdo->prepare("
            SELECT cp.*, ck.keyword, ck.language, ck.search_volume
            FROM content_plans cp
            JOIN content_keywords ck ON cp.keyword_id = ck.id
            WHERE cp.website_id = ?
            ORDER BY cp.id DESC
        ");
        $stmt->execute([$website_id]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'delete_plan':
        $input = json_decode(file_get_contents('php://input'), true);
        $plan_id = $input['plan_id'] ?? $input['id'] ?? $_GET['plan_id'] ?? $_GET['id'] ?? 0;
        
        $stmt = $pdo->prepare("DELETE FROM content_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        echo json_encode(['success' => true, 'message' => 'Plan silindi']);
        break;

    case 'save_plan':
        $input = json_decode(file_get_contents('php://input'), true);
        $plan_id = $input['plan_id'] ?? 0;
        $title = $input['title'] ?? '';
        $content = $input['content'] ?? '';
        $meta_description = $input['meta_description'] ?? '';
        
        if ($plan_id) {
            $stmt = $pdo->prepare("UPDATE content_plans SET title = ?, content = ?, meta_description = ? WHERE id = ?");
            $stmt->execute([$title, $content, $meta_description, $plan_id]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Plan kaydedildi']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Bilinmeyen action: ' . $action]);
        break;
}