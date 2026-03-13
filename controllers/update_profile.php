<?php
// controllers/update_profile.php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่ได้รับอนุญาต']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role_id = $_SESSION['role_id'];
    $user_id = $_SESSION['user_id'];

    if (empty($full_name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อ-นามสกุลและอีเมล']);
        exit;
    }

    if (!empty($phone) && !ctype_digit($phone)) {
         echo json_encode(['success' => false, 'message' => 'เบอร์โทรศัพท์ต้องเป็นตัวเลขเท่านั้น']);
         exit;
    }

    try {
        $db = Database::connect();
        
        // Determine Table based on Role
        // Role 4 = General User (users table)
        // Others = Employees (employees table)
        // Check if employees table exists and migration happened, 
        // essentially users with role 4 are in `users`, others in `employees`.
        
        $table = ($role_id == 4) ? 'users' : 'employees';
        
        // 1. Handle Image Upload
        $new_filename = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['profile_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = uniqid('profile_') . '.' . $ext;
                $upload_path = '../assets/img/profile/' . $new_filename;
                
                if (!is_dir('../assets/img/profile')) {
                    mkdir('../assets/img/profile', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Good to go
                } else {
                     echo json_encode(['success' => false, 'message' => 'อัปโหลดรูปภาพไม่สำเร็จ']);
                     exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ชนิดไฟล์ไม่ถูกต้อง: ' . htmlspecialchars($ext)]);
                exit;
            }
        }

        // 2. Update Database
        
        // Self-healing: Ensure profile_image column exists
        try {
            $check_col = $db->query("SHOW COLUMNS FROM $table LIKE 'profile_image'");
            if ($check_col->rowCount() == 0) {
                $db->exec("ALTER TABLE $table ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER email");
            }
            
            // Self-healing: Ensure phone column exists
            $check_phone = $db->query("SHOW COLUMNS FROM $table LIKE 'phone'");
            if ($check_phone->rowCount() == 0) {
                $db->exec("ALTER TABLE $table ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER email");
            }
        } catch (Exception $e) {
            // Ignore error if column already exists or other minor issue
        }

        $sql = "UPDATE $table SET full_name = ?, email = ?, phone = ?";
        $params = [$full_name, $email, $phone];
        
        if ($new_filename) {
            $sql .= ", profile_image = ?";
            $params[] = $new_filename;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $user_id;

        // Fetch old image to delete later
        $stmt_old = $db->prepare("SELECT profile_image FROM $table WHERE id = ?");
        $stmt_old->execute([$user_id]);
        $old_image = $stmt_old->fetchColumn();

        $stmt = $db->prepare($sql);
        
        if ($stmt->execute($params)) {
            // Delete old image if new one was uploaded and update was successful
            if ($new_filename && $old_image && $old_image != $new_filename) {
                $old_file = '../assets/img/profile/' . $old_image;
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }

            // Update Session
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            if ($new_filename) {
                $_SESSION['profile_image'] = $new_filename;
            }

            echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
        } else {
            echo json_encode(['success' => false, 'message' => 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่อีกครั้ง']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดที่ฐานข้อมูล: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'รูปแบบคำขอไม่ถูกต้อง']);
}
?>
