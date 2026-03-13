<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/language_handler.php';

// Determine relative path
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
if (in_array($current_dir, ['admin', 'pr', 'department', 'user', 'public'])) {
    $base_path = '../';
} else {
    $base_path = '';
}

// Function to build language switch URL preserving other parameters
function getLangUrl($lang_target) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $params = $_GET;
    $params['lang'] = $lang_target;
    return '?' . http_build_query($params);
}

// Check if account is suspended
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    if (!isset($db)) {
        $db = Database::connect();
    }
    
    $u_type = $_SESSION['user_type'] ?? 'user';
    if ($u_type === 'employee') {
        $stmt_check = $db->prepare("SELECT is_active FROM employees WHERE id = ?");
    } else {
        $stmt_check = $db->prepare("SELECT is_active FROM users WHERE id = ?");
    }
    $stmt_check->execute([$_SESSION['user_id']]);
    $u_status = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$u_status || (isset($u_status['is_active']) && $u_status['is_active'] == 0)) {
        session_unset();
        $_SESSION['error'] = "login_error_deactivated";
        header("Location: " . $base_path . "login.php");
        exit;
    }

    // Forced Password Change redirection
    if (isset($_SESSION['require_password_change']) && $_SESSION['require_password_change'] === true) {
        $current_script = basename($_SERVER['PHP_SELF']);
        if ($current_script !== 'change_password.php' && $current_script !== 'logout.php') {
            header("Location: " . $base_path . "change_password.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (isset($curr_lang) && $curr_lang == 'en') ? 'VOC System – MCOT' : 'ระบบรับเรื่องร้องเรียน อสมท'; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="<?php echo $base_path; ?>assets/img/logo/logo-mcot.jpeg">

    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind (for submit/history pages if needed, index uses custom) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/landing.css">
    
    <style>
        /* Overrides for internal pages using landing header */
        <?php if (!isset($is_index_page) || !$is_index_page): ?>
        body {
            background-color: #f3f4f6; /* Gray-100 equivalent */
            <?php if (!isset($hide_header) || !$hide_header): ?>
            padding-top: 100px; /* Space for fixed header */
            <?php endif; ?>
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <!-- Header / Navigation -->
    <?php if (!isset($hide_header) || !$hide_header): ?>
    <header class="landing-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="<?php echo $base_path; ?>assets/img/logo/logo-mcot-removebg-preview.png" alt="MCOT Logo" style="height: 40px; object-fit: contain;">
                </div>
                
                <div class="hamburger" id="mobileMenuBtn" tabindex="0" role="button" aria-label="Menu">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
                
                <nav class="main-nav">
                    <?php if (!isset($hide_nav) || !$hide_nav): ?>
                    <a href="<?php echo $base_path; ?>index.php#home" class="nav-link"><?php echo __('index_nav_home', 'หน้าแรก'); ?></a>
                    <a href="<?php echo $base_path; ?>index.php#complaint" class="nav-link"><?php echo __('index_nav_process', 'ขั้นตอนการปฏิบัติ'); ?></a>
                    <a href="<?php echo $base_path; ?>index.php#process2" class="nav-link"><?php echo __('index_nav_process2', 'แนวทางการปฏิบัติ'); ?></a>
                    <a href="<?php echo $base_path; ?>index.php#track" class="nav-link"><?php echo __('index_nav_about', 'เกี่ยวกับระบบ'); ?></a>
                    <a href="<?php echo $base_path; ?>index.php#contact" class="nav-link"><?php echo __('index_nav_contact', 'ติดต่อ'); ?></a>
                    <?php endif; ?>
                </nav>
                
                <!-- Language Switcher -->
                <div class="lang-switcher">
                    <a href="<?php echo getLangUrl('th'); ?>" class="lang-link <?php echo $curr_lang == 'th' ? 'active' : ''; ?>">TH</a>
                    <span class="lang-separator">|</span>
                    <a href="<?php echo getLangUrl('en'); ?>" class="lang-link <?php echo $curr_lang == 'en' ? 'active' : ''; ?>">EN</a>
                </div>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                     <!-- Profile Dropdown -->
                     <div class="relative ml-5" id="profileDropdownContainer">
                        <button onclick="toggleProfileDropdown()" class="flex items-center gap-3 focus:outline-none">
                            <div class="text-right hidden md:block">
                                <span class="block text-base font-bold text-gray-900"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                                <span class="block text-sm text-gray-500"><?php echo htmlspecialchars($_SESSION['role_name'] ?? 'General User'); ?></span>
                            </div>
                            <!-- Avatar Placeholder -->
                            <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 overflow-hidden">
                                <?php if (!empty($_SESSION['profile_image'])): ?>
                                    <img src="<?php echo $base_path . 'assets/img/profile/' . $_SESSION['profile_image']; ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class='bx bxs-user text-xl'></i>
                                <?php endif; ?>
                            </div>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2 z-50 border border-gray-100">
                            <?php 
                                $dashboard_link = $base_path . 'index.php';
                                if (isset($_SESSION['role_id'])) {
                                    switch($_SESSION['role_id']) {
                                        case 1: $dashboard_link = $base_path . 'admin/dashboard.php'; break;
                                        case 2: $dashboard_link = $base_path . 'pr/dashboard.php'; break;
                                        case 3: $dashboard_link = $base_path . 'department/dashboard.php'; break;
                                        case 4: $dashboard_link = $base_path . 'index.php'; break;
                                    }
                                }
                            ?>
                            <?php if ($_SESSION['role_id'] != 4): ?>
                            <a href="<?php echo $dashboard_link; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                <i class='bx bxs-dashboard'></i> Dashboard
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo $base_path; ?>public/profile-user.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                <i class='bx bxs-user-detail'></i> My Profile
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="<?php echo $base_path; ?>logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                                <i class='bx bx-log-out'></i> <?php echo __('header_logout', 'ออกจากระบบ'); ?>
                            </a>
                        </div>
                     </div>

                     <script>
                        function toggleProfileDropdown() {
                            const dropdown = document.getElementById('profileDropdown');
                            dropdown.classList.toggle('hidden');
                        }

                        // Close dropdown when clicking outside
                        window.addEventListener('click', function(e) {
                            const container = document.getElementById('profileDropdownContainer');
                            if (!container.contains(e.target)) {
                                document.getElementById('profileDropdown').classList.add('hidden');
                            }
                        });
                     </script>
                <?php else: ?>
                    <a href="<?php echo $base_path; ?>login.php?redirect=index.php" class="btn-login"><?php echo __('index_btn_login', 'เข้าสู่ระบบ'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <?php endif; ?>

    <script src="<?php echo $base_path; ?>assets/js/landing.js"></script>

    <?php 
    // Include User Profile Modal (Only if NOT on the standalone profile page)
    if (!isset($is_standalone_profile) || !$is_standalone_profile) {
        require_once __DIR__ . '/user_profile_modal.php'; 
    }
    ?>
