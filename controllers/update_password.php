<?php
// controllers/update_password.php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/language_handler.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => __('msg_unauthorized', 'ไม่ได้รับอนุญาต')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_id = $_SESSION['user_id'];
    $role_id = $_SESSION['role_id'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => __('msg_required_fields', 'กรุณากรอกข้อมูลให้ครบทุกช่อง')]);
        exit;
    }

    if (strlen($new_password) < 8) {
        echo json_encode(['success' => false, 'message' => __('msg_pass_length', 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร')]);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => __('msg_pass_mismatch', 'รหัสผ่านใหม่ไม่ตรงกัน')]);
        exit;
    }

    try {
        $db = Database::connect();
        $table = ($role_id == 4) ? 'users' : 'employees';

        // Verify current password
        $stmt = $db->prepare("SELECT password_hash FROM $table WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current_password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'message' => __('msg_pass_incorrect', 'รหัสผ่านปัจจุบันไม่ถูกต้อง')]);
            exit;
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $db->prepare("UPDATE $table SET password_hash = ?, require_change = 0 WHERE id = ?");
        
        if ($update_stmt->execute([$hashed_password, $user_id])) {
            // Clear forced change flag if it exists in session
            if (isset($_SESSION['require_password_change'])) {
                unset($_SESSION['require_password_change']);
            }
            echo json_encode(['success' => true, 'message' => __('msg_pass_updated', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว')]);
        } else {
            echo json_encode(['success' => false, 'message' => __('msg_pass_update_failed', 'เปลี่ยนรหัสผ่านไม่สำเร็จ กรุณาลองใหม่อีกครั้ง')]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => __('msg_db_error', 'เกิดข้อผิดพลาดที่ฐานข้อมูล: ') . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => __('msg_invalid_method', 'รูปแบบคำขอไม่ถูกต้อง')]);
}
?>
