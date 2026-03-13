<?php
// controllers/auth_register.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $full_name = trim($first_name . ' ' . $last_name);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $occupation = trim($_POST['occupation']);
    
    // Handle "Other" occupation
    if ($occupation === 'other' && !empty($_POST['occupation_other'])) {
        $occupation = trim($_POST['occupation_other']);
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Verify Email with Session
    if (!isset($_SESSION['verified_email']) || $_SESSION['verified_email'] !== $email) {
        $_SESSION['error'] = "กรุณายืนยันอีเมลด้วยรหัส OTP ก่อนสมัครสมาชิก";
        header("Location: ../register.php");
        exit;
    }

    // Basic Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($occupation) || empty($username) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
        header("Location: ../register.php");
        exit;
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "รหัสผ่านไม่ตรงกัน";
        header("Location: ../register.php");
        exit;
    }

    try {
        $db = Database::connect();

        // Check if username already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
        $stmt->execute([':username' => $username, ':email' => $email]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "ชื่อผู้ใช้งานหรืออีเมลนี้มีอยู่ในระบบแล้ว";
            header("Location: ../register.php");
            exit;
        }

        // Insert new user
        // Role ID 4 = General User (Default)
        $role_id = 4;
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("INSERT INTO users (username, email, phone, occupation, password_hash, full_name, role_id) VALUES (:username, :email, :phone, :occupation, :password_hash, :full_name, :role_id)");
        $result = $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':phone' => $phone,
            ':occupation' => $occupation,
            ':password_hash' => $password_hash,
            ':full_name' => $full_name,
            ':role_id' => $role_id
        ]);

        if ($result) {
            unset($_SESSION['verified_email']); // Clear verified status
            $_SESSION['success'] = "สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ";
            header("Location: ../login.php");
            exit;
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการลงทะเบียน";
            header("Location: ../register.php");
            exit;
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
        header("Location: ../register.php");
        exit;
    }

} else {
    header("Location: ../register.php");
    exit;
}
