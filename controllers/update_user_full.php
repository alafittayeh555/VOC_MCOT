<?php
// controllers/update_user_full.php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/language_handler.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => __('msg_unauthorized', 'Unauthorized')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Handle Occupation
    $occupation = $_POST['occupation'] ?? '';
    if ($occupation === 'อื่น ๆ (Other)') {
        $occupation_other = trim($_POST['occupation_other'] ?? '');
        if (!empty($occupation_other)) {
            $occupation = $occupation_other;
        }
    }

    if (empty($full_name) || empty($username) || empty($email)) {
        echo json_encode(['success' => false, 'message' => __('msg_required_fields', 'Full Name, Username, and Email are required.')]);
        exit;
    }

    // Email verification check
    try {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $old_email = $stmt->fetchColumn();

        if ($email !== $old_email) {
            if (!isset($_SESSION['verified_email']) || $_SESSION['verified_email'] !== $email) {
                echo json_encode(['success' => false, 'message' => __('msg_email_verify_required', 'Please verify your new email address with OTP first.')]);
                exit;
            }
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => __('msg_db_error', 'Database error')]);
        exit;
    }

    try {
        $db = Database::connect();

        // 1. Validate Uniqueness
        $stmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $user_id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => __('msg_user_email_exists', 'Username or Email already exists.')]);
            exit;
        }

        // 2. Handle Image Upload
        $new_filename = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = uniqid('profile_') . '.' . $ext;
                $upload_path = '../assets/img/profile/' . $new_filename;
                
                // Ensure directory exists
                if (!is_dir('../assets/img/profile')) {
                    mkdir('../assets/img/profile', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Fetch old image first
                    $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $old_image = $stmt->fetchColumn();

                    // Update DB with image
                    $stmt = $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                    if ($stmt->execute([$new_filename, $user_id])) {
                        $_SESSION['profile_image'] = $new_filename; // Update session
                        
                        // Delete old image
                        if ($old_image && $old_image != $new_filename) {
                            $old_file = '../assets/img/profile/' . $old_image;
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => __('msg_upload_failed', 'Failed to move uploaded file.')]);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => __('msg_invalid_file_type', 'Invalid file type. Only JPG, PNG, GIF allowed.')]);
                exit;
            }
        }

        // 3. Update Text Data
        $stmt = $db->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, address = ?, occupation = ? WHERE id = ?");
        if ($stmt->execute([$full_name, $username, $email, $phone, $address, $occupation, $user_id])) {
            
            // Update Session
            $_SESSION['full_name'] = $full_name;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            
            $response = [
                'success' => true, 
                'message' => __('msg_profile_updated', 'Profile updated successfully.'),
                'user' => [
                    'full_name' => $full_name,
                    'username' => $username,
                    'email' => $email
                ]
            ];
            
            if ($new_filename) {
                $response['new_image'] = $new_filename;
            }
            
            // Clear verified email from session after successful update
            unset($_SESSION['verified_email']);
            
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'message' => __('msg_db_error', 'Failed to update database.')]);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => __('msg_db_error', 'Database error: ') . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => __('msg_invalid_method', 'Invalid request method.')]);
}
?>
