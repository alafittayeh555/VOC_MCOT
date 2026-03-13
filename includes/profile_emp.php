<?php
// includes/profile_emp.php

// Fetch fresh user data for the modal
if (isset($_SESSION['user_id']) && !isset($emp_data)) {
    if (!isset($db)) {
        require_once __DIR__ . '/../config/database.php';
        $db = Database::connect();
    }
    
    // Fetch user with Role and Department names
    // Note: Department ID might be NULL for some roles (e.g. Admin)
    $stmt_emp = $db->prepare("
        SELECT u.*, r.role_name, d.name as department_name 
        FROM employees u 
        LEFT JOIN roles r ON u.role_id = r.id 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE u.id = ?
    ");
    $stmt_emp->execute([$_SESSION['user_id']]);
    $emp_data = $stmt_emp->fetch(PDO::FETCH_ASSOC);

    // If not found in employees table (maybe checking 'users' table if migration incomplete, but we assume employees now)
    if (!$emp_data && isset($_SESSION['role_id']) && $_SESSION['role_id'] != 4) {
         // Fallback or error handling if needed
    }
}
?>

<!-- Profile Modal -->
<div id="profileModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl mx-4 transform transition-all duration-300 scale-100 overflow-hidden">
        
        <!-- Close Button (Absolute) -->
        <button onclick="document.getElementById('profileModal').classList.add('hidden')" class="absolute top-4 right-4 z-10 text-white bg-black bg-opacity-20 hover:bg-opacity-40 rounded-full p-2 transition-all focus:outline-none">
            <i class='bx bx-x text-xl'></i>
        </button>

        <!-- Cover Image -->
        <div class="h-40 w-full bg-gradient-to-r from-slate-400 to-slate-500 relative">
             <!-- Optional: Actual cover image if available, else gradient -->
             <img src="https://images.unsplash.com/photo-1542831371-29b0f74f9713?q=80&w=2070&auto=format&fit=crop" alt="Cover" class="w-full h-full object-cover opacity-60">
        </div>

        <!-- Header / Avatar Section -->
        <div class="px-8 relative">
            <form id="profileForm" enctype="multipart/form-data"> <!-- Form starts here to capture inputs -->
            <div class="flex flex-col items-start -mt-16 mb-4">
                <!-- Avatar with Upload -->
                <div class="relative group cursor-pointer" onclick="document.getElementById('emp_profile_upload').click()">
                    <div class="w-32 h-32 rounded-full border-4 border-white shadow-lg overflow-hidden bg-white">
                        <?php if (!empty($emp_data['profile_image'])): ?>
                            <img id="emp_profile_preview" src="<?php echo $base_path . 'assets/img/profile/' . htmlspecialchars($emp_data['profile_image']); ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div id="emp_profile_placeholder" class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-400">
                                <i class='bx bxs-user text-6xl'></i>
                            </div>
                            <img id="emp_profile_preview" src="" alt="Profile" class="w-full h-full object-cover hidden">
                        <?php endif; ?>
                    </div>
                     <!-- Edit Icon -->
                    <div class="absolute bottom-1 right-1 bg-pink-500 text-white p-2 rounded-full shadow-md border-2 border-white group-hover:bg-pink-600 transition-colors">
                        <i class='bx bxs-camera text-xs'></i>
                    </div>
                </div>
                
                <!-- Helper Text -->
                <input type="file" name="profile_image" id="emp_profile_upload" class="hidden" accept="image/*" onchange="previewEmpImage(this)">
            </div>

            <!-- User Info Header -->
            <div class="flex justify-between items-start mb-6">
                <div>
                   <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <?php echo htmlspecialchars($emp_data['full_name'] ?? $_SESSION['full_name']); ?>
                        <i class='bx bxs-badge-check text-blue-500 text-xl' title="Verified"></i>
                   </h2>
                   <div class="text-gray-500 text-sm mt-1 flex items-center gap-3">
                        <span class="flex items-center gap-1"><i class='bx bx-user'></i> <?php echo htmlspecialchars($emp_data['username'] ?? $_SESSION['username'] ?? ''); ?></span>
                        <span class="text-gray-300">|</span>
                        <span class="flex items-center gap-1"><i class='bx bx-map'></i> <?php echo htmlspecialchars($emp_data['department_name'] ?? 'MCOT'); ?></span>
                   </div>
                </div>
                
                <div class="flex gap-2">
                     <button type="button" class="px-4 py-2 border border-gray-300 rounded-full text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2">
                        <i class='bx bx-bar-chart-alt-2'></i> Statistics
                     </button>
                      <a href="<?php echo $base_path; ?>logout.php" class="px-4 py-2 border border-gray-300 rounded-full text-sm font-medium text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors flex items-center gap-2">
                        <i class='bx bx-log-out'></i> Sign out
                     </a>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-6 border-b border-gray-100 mb-8 overflow-x-auto">
                <button type="button" id="tab-btn-profile" onclick="switchTab('profile')" class="pb-3 border-b-2 border-black font-semibold text-gray-800 flex items-center gap-2 whitespace-nowrap transition-colors">
                    <i class='bx bx-user'></i> Profile
                </button>
                <button type="button" id="tab-btn-security" onclick="switchTab('security')" class="pb-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 whitespace-nowrap transition-colors">
                    <i class='bx bx-shield-quarter'></i> Security
                </button>
                <button type="button" class="pb-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 whitespace-nowrap cursor-not-allowed opacity-50">
                    <i class='bx bx-briefcase'></i> Experience
                </button>
                <button type="button" id="tab-btn-job" onclick="switchTab('job')" class="pb-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 whitespace-nowrap transition-colors">
                    <i class='bx bxs-id-card'></i> Job Info
                </button>
            </div>
            
            <div id="profileMessage" class="hidden rounded-md p-3 text-sm mb-4"></div>

            <!-- Profile Tab Content -->
            <div id="tab-content-profile" class="space-y-8 mb-8">


                <!-- Full Name (Split) -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 md:col-span-3">Full Name</label>
                     <div class="md:col-span-9 grid grid-cols-2 gap-6">
                        <?php 
                            $fullName = $emp_data['full_name'] ?? $_SESSION['full_name'] ?? '';
                            $parts = explode(' ', $fullName, 2);
                            $firstName = $parts[0] ?? '';
                            $lastName = $parts[1] ?? '';
                        ?>
                        <input type="text" name="first_name" 
                            value="<?php echo htmlspecialchars($firstName); ?>" placeholder="First Name"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-2xl focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent outline-none transition-all text-sm text-gray-700 placeholder-gray-400 font-medium">
                        <input type="text" name="last_name" 
                            value="<?php echo htmlspecialchars($lastName); ?>" placeholder="Last Name"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-2xl focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent outline-none transition-all text-sm text-gray-700 placeholder-gray-400 font-medium">
                    </div>
                </div>

                <!-- Email -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                     <label class="block text-sm font-bold text-gray-800 md:col-span-3">Email</label>
                      <div class="md:col-span-9 relative">
                        <!-- Left Border Line visual -->
                        <span class="absolute inset-y-2.5 left-0 w-1 bg-black rounded-r-md"></span>
                        <input type="email" name="email" 
                            value="<?php echo htmlspecialchars($emp_data['email'] ?? $_SESSION['email']); ?>"
                            class="w-full pl-6 pr-5 py-2.5 bg-white border border-gray-200 rounded-2xl focus:ring-2 focus:ring-black focus:border-transparent outline-none transition-all text-sm text-gray-700 placeholder-gray-400 font-medium">
                    </div>
                </div>

                <!-- Phone Number -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                     <label class="block text-sm font-bold text-gray-800 md:col-span-3">Phone Number</label>
                     <div class="md:col-span-9 relative">
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($emp_data['phone'] ?? $_SESSION['phone'] ?? ''); ?>" placeholder="Enter phone number"
                            class="w-full pl-5 pr-5 py-2.5 bg-gray-50 border border-transparent rounded-2xl focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent outline-none transition-all text-sm text-gray-700 placeholder-gray-400 font-medium">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-5 text-gray-400">
                            <i class='bx bx-phone text-xl'></i>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Security Tab Content -->
            <div id="tab-content-security" class="hidden space-y-8 mb-8">
                <!-- Current Password -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 md:col-span-3">Current Password</label>
                    <div class="md:col-span-9 relative">
                        <input type="password" name="current_password" id="current_password" placeholder="Enter current password"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-2xl focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent outline-none transition-all text-sm text-gray-700 placeholder-gray-400 font-medium pr-10">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-400 hover:text-gray-600 transition-colors" onclick="togglePasswordVisibility('current_password', this)">
                            <i class='bx bx-show text-xl'></i>
                        </span>
                    </div>
                </div>

                <!-- New Password -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 md:col-span-3">New Password</label>
                    <div class="md:col-span-9 relative">
                        <input type="password" name="new_password" id="new_password" placeholder="Enter new password"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-2xl focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent outline-none transition-all text-sm text-gray-700 placeholder-gray-400 font-medium pr-10">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-400 hover:text-gray-600 transition-colors" onclick="togglePasswordVisibility('new_password', this)">
                            <i class='bx bx-show text-xl'></i>
                        </span>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 md:col-span-3">Confirm Password</label>
                    <div class="md:col-span-9 relative">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-2xl focus:bg-white focus:ring-2 focus:ring-black focus:border-transparent outline-none transition-all text-sm text-gray-700 placeholder-gray-400 font-medium pr-10">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-400 hover:text-gray-600 transition-colors" onclick="togglePasswordVisibility('confirm_password', this)">
                            <i class='bx bx-show text-xl'></i>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Job Info Tab Content -->
            <div id="tab-content-job" class="hidden space-y-8 mb-8">
                <!-- Employee ID -->
                 <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 md:col-span-3">Employee ID</label>
                    <div class="md:col-span-9">
                        <input type="text" value="<?php echo htmlspecialchars($emp_data['employee_id'] ?? '-'); ?>" disabled
                            class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-2xl text-sm text-gray-500 cursor-not-allowed font-medium">
                    </div>
                </div>

                <!-- Role -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 md:col-span-3">Role</label>
                    <div class="md:col-span-9">
                        <input type="text" value="<?php echo htmlspecialchars($emp_data['role_name'] ?? '-'); ?>" disabled
                            class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-2xl text-sm text-gray-500 cursor-not-allowed font-medium">
                    </div>
                </div>

                <?php if (!empty($emp_data['department_name'])): ?>
                <!-- Department -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 md:col-span-3">Department</label>
                    <div class="md:col-span-9">
                        <input type="text" value="<?php echo htmlspecialchars($emp_data['department_name']); ?>" disabled
                            class="w-full px-4 py-2.5 bg-gray-50 border border-transparent rounded-2xl text-sm text-gray-500 cursor-not-allowed font-medium">
                    </div>
                </div>
                <?php endif; ?>


            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100 mb-6">
                <button type="button" onclick="document.getElementById('profileModal').classList.add('hidden')"
                    class="px-6 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-xl border border-gray-200 hover:bg-gray-50 transition-all">
                    Close
                </button>
                <button type="submit" id="save-btn"
                    class="px-6 py-2.5 bg-black text-white text-sm font-medium rounded-xl shadow-lg hover:bg-gray-800 transition-all flex items-center gap-2">
                    Save Changes
                </button>
            </div>
            
            </form>
        </div>
    </div>
</div>

<script>
    let currentTab = 'profile';

    function openProfileModal() {
        document.getElementById('profileModal').classList.remove('hidden');
        document.getElementById('profileMessage').classList.add('hidden');
        switchTab('profile'); // Reset to profile tab on open
    }

    function switchTab(tab) {
        currentTab = tab;
        const tabs = ['profile', 'security', 'job'];
        
        // Hide/Show Save Button based on tab
        const saveBtn = document.getElementById('save-btn');
        if (tab === 'job') {
            saveBtn.classList.add('hidden');
        } else {
            saveBtn.classList.remove('hidden');
        }

        tabs.forEach(t => {
            const btn = document.getElementById(`tab-btn-${t}`);
            const content = document.getElementById(`tab-content-${t}`);
            const inputs = content.querySelectorAll('input');

            if (t === tab) {
                // Active
                btn.classList.add('border-black', 'text-gray-800');
                btn.classList.remove('border-transparent', 'text-gray-500');
                content.classList.remove('hidden');
                
                // Enable inputs (except for job tab which is read-only)
                if (t !== 'job') {
                    inputs.forEach(input => input.disabled = false);
                }
            } else {
                // Inactive
                btn.classList.remove('border-black', 'text-gray-800');
                btn.classList.add('border-transparent', 'text-gray-500');
                content.classList.add('hidden');
                // Disable inputs to prevent submission/validation
                inputs.forEach(input => input.disabled = true);
            }
        });
        
        // Hide messages when switching
        document.getElementById('profileMessage').classList.add('hidden');
    }

    function previewEmpImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('emp_profile_preview');
                const placeholder = document.getElementById('emp_profile_placeholder');
                
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                if(placeholder) placeholder.classList.add('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function togglePasswordVisibility(inputId, toggleIcon) {
        const input = document.getElementById(inputId);
        const icon = toggleIcon.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bx-show');
            icon.classList.add('bx-hide');
            // Keep input focused if needed, or just let it be
        } else {
            input.type = 'password';
            icon.classList.remove('bx-hide');
            icon.classList.add('bx-show');
        }
    }

    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        let url = '';
        
        if (currentTab === 'profile') {
            url = '<?php echo $base_path; ?>controllers/update_profile.php';
            // Combine First and Last Name into full_name
            const firstName = formData.get('first_name') ? formData.get('first_name').trim() : '';
            const lastName = formData.get('last_name') ? formData.get('last_name').trim() : '';
            const fullName = (firstName + ' ' + lastName).trim();
            formData.append('full_name', fullName);

            // Validate Phone Number
            const phone = formData.get('phone');
            if (phone && !/^\d+$/.test(phone)) {
                const msgDiv = document.getElementById('profileMessage');
                msgDiv.classList.remove('hidden');
                msgDiv.className = 'rounded-md p-3 text-sm bg-red-100 text-red-700 border border-red-200';
                msgDiv.innerHTML = '<i class="bx bx-error"></i> เบอร์โทรศัพท์ต้องเป็นตัวเลขเท่านั้น';
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                return;
            }

        } else if (currentTab === 'security') {
            url = '<?php echo $base_path; ?>controllers/update_password.php';
            
            // Validate Password Length
            const newPass = formData.get('new_password');
            if (newPass && newPass.length < 8) {
                const msgDiv = document.getElementById('profileMessage');
                msgDiv.classList.remove('hidden');
                msgDiv.className = 'rounded-md p-3 text-sm bg-red-100 text-red-700 border border-red-200';
                msgDiv.innerHTML = '<i class="bx bx-error"></i> รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                return;
            }
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> กำลังบันทึก...';

        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const msgDiv = document.getElementById('profileMessage');
            msgDiv.classList.remove('hidden');
            if (data.success) {
                msgDiv.className = 'rounded-md p-3 text-sm bg-green-100 text-green-700 border border-green-200';
                msgDiv.innerHTML = '<i class="bx bx-check-circle"></i> ' + data.message;
                
                if (currentTab === 'profile') {
                    setTimeout(() => {
                        location.reload(); // Reload to show new name/email
                    }, 1500);
                } else {
                     // Clear password fields
                     document.querySelectorAll('#tab-content-security input').forEach(inp => inp.value = '');
                     submitBtn.disabled = false;
                     submitBtn.innerHTML = originalBtnText;
                }
            } else {
                msgDiv.className = 'rounded-md p-3 text-sm bg-red-100 text-red-700 border border-red-200';
                msgDiv.innerHTML = '<i class="bx bx-error"></i> ' + data.message;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
</script>
