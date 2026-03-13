<?php
// forgot_password.php
session_start();
require_once 'includes/language_handler.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    $role_id = $_SESSION['role_id'];
    switch ($role_id) {
        case 1: header("Location: admin/dashboard.php"); exit;
        case 2: header("Location: pr/dashboard.php"); exit;
        case 3: header("Location: department/dashboard.php"); exit;
        case 4: header("Location: user/dashboard.php"); exit;
    }
}
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
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .login-container {
            height: auto;
            min-height: auto;
            max-width: 700px;
            width: 100%;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 0;
        }
        .col-span-2 {
            grid-column: span 2;
        }
        .hidden {
            display: none !important;
        }
        /* Extra styles for OTP section */
        #step1-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .otp-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 15px 0;
        }
        .otp-box {
            width: 50px;
            height: 60px;
            border: 2px solid #ddd;
            border-radius: 12px;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            font-family: 'Prompt', sans-serif;
            transition: all 0.3s ease;
            outline: none;
            background: #fff;
        }
        .otp-box:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 8px rgba(74, 144, 226, 0.3);
            transform: translateY(-2px);
        }
        .otp-box.filled {
            border-color: #4a90e2;
        }
        
        .btn-otp {
            padding: 12px 20px;
            background: rgba(30, 60, 114, 0.9);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Prompt', sans-serif;
            font-size: 14px;
            transition: 0.3s;
            white-space: nowrap;
            height: 48px; /* Match input height roughly */
        }
        .btn-otp:hover {
            background: rgba(42, 82, 152, 0.9);
        }
        .btn-otp:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .login-container {
                width: 100%;
                margin: 0;
                border-radius: 0;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .col-span-2 {
                grid-column: span 1;
            }
            .otp-group {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-otp {
                width: 100%;
            }
            .otp-box {
                width: 40px;
                height: 50px;
                font-size: 20px;
            }
            .otp-inputs {
                gap: 5px;
            }
            .login-left {
                display: none;
            }
            .login-right {
                padding: 40px 20px;
            }
            .hidden-mobile {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        

        <!-- Right Side: Reset Form -->
        <div class="login-right">
            <div class="login-header">
                <!-- Language Switcher -->
                <div class="lang-switcher-login">
                    <a href="?lang=th" class="<?php echo $curr_lang == 'th' ? 'active' : ''; ?>">TH</a>
                    <span class="divider">|</span>
                    <a href="?lang=en" class="<?php echo $curr_lang == 'en' ? 'active' : ''; ?>">EN</a>
                </div>
                <h2><?php echo __('forgot_pass_title', 'ลืมรหัสผ่าน'); ?></h2>
                <p><?php echo __('forgot_pass_subtitle', 'กรุณายืนยันอีเมลเพื่อตั้งรหัสผ่านใหม่'); ?></p>
            </div>

            <!-- Alerts -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="login-alert error">
                     <i class='bx bxs-error-circle'></i>
                     <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="login-alert success">
                     <i class='bx bxs-check-circle'></i>
                     <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>

            <form action="controllers/auth_forgot.php" method="POST" id="resetForm">
                <input type="hidden" name="action" value="reset_password_otp">

                <!-- Step 1: Email Verification -->
                <div id="step1-container" class="form-grid">
                    <div class="form-group">
                        <label><?php echo __('forgot_pass_email_label', 'อีเมลที่ใช้สมัครสมาชิก'); ?></label>
                        <div class="input-icon-wrap">
                            <i class='bx bxs-envelope'></i>
                            <input type="email" name="email" id="email" class="form-control" placeholder="<?php echo __('forgot_pass_email_placeholder', 'กรุณากรอกอีเมล'); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="hidden-mobile">&nbsp;</label>
                        <button type="button" class="btn-login-submit" id="btnSendOtp" style="margin-top: 0; height: 48px;">
                             <?php echo __('forgot_pass_btn_send_otp', 'ส่งรหัสยืนยัน'); ?>
                        </button>
                    </div>

                    <div class="form-group hidden col-span-2" id="otp-section">
                        <label><?php echo __('profile_otp_label', 'รหัสยืนยัน (OTP)'); ?></label>
                        <div class="otp-inputs" id="otp-inputs-container">
                            <input type="text" maxlength="1" class="otp-box" pattern="\d*">
                            <input type="text" maxlength="1" class="otp-box" pattern="\d*">
                            <input type="text" maxlength="1" class="otp-box" pattern="\d*">
                            <input type="text" maxlength="1" class="otp-box" pattern="\d*">
                            <input type="text" maxlength="1" class="otp-box" pattern="\d*">
                            <input type="text" maxlength="1" class="otp-box" pattern="\d*">
                        </div>
                        <input type="hidden" id="otp" name="otp">
                        <div class="text-center hidden">
                            <button type="button" class="btn-otp" id="btnVerifyOtp" style="width: 200px; margin: 0 auto;"><?php echo __('profile_btn_verify', 'ยืนยัน'); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: New Password -->
                <div id="step2-container" class="hidden">
                    <div class="form-grid">
                        <div class="form-group col-span-2">
                            <div class="login-alert" style="margin-bottom: 0; background-color: #e8f5e9; color: #2e7d32; border-left-color: #4caf50;">
                                <i class='bx bxs-check-circle'></i>
                                <span><?php echo __('forgot_pass_step2_info', 'ยืนยันอีเมลสำเร็จ โปรดตั้งรหัสผ่านใหม่'); ?></span>
                            </div>
                        </div>

                        <!-- Row: Password & Confirm Password -->
                        <div class="form-group">
                            <label><?php echo __('profile_new_pass', 'รหัสผ่านใหม่'); ?></label>
                            <div class="input-icon-wrap">
                                <i class='bx bxs-lock-alt'></i>
                                <input type="password" name="password" id="password" class="form-control" placeholder="<?php echo __('profile_pass_placeholder', 'กรุณากรอกรหัสผ่านใหม่'); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><?php echo __('profile_conf_pass', 'ยืนยันรหัสผ่านใหม่'); ?></label>
                            <div class="input-icon-wrap">
                                <i class='bx bxs-lock-alt'></i>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="<?php echo __('profile_pass_placeholder', 'กรุณายืนยันรหัสผ่านใหม่อีกครั้ง'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-2">
                        <button type="submit" class="btn-login-submit" style="margin-top: 20px;">
                            <i class='bx bx-check-circle'></i> <?php echo __('forgot_pass_btn_save', 'บันทึกรหัสผ่านใหม่'); ?>
                        </button>
                    </div>
                </div>

            </form>

            <div class="login-links" style="margin-top:20px; text-align: center;">
                <a href="login.php" class="login-link"><?php echo __('forgot_pass_back_to_login', 'ย้อนกลับไปหน้าเข้าสู่ระบบ'); ?></a>
            </div>

        </div>
    </div>

    <!-- AJAX Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnSendOtp = document.getElementById('btnSendOtp');
        const btnVerifyOtp = document.getElementById('btnVerifyOtp');
        const emailInput = document.getElementById('email');
        const otpInput = document.getElementById('otp');
        const otpSection = document.getElementById('otp-section');
        const step1Container = document.getElementById('step1-container');
        const step2Container = document.getElementById('step2-container');
        const form = document.getElementById('resetForm');

        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        btnSendOtp.addEventListener('click', function() {
            const email = emailInput.value.trim();
            if (!email) {
                Swal.fire('<?php echo __('login_error_title', 'ข้อผิดพลาด'); ?>', '<?php echo __('msg_required_fields', 'กรุณากรอกอีเมล'); ?>', 'error');
                return;
            }
            if (!isValidEmail(email)) {
                Swal.fire('<?php echo __('login_error_title', 'ข้อผิดพลาด'); ?>', '<?php echo __('msg_invalid_email', 'รูปแบบอีเมลไม่ถูกต้อง'); ?>', 'error');
                return;
            }

            btnSendOtp.disabled = true;
            btnSendOtp.innerText = '<?php echo __('btn_sending', 'กำลังส่ง...'); ?>';

            const formData = new FormData();
            formData.append('email', email);

            // Using the newly created controller specifically for forgot password
            fetch('controllers/forgot_send_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btnSendOtp.disabled = false;
                btnSendOtp.innerText = '<?php echo __('forgot_pass_btn_send_otp', 'ส่งรหัสยืนยัน'); ?>';

                if (data.status === 'success') {
                    otpSection.classList.remove('hidden');
                    emailInput.readOnly = true;
                    btnSendOtp.innerText = '<?php echo __('forgot_pass_btn_resend', 'ส่งอีกครั้ง'); ?>';
                } else {
                    Swal.fire('<?php echo __('login_error_title', 'ข้อผิดพลาด'); ?>', data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                btnSendOtp.disabled = false;
                btnSendOtp.innerText = '<?php echo __('forgot_pass_btn_send_otp', 'ส่งรหัสยืนยัน'); ?>';
                Swal.fire('<?php echo __('login_error_title', 'ข้อผิดพลาด'); ?>', '<?php echo __('msg_error_occurred', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'); ?>', 'error');
            });
        });

        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length > 1) {
                    e.target.value = e.target.value.slice(0, 1);
                }
                if (e.target.value.length === 1 && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                updateHiddenOtp();

                // Auto verify if all 6 digits are filled
                const otp = otpHiddenInput.value.trim();
                if (otp.length === 6) {
                    verifyOtp();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });

            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').slice(0, 6).split('');
                pasteData.forEach((char, i) => {
                    if (otpInputs[i]) {
                        otpInputs[i].value = char;
                    }
                });
                updateHiddenOtp();

                const otp = otpHiddenInput.value.trim();
                if (otp.length === 6) {
                    verifyOtp();
                } else {
                    const nextFocus = Math.min(pasteData.length, 5);
                    otpInputs[nextFocus].focus();
                }
            });
        });

        function updateHiddenOtp() {
            let combined = '';
            otpInputs.forEach(input => {
                combined += input.value;
                if (input.value) {
                    input.classList.add('filled');
                } else {
                    input.classList.remove('filled');
                }
            });
            otpHiddenInput.value = combined;
        }

        function verifyOtp() {
            const email = emailInput.value.trim();
            const otp = otpHiddenInput.value.trim();

            if (otp.length < 6) return;

            btnVerifyOtp.disabled = true;
            btnVerifyOtp.innerText = '<?php echo __('btn_verifying', 'กำลังตรวจสอบ...'); ?>';

            const formData = new FormData();
            formData.append('email', email);
            formData.append('otp', otp);

            // We can reuse the same verification controller as registration
            fetch('controllers/verify_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btnVerifyOtp.disabled = false;
                btnVerifyOtp.innerText = '<?php echo __('profile_btn_verify', 'ยืนยัน'); ?>';

                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo __('msg_success', 'สำเร็จ'); ?>',
                        text: '<?php echo __('msg_otp_verified', 'ยืนยันตัวตนสำเร็จ'); ?>',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    // Proceed to Step 2
                    step1Container.classList.add('hidden');
                    step2Container.classList.remove('hidden');
                } else {
                    Swal.fire('<?php echo __('login_error_title', 'ข้อผิดพลาด'); ?>', data.message, 'error');
                    // Clear OTP on error to allow retry
                    otpInputs.forEach(input => input.value = '');
                    otpHiddenInput.value = '';
                    updateHiddenOtp();
                    otpInputs[0].focus();
                }
            })
            .catch(err => {
                console.error(err);
                btnVerifyOtp.disabled = false;
                btnVerifyOtp.innerText = '<?php echo __('profile_btn_verify', 'ยืนยัน'); ?>';
                Swal.fire('<?php echo __('login_error_title', 'ข้อผิดพลาด'); ?>', '<?php echo __('msg_error_occurred', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'); ?>', 'error');
            });
        }

        btnVerifyOtp.addEventListener('click', verifyOtp);

        // Add form submit checking just in case
        form.addEventListener('submit', function(e) {
            if (step2Container.classList.contains('hidden')) {
                e.preventDefault();
                Swal.fire('<?php echo __('login_error_title', 'ข้อผิดพลาด'); ?>', '<?php echo __('msg_otp_verify_required', 'กรุณายืนยันรหัส OTP ก่อนตั้งรหัสผ่านใหม่'); ?>', 'warning');
            }
        });
    });
    </script>
    
    <!-- Background Animation -->
    <script src="assets/js/login-particles.js"></script>

</body>
</html>
