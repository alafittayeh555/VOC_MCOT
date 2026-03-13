<?php
// change_password.php
session_start();
require_once 'includes/language_handler.php';

// Only allow if logged in and change is actually required
if (!isset($_SESSION['user_id']) || !isset($_SESSION['require_password_change']) || $_SESSION['require_password_change'] !== true) {
    header("Location: login.php");
    exit;
}

$curr_lang = $_SESSION['curr_lang'] ?? 'th';
?>
<!DOCTYPE html>
<html lang="<?php echo $curr_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('pass_change_forced_title', 'Password Change Required'); ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="assets/img/logo/logo-mcot.jpeg">
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/login.css?v=<?php echo time(); ?>">
    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .login-right { flex: 1.5; }
        .form-control:read-only { background-color: #f1f5f9; cursor: not-allowed; }
    </style>
</head>
<body class="bg-slate-900">

    <div class="login-container">
        <!-- Left Side: Info Panel (Matched with login.php) -->
        <div class="login-left">
            <div class="brand-icon" style="background: transparent; box-shadow: none; width: auto; height: auto;">
                 <img src="assets/img/logo/logo-mcot-removebg-preview.png" alt="Logo" style="width: 250px; height: auto;">
            </div>
            
            <h1 class="brand-title"><?php echo __('login_brand_title', 'ระบบร้องเรียนบริษัท อสมท จํากัด (มหาชน)'); ?></h1>
            
            <div class="welcome-msg">
                <i class='bx bxs-shield-quarter mb-2 text-2xl'></i><br>
                <strong><?php echo __('pass_change_forced_title'); ?></strong><br>
                <?php echo __('pass_change_forced_desc'); ?>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="login-right">
            <div class="login-header">
                <!-- Language Switcher -->
                <div class="lang-switcher-login">
                    <a href="?lang=th" class="<?php echo $curr_lang == 'th' ? 'active' : ''; ?>">TH</a>
                    <span class="divider">|</span>
                    <a href="?lang=en" class="<?php echo $curr_lang == 'en' ? 'active' : ''; ?>">EN</a>
                </div>
                <h2><?php echo __('pass_change_forced_title'); ?></h2>
                <p><?php echo __('pass_change_forced_desc'); ?></p>
            </div>

            <form id="changePasswordForm" class="space-y-4">
                <div class="form-group">
                    <label><?php echo __('pass_label_current'); ?></label>
                    <div class="input-icon-wrap">
                        <i class='bx bxs-lock-open'></i>
                        <input type="password" name="current_password" class="form-control" value="1234" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label><?php echo __('pass_label_new'); ?></label>
                    <div class="input-icon-wrap">
                        <i class='bx bxs-lock'></i>
                        <input type="password" name="new_password" id="new_password" class="form-control" placeholder="<?php echo __('login_password_placeholder', '••••••••'); ?>" required autofocus>
                        <i class='bx bx-show' id="toggleNewPassword" style="left: auto; right: 15px; cursor: pointer; color: #ccc;" onclick="togglePass('new_password', 'toggleNewPassword')"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label><?php echo __('pass_label_confirm'); ?></label>
                    <div class="input-icon-wrap">
                        <i class='bx bxs-check-shield'></i>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="<?php echo __('login_password_placeholder', '••••••••'); ?>" required>
                        <i class='bx bx-show' id="toggleConfirmPassword" style="left: auto; right: 15px; cursor: pointer; color: #ccc;" onclick="togglePass('confirm_password', 'toggleConfirmPassword')"></i>
                    </div>
                </div>

                <div id="alertMessage" class="hidden"></div>

                <button type="submit" id="submitBtn" class="btn-login-submit">
                    <?php echo __('pass_btn_update'); ?>
                </button>
            </form>
        </div>
    </div>

    <script>
        function togglePass(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bx-show', 'bx-hide');
            } else {
                input.type = 'password';
                icon.classList.replace('bx-hide', 'bx-show');
            }
        }

        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const alertBox = document.getElementById('alertMessage');
            const formData = new FormData(this);
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bx bx-loader-alt animate-spin"></i> Processing...';
            
            fetch('controllers/update_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertBox.className = 'login-alert success';
                    alertBox.innerHTML = '<i class="bx bxs-check-circle"></i> <span>' + data.message + '</span>';
                    alertBox.classList.remove('hidden');
                    
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 1500);
                } else {
                    alertBox.className = 'login-alert error';
                    alertBox.innerHTML = '<i class="bx bxs-error-circle"></i> <span>' + data.message + '</span>';
                    alertBox.classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<?php echo __('pass_btn_update'); ?>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<?php echo __('pass_btn_update'); ?>';
            });
        });
    </script>
    
    <!-- Background Animation -->
    <script src="assets/js/login-particles.js"></script>

</body>
</html>
