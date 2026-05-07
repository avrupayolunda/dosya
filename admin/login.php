<?php
session_start();
require_once __DIR__ . '/../includes/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $query = "SELECT id, username, password FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Gecersiz kullanici adi veya sifre';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Giris</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/dashboard.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="width: 56px; height: 56px; background: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                <i class="fas fa-cube" style="font-size: 24px; color: white;"></i>
            </div>
            <h2>Hos Geldiniz</h2>
            <p class="login-subtitle">Devam etmek icin giris yapin</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Kullanici Adi</label>
                <input type="text" name="username" class="form-control" placeholder="Kullanici adinizi girin" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Sifre</label>
                <input type="password" name="password" class="form-control" placeholder="Sifrenizi girin" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <i class="fas fa-sign-in-alt"></i> Giris Yap
            </button>
        </form>
    </div>
</body>
</html>
