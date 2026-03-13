<?php
// controllers/switch_role.php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check permission to switch
// Allowed if current role is Admin (1) OR if they have an 'original_role_id' of 1 (meaning they switched previously)
$can_switch = ($_SESSION['role_id'] == 1) || (isset($_SESSION['original_role_id']) && $_SESSION['original_role_id'] == 1);

if (!$can_switch) {
    // If they try to hack it, send them back to home
    header("Location: ../index.php");
    exit;
}

// Get requested role
$new_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;

if ($new_role_id < 1 || $new_role_id > 5) {
    // Invalid role
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// Set 'original_role_id' if not already set, so we can switch back later
if (!isset($_SESSION['original_role_id'])) {
    $_SESSION['original_role_id'] = $_SESSION['role_id']; // This should be 1
}

// If switching back to Admin (1), we can optionally clear the original_role_id, 
// OR keep it to allow persistent switching. 
// Let's keep it simple: if they switch to 1, they become 1. 
// If the original role was 1, they can always switch again because role_id=1 allows it.
// BUT if we clear it, code relying solely on original_role_id might fail if we change logic later.
// However, the check `$can_switch = ($_SESSION['role_id'] == 1) ...` covers it.

// Update Session
$_SESSION['role_id'] = $new_role_id;

// Update Role Name for display
switch ($new_role_id) {
    case 1:
        $_SESSION['role_name'] = 'System Administrator';
        $redirect = '../admin/dashboard.php';
        // If back to admin, maybe clear the "simulation" flag if we wanted to be strict, 
        // but keeping original_role_id doesn't hurt.
        break;
    case 2:
        $_SESSION['role_name'] = 'Public Relations Officer';
        $redirect = '../pr/dashboard.php';
        break;
    case 3:
        $_SESSION['role_name'] = 'Department Officer';
        $redirect = '../department/dashboard.php';
        // Handle Department Selection
        if (isset($_GET['dept_id'])) {
            $_SESSION['department_id'] = (int)$_GET['dept_id'];
            // Also update role_name to include department for clarity?
            // Optional: Fetch department name to append to role name
            $stmt = Database::connect()->prepare("SELECT name FROM departments WHERE id = ?");
            $stmt->execute([$_SESSION['department_id']]);
            $dept = $stmt->fetch();
            if ($dept) {
                $_SESSION['role_name'] = 'Department Officer (' . $dept['name'] . ')';
            }
        }
        break;
    case 4:
        $_SESSION['role_name'] = 'General User';
        $redirect = '../index.php';
        break;
    case 5:
        $_SESSION['role_name'] = 'Employee';
        $redirect = '../employee/dashboard.php';
        if (isset($_GET['dept_id'])) {
            $_SESSION['department_id'] = (int)$_GET['dept_id'];
            $stmt = Database::connect()->prepare("SELECT name FROM departments WHERE id = ?");
            $stmt->execute([$_SESSION['department_id']]);
            $dept = $stmt->fetch();
            if ($dept) {
                $_SESSION['role_name'] = 'Employee (' . $dept['name'] . ')';
            }
        }
        break;
    default:
        $redirect = '../index.php';
}

header("Location: $redirect");
exit;
