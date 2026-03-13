<?php
// register.php
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
            width: 700px;
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
        
        @media (max-width: 768px) {
            .otp-box {
                width: 40px;
                height: 50px;
                font-size: 20px;
            }
            .otp-inputs {
                gap: 5px;
            }
        }
        
        @media (max-width: 720px) {
            .login-container {
                width: 90%;
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
            .hidden-mobile {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <!-- Right Side: Registration Form -->
        <div class="login-right" style="width: 100%; border-radius: 20px;">
            <div class="login-header">
                <!-- Language Switcher -->
                <div class="lang-switcher-login">
                    <a href="?lang=th" class="<?php echo $curr_lang == 'th' ? 'active' : ''; ?>">TH</a>
                    <span class="divider">|</span>
                    <a href="?lang=en" class="<?php echo $curr_lang == 'en' ? 'active' : ''; ?>">EN</a>
                </div>
                <h2><?php echo __('register_title', 'สมัครสมาชิก'); ?></h2>
                <p><?php echo __('register_subtitle', 'กรุณากรอกข้อมูลเพื่อสร้างบัญชี'); ?></p>
            </div>

            <!-- Alerts -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="login-alert error">
                     <i class='bx bxs-error-circle'></i>
                     <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <form action="controllers/auth_register.php" method="POST" id="registerForm">
                
                <!-- Step 1: Email Verification -->
                <div id="step1-container" class="form-grid">
                    <div class="form-group">
                        <label><?php echo __('register_email_label', 'อีเมล'); ?></label>
                        <div class="input-icon-wrap">
                            <i class='bx bxs-envelope'></i>
                            <input type="email" name="email" id="email" class="form-control" placeholder="<?php echo __('register_email_placeholder', 'กรุณากรอกอีเมล'); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="hidden-mobile">&nbsp;</label>
                        <button type="button" class="btn-login-submit" id="btnSendOtp" style="margin-top: 0; height: 48px;">
                            <?php echo __('register_btn_send_otp', 'ส่งรหัสยืนยัน'); ?>
                        </button>
                    </div>

                    <div class="form-group hidden col-span-2" id="otp-section">
                        <label><?php echo __('register_otp_label', 'รหัสยืนยัน (OTP)'); ?></label>
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
                            <button type="button" class="btn-otp" id="btnVerifyOtp" style="width: 200px; margin: 0 auto;"><?php echo __('register_btn_verify', 'ยืนยัน'); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Personal Information -->
                <div id="step2-container" class="hidden">
                    <div class="form-grid">
                        <div class="form-group col-span-2">
                            <div class="login-alert" style="margin-bottom: 0; background-color: #e8f5e9; color: #2e7d32; border-left-color: #4caf50;">
                                <i class='bx bxs-check-circle'></i>
                                <span><?php echo __('register_email_verified_success', 'อีเมลได้รับการยืนยันแล้ว'); ?> (<span id="verified-email-display"></span>)</span>
                            </div>
                        </div>

                        <!-- Row 1: First Name & Last Name -->
                        <div class="form-group">
                            <label><?php echo __('profile_first_name', 'ชื่อ'); ?></label>
                            <div class="input-icon-wrap">
                                <i class='bx bxs-user'></i>
                                <input type="text" name="first_name" class="form-control" placeholder="<?php echo __('profile_first_name_placeholder', 'กรุณากรอกชื่อ'); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><?php echo __('profile_last_name', 'นามสกุล'); ?></label>
                            <div class="input-icon-wrap">
                                <i class='bx bxs-user-detail'></i>
                                <input type="text" name="last_name" class="form-control" placeholder="<?php echo __('profile_last_name_placeholder', 'กรุณากรอกนามสกุล'); ?>" required>
                            </div>
                        </div>

                        <!-- Row 2: Username & Phone -->
                        <div class="form-group">
                            <label><?php echo __('login_username_label', 'ชื่อผู้ใช้งาน'); ?></label>
                            <div class="input-icon-wrap">
                                <i class='bx bxs-user'></i>
                                <input type="text" name="username" class="form-control" placeholder="<?php echo __('login_username_placeholder', 'กรุณากรอกชื่อผู้ใช้งาน'); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><?php echo __('profile_phone', 'เบอร์โทรศัพท์'); ?></label>
                            <div class="input-icon-wrap">
                                <i class='bx bxs-phone'></i>
                                <input type="tel" name="phone" id="phone" class="form-control" placeholder="<?php echo __('profile_phone_placeholder', 'กรุณากรอกเบอร์โทรศัพท์'); ?>" required>
                            </div>
                        </div>

                        <!-- Row 3: Occupation -->
                        <div class="form-group col-span-2">
                            <label><?php echo __('profile_occ_title', 'อาชีพ'); ?></label>
                            <div class="input-icon-wrap">
                                <i class='bx bxs-briefcase'></i>
                                <select name="occupation" id="occupation" class="form-control" required style="appearance: auto; padding-left: 45px;">
                                    <option value=""><?php echo __('profile_occ_select_default', '-- เลือกอาชีพ --'); ?></option>
                                    <option value="<?php echo __('profile_occ_student', 'นักเรียน'); ?>"><?php echo __('profile_occ_student', 'นักเรียน'); ?></option>
                                    <option value="<?php echo __('profile_occ_undergraduate', 'นักศึกษา'); ?>"><?php echo __('profile_occ_undergraduate', 'นักศึกษา'); ?></option>
                                    <option value="<?php echo __('profile_occ_gov_officer', 'ข้าราชการ'); ?>"><?php echo __('profile_occ_gov_officer', 'ข้าราชการ'); ?></option>
                                    <option value="<?php echo __('profile_occ_company_emp', 'พนักงานบริษัท'); ?>"><?php echo __('profile_occ_company_emp', 'พนักงานบริษัท'); ?></option>
                                    <option value="<?php echo __('profile_occ_state_emp', 'พนักงานรัฐวิสาหกิจ'); ?>"><?php echo __('profile_occ_state_emp', 'พนักงานรัฐวิสาหกิจ'); ?></option>
                                    <option value="<?php echo __('profile_occ_business', 'ธุรกิจส่วนตัว'); ?>"><?php echo __('profile_occ_business', 'ธุรกิจส่วนตัว'); ?></option>
                                    <option value="<?php echo __('profile_occ_freelance', 'ฟรีแลนซ์'); ?>"><?php echo __('profile_occ_freelance', 'ฟรีแลนซ์'); ?></option>
                                    <option value="<?php echo __('profile_occ_merchant', 'ค้าขาย'); ?>"><?php echo __('profile_occ_merchant', 'ค้าขาย'); ?></option>
                                    <option value="<?php echo __('profile_occ_farmer', 'เกษตรกร'); ?>"><?php echo __('profile_occ_farmer', 'เกษตรกร'); ?></option>
                                    <option value="<?php echo __('profile_occ_engineer', 'วิศวกร'); ?>"><?php echo __('profile_occ_engineer', 'วิศวกร'); ?></option>
                                    <option value="other"><?php echo __('profile_occ_other', 'อื่น ๆ'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group col-span-2 hidden" id="occupation-other-wrap">
                            <label><?php echo __('profile_occ_specify', 'ระบุอาชีพ'); ?></label>
                            <div class="input-icon-wrap">
                                <i class='bx bx-edit-alt'></i>
                                <input type="text" name="occupation_other" id="occupation_other" class="form-control" placeholder="<?php echo __('profile_occ_placeholder', 'กรุณากรอกอาชีพ'); ?>">
                            </div>
                        </div>

                        <!-- Row 3: Password & Confirm Password -->
                        <div class="form-group">
                            <label><?php echo __('login_password_label', 'รหัสผ่าน'); ?></label>
                            <div class="input-icon-wrap">
                                <i class='bx bxs-lock-alt'></i>
                                <input type="password" name="password" id="password" class="form-control" placeholder="<?php echo __('login_password_placeholder', 'กรุณากรอกรหัสผ่าน'); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><?php echo __('profile_conf_pass', 'ยืนยันรหัสผ่าน'); ?></label>
                            <div class="input-icon-wrap">
                                <i class='bx bxs-lock-alt'></i>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="<?php echo __('profile_pass_placeholder', 'กรุณายืนยันรหัสผ่าน'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-login-submit" style="margin-top: 20px;">
                        <i class='bx bx-user-plus'></i> <?php echo __('register_btn_submit', 'สมัครสมาชิก'); ?>
                    </button>
                </div>

            </form>

            <div class="login-links" style="margin-top:20px; text-align: center;">
                <span style="color: #666;"><?php echo __('register_already_have_account', 'มีบัญชีอยู่แล้ว?'); ?></span>
                <a href="login.php" class="login-link"><?php echo __('login_btn_submit', 'เข้าสู่ระบบ'); ?></a>
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
        const verifiedEmailDisplay = document.getElementById('verified-email-display');
        const form = document.getElementById('registerForm');

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

            fetch('controllers/send_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btnSendOtp.disabled = false;
                btnSendOtp.innerText = '<?php echo __('register_btn_send_otp', 'ส่งรหัสยืนยัน'); ?>';

                if (data.status === 'success') {
                    otpSection.classList.remove('hidden');
                    emailInput.readOnly = true;
                    btnSendOtp.innerText = '<?php echo __('register_btn_resend', 'ส่งอีกครั้ง'); ?>';
                } else {
                    Swal.fire('<?php echo __('login_error_title', 'ข้อผิดพลาด'); ?>', data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                btnSendOtp.disabled = false;
                btnSendOtp.innerText = '<?php echo __('register_btn_send_otp', 'ส่งรหัสยืนยัน'); ?>';
                Swal.fire('<?php echo __('login_error_title', 'ข้อผิดพลาด'); ?>', '<?php echo __('msg_error_occurred', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'); ?>', 'error');
            });
        });

        const otpInputs = document.querySelectorAll('.otp-box');
        const otpHiddenInput = document.getElementById('otp');

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

            fetch('controllers/verify_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btnVerifyOtp.disabled = false;
                btnVerifyOtp.innerText = '<?php echo __('register_btn_verify', 'ยืนยัน'); ?>';

                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo __('msg_success', 'สำเร็จ'); ?>',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    // Proceed to Step 2
                    step1Container.classList.add('hidden');
                    step2Container.classList.remove('hidden');
                    verifiedEmailDisplay.innerText = email;
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
                btnVerifyOtp.innerText = '<?php echo __('register_btn_verify', 'ยืนยัน'); ?>';
                Swal.fire('<?php echo __('login_error_title', 'ข้อผิดพลาด'); ?>', '<?php echo __('msg_error_occurred', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'); ?>', 'error');
            });
        }

        btnVerifyOtp.addEventListener('click', verifyOtp);

        // Occupation Toggle
        const occSelect = document.getElementById('occupation');
        const occOtherWrap = document.getElementById('occupation-other-wrap');
        const occOtherInput = document.getElementById('occupation_other');

        if (occSelect) {
            occSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    occOtherWrap.classList.remove('hidden');
                    occOtherInput.setAttribute('required', 'required');
                } else {
                    occOtherWrap.classList.add('hidden');
                    occOtherInput.removeAttribute('required');
                    occOtherInput.value = '';
                }
            });
        }

        // Add form submit checking just in case
        form.addEventListener('submit', function(e) {
            if (step2Container.classList.contains('hidden')) {
                e.preventDefault();
                Swal.fire('<?php echo __('login_error_title', 'ข้อผิดพลาด'); ?>', '<?php echo __('msg_email_verify_required', 'กรุณายืนยันอีเมลก่อนทำรายการ'); ?>', 'warning');
            }
        });
    });
    </script>

</body>
</html>
