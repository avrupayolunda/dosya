<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/AI.php';
require_once __DIR__ . '/analyzer.php';

$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;
$website_id = $_GET['state'] ?? $_SESSION['gsc_website_id'] ?? 0;

// Hata kontrol
if ($error) {
    error_log("Google OAuth Error: $error - " . ($_GET['error_description'] ?? ''));
    die("Google Hatası: $error");
}

if (!$code) {
    error_log("No code received. GET: " . json_encode($_GET));
    die('Yetkilendirme hatası: code alınamadı.');
}

if (!$website_id) {
    error_log("No website_id. Session: " . json_encode($_SESSION));
    die('Site ID hatası.');
}

$stmt = $pdo->query("SELECT google_client_id, google_client_secret FROM ai_settings WHERE id = 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
$clientId = $settings['google_client_id'] ?? '';
$clientSecret = $settings['google_client_secret'] ?? '';

if (empty($clientId) || empty($clientSecret)) {
    die('Google API bilgileri ayarlanmamış.');
}

$redirectUri = 'https://mailfly.be/content/includes/callback.php';

// TOKEN İSTEĞİ
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

error_log("Token response HTTP: $httpCode");
error_log("Token response: $response");

$token = json_decode($response, true);

// Access token kontrolü
if (!isset($token['access_token'])) {
    error_log("No access_token in response: " . json_encode($token));
    die('Token alınamadı: ' . json_encode($token));
}

$accessToken = $token['access_token'];
$refreshToken = $token['refresh_token'] ?? null;

// Refresh token'ı kaydet (varsa)
if ($refreshToken) {
    $stmt = $pdo->prepare("UPDATE websites SET 
        gsc_connected = 1, 
        gsc_refresh_token = ?, 
        gsc_last_sync = NOW() 
        WHERE id = ?");
    $stmt->execute([$refreshToken, $website_id]);
    error_log("Refresh token saved for website $website_id");
} else {
    // Refresh token yoksa connect flagı set et
    $stmt = $pdo->prepare("UPDATE websites SET 
        gsc_connected = 1, 
        gsc_last_sync = NOW() 
        WHERE id = ?");
    $stmt->execute([$website_id]);
    error_log("No refresh_token for website $website_id (using access_token only)");
}

// Search Console'daki site URL'sini bul
$ch = curl_init('https://www.googleapis.com/webmasters/v3/sites');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$sitesResponse = curl_exec($ch);
curl_close($ch);

$sites = json_decode($sitesResponse, true);

$website = $pdo->prepare("SELECT url FROM websites WHERE id = ?");
$website->execute([$website_id]);
$site = $website->fetch(PDO::FETCH_ASSOC);

if ($site && isset($sites['siteEntry'])) {
    foreach ($sites['siteEntry'] as $entry) {
        $gscUrl = $entry['siteUrl'];
        $ourDomain = parse_url($site['url'], PHP_URL_HOST);
        if (stripos($gscUrl, $ourDomain) !== false) {
            $pdo->prepare("UPDATE websites SET gsc_site_url = ? WHERE id = ?")
                ->execute([$gscUrl, $website_id]);
            break;
        }
    }
}

// Hemen GSC verilerini çek
try {
    $ai = new AI($pdo);
    $analyzer = new SiteAnalyzer($pdo, $ai, $website_id);
    $analyzer->fetchGSCDataWithToken($accessToken);
} catch (Exception $e) {
    error_log("GSC Data fetch error: " . $e->getMessage());
}

header("Location: ../add-site.php?id={$website_id}&gsc=success");
exit;
?>