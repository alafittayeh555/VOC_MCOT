<?php
// includes/email_helper.php

class EmailHelper {
    /**
     * Send a completion email to the complainant.
     * 
     * @param int $complaint_id
     * @return bool
     */
    public static function sendCompletionEmail($complaint_id) {
        try {
            require_once __DIR__ . '/../config/database.php';
            require_once __DIR__ . '/language_handler.php';

            $db = Database::connect();

            // Fetch complaint details
            $stmt = $db->prepare("
                SELECT c.subject, c.reporter_email, c.reporter_name, u.email AS user_email, u.full_name AS user_name
                FROM complaints c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$complaint_id]);
            $complaint = $stmt->fetch();

            $user_email = !empty($complaint['reporter_email']) ? $complaint['reporter_email'] : ($complaint['user_email'] ?? null);
            $user_name = !empty($complaint['reporter_name']) ? $complaint['reporter_name'] : ($complaint['user_name'] ?? 'User');

            if (!$complaint || empty($user_email)) {
                error_log("EmailHelper: No reporter email found for complaint ID " . $complaint_id);
                return false;
            }

            $subject_text = $complaint['subject'];

            // Force email to be in Thai as requested
            $subject = 'แจ้งผลการดําเนินการเรื่อง';
            $body_template = "เรียนคุณ {name}\nตามที่ท่านได้แจ้งเรื่องร้องเรียน / ข้อเสนอแนะ ผ่านระบบ VOC มายังหน่วยงานนั้น ทางหน่วยงาน\nได้รับเรื่องดังกล่าวและได้ดําเนินการตรวจสอบข้อเท็จจริง พร้อมทั้งพิจารณาแนวทางแก้ไขตามขั้นตอน\nที่เกี่ยวข้องเรียบร้อยแล้ว\nบัดนี้ การดําเนินการเกี่ยวกับเรื่องที่ท่านแจ้งได้เสร็จสิ้นเป็นที่เรียบร้อยแล้ว หน่วยงานขอขอบคุณท่าน\nเป็นอย่างยิ่งที่ได้ให้ความร่วมมือในการแจ้งข้อมูล ข้อคิดเห็น และข้อเสนอแนะอันเป็นประโยชน์ ซึ่งมี\nส่วนสําคัญในการช่วยให้หน่วยงานสามารถปรับปรุงและพัฒนาการให้บริการให้มีประสิทธิภาพยิ่งขึ้น\nทั้งนี้ หากท่านมีข้อสงสัยเพิ่มเติม หรือต้องการสอบถามข้อมูลเพิ่มเติม สามารถติดต่อหน่วยงานผ่าน\nระบบ VOC หรือช่องทางติดต่อของหน่วยงานได้ตามความสะดวก\nจึงเรียนมาเพื่อโปรดทราบ และขอขอบคุณท่านที่ให้ความไว้วางใจในการใช้บริการ\nลิงค์ http://406565010.student.yru.ac.th/";

            // Replace placeholders
            $body = str_replace(
                ['{name}', '{subject}', '{id}'],
                [$user_name, $subject_text, $complaint_id],
                $body_template
            );
            
            require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
            require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
            require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';
            
            $smtp_config = require __DIR__ . '/../config/smtp_config.php';
            
            if ($smtp_config['username'] === 'YOUR_GMAIL@gmail.com') {
                error_log("EmailHelper: SMTP not configured yet.");
                return false;
            }

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
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
            $mail->addAddress($user_email);

            // Content
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            // Send email
            $result = $mail->send();
            error_log("EmailHelper: PHPMailer attempt to $user_email for ID $complaint_id. Result: " . ($result ? 'Success' : 'Failed'));
            return $result;

        } catch (Exception $e) {
            error_log("EmailHelper Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
