<?php
// controllers/verify_otp.php
session_start();
require_once '../includes/language_handler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$otp = trim($_POST['otp'] ?? '');

if (empty($email) || empty($otp)) {
    echo json_encode(['status' => 'error', 'message' => __('msg_required_fields', 'กรุณากรอกอีเมลและรหัสยืนยัน')]);
    exit;
}

if (!isset($_SESSION['otp'][$email])) {
    echo json_encode(['status' => 'error', 'message' => __('msg_otp_not_found', 'ไม่พบรหัส OTP ของอีเมลนี้ กรุณากดส่งรหัสยืนยันอีกครั้ง')]);
    exit;
}

$session_otp = $_SESSION['otp'][$email];

if (time() > $session_otp['expires_at']) {
    unset($_SESSION['otp'][$email]);
    echo json_encode(['status' => 'error', 'message' => __('msg_otp_expired', 'รหัสยืนยันหมดอายุ กรุณาขอรหัสใหม่')]);
    exit;
}

if ($session_otp['code'] === $otp) {
    // Correct OTP
    $_SESSION['verified_email'] = $email;
    unset($_SESSION['otp'][$email]); // Prevent reuse
    echo json_encode(['status' => 'success', 'message' => __('msg_otp_verified', 'ยืนยันอีเมลสำเร็จ')]);
} else {
    // Incorrect OTP
    echo json_encode(['status' => 'error', 'message' => __('msg_otp_invalid', 'รหัสยืนยันไม่ถูกต้อง')]);
}
?>
