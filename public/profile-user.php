<?php
// public/profile-user.php
$is_standalone_profile = true;
require_once '../includes/header_landing.php'; // Uses landing style header

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Ensure $is_modal is false for standalone view
$is_modal = false;

// The UI and logic are now contained in includes/profile_user.php
include '../includes/profile_user.php';
?>
