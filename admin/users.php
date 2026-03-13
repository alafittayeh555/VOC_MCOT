<?php
// admin/users.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();

// Auto-run DB Setup/Migration
require_once __DIR__ . '/../config/db_setup.php';

$message = '';
$error = '';

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {

    // 1. Add User (General User)
    if ($_POST['action'] == 'add_user') {
        $username  = trim($_POST['username']);
        $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = trim($_POST['full_name']);
        $email     = trim($_POST['email']);
        $phone      = trim($_POST['phone'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $role_id    = 4;
        $department_id = null;
        $employee_id   = null;

        try {
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, phone, occupation, email, role_id, department_id, employee_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$username, $password, $full_name, $phone ?: null, $occupation ?: null, $email, $role_id, $department_id, $employee_id]);
            $message = "User created successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                 if (strpos($e->getMessage(), "'username'") !== false) {
                     $error = "Error: The Username '$username' is already taken.";
                 } elseif (strpos($e->getMessage(), "'email'") !== false) {
                     $error = "Error: The Email '$email' is already registered.";
                 } else {
                     $error = "Error: Username or Email already exists.";
                 }
            } else {
                 $error = "Error creating user: " . $e->getMessage();
            }
        }
    }

    // 2. Edit User
    elseif ($_POST['action'] == 'edit_user') {
        $user_id = $_POST['user_id'];
        $full_name = trim($_POST['full_name']);
        $email      = trim($_POST['email']);
        $phone      = trim($_POST['phone'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');

        try {
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, occupation = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $phone ?: null, $occupation ?: null, $user_id]);
            $message = "User updated successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                 if (strpos($e->getMessage(), "'email'") !== false) {
                     $error = "Error: The Email '$email' is already registered to another user.";
                 } else {
                     $error = "Error: Email already exists.";
                 }
            } else {
                $error = "Error updating user: " . $e->getMessage();
            }
        }
    }

    // 3. Toggle Status (Activate/Deactivate)
    elseif ($_POST['action'] == 'toggle_status') {
        $user_id = $_POST['user_id'];
        $current_status = $_POST['current_status'];
        $new_status = $current_status ? 0 : 1;

        try {
            $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $user_id]);
            $message = "User status updated!";
        } catch (PDOException $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    }

    // 4. Reset Password
    elseif ($_POST['action'] == 'reset_password') {
        $user_id = $_POST['user_id'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

        try {
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_password, $user_id]);
            $message = "Password reset successfully!";
        } catch (PDOException $e) {
            $error = "Error resetting password: " . $e->getMessage();
        }
    }
}

// Filters logic
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$occ_filter = isset($_GET['occupation']) ? $_GET['occupation'] : '';

// Fetch Users (Role ID 4 ONLY)
$where = ["u.role_id = 4"];
$params = [];

// Filter by Search
if (!empty($search)) {
    $where[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Filter by Status
if ($status_filter !== '') {
    $where[] = "u.is_active = ?";
    $params[] = $status_filter;
}

// Filter by Occupation
if ($occ_filter !== '') {
    $where[] = "u.occupation = ?";
    $params[] = $occ_filter;
}

$sql = "SELECT u.* FROM users u";

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();
?>

<!-- Main Content -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('gen_user_page_title', 'Users'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('user_breadcrumb_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('gen_user_breadcrumb_users', 'Users'); ?></a></li>
        </ul>
    </div>
    <button onclick="openModal('addUserModal')" class="btn-download">
        <i class='bx bxs-user-plus'></i>
        <span class="text"><?php echo __('user_btn_add'); ?></span>
    </button>
</div>

<?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-4 dark:bg-green-900 dark:text-green-300 dark:border-green-700" role="alert">
        <span class="block sm:inline"><?php echo $message; ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4 dark:bg-red-900 dark:text-red-300 dark:border-red-700" role="alert">
        <span class="block sm:inline"><?php echo $error; ?></span>
    </div>
<?php endif; ?>

<div class="table-data" style="margin-top: 0; background: transparent; padding: 0;">
    <div class="order" style="background: transparent; padding: 0;">
        
        <!-- Toolbar -->
        <div class="assigned-toolbar">
            <!-- Search -->
            <form method="GET" style="display: flex; align-items: center; flex: 1; margin-right: 15px;">
                <div class="filter-pill" style="width: 100%;">
                    <i class='bx bx-search'></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo __('assigned_search_placeholder', 'Search...'); ?>" 
                           class="assigned-input" style="width: 100%;">
                    <?php if($status_filter !== ''): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>"><?php endif; ?>
                    <?php if($occ_filter !== ''): ?><input type="hidden" name="occupation" value="<?php echo htmlspecialchars($occ_filter); ?>"><?php endif; ?>
                </div>
            </form>

            <!-- Filters -->
            <form method="GET" id="filterForm" style="display: flex; gap: 10px; align-items: center; flex-wrap: nowrap;">
                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>

                <div class="filter-pill select-wrapper" style="min-width: 140px;">
                    <i class='bx bxs-toggle-left'></i>
                    <select name="status" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('user_filter_all_status'); ?></option>
                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>><?php echo __('user_filter_active'); ?></option>
                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>><?php echo __('user_filter_inactive'); ?></option>
                    </select>
                </div>

                <div class="filter-pill select-wrapper" style="min-width: 160px;">
                    <i class='bx bxs-briefcase'></i>
                    <select name="occupation" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('profile_occupation', 'All Occupations'); ?></option>
                        <?php 
                        $occs = ['student','university_student','government_officer','employee','state_enterprise','business_owner','freelancer','merchant','farmer','engineer','doctor','nurse','teacher','programmer','designer','technician','homemaker','unemployed','retired','other'];
                        foreach($occs as $occ): 
                        ?>
                        <option value="<?php echo $occ; ?>" <?php echo $occ_filter === $occ ? 'selected' : ''; ?>>
                            <?php echo __('occ_'.$occ, $occ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Reset -->
                <?php if($status_filter !== '' || $occ_filter !== '' || $search): ?>
                    <a href="users.php" class="reset-btn" title="<?php echo __('filter_btn_reset'); ?>">
                        <i class='bx bx-refresh' style="font-size: 24px;"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table Card -->
        <div class="assigned-table-card">
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%; text-align: left; padding-left: 20px;"><?php echo __('user_th_user'); ?></th>
                        <th style="width: 22%; text-align: left;"><?php echo __('user_label_email'); ?></th>
                        <th style="width: 13%; text-align: left;"><?php echo __('submit_phone', 'Phone'); ?></th>
                        <th style="width: 15%; text-align: left;"><?php echo __('profile_occupation', 'Occupation'); ?></th>
                        <th style="width: 12%; text-align: center;"><?php echo __('user_th_status'); ?></th>
                        <th style="width: 13%; text-align: center;"><?php echo __('user_th_action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $u): ?>
                            <tr>
                                <td style="padding-left: 20px;">
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #EEF2FF; color: #4F46E5; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); flex-shrink: 0; overflow: hidden;">
                                            <?php if (!empty($u['profile_image'])): ?>
                                                <img src="../assets/img/profile/<?php echo htmlspecialchars($u['profile_image']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 100%; height: 100%; background: #F1F5F9; display: flex; align-items: center; justify-content: center;">
                                                    <i class='bx bxs-user' style="color: #94A3B8; font-size: 24px;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <p style="font-weight: 600; font-size: 14px; margin: 0; color: var(--dark);"><?php echo htmlspecialchars($u['full_name']); ?></p>
                                            <small style="color: var(--dark-grey); font-size: 12px; opacity: 0.8;"><?php echo htmlspecialchars($u['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: left;"><span style="color: var(--dark);"><?php echo htmlspecialchars($u['email']); ?></span></td>
                                <td style="text-align: left;">
                                    <?php if (!empty($u['phone'])): ?>
                                        <span style="color: var(--dark); font-size: 13px;">
                                            <i class='bx bx-phone' style="vertical-align: middle; margin-right: 4px;"></i>
                                            <?php echo htmlspecialchars($u['phone']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--dark-grey); font-size: 12px; opacity: 0.6;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: left;">
                                    <?php if (!empty($u['occupation'])): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; background: #EEF2FF; color: #4F46E5; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px;">
                                            <i class='bx bxs-briefcase'></i>
                                            <?php echo htmlspecialchars(__('occ_' . $u['occupation'], $u['occupation'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--dark-grey); font-size: 12px; opacity: 0.6;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="status <?php echo $u['is_active'] ? 'completed' : 'pending'; ?>" 
                                          style="padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <?php echo $u['is_active'] ? __('user_filter_active') : __('user_filter_inactive'); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; justify-content: center; gap: 15px;">
                                        <!-- Edit -->
                                        <a href="#" onclick='openEditModal(<?php echo json_encode($u); ?>)' style="color: var(--blue); font-size: 18px; transition: transform 0.2s;" title="<?php echo __('user_modal_edit_title'); ?>" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                            <i class='bx bxs-edit-alt'></i>
                                        </a>
                                        <!-- Reset Password -->
                                        <a href="#" onclick="openResetPasswordModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')" style="color: var(--orange); font-size: 18px; transition: transform 0.2s;" title="<?php echo __('user_reset_title'); ?>" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                            <i class='bx bxs-key'></i>
                                        </a>
                                        <!-- Toggle Status -->
                                        <form method="POST" onsubmit="return confirm('<?php echo __('user_confirm_status'); ?>');" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $u['is_active']; ?>">
                                            <button type="submit" style="background: none; border: none; cursor: pointer; color: <?php echo $u['is_active'] ? 'var(--red)' : 'var(--green)'; ?>; font-size: 18px; transition: transform 0.2s;" 
                                                    title="<?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                    onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                                <i class='bx <?php echo $u['is_active'] ? 'bxs-user-x' : 'bxs-user-check'; ?>'></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="padding: 50px;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                                    <i class='bx bx-user-x' style="font-size: 64px; color: #cbd5e1; margin-bottom: 15px;"></i>
                                    <p style="color: var(--dark-grey); font-size: 16px; margin: 0;"><?php echo __('user_empty', 'No users found'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal (Simplified for General User) -->
<div id="addUserModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-100">
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 rounded-t-xl flex items-center justify-between">
            <h3 class="text-white text-lg font-bold flex items-center gap-2">
                <i class='bx bxs-user-plus'></i>
                <?php echo __('user_modal_add_title'); ?>
            </h3>
            <button onclick="closeModal('addUserModal')" class="text-white hover:text-gray-200 focus:outline-none">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>

        <form method="POST" class="px-6 py-6 space-y-4">
            <input type="hidden" name="action" value="add_user">

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('user_label_fullname'); ?></label>
                 <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bxs-id-card'></i>
                    </span>
                    <input type="text" name="full_name" required
                        class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white placeholder-gray-400">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('user_label_username'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bxs-user'></i>
                    </span>
                    <input type="text" name="username" required
                        class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white placeholder-gray-400">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('user_label_email'); ?></label>
                 <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bxs-envelope'></i>
                    </span>
                    <input type="email" name="email" required
                        class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white placeholder-gray-400">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('user_label_password'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bxs-lock-alt'></i>
                    </span>
                    <input type="password" name="password" required
                        class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white placeholder-gray-400">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('submit_phone', 'Phone Number'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bx-phone'></i>
                    </span>
                    <input type="tel" name="phone"
                        class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white placeholder-gray-400"
                        placeholder="0812345678">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('profile_occupation', 'Occupation'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500" style="pointer-events:none;">
                        <i class='bx bxs-briefcase'></i>
                    </span>
                    <select name="occupation"
                        class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white">
                        <option value=""><?php echo __('submit_select_occupation_default', '-- เลือกอาชีพ --'); ?></option>
                        <?php $occs = ['student','university_student','government_officer','employee','state_enterprise','business_owner','freelancer','merchant','farmer','engineer','doctor','nurse','teacher','programmer','designer','technician','homemaker','unemployed','retired','other']; foreach($occs as $occ): ?>
                        <option value="<?php echo $occ; ?>"><?php echo __('occ_'.$occ, $occ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700 mt-6">
                <button type="button" onclick="closeModal('addUserModal')"
                    class="px-5 py-2.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all shadow-sm">
                    <?php echo __('user_btn_cancel'); ?>
                </button>
                <button type="submit" class="px-5 py-2.5 bg-purple-600 text-white text-sm font-medium rounded-lg shadow-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-1 transition-all flex items-center gap-2">
                    <i class='bx bx-check'></i> <?php echo __('user_btn_create'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center backdrop-blur-sm">
     <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-100">
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 rounded-t-xl flex items-center justify-between">
            <h3 class="text-white text-lg font-bold flex items-center gap-2">
                <i class='bx bxs-edit'></i>
                <?php echo __('user_modal_edit_title'); ?>
            </h3>
            <button onclick="closeModal('editUserModal')" class="text-white hover:text-gray-200 focus:outline-none">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>

        <form method="POST" class="px-6 py-6 space-y-4">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('user_label_fullname'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bxs-id-card'></i>
                    </span>
                    <input type="text" name="full_name" id="edit_full_name" required
                        class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('user_label_email'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bxs-envelope'></i>
                    </span>
                    <input type="email" name="email" id="edit_email" required
                        class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('submit_phone', 'Phone Number'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bx-phone'></i>
                    </span>
                    <input type="tel" name="phone" id="edit_phone"
                        class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white"
                        placeholder="0812345678">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('profile_occupation', 'Occupation'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500" style="pointer-events:none;">
                        <i class='bx bxs-briefcase'></i>
                    </span>
                    <select name="occupation" id="edit_occupation"
                        class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white">
                        <option value=""><?php echo __('submit_select_occupation_default', '-- เลือกอาชีพ --'); ?></option>
                        <?php $occs = ['student','university_student','government_officer','employee','state_enterprise','business_owner','freelancer','merchant','farmer','engineer','doctor','nurse','teacher','programmer','designer','technician','homemaker','unemployed','retired','other']; foreach($occs as $occ): ?>
                        <option value="<?php echo $occ; ?>"><?php echo __('occ_'.$occ, $occ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700 mt-6">
                <button type="button" onclick="closeModal('editUserModal')"
                    class="px-5 py-2.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all shadow-sm">
                    <?php echo __('user_btn_cancel'); ?>
                </button>
                <button type="submit" class="px-5 py-2.5 bg-purple-600 text-white text-sm font-medium rounded-lg shadow-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-1 transition-all flex items-center gap-2">
                    <i class='bx bx-save'></i> <?php echo __('user_btn_save'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-sm mx-4 transform transition-all duration-300 scale-100">
         <!-- Header -->
         <div class="bg-gradient-to-r from-yellow-500 to-orange-500 px-6 py-4 rounded-t-xl flex items-center justify-between">
            <h3 class="text-white text-lg font-bold flex items-center gap-2">
                <i class='bx bxs-key'></i>
                <?php echo __('user_reset_title'); ?>
            </h3>
            <button onclick="closeModal('resetPasswordModal')" class="text-white hover:text-gray-200 focus:outline-none">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>

        <div class="p-6">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4"><?php echo __('user_reset_hint'); ?> <span class="font-bold text-gray-800 dark:text-white"
                id="reset_pass_username"></span></p>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_pass_user_id">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('user_label_new_pass'); ?></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                            <i class='bx bxs-lock'></i>
                        </span>
                        <input type="password" name="new_password" required
                            class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 outline-none transition-all dark:text-white">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700 mt-6">
                    <button type="button" onclick="closeModal('resetPasswordModal')"
                        class="px-5 py-2.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all shadow-sm">
                        <?php echo __('user_btn_cancel'); ?>
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-yellow-500 text-white text-sm font-medium rounded-lg shadow-md hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:ring-offset-1 transition-all flex items-center gap-2">
                        <i class='bx bxs-check-shield'></i> <?php echo __('user_btn_reset'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    function openEditModal(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_full_name').value = user.full_name;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_phone').value = user.phone || '';
        document.getElementById('edit_occupation').value = user.occupation || '';

        openModal('editUserModal');
    }

    function openResetPasswordModal(id, username) {
        document.getElementById('reset_pass_user_id').value = id;
        document.getElementById('reset_pass_username').innerText = username;
        openModal('resetPasswordModal');
    }
</script>

<?php require_once '../includes/footer.php'; ?>

