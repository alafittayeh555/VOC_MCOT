<?php
// controllers/auth_forgot.php
session_start();
require_once '../config/database.php';
require_once '../vendor/phpmailer/Exception.php';
require_once '../vendor/phpmailer/PHPMailer.php';
require_once '../vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'request_reset') {
        $email = trim($_POST['email']);

        if (empty($email)) {
            $_SESSION['error'] = "กรุณากรอกอีเมล";
            header("Location: ../forgot_password.php");
            exit;
        }

        try {
            $db = Database::connect();

            // Ensure table exists
            $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
                email VARCHAR(255) NOT NULL,
                token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

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
                // To prevent email enumeration, we show a generic success message
                $_SESSION['success'] = "หากอีเมลนี้อยู่ในระบบ เราได้ส่งลิงก์รีเซ็ตรหัสผ่านไปให้ท่านแล้ว";
                header("Location: ../forgot_password.php");
                exit;
            }

            // Create token
            $token = bin2hex(random_bytes(32)); // 64 chars
            
            // Delete old tokens for this email
            $stmtDel = $db->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmtDel->execute([$email]);

            // Insert new token
            $stmtIns = $db->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
            $stmtIns->execute([$email, $token]);

            // Construct Reset Link
            // Since we might be on localhost or a real domain, we need the base url
            $isSecure = false;
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
                $isSecure = true;
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
                $isSecure = true;
            }
            $protocol = $isSecure ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST']; // localhost or domain
            
            // Getting the current script's directory and working our way up
            $dir = dirname($_SERVER['PHP_SELF']); 
            // example: /voc_system-main/controllers
            $base_folder = str_replace('/controllers', '', $dir); 
            // falls back to correct folder

            $reset_link = $protocol . $host . $base_folder . "/reset_password.php?token=" . $token;

            // Send Email
            $smtp_config = require '../config/smtp_config.php';
            
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp_config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_config['username'];
            $mail->Password   = $smtp_config['password'];
            $mail->SMTPSecure = $smtp_config['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_config['port'];
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'รีเซ็ตรหัสผ่าน - ระบบร้องเรียน';
            $mail->Body    = "
            <html>
            <head>
                <style>
                    body { font-family: 'Prompt', sans-serif; background-color: #f4f7f6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
                    .btn { display: inline-block; padding: 12px 24px; background-color: #1e3c72; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>เรียนคุณ {$user['full_name']},</h2>
                    <p>เราได้รับการแจ้งเตือนขอรีเซ็ตรหัสผ่านสำหรับรหัสผ่านของระบบร้องเรียน (VOC System) กรุณาคลิกปุ่มด้านล่างเพื่อตั้งค่ารหัสผ่านใหม่:</p>
                    <a href='{$reset_link}' class='btn'>รีเซ็ตรหัสผ่านใหม่</a>
                    <p style='margin-top: 30px; font-size: 13px; color: #666;'>* ลิงก์นี้จะหมดอายุภายใน 1 ชั่วโมง หากคลิกปุ่มไม่ได้ ให้ก๊อปปี้ลิงก์นี้ไปวางที่ Browser: <br>{$reset_link}</p>
                </div>
            </body>
            </html>
            ";

            $mail->send();

            $_SESSION['success'] = "หากอีเมลนี้อยู่ในระบบ เราได้ส่งลิงก์รีเซ็ตรหัสผ่านไปให้ท่านแล้ว โปรดตรวจสอบกล่องจดหมาย (Inbox) ของท่าน";
            header("Location: ../forgot_password.php");
            exit;

        } catch (\Exception $e) {
            error_log("Forgot Password Error: " . $e->getMessage());
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการส่งอีเมล กรุณาลองใหม่อีกครั้ง";
            header("Location: ../forgot_password.php");
            exit;
        }

    } 
    
    // ----------- ACTION: RESET PASSWORD VIA OTP -----------
    elseif (isset($_POST['action']) && $_POST['action'] === 'reset_password_otp') {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $email = $_POST['email']; // Usually read-only from the frontend, but we need to verify with session

        if (empty($password) || empty($confirm_password) || empty($email)) {
            $_SESSION['error'] = "กรุณากรอกข้อมูลให้ครบถ้วน";
            header("Location: ../forgot_password.php");
            exit;
        }

        if ($password !== $confirm_password) {
            $_SESSION['error'] = "รหัสผ่านไม่ตรงกัน";
            header("Location: ../forgot_password.php");
            exit;
        }

        // Verify Email with Session
        if (!isset($_SESSION['verified_email']) || $_SESSION['verified_email'] !== $email) {
            $_SESSION['error'] = "กรุณายืนยันอีเมลด้วยรหัส OTP ก่อนเปลี่ยนรหัสผ่าน";
            header("Location: ../forgot_password.php");
            exit;
        }

        try {
            $db = Database::connect();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update user or employee
            $updated = false;
            
            // Try users first
            $stmtUser = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $stmtUser->execute([$hashed_password, $email]);
            if ($stmtUser->rowCount() > 0) {
                $updated = true;
            } else {
                // Try employees
                $stmtEmp = $db->prepare("UPDATE employees SET password_hash = ? WHERE email = ?");
                $stmtEmp->execute([$hashed_password, $email]);
                if ($stmtEmp->rowCount() > 0) {
                    $updated = true;
                }
            }

            if ($updated) {
                // Done - Clear verification status
                unset($_SESSION['verified_email']);

                $_SESSION['success'] = "รีเซ็ตรหัสผ่านเรียบร้อยแล้ว กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่";
                header("Location: ../login.php");
                exit;
            } else {
                $_SESSION['error'] = "ไม่พบบัญชีผู้ใช้งานที่ตรงกับอีเมลนี้";
                header("Location: ../forgot_password.php");
                exit;
            }

        } catch (\Exception $e) {
            error_log("Password Reset Error: " . $e->getMessage());
            $_SESSION['error'] = "เกิดข้อผิดพลาดของระบบ กรุณาลองใหม่อีกครั้ง";
            header("Location: ../forgot_password.php");
            exit;
        }
    }
}
?>
