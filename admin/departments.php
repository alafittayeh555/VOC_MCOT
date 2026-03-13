<?php
// admin/departments.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();
$message = '';
$error = '';

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // 1. Add Department
    if ($_POST['action'] == 'add_dept') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);

        try {
            $stmt = $db->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $message = __('dept_msg_create_success');
        } catch (PDOException $e) {
            $error = __('dept_error_create') . " " . $e->getMessage();
        }
    }

    // 2. Edit Department
    elseif ($_POST['action'] == 'edit_dept') {
        $id = $_POST['dept_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);

        try {
            $stmt = $db->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
            $message = __('dept_msg_update_success');
        } catch (PDOException $e) {
            $error = __('dept_error_update') . " " . $e->getMessage();
        }
    }

    // 3. Delete Department
    elseif ($_POST['action'] == 'delete_dept') {
        $id = $_POST['dept_id'];

        try {
            $db->beginTransaction();

            // 1. Unassign Employees
            $stmt = $db->prepare("UPDATE employees SET department_id = NULL WHERE department_id = ?");
            $stmt->execute([$id]);

            // 2. Unassign Complaints
            $stmt = $db->prepare("UPDATE complaints SET assigned_dept_id = NULL WHERE assigned_dept_id = ?");
            $stmt->execute([$id]);

            // 3. Delete Department
            $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
            $message = __('dept_msg_delete_success');
        } catch (PDOException $e) {
            $db->rollBack();
            $error = __('dept_error_delete') . " " . $e->getMessage();
        }
    }
}

// Fetch Departments
$stmt = $db->query("
    SELECT d.*, 
    (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id) as user_count,
    (SELECT COUNT(*) FROM complaints c WHERE c.assigned_dept_id = d.id) as complaint_count
    FROM departments d
    ORDER BY d.id ASC
");
$departments = $stmt->fetchAll();
?>

<!-- Main Content -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('dept_page_title'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('dept_breadcrumb_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('dept_breadcrumb_departments'); ?></a></li>
        </ul>
    </div>
    <button onclick="document.getElementById('addDeptModal').classList.remove('hidden')" class="btn-download">
        <i class='bx bxs-building-house'></i>
        <span class="text"><?php echo __('dept_btn_add'); ?></span>
    </button>
</div>

<?php if ($message): ?>
    <div class="mb-6 animate-fade-in">
        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/50 p-4 rounded-2xl flex items-center justify-between shadow-sm backdrop-blur-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shadow-inner">
                    <i class='bx bxs-check-circle text-2xl'></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-emerald-900 dark:text-emerald-100"><?php echo __('swal_success', 'Success!'); ?></p>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400 font-medium"><?php echo $message; ?></p>
                </div>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="text-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-200 transition-colors p-2">
                <i class='bx bx-x text-xl'></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-6 animate-fade-in">
        <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800/50 p-4 rounded-2xl flex items-center justify-between shadow-sm backdrop-blur-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-900/40 flex items-center justify-center text-rose-600 dark:text-rose-400 shadow-inner">
                    <i class='bx bxs-error-circle text-2xl'></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-rose-900 dark:text-rose-100"><?php echo __('swal_error', 'Error!'); ?></p>
                    <p class="text-xs text-rose-700 dark:text-rose-400 font-medium"><?php echo $error; ?></p>
                </div>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="text-rose-400 hover:text-rose-600 dark:hover:text-rose-200 transition-colors p-2">
                <i class='bx bx-x text-xl'></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<div class="table-data">
    <div class="order">
        <div class="head">
            <h3><?php echo __('dept_title_list'); ?></h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th><?php echo __('dept_th_id', 'ID'); ?></th>
                    <th><?php echo __('dept_th_name'); ?></th>
                    <th><?php echo __('dept_th_desc'); ?></th>
                    <th><?php echo __('dept_th_staff'); ?></th>
                    <th><?php echo __('dept_th_action'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departments)): ?>
                    <tr><td colspan="5" style="text-align:center; padding: 1.5rem; color: #6b7280;"><?php echo __('user_empty'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($departments as $index => $d): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <p class="font-bold flex items-center gap-2 dark:text-white">
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </p>
                            </td>
                            <td>
                                <p style="font-size: 14px; color: #4b5563; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; max-width: 300px;" class="dark:text-gray-400">
                                    <?php echo htmlspecialchars($d['description'] ?? '-'); ?>
                                </p>
                            </td>
                        <td>
                            <div class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-[#1da1f2] dark:bg-blue-600 text-white rounded-full font-black text-[10px] shadow-lg shadow-blue-400/20 dark:shadow-blue-900/40 transition-all duration-300 transform hover:scale-105 cursor-default group">
                                <i class='bx bx-user text-[14px] group-hover:rotate-12 transition-transform duration-300'></i>
                                <span class="uppercase tracking-widest">
                                    <?php echo $d['user_count']; ?> <?php echo __('dept_label_staff'); ?>
                                </span>
                            </div>
                        </td>

                        <td>
                            <div class="flex items-center gap-2.5">
                                <button onclick='openEditModal(<?php echo json_encode($d); ?>)' 
                                    class="text-blue-500 hover:text-blue-700 transition-colors" title="<?php echo __('dept_title_edit'); ?>">
                                    <i class='bx bx-edit-alt' style="font-size: 1.25rem;"></i>
                                </button>
                                
                                <form method="POST" onsubmit="return confirmAction(event, '<?php echo __('dept_confirm_delete'); ?>');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_dept">
                                    <input type="hidden" name="dept_id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700 transition-colors" title="<?php echo __('dept_title_delete'); ?>">
                                        <i class='bx bx-trash' style="font-size: 1.25rem;"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Dept Modal -->
<div id="addDeptModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-100 overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 rounded-t-xl flex items-center justify-between">
            <h3 class="text-white text-lg font-bold flex items-center gap-2">
                <i class='bx bxs-building-house'></i>
                <?php echo __('dept_modal_add_title'); ?>
            </h3>
            <button onclick="document.getElementById('addDeptModal').classList.add('hidden')" class="text-white hover:text-gray-200 focus:outline-none">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>

        <!-- Body -->
        <form method="POST" class="px-6 py-6 space-y-4">
            <input type="hidden" name="action" value="add_dept">

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('dept_label_name'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bxs-business'></i>
                    </span>
                    <input type="text" name="name" required 
                           class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all placeholder-gray-400 dark:text-white font-medium shadow-sm"
                           placeholder="<?php echo __('dept_placeholder_name'); ?>">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('dept_label_desc'); ?></label>
                <div class="relative">
                    <textarea name="description" rows="3" 
                              class="w-full p-3 bg-gray-50 dark:bg-gray-700/50 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all placeholder-gray-400 dark:text-white font-medium resize-none shadow-sm"
                              placeholder="<?php echo __('dept_placeholder_desc'); ?>"></textarea>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700 mt-6">
                <button type="button" onclick="document.getElementById('addDeptModal').classList.add('hidden')"
                    class="px-5 py-2.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-all shadow-sm">
                    <?php echo __('dept_btn_cancel'); ?>
                </button>
                <button type="submit"
                    class="px-5 py-2.5 bg-purple-600 text-white text-sm font-medium rounded-lg shadow-md hover:bg-purple-700 transition-all flex items-center gap-2">
                    <i class='bx bx-check'></i> <?php echo __('dept_btn_create'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Dept Modal -->
<div id="editDeptModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-100 overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 rounded-t-xl flex items-center justify-between">
            <h3 class="text-white text-lg font-bold flex items-center gap-2">
                <i class='bx bxs-edit'></i>
                <?php echo __('dept_modal_edit_title'); ?>
            </h3>
            <button onclick="document.getElementById('editDeptModal').classList.add('hidden')" class="text-white hover:text-gray-200 focus:outline-none">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>

        <!-- Body -->
        <form method="POST" class="px-6 py-6 space-y-4">
            <input type="hidden" name="action" value="edit_dept">
            <input type="hidden" name="dept_id" id="edit_dept_id">

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('dept_label_name'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bxs-business'></i>
                    </span>
                    <input type="text" name="name" id="edit_dept_name" required 
                           class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all placeholder-gray-400 dark:text-white font-medium shadow-sm"
                           placeholder="<?php echo __('dept_placeholder_name'); ?>">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"><?php echo __('dept_label_desc'); ?></label>
                <div class="relative">
                    <textarea name="description" id="edit_dept_desc" rows="3" 
                              class="w-full p-3 bg-gray-50 dark:bg-gray-700/50 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all placeholder-gray-400 dark:text-white font-medium resize-none shadow-sm"
                              placeholder="<?php echo __('dept_placeholder_desc'); ?>"></textarea>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700 mt-6">
                <button type="button" onclick="document.getElementById('editDeptModal').classList.add('hidden')"
                    class="px-5 py-2.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-all shadow-sm">
                    <?php echo __('dept_btn_cancel'); ?>
                </button>
                <button type="submit"
                    class="px-5 py-2.5 bg-purple-600 text-white text-sm font-medium rounded-lg shadow-md hover:bg-purple-700 transition-all flex items-center gap-2">
                    <i class='bx bx-save'></i> <?php echo __('dept_btn_save'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(dept) {
        document.getElementById('edit_dept_id').value = dept.id;
        document.getElementById('edit_dept_name').value = dept.name;
        document.getElementById('edit_dept_desc').value = dept.description;
        document.getElementById('editDeptModal').classList.remove('hidden');
    }

    function confirmAction(e, message) {
        e.preventDefault();
        const form = e.currentTarget;
        
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