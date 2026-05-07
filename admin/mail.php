<?php
include 'header.php';

require_once 'includes/PHPMailer/src/Exception.php';
require_once 'includes/PHPMailer/src/PHPMailer.php';
require_once 'includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

global $conn;

$query = "SELECT * FROM mail_settings WHERE id = 1";
$result = mysqli_query($conn, $query);
$mail_settings = mysqli_fetch_assoc($result);

function testSMTPConnection($settings) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'];
        $mail->Password = $settings['smtp_password'];
        $mail->SMTPSecure = $settings['smtp_encryption'];
        $mail->Port = $settings['smtp_port'];
        if($mail->smtpConnect()) {
            $mail->smtpClose();
            return ['success' => true, 'message' => 'SMTP baglantisi basarili!'];
        } else {
            return ['success' => false, 'message' => 'SMTP baglantisi basarisiz!'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'SMTP baglanti hatasi: ' . $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_settings'])) {
        $smtp_host = mysqli_real_escape_string($conn, $_POST['smtp_host']);
        $smtp_port = (int)$_POST['smtp_port'];
        $smtp_username = mysqli_real_escape_string($conn, $_POST['smtp_username']);
        $smtp_password = mysqli_real_escape_string($conn, $_POST['smtp_password']);
        $smtp_encryption = mysqli_real_escape_string($conn, $_POST['smtp_encryption']);
        $from_email = mysqli_real_escape_string($conn, $_POST['from_email']);
        $from_name = mysqli_real_escape_string($conn, $_POST['from_name']);

        $query = "UPDATE mail_settings SET 
                    smtp_host = '$smtp_host',
                    smtp_port = $smtp_port,
                    smtp_username = '$smtp_username',
                    smtp_password = '$smtp_password',
                    smtp_encryption = '$smtp_encryption',
                    from_email = '$from_email',
                    from_name = '$from_name'
                 WHERE id = 1";

        if (mysqli_query($conn, $query)) {
            $success_message = "Mail ayarlari basariyla guncellendi.";
            $result = mysqli_query($conn, "SELECT * FROM mail_settings WHERE id = 1");
            $mail_settings = mysqli_fetch_assoc($result);
        } else {
            $error_message = "Mail ayarlari guncellenirken bir hata olustu.";
        }
    }

    if (isset($_POST['test_connection'])) {
        $test_result = testSMTPConnection($mail_settings);
        if ($test_result['success']) {
            $success_message = $test_result['message'];
        } else {
            $error_message = $test_result['message'];
        }
    }

    if (isset($_POST['send_test'])) {
        try {
            $mail = new PHPMailer(true);
            $test_email = mysqli_real_escape_string($conn, $_POST['test_email']);
            $mail->isSMTP();
            $mail->Host = $mail_settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mail_settings['smtp_username'];
            $mail->Password = $mail_settings['smtp_password'];
            $mail->SMTPSecure = $mail_settings['smtp_encryption'];
            $mail->Port = $mail_settings['smtp_port'];
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($mail_settings['from_email'], $mail_settings['from_name']);
            $mail->addAddress($test_email);
            $mail->isHTML(true);
            $mail->Subject = 'Test E-postasi';
            $mail->Body = 'Bu bir test e-postasidur. Mail ayarlariniz basariyla calisiyor.';
            $mail->send();
            $success_message = "Test maili basariyla gonderildi!";
        } catch (Exception $e) {
            $error_message = "Test maili gonderilemedi. Hata: " . $mail->ErrorInfo;
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1>Mail Ayarlari</h1>
        <p>SMTP ve e-posta gonderim ayarlarinizi yapilandirin.</p>
    </div>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
<?php endif; ?>
<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div>
        <form method="POST">
            <div class="card mb-3">
                <div class="card-header">
                    <h3><i class="fas fa-server" style="margin-right: 8px; color: var(--primary);"></i> SMTP Ayarlari</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">SMTP Sunucu <span class="required">*</span></label>
                            <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($mail_settings['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">SMTP Port <span class="required">*</span></label>
                            <input type="number" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars($mail_settings['smtp_port'] ?? ''); ?>" placeholder="587" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sifreleme Turu</label>
                        <select class="form-control" name="smtp_encryption">
                            <option value="tls" <?php echo ($mail_settings['smtp_encryption'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo ($mail_settings['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">SMTP Kullanici Adi <span class="required">*</span></label>
                            <input type="text" class="form-control" name="smtp_username" value="<?php echo htmlspecialchars($mail_settings['smtp_username'] ?? ''); ?>" placeholder="E-posta adresiniz" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">SMTP Sifre <span class="required">*</span></label>
                            <input type="password" class="form-control" name="smtp_password" value="<?php echo htmlspecialchars($mail_settings['smtp_password'] ?? ''); ?>" placeholder="E-posta sifreniz" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Gonderen E-posta <span class="required">*</span></label>
                            <input type="email" class="form-control" name="from_email" value="<?php echo htmlspecialchars($mail_settings['from_email'] ?? ''); ?>" placeholder="info@siteniz.com" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gonderen Adi <span class="required">*</span></label>
                            <input type="text" class="form-control" name="from_name" value="<?php echo htmlspecialchars($mail_settings['from_name'] ?? ''); ?>" placeholder="Site Adi" required>
                        </div>
                    </div>
                </div>
                <div class="card-footer" style="display: flex; gap: 12px;">
                    <button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save"></i> Ayarlari Kaydet</button>
                    <button type="submit" name="test_connection" class="btn btn-info"><i class="fas fa-plug"></i> Baglantiyi Test Et</button>
                </div>
            </div>
        </form>
    </div>

    <div>
        <form method="POST">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-paper-plane" style="margin-right: 8px; color: var(--success);"></i> Test Mail</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Test Mail Adresi</label>
                        <input type="email" class="form-control" name="test_email" placeholder="test@ornek.com" required>
                        <span class="form-hint">Test e-postasi gonderilecek adres</span>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" name="send_test" class="btn btn-success btn-block"><i class="fas fa-paper-plane"></i> Test Maili Gonder</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
