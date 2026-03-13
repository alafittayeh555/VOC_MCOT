<?php
// admin/employees.php
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

    // 1. Add Employee
    if ($_POST['action'] == 'add_user') {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role_id = $_POST['role_id'];
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $employee_id = trim($_POST['employee_id']);

        // Validation: Employee ID must be 6 characters
        if (mb_strlen($employee_id) !== 6) {
             $error = __('user_error_employee_id_length');
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO employees (username, password_hash, full_name, email, role_id, department_id, employee_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$username, $password, $full_name, $email, $role_id, $department_id, $employee_id]);
                $message = __('user_msg_create_success');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                     if (strpos($e->getMessage(), "'username'") !== false) {
                         $error = __('user_error_username_taken');
                     } elseif (strpos($e->getMessage(), "'email'") !== false) {
                         $error = __('user_error_email_exists');
                     } elseif (strpos($e->getMessage(), "'employee_id'") !== false) {
                         $error = __('user_error_employee_id_exists');
                     } else {
                         $error = __('user_error_email_exists'); // Generic duplicate
                     }
                } else {
                     $error = __('user_error_create') . " " . $e->getMessage();
                }
            }
        }
    }

    // 2. Edit Employee
    elseif ($_POST['action'] == 'edit_user') {
        $user_id = $_POST['user_id'];
        $full_name = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $role_id = $_POST['role_id'];
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $employee_id = trim($_POST['employee_id']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $new_password_raw = $_POST['new_password'] ?? '';

        // Validation: Employee ID must be 6 characters
        if (mb_strlen($employee_id) !== 6) {
             $error = __('user_error_employee_id_length');
        } else {
            try {
                $sql = "UPDATE employees SET full_name = ?, username = ?, email = ?, phone = ?, role_id = ?, department_id = ?, employee_id = ?, is_active = ?";
                $params = [$full_name, $username, $email, $phone, $role_id, $department_id, $employee_id, $is_active];

                $sql .= " WHERE id = ?";
                $params[] = $user_id;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $message = __('user_msg_update_success');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                     if (strpos($e->getMessage(), "'username'") !== false) {
                         $error = __('user_error_username_taken');
                     } elseif (strpos($e->getMessage(), "'email'") !== false) {
                         $error = __('user_error_email_exists');
                     } elseif (strpos($e->getMessage(), "'employee_id'") !== false) {
                         $error = __('user_error_employee_id_exists');
                     } else {
                         $error = __('user_error_email_exists');
                     }
                } else {
                    $error = __('user_error_update') . " " . $e->getMessage();
                }
            }
        }
    }

    // 3. Toggle Status (Activate/Deactivate)
    elseif ($_POST['action'] == 'toggle_status') {
        $user_id = $_POST['user_id'];
        $current_status = $_POST['current_status'];
        $new_status = $current_status ? 0 : 1;

        try {
            $stmt = $db->prepare("UPDATE employees SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $user_id]);
            $message = __('user_msg_status_success');
        } catch (PDOException $e) {
            $error = __('user_error_status') . " " . $e->getMessage();
        }
    }

    // 4. Reset Password
    elseif ($_POST['action'] == 'reset_password') {
        $user_id = $_POST['user_id'];
        $new_password = password_hash('1234', PASSWORD_DEFAULT);

        try {
            $stmt = $db->prepare("UPDATE employees SET password_hash = ?, require_change = 1 WHERE id = ?");
            $stmt->execute([$new_password, $user_id]);
            $message = __('user_reset_success_msg');
        } catch (PDOException $e) {
            $error = __('user_error_reset') . ": " . $e->getMessage();
        }
    }

    // 5. Delete Employee
    elseif ($_POST['action'] == 'delete_employee') {
        $id = $_POST['user_id'];
        try {
            $db->beginTransaction();
            // Unassign complaints
            $stmt = $db->prepare("UPDATE complaints SET assigned_employee_id = NULL WHERE assigned_employee_id = ?");
            $stmt->execute([$id]);
            // Delete from employees
            $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->execute([$id]);
            $db->commit();
            $message = __('user_msg_delete_success');
        } catch (PDOException $e) {
            $db->rollBack();
            $error = __('user_error_delete') . " " . $e->getMessage();
        }
    }
}

// Fetch Roles and Departments
$roles = $db->query("SELECT * FROM roles WHERE id != 4")->fetchAll();
$departments = $db->query("SELECT * FROM departments")->fetchAll();

// Filters logic
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$dept_filter = isset($_GET['dept']) ? $_GET['dept'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Fetch Users (Employees) with Filters
$where = ["u.role_id != 4"]; // Exclude Super Admin by default
$params = [];

// Filter by Search
if (!empty($search)) {
    $where[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Filter by Role
if (!empty($role_filter)) {
    $where[] = "u.role_id = ?";
    $params[] = $role_filter;
}

// Filter by Department
if (!empty($dept_filter)) {
    $where[] = "u.department_id = ?";
    $params[] = $dept_filter;
}

// Filter by Status
if ($status_filter !== '') {
    $where[] = "u.is_active = ?";
    $params[] = $status_filter;
}

$sql = "SELECT u.*, r.role_name, d.name as department_name 
        FROM employees u 
        LEFT JOIN roles r ON u.role_id = r.id 
        LEFT JOIN departments d ON u.department_id = d.id";

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
        <h1><?php echo __('user_page_title'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('user_breadcrumb_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('user_breadcrumb_users'); ?></a></li>
        </ul>
    </div>
    <button onclick="openModal('addUserModal')" class="btn-download">
        <i class='bx bxs-user-plus'></i>
        <span class="text"><?php echo __('user_btn_add'); ?></span>
    </button>
</div>

<?php if ($message): ?>
    <div class="flex items-center p-4 mb-4 text-sm text-green-800 border border-green-200 rounded-2xl bg-green-50 dark:bg-gray-800 dark:text-green-400 dark:border-green-800 shadow-sm transition-all duration-300 transform" role="alert">
        <i class='bx bxs-check-circle text-xl mr-3'></i>
        <div class="font-medium text-base">
            <?php echo $message; ?>
        </div>
        <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-green-50 text-green-500 rounded-lg focus:ring-2 focus:ring-green-400 p-1.5 hover:bg-green-100 inline-flex h-8 w-8 dark:bg-gray-800 dark:text-green-400 dark:hover:bg-gray-700 transition-colors" onclick="this.parentElement.remove()" aria-label="Close">
            <i class='bx bx-x text-xl'></i>
        </button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="flex items-center p-4 mb-4 text-sm text-red-800 border border-red-200 rounded-2xl bg-red-50 dark:bg-gray-800 dark:text-red-400 dark:border-red-800 shadow-sm transition-all duration-300 transform" role="alert">
        <i class='bx bxs-error-circle text-xl mr-3'></i>
        <div class="font-medium text-base">
            <?php echo $error; ?>
        </div>
        <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-red-50 text-red-500 rounded-lg focus:ring-2 focus:ring-red-400 p-1.5 hover:bg-red-100 inline-flex h-8 w-8 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700 transition-colors" onclick="this.parentElement.remove()" aria-label="Close">
            <i class='bx bx-x text-xl'></i>
        </button>
    </div>
<?php endif; ?>

<div class="table-data" style="margin-top: 0; background: transparent; padding: 0;">
    <div class="order" style="background: transparent; padding: 0;">
        
        <!-- Toolbar -->
        <!-- Toolbar -->
        <div class="assigned-toolbar">
            <!-- Search -->
            <form method="GET" style="display: flex; align-items: center; flex: 1; margin-right: 15px;">
                <div class="filter-pill" style="width: 100%;">
                    <i class='bx bx-search'></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo __('user_search_placeholder'); ?>" 
                           class="assigned-input" style="width: 100%;">
                    <?php if($role_filter): ?><input type="hidden" name="role" value="<?php echo htmlspecialchars($role_filter); ?>"><?php endif; ?>
                    <?php if($dept_filter): ?><input type="hidden" name="dept" value="<?php echo htmlspecialchars($dept_filter); ?>"><?php endif; ?>
                    <?php if($status_filter !== ''): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>"><?php endif; ?>
                </div>
            </form>

            <!-- Filters -->
            <form method="GET" id="filterForm" style="display: flex; gap: 10px; align-items: center; flex-wrap: nowrap;">
                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>

                <div class="filter-pill select-wrapper" style="min-width: 150px;">
                    <i class='bx bxs-user-detail'></i>
                    <select name="role" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('user_filter_all_roles'); ?></option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo $role_filter == $r['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['role_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-pill select-wrapper" style="min-width: 180px;">
                    <i class='bx bxs-building-house'></i>
                    <select name="dept" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('user_filter_all_depts'); ?></option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['id']; ?>" <?php echo $dept_filter == $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-pill select-wrapper" style="min-width: 140px;">
                    <i class='bx bxs-toggle-left'></i>
                    <select name="status" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('user_filter_all_status'); ?></option>
                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>><?php echo __('user_filter_active'); ?></option>
                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>><?php echo __('user_filter_inactive'); ?></option>
                    </select>
                </div>

                <!-- Reset -->
                <?php if($role_filter || $dept_filter || $status_filter || $search): ?>
                    <a href="employees.php" class="reset-btn" title="<?php echo __('filter_btn_reset'); ?>">
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
                        <th style="width: 15%; text-align: left; text-transform: uppercase;"><?php echo __('user_label_employee_id', 'Employee ID'); ?></th>
                        <th style="width: 15%; text-align: left;"><?php echo __('user_th_role'); ?></th>
                        <th style="width: 20%; text-align: left;"><?php echo __('user_th_dept'); ?></th>
                        <th style="width: 10%; text-align: center;"><?php echo __('user_th_status'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('user_th_action'); ?></th>
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
                                <td style="text-align: left;"><span style="font-family: monospace; font-weight: 500; color: var(--dark);"><?php echo htmlspecialchars($u['employee_id'] ?? '-'); ?></span></td>
                                <td style="text-align: left;"><span style="font-weight: 500; color: var(--dark-grey);"><?php echo htmlspecialchars($u['role_name']); ?></span></td>
                                <td style="text-align: left; color: var(--dark); font-weight: 400;"><?php echo htmlspecialchars($u['department_name'] ?? '-'); ?></td>
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
                                            <i class='bx bx-edit'></i>
                                        </a>
                                        <!-- Reset Password -->
                                        <a href="#" onclick="openResetPasswordModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')" style="color: var(--orange); font-size: 18px; transition: transform 0.2s;" title="<?php echo __('user_reset_title'); ?>" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                            <i class='bx bxs-key'></i>
                                        </a>
                                        <!-- Toggle Status -->
                                        <form method="POST" onsubmit="return confirmAction(event, '<?php echo __('user_confirm_status'); ?>');" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $u['is_active']; ?>">
                                            <button type="submit" style="background: none; border: none; cursor: pointer; color: <?php echo $u['is_active'] ? 'var(--red)' : 'var(--green)'; ?>; font-size: 18px; transition: transform 0.2s;" 
                                                    title="<?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                    onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                                <i class='bx <?php echo $u['is_active'] ? 'bxs-user-x' : 'bxs-user-check'; ?>'></i>
                                            </button>
                                        </form>
                                        <!-- Delete -->
                                        <form method="POST" onsubmit="return confirmAction(event, '<?php echo __('user_confirm_delete'); ?>');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_employee">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" style="background: none; border: none; cursor: pointer; color: var(--red); font-size: 18px; transition: transform 0.2s;" 
                                                    title="Delete"
                                                    onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                                <i class='bx bxs-trash'></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding: 50px;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                                    <i class='bx bx-user-x' style="font-size: 64px; color: #cbd5e1; margin-bottom: 15px;"></i>
                                    <p style="color: var(--dark-grey); font-size: 16px; margin: 0;"><?php echo __('user_empty', 'No employees found'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
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
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('user_label_employee_id', 'Employee ID'); ?></label>
                 <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bxs-badge-check'></i>
                    </span>
                    <input type="text" name="employee_id" required
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
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('user_label_role'); ?></label>
                <select name="role_id" required
                    class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white"
                    onchange="toggleDept(this.value, 'deptFieldAdd')">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="deptFieldAdd" class="hidden">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('user_label_dept'); ?></label>
                <select name="department_id"
                    class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all dark:text-white">
                    <option value=""><?php echo __('details_select_dept'); ?></option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
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

<!-- Edit User Modal (Redesigned) -->
<div id="editUserModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-2xl w-full max-w-2xl mx-4 transform transition-all duration-300 scale-100 overflow-hidden">
        
        <!-- Close Button (Absolute) -->
        <button onclick="closeModal('editUserModal')" class="absolute top-4 right-4 z-10 text-white bg-black bg-opacity-20 hover:bg-opacity-40 rounded-full p-2 transition-all focus:outline-none">
            <i class='bx bx-x text-xl'></i>
        </button>

        <!-- Cover Image -->
        <div class="h-32 w-full bg-gradient-to-r from-purple-500 to-indigo-600 relative">
             <img src="https://images.unsplash.com/photo-1542831371-29b0f74f9713?q=80&w=2070&auto=format&fit=crop" alt="Cover" class="w-full h-full object-cover opacity-60">
        </div>

        <form method="POST" class="px-8 relative">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id">

            <!-- Avatar Section -->
            <div class="flex flex-col items-start -mt-16 mb-4">
                <div class="w-32 h-32 rounded-full border-4 border-white dark:border-gray-800 shadow-lg overflow-hidden bg-white dark:bg-gray-700">
                    <img id="edit_profile_preview" src="../assets/img/profile/placeholder.png" alt="Profile" class="w-full h-full object-cover">
                </div>
            </div>

            <!-- Header Info -->
            <div class="mb-6">
                <h2 id="edit_header_name" class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    -
                    <i class='bx bxs-badge-check text-blue-500 text-xl'></i>
                </h2>
                <div class="text-gray-500 dark:text-gray-400 text-sm mt-1 flex items-center gap-3">
                    <span class="flex items-center gap-1"><i class='bx bx-user'></i> <span id="edit_header_username">-</span></span>
                    <span class="text-gray-300">|</span>
                    <span class="flex items-center gap-1"><i class='bx bx-map'></i> <span id="edit_header_dept">-</span></span>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-6 border-b border-gray-100 dark:border-gray-700 mb-8 overflow-x-auto">
                <button type="button" id="edit-tab-btn-profile" onclick="switchEditTab('profile')" class="pb-3 border-b-2 border-purple-600 font-semibold text-gray-800 dark:text-white flex items-center gap-2 whitespace-nowrap transition-all">
                    <i class='bx bx-user'></i> <?php echo __('user_tab_profile'); ?>
                </button>
                <button type="button" id="edit-tab-btn-job" onclick="switchEditTab('job')" class="pb-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-2 whitespace-nowrap transition-all">
                    <i class='bx bxs-id-card'></i> <?php echo __('user_tab_job'); ?>
                </button>
            </div>

            <!-- Profile Tab -->
            <div id="edit-tab-content-profile" class="space-y-6 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 dark:text-gray-200 md:col-span-3"><?php echo __('user_label_fullname'); ?></label>
                    <div class="md:col-span-9 grid grid-cols-2 gap-4">
                        <input type="text" name="first_name" id="edit_first_name" placeholder="<?php echo __('user_label_firstname'); ?>"
                            class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-transparent rounded-2xl focus:bg-white dark:focus:bg-gray-600 focus:ring-2 focus:ring-purple-500 outline-none transition-all text-sm dark:text-white font-medium">
                        <input type="text" name="last_name" id="edit_last_name" placeholder="<?php echo __('user_label_lastname'); ?>"
                            class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-transparent rounded-2xl focus:bg-white dark:focus:bg-gray-600 focus:ring-2 focus:ring-purple-500 outline-none transition-all text-sm dark:text-white font-medium">
                        <!-- Hidden field to combine for backend if needed or just use these two -->
                        <input type="hidden" name="full_name" id="edit_full_name">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 dark:text-gray-200 md:col-span-3"><?php echo __('user_label_username'); ?></label>
                    <div class="md:col-span-9 relative">
                        <input type="text" name="username" id="edit_username" required
                            class="w-full pl-5 pr-5 py-2 bg-gray-50 dark:bg-gray-700 border border-transparent rounded-2xl focus:bg-white dark:focus:bg-gray-600 focus:ring-2 focus:ring-purple-500 outline-none transition-all text-sm dark:text-white font-medium">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 dark:text-gray-200 md:col-span-3"><?php echo __('user_label_email'); ?></label>
                    <div class="md:col-span-9 relative">
                        <span class="absolute inset-y-2.5 left-0 w-1 bg-purple-600 rounded-r-md"></span>
                        <input type="email" name="email" id="edit_email" required
                            class="w-full pl-6 pr-5 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-2xl focus:ring-2 focus:ring-purple-500 outline-none transition-all text-sm dark:text-white font-medium">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 dark:text-gray-200 md:col-span-3"><?php echo __('profile_phone'); ?></label>
                    <div class="md:col-span-9 relative">
                        <input type="text" name="phone" id="edit_phone" placeholder="<?php echo __('user_placeholder_phone'); ?>"
                            class="w-full pl-5 pr-10 py-2 bg-gray-50 dark:bg-gray-700 border border-transparent rounded-2xl focus:bg-white dark:focus:bg-gray-600 focus:ring-2 focus:ring-purple-500 outline-none transition-all text-sm dark:text-white font-medium">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                            <i class='bx bx-phone text-xl'></i>
                        </span>
                    </div>
                </div>
            </div>


            <!-- Job Info Tab -->
            <div id="edit-tab-content-job" class="hidden space-y-6 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 dark:text-gray-200 md:col-span-3"><?php echo __('user_label_employee_id', 'Employee ID'); ?></label>
                    <div class="md:col-span-9">
                        <input type="text" name="employee_id" id="edit_employee_id" required
                            class="w-full px-5 py-2 bg-gray-50 dark:bg-gray-700 border border-transparent rounded-2xl focus:ring-2 focus:ring-purple-500 outline-none transition-all text-sm dark:text-white font-medium">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 dark:text-gray-200 md:col-span-3"><?php echo __('user_label_role'); ?></label>
                    <div class="md:col-span-9">
                        <select name="role_id" id="edit_role_id" required
                            class="w-full px-5 py-2 bg-white dark:bg-gray-700 border border-purple-200 dark:border-purple-900 rounded-2xl focus:ring-2 focus:ring-purple-500 outline-none transition-all text-sm dark:text-white font-medium appearance-none"
                            onchange="toggleDept(this.value, 'deptFieldEdit')">
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div id="deptFieldEdit" class="hidden grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 dark:text-gray-200 md:col-span-3"><?php echo __('user_label_dept'); ?></label>
                    <div class="md:col-span-9">
                        <select name="department_id" id="edit_department_id"
                            class="w-full px-5 py-2 bg-white dark:bg-gray-700 border border-purple-200 dark:border-purple-900 rounded-2xl focus:ring-2 focus:ring-purple-500 outline-none transition-all text-sm dark:text-white font-medium appearance-none">
                            <option value=""><?php echo __('details_select_dept'); ?></option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                    <label class="block text-sm font-bold text-gray-800 dark:text-gray-200 md:col-span-3"><?php echo __('user_th_status'); ?></label>
                    <div class="md:col-span-9 flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-2xl">
                         <span class="text-sm font-medium text-gray-500"><?php echo __('user_label_active_account'); ?></span>
                         <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 dark:peer-focus:ring-purple-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100 dark:border-gray-700 mb-8">
                <button type="button" onclick="closeModal('editUserModal')"
                    class="px-6 py-2 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-xl border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
                    <?php echo __('user_btn_cancel'); ?>
                </button>
                <button type="submit" onmouseover="this.querySelector('input[name=full_name]').value = (document.getElementById('edit_first_name').value + ' ' + document.getElementById('edit_last_name').value).trim()"
                    class="px-8 py-2 bg-black dark:bg-purple-600 text-white text-sm font-medium rounded-xl shadow-lg hover:bg-gray-800 dark:hover:bg-purple-700 transition-all flex items-center gap-2">
                    <?php echo __('user_btn_save'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Reset Password Form -->
<form id="resetPasswordForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="user_id" id="reset_pass_user_id">
</form>


<script>
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    function toggleDept(roleId, elementId) {
        const deptField = document.getElementById(elementId);
        // Assuming Role ID 3 is Department Staff/Officer and Role ID 5 is Employee
        if (roleId == 3 || roleId == 5) {
            deptField.classList.remove('hidden');
        } else {
            deptField.classList.add('hidden');
        }
    }

    function switchEditTab(tab) {
        const tabs = ['profile', 'job'];
        tabs.forEach(t => {
            const btn = document.getElementById(`edit-tab-btn-${t}`);
            const content = document.getElementById(`edit-tab-content-${t}`);
            if (t === tab) {
                btn.classList.add('border-purple-600', 'text-gray-800', 'dark:text-white');
                btn.classList.remove('border-transparent', 'text-gray-500');
                content.classList.remove('hidden');
                if (t === 'job') content.classList.add('grid');
            } else {
                btn.classList.remove('border-purple-600', 'text-gray-800', 'dark:text-white');
                btn.classList.add('border-transparent', 'text-gray-500');
                content.classList.add('hidden');
                if (t === 'job') content.classList.remove('grid');
            }
        });
    }

    function openEditModal(user) {
        document.getElementById('edit_user_id').value = user.id;
        
        // Split Name
        const name = user.full_name || '';
        const parts = name.split(' ');
        document.getElementById('edit_first_name').value = parts[0] || '';
        document.getElementById('edit_last_name').value = parts.slice(1).join(' ') || '';
        document.getElementById('edit_full_name').value = name;

        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_employee_id').value = user.employee_id || '';
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_phone').value = user.phone || '';
        document.getElementById('edit_role_id').value = user.role_id;
        document.getElementById('edit_is_active').checked = user.is_active == 1;

        // Header Info
        document.getElementById('edit_header_name').childNodes[0].textContent = user.full_name + ' ';
        document.getElementById('edit_header_username').textContent = user.username;
        document.getElementById('edit_header_dept').textContent = user.department_name || "<?php echo __('user_dept_general'); ?>";

        // Profile Preview
        const preview = document.getElementById('edit_profile_preview');
        if (user.profile_image) {
            preview.src = '../assets/img/profile/' + user.profile_image;
        } else {
            preview.src = '../assets/img/profile/placeholder.png'; // Fallback
        }

        // Set Department
        const deptSelect = document.getElementById('edit_department_id');
        deptSelect.value = user.department_id || "";

        // Trigger role logic to show/hide dept dropdown
        toggleDept(user.role_id, 'deptFieldEdit');

        switchEditTab('profile');
        openModal('editUserModal');
    }

    function openResetPasswordModal(id, username) {
        Swal.fire({
            title: '<?php echo __('user_reset_title'); ?>',
            html: `
                <div class="text-left">
                    <div class="mb-5 flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-700">
                        <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-600 dark:text-purple-400">
                            <i class='bx bxs-user-circle text-2xl'></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 leading-tight"><?php echo __('user_reset_hint'); ?></p>
                            <p class="text-sm font-bold text-gray-800 dark:text-white">${username}</p>
                        </div>
                    </div>

                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 p-5 rounded-2xl shadow-sm">
                        <div class="flex items-start gap-4">
                            <div class="mt-1 w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
                                <i class='bx bxs-shield-quarter text-xl'></i>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-amber-900 dark:text-amber-200 font-medium leading-relaxed">
                                    <?php echo __('user_reset_confirm_msg'); ?> 
                                    <span class="block text-2xl font-black tracking-widest text-amber-600 dark:text-amber-400 mt-1">1234</span>
                                </p>
                                <p class="text-[11px] text-amber-700/70 dark:text-amber-400/60 leading-normal italic">
                                    <i class='bx bxs-info-circle mr-1'></i>
                                    <?php echo __('user_reset_forced_msg'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            `,
            icon: undefined, // Using custom iconography in HTML
            showCancelButton: true,
            confirmButtonText: '<?php echo __('user_btn_reset'); ?>',
            cancelButtonText: '<?php echo __('user_btn_cancel'); ?>',
            reverseButtons: true,
            customClass: {
                confirmButton: 'swal2-confirm',
                cancelButton: 'swal2-cancel'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('reset_pass_user_id').value = id;
                document.getElementById('resetPasswordForm').submit();
            }
        });
    }

    function confirmAction(e, message) {
        e.preventDefault();
        const form = e.currentTarget;
        
        // Split message if it's a long confirmation to look better
        let title = message;
        let text = '';
        if (message.includes('?')) {
            const parts = message.split('?');
            title = parts[0] + '?';
            text = parts[1].trim();
        }

        Swal.fire({
            title: title,
            text: text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?php echo __('swal_confirm'); ?>',
            cancelButtonText: '<?php echo __('swal_cancel'); ?>',
            reverseButtons: true,
            customClass: {
                confirmButton: 'swal2-confirm',
                cancelButton: 'swal2-cancel'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
        return false;
    }
</script>

<?php require_once '../includes/footer.php'; ?>