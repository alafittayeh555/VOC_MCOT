<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include Language Handler
require_once __DIR__ . '/language_handler.php';

// Determine relative path to root
// Count how many directories deep we are relative to index.php (implied root)
// This is a simple heuristic: if we are in admin/, pr/, department/, user/, we are 1 level deep.
// If we are in root, we are 0 levels deep.
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$base_path = ($current_dir == 'project-mcot') ? '' : '../'; // Assuming project folder name matches.
// A safer way is to check if the current script is in one of our known subdirs.
if (in_array($current_dir, ['admin', 'pr', 'department', 'user', 'employee', 'public'])) {
    $base_path = '../';
} else {
    $base_path = '';
}

// Fetch user data for header and profile modal
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    if (!isset($db)) {
        $db = Database::connect();
    }
    
    $user_type = $_SESSION['user_type'] ?? 'employee';
    if ($user_type === 'employee') {
        $stmt = $db->prepare("
            SELECT u.*, r.role_name, d.name as department_name 
            FROM employees u 
            LEFT JOIN roles r ON u.role_id = r.id 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.id = ?
        ");
    } else {
        $stmt = $db->prepare("SELECT u.* FROM users u WHERE u.id = ?");
    }
    
    $stmt->execute([$_SESSION['user_id']]);
    $emp_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if account is suspended or deleted
    if (!$emp_data || (isset($emp_data['is_active']) && $emp_data['is_active'] == 0)) {
        // Clear session and redirect to login
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (isset($curr_lang) && $curr_lang == 'en') ? 'VOC System – MCOT' : 'ระบบรับเรื่องร้องเรียน อสมท'; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="<?php echo $base_path; ?>assets/img/logo/logo-mcot.jpeg">

    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css?v=<?php echo time(); ?>">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-popup {
            border-radius: 28px !important;
            padding: 2rem !important;
            width: 30em !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
        }
        .swal2-title {
            font-size: 1.4rem !important;
            font-weight: 800 !important;
            color: #111827 !important;
            line-height: 1.4 !important;
            padding-top: 0.5rem !important;
        }
        .swal2-html-container {
            font-size: 0.95rem !important;
            color: #4b5563 !important;
            line-height: 1.6 !important;
        }
        .dark .swal2-title {
            color: #f9fafb !important;
        }
        .dark .swal2-html-container {
            color: #d1d5db !important;
        }
        .dark .swal2-popup {
            background: #111827 !important;
            color: #f3f4f6 !important;
            border: 1px solid #374151 !important;
        }
        .swal2-actions {
            margin-top: 2rem !important;
            gap: 12px !important;
        }
        .swal2-confirm {
            background: linear-gradient(135deg, #9333ea 0%, #4f46e5 100%) !important;
            color: #ffffff !important;
            border-radius: 16px !important;
            padding: 14px 32px !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            box-shadow: 0 10px 15px -3px rgba(147, 51, 234, 0.3) !important;
            transition: all 0.3s ease !important;
            border: none !important;
        }
        .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 20px 25px -5px rgba(147, 51, 234, 0.4) !important;
        }
        .swal2-cancel {
            background: #f3f4f6 !important;
            color: #374151 !important;
            border-radius: 16px !important;
            padding: 14px 32px !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            transition: all 0.3s ease !important;
            border: none !important;
        }
        .swal2-cancel:hover {
            background: #e5e7eb !important;
            transform: translateY(-2px) !important;
        }
        .dark .swal2-cancel {
            background: #374151 !important;
            color: #f3f4f6 !important;
        }
        .dark .swal2-cancel:hover {
            background: #4b5563 !important;
        }
        .swal2-icon {
            border-width: 3px !important;
            transform: scale(0.9) !important;
            margin-bottom: 0.5rem !important;
        }
    </style>
</head>

<body class="<?php echo isset($_SESSION['user_id']) ? '' : 'bg-gray-100'; ?>">
    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
            document.body.classList.add('dark');
        }
        // Immediately apply saved sidebar state to prevent FOUC (flash of unstyled content)
        if (localStorage.getItem('sidebarStart') === 'hide') {
            document.documentElement.style.setProperty('--sidebar-width', '60px'); // Optional helper
        }
    </script>
    <style>
        /* Check if sidebar should be hidden before body renders fully */
        body.sidebar-hidden #sidebar {
            width: 60px;
        }
        body.sidebar-hidden #sidebar .side-menu li a {
            width: calc(48px - (4px * 2));
        }
        /* Hide transition on initial load to prevent bounce animation if preferred */
        .no-transition {
            transition: none !important;
        }
    </style>
    <script>
        // Apply class to body immediately
        if (localStorage.getItem('sidebarStart') === 'hide') {
            document.write('<style id="fouc-fix">#sidebar { width: 60px; } #sidebar .side-menu li a { width: calc(48px - 8px); } #content { width: calc(100% - 60px); left: 60px; }</style>');
        }
    </script>

    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- SIDEBAR -->
        <section id="sidebar">
            <a href="<?php echo $base_path; ?>index.php" class="brand">
                <img src="<?php echo $base_path; ?>assets/img/logo/logo.png" alt="MCOT Logo"
                    style="width: 48px; height: auto; margin-right: 10px;">
                <span class="text"><?php echo __('mcot_logo_text'); ?></span>
            </a>
            <ul class="side-menu top">


                <?php
                $role_id = $_SESSION['role_id'];
                $current_page = basename($_SERVER['PHP_SELF']);

                // Note: Links below assume simplified flat structure for legacy files for now, 
                // or point to new subdirs. 
            
                // DEBUG: Role ID is verified inside PHP block
            
                if ($role_id == 1) { // Admin
                    ?>
                    <li class="<?php echo $current_page == 'dashboard.php' && $current_dir == 'admin' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>admin/dashboard.php">
                            <i class='bx bxs-dashboard'></i>
                            <span class="text"><?php echo __('menu_dashboard'); ?></span>
                        </a>
                    </li>

                    <li class="<?php echo $current_page == 'employees.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>admin/employees.php">
                            <i class='bx bxs-user-detail'></i>
                            <span class="text"><?php echo __('menu_user_management'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>admin/users.php">
                            <i class='bx bxs-group'></i>
                            <span class="text"><?php echo __('menu_general_users'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'departments.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>admin/departments.php">
                            <i class='bx bxs-building-house'></i>
                            <span class="text"><?php echo __('menu_departments'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'agencies.php' || $current_page == 'agency_options.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>admin/agencies.php">
                            <i class='bx bxs-buildings'></i>
                            <span class="text"><?php echo __('menu_agencies'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>admin/reports.php">
                            <i class='bx bxs-report'></i>
                            <span class="text"><?php echo __('menu_reports'); ?></span>
                        </a>
                    </li>

                    <li class="<?php echo $current_page == 'edit_homepage.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>admin/edit_homepage.php">
                            <i class='bx bx-layout'></i>
                            <span class="text"><?php echo __('menu_edit_homepage'); ?></span>
                        </a>
                    </li>
                    <?php
                } elseif ($role_id == 2) { // PR Officer
                    ?>
                    <li class="<?php echo $current_page == 'dashboard.php' && $current_dir == 'pr' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>pr/dashboard.php">
                            <i class='bx bxs-dashboard'></i>
                            <span class="text"><?php echo __('menu_dashboard'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'Complaint_Suggestion.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>pr/Complaint_Suggestion.php">
                            <i class='bx bxs-edit'></i>
                            <span class="text"><?php echo __('menu_complaint_suggestion'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'new_complaint.php' || $current_page == 'complaint_details.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>pr/new_complaint.php">
                            <i class='bx bxs-bell-ring'></i>
                            <span class="text"><?php echo __('menu_new_complaints'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'assigned.php' || $current_page == 'assigned_complaint_details.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>pr/assigned.php">
                            <i class='bx bxs-briefcase'></i>
                            <span class="text"><?php echo __('menu_assigned_complaints'); ?></span>
                        </a>
                    </li>

                    <li class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>pr/reports.php">
                            <i class='bx bxs-report'></i>
                            <span class="text"><?php echo __('menu_reports'); ?></span>
                        </a>
                    </li>

                    <li class="<?php echo $current_page == 'history.php' || $current_page == 'history_details.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>pr/history.php">
                            <i class='bx bx-history'></i>
                            <span class="text"><?php echo __('sidebar_history'); ?></span>
                        </a>
                    </li>
                    <?php
                } elseif ($role_id == 5) { // Employee
                    ?>
                    <li class="<?php echo $current_page == 'dashboard.php' && $current_dir == 'employee' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>employee/dashboard.php">
                            <i class='bx bxs-dashboard'></i>
                            <span class="text"><?php echo __('menu_dashboard'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'assigned_case.php' || $current_page == 'case_details.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>employee/assigned_case.php">
                            <i class='bx bxs-briefcase'></i>
                            <span class="text"><?php echo __('menu_assigned_cases'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                         <a href="<?php echo $base_path; ?>employee/reports.php">
                            <i class='bx bxs-report'></i>
                            <span class="text"><?php echo __('menu_reports'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'history.php' || $current_page == 'history_details.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>employee/history.php">
                            <i class='bx bx-history'></i>
                            <span class="text"><?php echo __('sidebar_history'); ?></span>
                        </a>
                    </li>
                    <?php
                } elseif ($role_id == 3) { // Dept Officer
                    ?>
                    <li class="<?php echo $current_page == 'dashboard.php' && $current_dir == 'department' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>department/dashboard.php">
                            <i class='bx bxs-dashboard'></i>
                            <span class="text"><?php echo __('menu_dashboard'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'new_complaints.php' || $current_page == 'new_complaints_details.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>department/new_complaints.php">
                            <i class='bx bxs-bell-ring'></i>
                            <span class="text"><?php echo __('menu_new_complaints'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'assigned.php' || $current_page == 'assigned_details.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>department/assigned.php">
                            <i class='bx bxs-briefcase'></i>
                            <span class="text"><?php echo __('menu_assigned_complaints'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>department/reports.php">
                            <i class='bx bxs-report'></i>
                            <span class="text"><?php echo __('menu_reports'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'employees.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>department/employees.php">
                            <i class='bx bxs-user-detail'></i>
                            <span class="text"><?php echo __('menu_employees'); ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'history.php' || $current_page == 'history_details.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>department/history.php">
                            <i class='bx bx-history'></i>
                            <span class="text"><?php echo __('sidebar_history'); ?></span>
                        </a>
                    </li>

                    <?php
                } elseif ($role_id == 4) { // General User
                    ?>
                    <li
                        class="<?php echo $current_page == 'Complaint_Suggestion.php' || $current_page == 'complaint_submit.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>user/Complaint_Suggestion.php"> <!-- Updated to new path -->
                            <i class='bx bxs-edit'></i>
                            <span class="text"><?php echo __('menu_complaint_suggestion'); ?></span>
                        </a>
                    </li>
                    <li
                        class="<?php echo $current_page == 'status.php' || $current_page == 'complaint_history.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $base_path; ?>user/status.php"> <!-- Updated to new path -->
                            <i class='bx bxs-time-five'></i>
                            <span class="text"><?php echo __('menu_track_status'); ?></span>
                        </a>
                    </li>
                    <?php
                }
                ?>
            </ul>
            <ul class="side-menu bottom">
                <li>
                    <a href="<?php echo $base_path; ?>logout.php" class="logout">
                        <i class='bx bx-log-out-circle'></i>
                        <span class="text"><?php echo __('menu_logout'); ?></span>
                    </a>
                </li>
            </ul>
        </section>
        <!-- SIDEBAR -->

        <!-- CONTENT -->
        <section id="content">
            <!-- NAVBAR -->
            <nav>
                <i class='bx bx-menu'></i>
                <form action="#">
                    <div class="form-input">
                    </div>
                </form>
                <input type="checkbox" class="checkbox" id="switch-mode" hidden />
                <label class="swith-lm" for="switch-mode">
                    <i class="bx bxs-moon"></i>
                    <i class="bx bx-sun"></i>
                    <div class="ball"></div>
                </label>
                <script>
                    if (localStorage.getItem('darkMode') === 'true') {
                        document.getElementById('switch-mode').checked = true;
                    }
                </script>

                <?php
                // Preserve existing query parameters for language switcher
                $query_params = $_GET;
                $query_params['lang'] = 'th';
                $th_url = '?' . http_build_query($query_params);
                
                $query_params['lang'] = 'en';
                $en_url = '?' . http_build_query($query_params);
                ?>
                <!-- Language Switcher -->
                <div class="lang-switch" style="margin-left: 15px; font-weight: bold; display: flex; gap: 5px;">
                    <a href="<?php echo htmlspecialchars($th_url); ?>"
                        style="text-decoration: none; color: <?php echo $curr_lang == 'th' ? 'var(--blue)' : 'var(--dark-grey)'; ?>;">TH</a>
                    <span>|</span>
                    <a href="<?php echo htmlspecialchars($en_url); ?>"
                        style="text-decoration: none; color: <?php echo $curr_lang == 'en' ? 'var(--blue)' : 'var(--dark-grey)'; ?>;">EN</a>
                </div>

                <a href="#" class="notification">
                    <i class='bx bxs-bell'></i>
                    <span class="num">8</span>
                </a>

                <div class="notification-menu">
                    <ul>
                        <li><a href="#"><?php echo __('header_welcome_system'); ?></a></li>
                    </ul>
                </div>

                <a href="#" class="profile" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
                    <span style="display: flex; flex-direction: column; text-align: right;">
                        <span
                            style="font-weight: 600; font-size: 14px; color: var(--dark);"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <span
                            style="font-size: 10px; color: var(--dark-grey); text-align: right;"><?php echo htmlspecialchars($_SESSION['role_name']); ?></span>
                    </span>
                    <div style="width: 36px; height: 36px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #F1F5F9; flex-shrink: 0;">
                        <?php if (!empty($emp_data['profile_image'])): ?>
                            <img src="<?php echo $base_path; ?>assets/img/profile/<?php echo htmlspecialchars($emp_data['profile_image']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class='bx bxs-user' style='font-size: 24px; color: #94A3B8;'></i>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="profile-menu">
                    <ul>

                        <li><a href="#" onclick="openProfileModal()"><i class='bx bxs-user-detail'></i> <?php echo __('menu_my_profile'); ?></a></li>
                        <li><a href="#"><i class='bx bxs-cog'></i> <?php echo __('menu_settings'); ?></a></li>
                        
                        <?php 
                        // Check if user is Admin OR has switched from Admin
                        if (($_SESSION['role_id'] == 1) || (isset($_SESSION['original_role_id']) && $_SESSION['original_role_id'] == 1)): 
                        ?>
                            <li class="switch-role-item">
                                <a href="#" onclick="toggleSwitchRole(event)" style="display: flex; justify-content: space-between; align-items: center;">
                                    <span><i class='bx bx-transfer-alt'></i> <?php echo __('menu_switch_role'); ?></span>
                                    <i class='bx bx-chevron-down' id="switch-role-icon"></i>
                                </a>
                                <ul style="display: none; background: var(--light); padding: 5px 0; border-radius: 10px;" id="switch-role-submenu">
                                    <?php if ($_SESSION['role_id'] != 1): ?>
                                        <li><a href="<?php echo $base_path; ?>controllers/switch_role.php?role_id=1" style="padding-left: 30px; font-size: 14px;"><i class='bx bxs-shield'></i> <?php echo __('role_admin'); ?></a></li>
                                    <?php endif; ?>
                                    
                                    <?php if ($_SESSION['role_id'] != 2): ?>
                                        <li><a href="<?php echo $base_path; ?>controllers/switch_role.php?role_id=2" style="padding-left: 30px; font-size: 14px;"><i class='bx bxs-megaphone'></i> <?php echo __('role_pr'); ?></a></li>
                                    <?php endif; ?>
                                    
                                    <?php if ($_SESSION['role_id'] != 3): ?>
                                        <li class="dept-switch-parent" style="position: relative;">
                                            <a href="#" onclick="toggleDeptSwitch(event)" style="padding-left: 30px; font-size: 14px; display: flex; justify-content: space-between; align-items: center;">
                                                <span><i class='bx bxs-building-house'></i> <?php echo __('role_dept'); ?></span>
                                                <i class='bx bx-chevron-left' id="dept-switch-icon"></i>
                                            </a>
                                            <ul id="dept-switch-submenu" style="display: none; position: absolute; right: 102%; top: -10px; background: var(--light); width: 220px; max-height: 300px; overflow-y: auto; box-shadow: -5px 5px 15px rgba(0,0,0,0.1); border-radius: 10px; padding: 10px 0; border: 1px solid var(--grey);">
                                                <?php 
                                                // Fetch departments safely
                                                if (file_exists(__DIR__ . '/../config/database.php')) {
                                                    require_once __DIR__ . '/../config/database.php';
                                                    if (class_exists('Database')) {
                                                        $db_switch = Database::connect();
                                                        $depts_switch = $db_switch->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
                                                        echo '<li style="padding: 5px 15px; font-size: 11px; color: var(--dark-grey); font-weight: bold; border-bottom: 1px solid var(--grey); margin-bottom: 5px;">SELECT DEPARTMENT</li>';
                                                        foreach ($depts_switch as $d) {
                                                            echo '<li><a href="' . $base_path . 'controllers/switch_role.php?role_id=3&dept_id=' . $d['id'] . '" style="padding: 8px 15px; font-size: 13px; display: block;">' . htmlspecialchars($d['name']) . '</a></li>';
                                                        }
                                                    }
                                                }
                                                ?>
                                            </ul>
                                        </li>
                                    <?php endif; ?>
                                    

                                </ul>
                                <script>
                                    function toggleSwitchRole(e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        const submenu = document.getElementById('switch-role-submenu');
                                        const icon = document.getElementById('switch-role-icon');
                                        
                                        if (submenu.style.display === 'none') {
                                            submenu.style.display = 'block';
                                            icon.style.transform = 'rotate(180deg)';
                                        } else {
                                            submenu.style.display = 'none';
                                            icon.style.transform = 'rotate(0deg)';
                                        }
                                    }

                                    function toggleDeptSwitch(e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        const submenu = document.getElementById('dept-switch-submenu');
                                        const icon = document.getElementById('dept-switch-icon');
                                        
                                        if (submenu.style.display === 'none') {
                                            submenu.style.display = 'block';
                                            // No rotation needed for left chevron to indicate "open", or maybe color change
                                            icon.style.color = 'var(--blue)';
                                        } else {
                                            submenu.style.display = 'none';
                                            icon.style.color = '';
                                        }
                                    }
                                </script>
                            </li>
                        <?php endif; ?>

                        <li><a href="<?php echo $base_path; ?>logout.php"><i class='bx bx-log-out-circle'></i> <?php echo __('header_profile_logout'); ?></a></li>
                    </ul>
                </div>
            </nav>
            <!-- NAVBAR -->

            <!-- MAIN -->
            <main>
            <?php else: ?>
                <!-- Guest Layout -->
                <div class="guest-wrapper min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
                <?php endif; ?>

<?php 
// Include Profile Modal
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/profile_emp.php'; 
}
?>