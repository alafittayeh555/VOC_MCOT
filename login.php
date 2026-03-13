<?php
// login.php
session_start();
require_once 'includes/language_handler.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
        $redirect_path = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
        header("Location: " . $redirect_path);
        exit;
    }
    
    $role_id = $_SESSION['role_id'];
    switch ($role_id) {
        case 1: header("Location: admin/dashboard.php"); exit;
        case 2: header("Location: pr/dashboard.php"); exit;
        case 3: header("Location: department/dashboard.php"); exit;
        case 4: header("Location: user/dashboard.php"); exit;
    }
}

$redirect_url = isset($_GET['redirect']) ? filter_var($_GET['redirect'], FILTER_SANITIZE_URL) : '';
?>
<!DOCTYPE html>
<html lang="<?php echo $curr_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (isset($curr_lang) && $curr_lang == 'en') ? 'VOC System – MCOT' : 'ระบบรับเรื่องร้องเรียน อสมท'; ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="assets/img/logo/logo-mcot.jpeg">
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/login.css?v=<?php echo time(); ?>">
    <!-- Tailwind (Optional, avoiding conflict if possible, but keeping for utility if needed) -->
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
</head>
<body>

    <div class="login-container">
        <!-- Left Side: Info Panel -->
        <div class="login-left">
            <div class="brand-icon" style="background: transparent; box-shadow: none; width: auto; height: auto;">
                 <img src="assets/img/logo/logo-mcot-removebg-preview.png" alt="Logo" style="width: 250px; height: auto;">
            </div>
            
            <h1 class="brand-title"><?php echo __('login_brand_title', 'ระบบร้องเรียนบริษัท อสมท จํากัด (มหาชน)'); ?></h1>
            
            <div class="welcome-msg">
                <?php echo __('login_welcome_title', 'ยินดีต้อนรับเข้าสู่ระบบร้องเรียน'); ?><br>
                <?php echo __('login_welcome_subtitle', 'กรุณาเข้าสู่ระบบเพื่อใช้งาน'); ?>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="login-right">
            <div class="login-header">
                <!-- Language Switcher -->
                <div class="lang-switcher-login">
                    <a href="?lang=th" class="<?php echo $curr_lang == 'th' ? 'active' : ''; ?>">TH</a>
                    <span class="divider">|</span>
                    <a href="?lang=en" class="<?php echo $curr_lang == 'en' ? 'active' : ''; ?>">EN</a>
                </div>
                <h2><?php echo __('login_title'); ?></h2>
                <p><?php echo __('login_subtitle'); ?></p>
            </div>

            <!-- Alerts -->
            <?php 
            $has_error = false;
            if (isset($_SESSION['error'])) {
                $has_error = true;
                $error_msg = $_SESSION['error'];
                unset($_SESSION['error']);
            }
            ?>

            <?php if ($has_error): ?>
                <div class="login-alert error">
                     <i class='bx bxs-error-circle'></i>
                     <span><?php echo __($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                 <div class="login-alert success">
                     <i class='bx bxs-check-circle'></i>
                     <span><?php echo __($_SESSION['success']); unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>

            <form action="controllers/auth.php" method="POST">
                <input type="hidden" name="action" value="login">
                <?php if (!empty($redirect_url)): ?>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_url); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username"><?php echo __('login_username_label'); ?></label>
                    <div class="input-icon-wrap">
                        <i class='bx bxs-user'></i>
                        <input type="text" name="username" id="username" class="form-control" placeholder="<?php echo __('login_username_placeholder', 'Username / Email'); ?>" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password"><?php echo __('login_password_label'); ?></label>
                    <div class="input-icon-wrap" style="margin-bottom: 5px;">
                        <i class='bx bxs-lock-alt'></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="<?php echo __('login_password_placeholder', '••••••••'); ?>" required>
                        <!-- Toggle Password Eye -->
                        <i class='bx bx-show' id="togglePassword" style="left: auto; right: 15px; cursor: pointer; color: #ccc;" onclick="togglePasswordVisibility()"></i>
                    </div>
                    <?php if ($has_error): ?>
                        <div style="text-align: right; margin-top: 5px;">
                            <a href="forgot_password.php" style="color: #ff4d4f; font-size: 14px; text-decoration: none; font-weight: 500;">
                                <?php echo __('login_forgot_password', 'ลืมรหัสผ่านใช่หรือไม่?'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-login-submit" <?php echo $has_error ? 'style="margin-top: 15px;"' : ''; ?>>
                    <?php echo __('login_btn_submit'); ?>
                </button>
            </form>

            <div class="login-links">
                <span><?php echo __('login_no_account'); ?></span>
                <a href="register.php" class="login-link"><?php echo __('login_register_link'); ?></a>
            </div>

            <div class="login-links" style="margin-top: 10px;">
                <a href="index.php" class="login-link" style="font-size: 13px; color: #a0aec0;">
                   <i class='bx bx-home-alt' style="vertical-align: middle;"></i> <?php echo __('login_back_to_home'); ?>
                </a>
            </div>

        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bx-show');
                toggleIcon.classList.add('bx-hide');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bx-hide');
                toggleIcon.classList.add('bx-show');
            }
        }
    </script>
    
    <!-- Background Animation -->
    <script src="assets/js/login-particles.js"></script>

</body>
</html>