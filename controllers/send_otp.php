<?php
// controllers/send_otp.php
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
    echo json_encode(['status' => 'error', 'message' => __('msg_invalid_email', 'รูปแบบอีเมลไม่ถูกต้อง')]);
    exit;
}

try {
    $db = Database::connect();
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'error', 'message' => __('msg_user_email_exists', 'อีเมลนี้มีอยู่ในระบบแล้ว')]);
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
        $mail->Subject = __('email_otp_subject', 'รหัสยืนยัน (OTP) สำหรับระบบ VOC');
        $mail->Body    = strtr(__('email_otp_body', "รหัสยืนยันสำหรับอีเมล {email} ของคุณคือ: {otp}\n\nรหัสนี้จะหมดอายุใน 10 นาที\n\nทีมงานระบบ VOC"), [
            '{email}' => $email,
            '{otp}' => $otp
        ]);

        // Check if user has configured the email yet
        if ($smtp_config['username'] === 'YOUR_GMAIL@gmail.com') {
            echo json_encode(['status' => 'error', 'message' => __('msg_smtp_not_configured', 'ระบบยังไม่ได้ตั้งค่าบัญชีอีเมล (SMTP) กรุณาติดต่อผู้ดูแลระบบ')]);
            exit;
        }

        $mail->send();
        echo json_encode(['status' => 'success', 'message' => __('msg_otp_sent', 'รหัสยืนยันถูกส่งไปยังอีเมลของคุณแล้ว')]);

    } catch (Exception $e) {
        $error_msg = __('msg_otp_send_failed', 'ไม่สามารถส่งอีเมลได้: ') . $mail->ErrorInfo;
        
        // Expose more details if on localhost to help debugging
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
