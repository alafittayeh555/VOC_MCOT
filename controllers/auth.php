<?php
// controllers/auth.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $_SESSION['error'] = "login_error_required";
            header("Location: ../login.php");
            exit;
        }

        $db = Database::connect();

        // 1. Try Login as Employee (Admin, PR, Dept)
        // Note: We join roles to get role_name, but we assume employees table structure
        $stmt_emp = $db->prepare("SELECT e.*, r.role_name FROM employees e JOIN roles r ON e.role_id = r.id WHERE e.username = :user OR e.email = :email");
        $stmt_emp->execute([':user' => $username, ':email' => $username]);
        $emp = $stmt_emp->fetch();

        if ($emp && password_verify($password, $emp['password_hash'])) {
            if (!$emp['is_active']) {
                $_SESSION['error'] = "login_error_deactivated";
                header("Location: ../login.php");
                exit;
            }
            // Employee Login Success
            $_SESSION['user_id'] = $emp['id'];
            $_SESSION['username'] = $emp['username'];
            $_SESSION['full_name'] = $emp['full_name'];
            $_SESSION['role_id'] = $emp['role_id'];
            $_SESSION['role_name'] = $emp['role_name'];
            $_SESSION['department_id'] = $emp['department_id'];
            $_SESSION['profile_image'] = $emp['profile_image'];
            $_SESSION['email'] = $emp['email']; // Add email to session
            $_SESSION['user_type'] = 'employee'; // Helper flag
            
            session_regenerate_id(true);

            // Check if password change is required
            if (isset($emp['require_change']) && $emp['require_change'] == 1) {
                $_SESSION['require_password_change'] = true;
                header("Location: ../change_password.php");
                exit;
            }

            // Redirect based on role
            switch ($emp['role_id']) {
                case 1: header("Location: ../admin/dashboard.php"); break;
                case 2: header("Location: ../pr/dashboard.php"); break;
                case 3: header("Location: ../department/dashboard.php"); break;
                case 5: header("Location: ../employee/dashboard.php"); break; // Employee shares Department Dashboard
                default: header("Location: ../index.php"); break;
            }
            exit;
        }

        // 2. Try Login as General User (Customers)
        // Users table now assumes role_id = 4 implicitly or explicitly if column exists
        // We will assume 'General User' role for session
        $stmt_user = $db->prepare("SELECT u.* FROM users u WHERE u.username = :user OR u.email = :email");
        $stmt_user->execute([':user' => $username, ':email' => $username]);
        $user = $stmt_user->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
             if (!$user['is_active']) {
                $_SESSION['error'] = "login_error_deactivated";
                header("Location: ../login.php");
                exit;
            }
            // User Login Success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_id'] = 4; // Force Role ID 4 for Users
            $_SESSION['role_name'] = 'General User';
            $_SESSION['department_id'] = null;
            $_SESSION['profile_image'] = $user['profile_image'];
            $_SESSION['email'] = $user['email']; // Add email to session
            $_SESSION['user_type'] = 'user'; // Helper flag

            session_regenerate_id(true);

            // Check if password change is required
            if (isset($user['require_change']) && $user['require_change'] == 1) {
                $_SESSION['require_password_change'] = true;
                header("Location: ../change_password.php");
                exit;
            }
            
            if (isset($_POST['redirect']) && !empty($_POST['redirect'])) {
                $redirect = filter_var($_POST['redirect'], FILTER_SANITIZE_URL);
                header("Location: ../" . ltrim($redirect, '/'));
            } else {
                header("Location: ../index.php");
            }
            exit;
        }
        
        // Login Failed
        $_SESSION['error'] = "login_error_invalid";
        header("Location: ../login.php");
        exit;
    }
} else {
    // If accessed directly without POST
    header("Location: ../login.php");
    exit;
}
