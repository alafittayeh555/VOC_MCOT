<?php
// controllers/forgot_send_otp.php
session_start();
require_once '../config/database.php';
require_once '../includes/language_handler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
    exit;
}

try {
    $db = Database::connect();
    
    // Check if email exists in users or employees table
    $stmtUser = $db->prepare("SELECT id, full_name, 'user' as type FROM users WHERE email = ?");
    $stmtUser->execute([$email]);
    $user = $stmtUser->fetch();

    if (!$user) {
        $stmtEmp = $db->prepare("SELECT id, full_name, 'employee' as type FROM employees WHERE email = ?");
        $stmtEmp->execute([$email]);
        $user = $stmtEmp->fetch();
    }

    if (!$user) {
        // Provide a generic message to prevent email enumeration, but in this specific OTP context 
        // it's better to tell them it's not registered
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบอีเมลนี้ในระบบ']);
        exit;
    }

    // Generate 6-digit OTP
    $otp = sprintf("%06d", mt_rand(1, 999999));
    
    // Store in session (expires in 10 minutes)
    $_SESSION['otp'][$email] = [
        'code' => $otp,
        'expires_at' => time() + 600
    ];

    // Load PHPMailer
    require_once '../vendor/phpmailer/Exception.php';
    require_once '../vendor/phpmailer/PHPMailer.php';
    require_once '../vendor/phpmailer/SMTP.php';

    $smtp_config = require '../config/smtp_config.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_config['username'];
        $mail->Password   = $smtp_config['password'];
        $mail->SMTPSecure = $smtp_config['encryption'] === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtp_config['port'];
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
        $mail->addAddress($email);

        // Content
        $mail->isHTML(false);
        $mail->Subject = "รหัสยืนยัน (OTP) สำหรับรีเซ็ตรหัสผ่าน VOC System";
        $mail->Body    = "รหัสยืนยันสำหรับรีเซ็ตรหัสผ่านของคุณคือ: {$otp}\n\nรหัสนี้จะหมดอายุใน 10 นาที\n\nVOC System Team";

        $mail->send();
        
        // Return masked email info
        echo json_encode(['status' => 'success', 'message' => 'รหัสยืนยันถูกส่งไปยังอีเมลของคุณแล้ว']);

    } catch (Exception $e) {
        $error_msg = 'ไม่สามารถส่งอีเมลได้: ' . $mail->ErrorInfo;
        
        $is_localhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']);
        if ($is_localhost) {
            $error_msg .= "\n\n(Localhost Error Details: {$e->getMessage()})";
        }
        
        echo json_encode(['status' => 'error', 'message' => $error_msg]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System error: ' . $e->getMessage()]);
}
?>
