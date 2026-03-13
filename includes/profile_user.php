<?php
/**
 * includes/profile_user.php
 * Reusable User Profile UI (Standalone & Modal)
 * Aligned with new Employee Profile Design (Tabbed)
 */

$is_modal = $is_modal ?? false;

// Fetch data if not provided (Standalone or Modal)
if (!isset($user) || !isset($total_complaints) || !isset($completed_complaints)) {
    if (!isset($db)) {
        require_once __DIR__ . '/../config/database.php';
        $db = Database::connect();
    }
    
    $fetch_user_id = $user_id ?? $_SESSION['user_id'] ?? null;
    
    if ($fetch_user_id) {
        if (!isset($user)) {
            $stmt = $db->prepare("
                SELECT u.*, r.role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$fetch_user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Stats
        if (!isset($total_complaints)) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM complaints WHERE user_id = ?");
            $stmt->execute([$fetch_user_id]);
            $total_complaints = $stmt->fetchColumn();
        }

        if (!isset($completed_complaints)) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM complaints WHERE user_id = ? AND status = 'completed'");
            $stmt->execute([$fetch_user_id]);
            $completed_complaints = $stmt->fetchColumn();
        }
    }
}

// Name Splitting Logic
$fullName = $user['full_name'] ?? $_SESSION['full_name'] ?? '';
$parts = explode(' ', $fullName, 2);
$firstName = $parts[0] ?? '';
$lastName = $parts[1] ?? '';

// Occupation Options
$occupation_options = [
    'student', 'university_student', 'government_officer', 'employee', 
    'state_enterprise', 'business_owner', 'freelancer', 'merchant', 
    'farmer', 'engineer', 'doctor', 'nurse', 'teacher', 
    'programmer', 'designer', 'technician', 'homemaker', 
    'unemployed', 'retired', 'other'
];
$current_occupation = $user['occupation'] ?? '';
$is_custom_occupation = !in_array($current_occupation, $occupation_options) && !empty($current_occupation);
$selected_option = $is_custom_occupation ? 'other' : $current_occupation;
$custom_value = $is_custom_occupation ? $current_occupation : '';
?>

<style>
    .profile-card {
        background: white;
        border-radius: 2rem;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.03);
    }

    .cover-header {
        height: 160px;
        background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
        position: relative;
    }

    .cover-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.6;
    }

    .avatar-wrapper {
        position: relative;
        margin-top: -64px;
        margin-bottom: 24px;
    }

    .avatar-main {
        width: 128px;
        height: 128px;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        background: white;
        overflow: hidden;
    }

    .camera-trigger {
        position: absolute;
        bottom: 4px;
        right: 4px;
        background: #ec4899;
        color: white;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 3px solid white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .camera-trigger:hover {
        background: #db2777;
        transform: scale(1.1);
    }

    .tab-btn {
        padding-bottom: 0.75rem;
        border-bottom: 2px solid transparent;
        font-weight: 600;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
        transition: all 0.3s ease;
    }

    .tab-btn.active {
        border-color: #000;
        color: #1e293b;
    }

    .form-input-custom {
        width: 100%;
        padding: 0.625rem 1rem;
        background-color: #f8fafc;
        border: 1px solid transparent;
        border-radius: 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #334155;
        transition: all 0.2s ease;
        outline: none;
    }

    .form-input-custom:focus {
        background-color: white;
        border-color: #000;
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
    }

    .form-input-custom:disabled {
        background-color: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .btn-action-outline {
        padding: 0.5rem 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 500;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }

    .btn-action-outline:hover {
        background-color: #f8fafc;
        border-color: #cbd5e1;
        color: #1e293b;
    }

    .btn-action-primary {
        padding: 0.625rem 1.5rem;
        background-color: #000;
        color: white;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .btn-action-primary:hover {
        background-color: #1e293b;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.15);
    }

    /* Standalone Container */
    .standalone-container {
        min-height: 100vh;
        background-color: #f3f4f6;
        padding: 4rem 1rem;
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    /* Modal Context */
    <?php if ($is_modal): ?>
    .profile-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        align-items: center;
        justify-content: center;
        z-index: 100;
        padding: 1rem;
    }
    .profile-modal-overlay:not(.hidden) {
        display: flex;
    }
    .profile-modal-container {
        width: 100%;
        max-width: 640px;
        max-height: 90vh;
        overflow-y: auto;
    }
    <?php endif; ?>
</style>

<?php if ($is_modal): ?>
<div id="userProfileModal" class="profile-modal-overlay hidden" onclick="event.target === this && closeUserProfileModal()">
    <div class="profile-modal-container">
<?php else: ?>
<div class="standalone-container">
    <div class="w-full max-w-2xl">
<?php endif; ?>

    <div class="profile-card relative animate-slide-up">
        <!-- Close Button (Modal Only) -->
        <?php if ($is_modal): ?>
        <button onclick="closeUserProfileModal()" class="absolute top-4 right-4 z-10 bg-black/20 hover:bg-black/40 text-white p-2 rounded-full transition-all">
            <i class='bx bx-x text-xl'></i>
        </button>
        <?php endif; ?>

        <!-- Cover Photo -->
        <div class="cover-header">
            <img src="https://images.unsplash.com/photo-1542831371-29b0f74f9713?q=80&w=2070&auto=format&fit=crop" class="cover-image" alt="Cover">
        </div>

        <div class="px-8 pb-8">
            <form id="userProfileForm_Shared" enctype="multipart/form-data">
                <input type="hidden" name="original_email" id="original_email" value="<?php echo htmlspecialchars($user['email']); ?>">
                <div class="flex flex-col items-start mb-4">
                    <!-- Avatar Section -->
                    <div class="avatar-wrapper">
                        <div class="avatar-main">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img id="profile_image_preview" src="<?php echo $base_path; ?>assets/img/profile/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div id="profile_image_placeholder" class="w-full h-full bg-gray-100 flex items-center justify-center text-gray-400">
                                    <i class='bx bxs-user text-6xl'></i>
                                </div>
                                <img id="profile_image_preview" src="" alt="Profile" class="w-full h-full object-cover hidden">
                            <?php endif; ?>
                        </div>
                        <label for="profile_upload_input" class="camera-trigger" title="Update Profile Picture">
                            <i class='bx bxs-camera text-sm'></i>
                        </label>
                        <input type="file" name="profile_image" id="profile_upload_input" class="hidden" accept="image/*" onchange="previewProfileImage(this)">
                    </div>
                </div>

                <!-- User Header Info -->
                <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                            <?php echo htmlspecialchars($user['full_name']); ?>
                            <i class='bx bxs-badge-check text-blue-500 text-xl' title="<?php echo __('profile_verified_user', 'Verified User'); ?>"></i>
                        </h2>
                    </div>
                    <div class="flex gap-2">
                        <a href="<?php echo $base_path; ?>logout.php" class="btn-action-outline hover:text-red-600 hover:bg-red-50 hover:border-red-100">
                            <i class='bx bx-log-out'></i> <?php echo __('header_logout', 'Sign out'); ?>
                        </a>
                    </div>
                </div>

                <!-- Tabbed Navigation -->
                <div class="flex gap-6 border-b border-slate-100 mb-8 overflow-x-auto pb-0.5">
                    <button type="button" onclick="switchUserTab('profile')" id="user-tab-btn-profile" class="tab-btn active">
                        <i class='bx bx-user'></i> <?php echo __('profile_tab_profile', 'Profile'); ?>
                    </button>
                    <button type="button" onclick="switchUserTab('security')" id="user-tab-btn-security" class="tab-btn">
                        <i class='bx bx-shield-quarter'></i> <?php echo __('profile_tab_security', 'Security'); ?>
                    </button>
                    <!-- Job Info removed - Role is in header, Occupation moved to Profile -->
                </div>

                <!-- Status Message -->
                <div id="userProfileMessage" class="hidden mb-6 p-4 rounded-xl text-sm font-medium border"></div>

                <!-- Tab Contents -->
                <div id="user-tab-content-profile" class="space-y-6">
                    <!-- Full Name -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <label class="md:col-span-3 text-sm font-bold text-slate-800"><?php echo __('profile_full_name', 'Full Name'); ?></label>
                        <div class="md:col-span-9 grid grid-cols-2 gap-4">
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($firstName); ?>" placeholder="<?php echo __('profile_first_name', 'First Name'); ?>" required class="form-input-custom">
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>" placeholder="<?php echo __('profile_last_name', 'Last Name'); ?>" required class="form-input-custom">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <label class="md:col-span-3 text-sm font-bold text-slate-800"><?php echo __('profile_email', 'Email'); ?></label>
                        <div class="md:col-span-9 relative">
                            <span class="absolute inset-y-2 left-0 w-1 bg-black rounded-r-full"></span>
                            <div class="flex gap-2">
                                <input type="email" name="email" id="userEmailInput" value="<?php echo htmlspecialchars($user['email']); ?>" required class="form-input-custom pl-4 !bg-white !border-slate-200" oninput="checkEmailChange()">
                                <button type="button" id="sendEmailOtpBtn" onclick="requestEmailOTP()" class="hidden px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-xl hover:bg-slate-700 transition-all shrink-0">
                                    <?php echo __('profile_btn_send_otp', 'Send OTP'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- OTP Verification Group (Hidden by default) -->
                    <div id="emailOtpGroup" class="hidden grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <div class="md:col-start-4 md:col-span-9">
                            <div class="flex gap-2 bg-blue-50/50 p-4 rounded-2xl border border-blue-100/50">
                                <div class="flex-1">
                                    <label class="block text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-1 px-1"><?php echo __('profile_otp_label', 'Verification Code'); ?></label>
                                    <input type="text" id="emailOtpCode" placeholder="######" class="form-input-custom !bg-white text-center tracking-[0.5em] font-mono text-lg">
                                </div>
                                <button type="button" onclick="verifyEmailOTP()" class="px-6 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition-all shadow-sm">
                                    <?php echo __('profile_btn_verify', 'Verify'); ?>
                                </button>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-500 px-2 flex items-center gap-1">
                                <i class='bx bx-info-circle'></i> <?php echo __('profile_otp_info', 'Verification required for email change'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Username (Relocated from Job Info) -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <label class="md:col-span-3 text-sm font-bold text-slate-800"><?php echo __('profile_username', 'Username'); ?></label>
                        <div class="md:col-span-9">
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required class="form-input-custom">
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <label class="md:col-span-3 text-sm font-bold text-slate-800"><?php echo __('profile_phone', 'Phone Number'); ?></label>
                        <div class="md:col-span-9 relative">
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="<?php echo __('profile_phone_placeholder', 'Enter phone number'); ?>" class="form-input-custom pr-10">
                            <i class='bx bx-phone absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg'></i>
                        </div>
                    </div>

                    <!-- Occupation -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <label class="md:col-span-3 text-sm font-bold text-slate-800"><?php echo __('profile_occupation', 'Occupation'); ?></label>
                        <div class="md:col-span-9 relative">
                        <select name="occupation" id="userOccSelect" onchange="toggleUserOccOther()" class="form-input-custom appearance-none cursor-pointer">
                            <option value=""><?php echo __('submit_select_occupation_default', '-- Select Occupation --'); ?></option>
                            <?php foreach ($occupation_options as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo $selected_option === $opt ? 'selected' : ''; ?>>
                                    <?php echo __('occ_' . $opt, $opt); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class='bx bx-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none'></i>
                        </div>
                    </div>
                    <div id="userOccOtherDiv" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center <?php echo $selected_option === 'other' ? '' : 'hidden'; ?>">
                        <div class="md:col-start-4 md:col-span-9">
                            <input type="text" name="occupation_other" value="<?php echo htmlspecialchars($custom_value); ?>" placeholder="<?php echo __('profile_occ_specify', 'Specify Occupation'); ?>" class="form-input-custom">
                        </div>
                    </div>

                </div>

                <div id="user-tab-content-security" class="hidden space-y-6">
                    <!-- Current Password -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <label class="md:col-span-3 text-sm font-bold text-slate-800"><?php echo __('profile_curr_pass', 'Current Password'); ?></label>
                        <div class="md:col-span-9 relative">
                            <input type="password" name="current_password" id="u_curr_pass" placeholder="<?php echo __('profile_pass_placeholder', 'Enter current password'); ?>" class="form-input-custom pr-10">
                            <span class="absolute inset-y-0 right-3 flex items-center cursor-pointer text-slate-400 hover:text-slate-600" onclick="togglePass('u_curr_pass', this)">
                                <i class='bx bx-show text-lg'></i>
                            </span>
                        </div>
                    </div>
                    <!-- New Password -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <label class="md:col-span-3 text-sm font-bold text-slate-800"><?php echo __('profile_new_pass', 'New Password'); ?></label>
                        <div class="md:col-span-9 relative">
                            <input type="password" name="new_password" id="u_new_pass" placeholder="<?php echo __('profile_pass_placeholder', 'Enter new password'); ?>" class="form-input-custom pr-10">
                            <span class="absolute inset-y-0 right-3 flex items-center cursor-pointer text-slate-400 hover:text-slate-600" onclick="togglePass('u_new_pass', this)">
                                <i class='bx bx-show text-lg'></i>
                            </span>
                        </div>
                    </div>
                    <!-- Confirm Password -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <label class="md:col-span-3 text-sm font-bold text-slate-800"><?php echo __('profile_conf_pass', 'Confirm Password'); ?></label>
                        <div class="md:col-span-9 relative">
                            <input type="password" name="confirm_password" id="u_conf_pass" placeholder="<?php echo __('profile_pass_placeholder', 'Confirm new password'); ?>" class="form-input-custom pr-10">
                            <span class="absolute inset-y-0 right-3 flex items-center cursor-pointer text-slate-400 hover:text-slate-600" onclick="togglePass('u_conf_pass', this)">
                                <i class='bx bx-show text-lg'></i>
                            </span>
                        </div>
                    </div>
                </div>


                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-8 border-t border-slate-100 mt-8">
                    <?php if ($is_modal): ?>
                    <button type="button" onclick="closeUserProfileModal()" class="px-6 py-2 bg-white text-slate-600 text-sm font-bold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all">
                        <?php echo __('profile_btn_close', 'Close'); ?>
                    </button>
                    <?php endif; ?>
                    <button type="submit" id="userProfileSaveBtn" class="btn-action-primary flex items-center gap-2">
                        <i class='bx bx-save'></i> <?php echo __('profile_btn_save', 'Save'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php if ($is_modal): ?>
    </div>
</div>
<?php else: ?>
    </div>
</div>
<?php endif; ?>

<script>
let currentUserTab = 'profile';
let isEmailVerified = false;

function checkEmailChange() {
    const currentEmail = document.getElementById('userEmailInput').value;
    const originalEmail = document.getElementById('original_email').value;
    const sendBtn = document.getElementById('sendEmailOtpBtn');
    const otpGroup = document.getElementById('emailOtpGroup');

    if (currentEmail !== originalEmail) {
        sendBtn.classList.remove('hidden');
        isEmailVerified = false;
    } else {
        sendBtn.classList.add('hidden');
        otpGroup.classList.add('hidden');
        isEmailVerified = true;
    }
}

function requestEmailOTP() {
    const email = document.getElementById('userEmailInput').value;
    const btn = document.getElementById('sendEmailOtpBtn');
    const originalText = btn.innerHTML;
    
    if (!email || !email.includes('@')) {
        alert('<?php echo __('msg_invalid_email', 'Please enter a valid email address'); ?>');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i>';

    const formData = new FormData();
    formData.append('email', email);

    fetch('<?php echo $base_path; ?>controllers/send_otp.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('emailOtpGroup').classList.remove('hidden');
            btn.classList.add('hidden');
            alert(data.message);
        } else {
            alert(data.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        alert('<?php echo __('msg_otp_send_error', 'Error sending OTP'); ?>');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function verifyEmailOTP() {
    const email = document.getElementById('userEmailInput').value;
    const otp = document.getElementById('emailOtpCode').value;
    
    if (!otp) {
        alert('<?php echo __('msg_required_fields', 'Please enter OTP code'); ?>');
        return;
    }

    const formData = new FormData();
    formData.append('email', email);
    formData.append('otp', otp);

    fetch('<?php echo $base_path; ?>controllers/verify_otp.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            isEmailVerified = true;
            document.getElementById('emailOtpGroup').innerHTML = `
                <div class="md:col-start-4 md:col-span-9">
                    <div class="flex items-center gap-2 text-emerald-600 bg-emerald-50 p-3 rounded-xl border border-emerald-100 text-sm font-bold">
                        <i class='bx bxs-check-circle text-lg'></i> ${data.message}
                    </div>
                </div>
            `;
            document.getElementById('userEmailInput').readOnly = true;
            document.getElementById('userEmailInput').classList.add('!bg-slate-50');
        } else {
            alert(data.message);
        }
    })
    .catch(err => alert('<?php echo __('msg_otp_verify_error', 'Error verifying OTP'); ?>'));
}

function switchUserTab(tab) {
    currentUserTab = tab;
    const tabs = ['profile', 'security'];
    const saveBtn = document.getElementById('userProfileSaveBtn');
    
    // In "job" tab, we might want to disable save if everything is read-only, 
    // but here occupation is editable, so we keep it.
    
    tabs.forEach(t => {
        const btn = document.getElementById(`user-tab-btn-${t}`);
        const content = document.getElementById(`user-tab-content-${t}`);
        if (!btn || !content) return;

        if (t === tab) {
            btn.classList.add('active');
            content.classList.remove('hidden');
            // Enable inputs
            content.querySelectorAll('input, select, textarea').forEach(el => {
                if (!el.hasAttribute('disabled')) el.disabled = false;
            });
        } else {
            btn.classList.remove('active');
            content.classList.add('hidden');
            // Disable inputs so they don't get submitted
            content.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
        }
    });

    document.getElementById('userProfileMessage').classList.add('hidden');
}

function previewProfileImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('profile_image_preview');
            const placeholder = document.getElementById('profile_image_placeholder');
            if (preview) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            }
            if (placeholder) placeholder.classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function togglePass(id, btn) {
    const input = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bx-show', 'bx-hide');
    } else {
        input.type = 'password';
        icon.classList.replace('bx-hide', 'bx-show');
    }
}

function toggleUserOccOther() {
    const select = document.getElementById('userOccSelect');
    const otherDiv = document.getElementById('userOccOtherDiv');
    if (select.value === 'other') {
        otherDiv.classList.remove('hidden');
        otherDiv.querySelector('input').disabled = false;
    } else {
        otherDiv.classList.add('hidden');
        otherDiv.querySelector('input').disabled = true;
    }
}

// Global Modal Control (Standalone might not have these functions yet)
function openUserProfileModal() {
    const modal = document.getElementById('userProfileModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        switchUserTab('profile');
    }
}

function closeUserProfileModal() {
    const modal = document.getElementById('userProfileModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// Form Submission
document.getElementById('userProfileForm_Shared')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const currentEmail = document.getElementById('userEmailInput').value;
    const originalEmail = document.getElementById('original_email').value;

    if (currentUserTab === 'profile' && currentEmail !== originalEmail && !isEmailVerified) {
        const msg = document.getElementById('userProfileMessage');
        msg.classList.remove('hidden');
        msg.className = 'mb-6 p-4 rounded-xl font-medium border bg-amber-50 text-amber-700 border-amber-100';
        msg.innerHTML = '<i class="bx bxs-info-circle mr-2"></i> <?php echo __('msg_email_verify_required', 'Please verify your new email address with OTP first.'); ?>';
        document.getElementById('emailOtpGroup').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    const formData = new FormData(this);
    const btn = document.getElementById('userProfileSaveBtn');
    const msg = document.getElementById('userProfileMessage');
    const originalText = btn.innerHTML;

    let url = '<?php echo $base_path; ?>controllers/update_user_full.php';
    
    // Combining names for profile tab
    if (currentUserTab === 'profile') {
        const firstName = formData.get('first_name') || '';
        const lastName = formData.get('last_name') || '';
        formData.append('full_name', (firstName + ' ' + lastName).trim());
    } else if (currentUserTab === 'security') {
        url = '<?php echo $base_path; ?>controllers/update_password.php';
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> <?php echo __('profile_btn_saving', 'Saving...'); ?>';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        msg.classList.remove('hidden');
        if (data.success) {
            msg.className = 'mb-6 p-4 rounded-xl font-medium border bg-emerald-50 text-emerald-700 border-emerald-100';
            msg.innerHTML = '<i class="bx bxs-check-circle mr-2"></i> ' + data.message;
            
            if (currentUserTab === 'profile' || currentUserTab === 'job') {
                setTimeout(() => window.location.href = '<?php echo $base_path; ?>index.php', 1500);
            } else {
                this.querySelectorAll('#user-tab-content-security input').forEach(el => el.value = '');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } else {
            msg.className = 'mb-6 p-4 rounded-xl font-medium border bg-red-50 text-red-700 border-red-100';
            msg.innerHTML = '<i class="bx bxs-error-circle mr-2"></i> ' + data.message;
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        msg.classList.remove('hidden');
        msg.className = 'mb-6 p-4 rounded-xl font-medium border bg-red-50 text-red-700 border-red-100';
        msg.innerHTML = '<i class="bx bxs-error-circle mr-2"></i> <?php echo __('msg_error_occurred', 'An error occurred.'); ?>';
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>
