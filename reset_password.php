<?php
// reset_password.php
session_start();
require_once 'includes/language_handler.php';

// Check if token exists
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    $_SESSION['error'] = "ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้อง";
    header("Location: forgot_password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
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
</head>
<body>

    <div class="login-container">
        <!-- Left Side: Info Panel -->
        <div class="login-left">
            <div class="brand-icon" style="background: transparent; box-shadow: none; width: auto; height: auto;">
                 <img src="assets/img/logo/logo-mcot-removebg-preview.png" alt="Logo" style="width: 250px; height: auto;">
            </div>
            
            <h1 class="brand-title">ระบบร้องเรียนบริษัท อสมท จํากัด (มหาชน)<br></h1>
            
            <div class="welcome-msg">
                ยินดีต้อนรับเข้าสู่ระบบร้องเรียน<br>
                ตั้งค่ารหัสผ่านใหม่
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="login-right">
            <div class="login-header">
                <h2>ตั้งรหัสผ่านใหม่</h2>
                <p>กรุณากรอกรหัสผ่านใหม่ที่ต้องการตั้งค่า</p>
            </div>

            <!-- Alerts -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="login-alert error">
                     <i class='bx bxs-error-circle'></i>
                     <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <form action="controllers/auth_forgot.php" method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="form-group">
                    <label>รหัสผ่านใหม่</label>
                    <div class="input-icon-wrap">
                        <i class='bx bxs-lock-alt'></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="กรุณากรอกรหัสผ่านใหม่" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>ยืนยันรหัสผ่านใหม่</label>
                    <div class="input-icon-wrap">
                        <i class='bx bxs-lock-alt'></i>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="กรุณายืนยันรหัสผ่านใหม่อีกครั้ง" required>
                    </div>
                </div>

                <button type="submit" class="btn-login-submit">
                    <i class='bx bx-check-circle'></i> บันทึกรหัสผ่านใหม่
                </button>
            </form>

            <div class="login-links">
                <a href="login.php" class="login-link">กลับสู่หน้าเข้าสู่ระบบ</a>
            </div>

        </div>
    </div>
    
    <!-- Background Animation -->
    <script src="assets/js/login-particles.js"></script>

</body>
</html>
