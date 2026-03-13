<?php
// admin/agencies.php
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
    
    // 1. Add Agency
    if ($_POST['action'] == 'add_agency') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $has_sub_options = isset($_POST['has_sub_options']) ? 1 : 0;
        $is_other = isset($_POST['is_other']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("INSERT INTO agencies (name, description, has_sub_options, is_other) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $has_sub_options, $is_other]);
            $message = __('agency_msg_create_success');
        } catch (PDOException $e) {
            $error = __('agency_error_create') . " " . $e->getMessage();
        }
    }

    // 2. Edit Agency
    elseif ($_POST['action'] == 'edit_agency') {
        $id = $_POST['agency_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $has_sub_options = isset($_POST['has_sub_options']) ? 1 : 0;
        $is_other = isset($_POST['is_other']) ? 1 : 0;

        try {
            $stmt = $db->prepare("UPDATE agencies SET name = ?, description = ?, has_sub_options = ?, is_other = ? WHERE id = ?");
            $stmt->execute([$name, $description, $has_sub_options, $is_other, $id]);
            $message = __('agency_msg_update_success');
        } catch (PDOException $e) {
            $error = __('agency_error_update') . " " . $e->getMessage();
        }
    }

    // 3. Delete Agency
    elseif ($_POST['action'] == 'delete_agency') {
        $id = $_POST['agency_id'];

        try {
            $stmt = $db->prepare("DELETE FROM agencies WHERE id = ?");
            $stmt->execute([$id]);
            $message = __('agency_msg_delete_success');
        } catch (PDOException $e) {
            $error = __('agency_error_delete') . " " . $e->getMessage();
        }
    }
}

// Fetch Agencies with Option Count
$sql = "SELECT a.*, (SELECT COUNT(*) FROM agency_options ao WHERE ao.agency_id = a.id) as option_count 
        FROM agencies a 
        ORDER BY a.id ASC";
$stmt = $db->query($sql);
$agencies = $stmt->fetchAll();
?>

<!-- Main Content -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('agency_page_title'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('agency_breadcrumb_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('agency_breadcrumb_agencies'); ?></a></li>
        </ul>
    </div>
    <button onclick="document.getElementById('addAgencyModal').classList.remove('hidden')" class="btn-download">
        <i class='bx bxs-building-house'></i>
        <span class="text"><?php echo __('agency_btn_add'); ?></span>
    </button>
</div>

<?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-4" role="alert">
        <span class="block sm:inline"><?php echo $message; ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4" role="alert">
        <span class="block sm:inline"><?php echo $error; ?></span>
    </div>
<?php endif; ?>

<div class="table-data">
    <div class="order">
        <div class="head">
            <h3><?php echo __('agency_title_list'); ?></h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th><?php echo __('agency_th_id', 'ID'); ?></th>
                    <th><?php echo __('agency_th_name'); ?></th>
                    <th><?php echo __('agency_th_desc'); ?></th>
                    <th><?php echo __('agency_th_options'); ?></th>
                    <th><?php echo __('agency_th_action'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agencies)): ?>
                    <tr><td colspan="5" style="text-align:center; padding: 1.5rem; color: #6b7280;"><?php echo __('user_empty'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($agencies as $index => $a): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <p class="font-bold flex items-center gap-2">
                                    <?php echo htmlspecialchars($a['name']); ?>
                                    <?php if ($a['is_other'] == 1): ?>
                                        <span class="status process" style="font-size: 10px; padding: 2px 8px;"><?php echo __('agency_label_is_other'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </td>
                            <td>
                                <p style="font-size: 14px; color: #4b5563; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; max-width: 300px;">
                                    <?php echo htmlspecialchars($a['description'] ?? '-'); ?>
                                </p>
                            </td>
                            <td>
                                <?php if ($a['has_sub_options']): ?>
                                    <span class="status completed">
                                        <i class='bx bx-layer mr-1'></i>
                                        <?php echo $a['option_count']; ?> Sub-options
                                    </span>
                                <?php else: ?>
                                    <span style="color: #d1d5db;"><i class='bx bx-minus'></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <a href="agency_options.php?agency_id=<?php echo $a['id']; ?>" 
                                       class="text-green-500 hover:text-green-700 <?php echo $a['has_sub_options'] ? '' : 'invisible' ?>" title="Manage Options">
                                        <i class='bx bx-list-ul' style="font-size: 1.25rem;"></i>
                                    </a>

                                    <button onclick='openEditModal(<?php echo json_encode($a); ?>)' 
                                        class="text-blue-500 hover:text-blue-700" title="Edit">
                                        <i class='bx bxs-edit' style="font-size: 1.25rem;"></i>
                                    </button>
                                    
                                    <form method="POST" onsubmit="return confirm('<?php echo __('agency_confirm_delete'); ?>');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_agency">
                                        <input type="hidden" name="agency_id" value="<?php echo $a['id']; ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700 ml-1" title="Delete">
                                            <i class='bx bxs-trash' style="font-size: 1.25rem;"></i>
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

<!-- Add Agency Modal -->
<div id="addAgencyModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-100 overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 rounded-t-xl flex items-center justify-between">
            <h3 class="text-white text-lg font-bold flex items-center gap-2">
                <i class='bx bxs-building-house'></i>
                <?php echo __('agency_modal_add_title'); ?>
            </h3>
            <button onclick="document.getElementById('addAgencyModal').classList.add('hidden')" 
                    class="text-white hover:text-gray-200 focus:outline-none">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>

        <!-- Body -->
        <form method="POST" class="px-6 py-6 space-y-4">
            <input type="hidden" name="action" value="add_agency">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo __('agency_label_name'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bx-pencil text-lg'></i>
                    </span>
                    <input type="text" name="name" required 
                           class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all placeholder-gray-400 text-gray-800 font-medium"
                           placeholder="Enter agency name...">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo __('agency_label_desc'); ?></label>
                <div class="relative">
                    <textarea name="description" rows="3" 
                               class="w-full p-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all placeholder-gray-400 text-gray-800 font-medium resize-none shadow-sm"
                               placeholder="Enter description..."></textarea>
                </div>
            </div>

            <div class="space-y-3">
                <label class="flex items-center justify-between cursor-pointer p-3 bg-gray-50 border border-gray-200 rounded-lg group hover:border-purple-300 transition-all">
                    <span class="flex flex-col">
                        <span class="text-sm font-bold text-gray-700"><?php echo __('agency_label_sub_options'); ?></span>
                        <span class="text-[10px] text-gray-500 uppercase"><?php echo __('agency_hint_sub_options'); ?></span>
                    </span>
                    <div class="relative inline-flex items-center">
                        <input type="checkbox" name="has_sub_options" class="peer sr-only">
                        <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600 shadow-inner"></div>
                    </div>
                </label>

                <label class="flex items-center justify-between cursor-pointer p-3 bg-gray-50 border border-gray-200 rounded-lg group hover:border-indigo-300 transition-all">
                    <span class="flex flex-col">
                        <span class="text-sm font-bold text-gray-700"><?php echo __('agency_label_is_other'); ?></span>
                        <span class="text-[10px] text-gray-500"><?php echo __('agency_hint_is_other'); ?></span>
                    </span>
                    <div class="relative inline-flex items-center">
                        <input type="checkbox" name="is_other" class="peer sr-only">
                        <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600 shadow-inner"></div>
                    </div>
                </label>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 mt-6">
                <button type="button" onclick="document.getElementById('addAgencyModal').classList.add('hidden')"
                    class="px-5 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-all shadow-sm">
                    <?php echo __('agency_btn_cancel'); ?>
                </button>
                <button type="submit"
                    class="px-5 py-2.5 bg-purple-600 text-white text-sm font-medium rounded-lg shadow-md hover:bg-purple-700 transition-all flex items-center gap-2">
                    <i class='bx bx-check'></i> <?php echo __('agency_btn_create'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Agency Modal -->
<div id="editAgencyModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-100 overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 rounded-t-xl flex items-center justify-between">
            <h3 class="text-white text-lg font-bold flex items-center gap-2">
                <i class='bx bxs-edit'></i>
                <?php echo __('agency_modal_edit_title'); ?>
            </h3>
            <button onclick="document.getElementById('editAgencyModal').classList.add('hidden')" 
                    class="text-white hover:text-gray-200 focus:outline-none">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>

        <!-- Body -->
        <form method="POST" class="px-6 py-6 space-y-4">
            <input type="hidden" name="action" value="edit_agency">
            <input type="hidden" name="agency_id" id="edit_agency_id">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo __('agency_label_name'); ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class='bx bx-pencil text-lg'></i>
                    </span>
                    <input type="text" name="name" id="edit_agency_name" required 
                           class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all placeholder-gray-400 text-gray-800 font-medium"
                           placeholder="Enter agency name...">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo __('agency_label_desc'); ?></label>
                <div class="relative">
                    <textarea name="description" id="edit_agency_desc" rows="3" 
                               class="w-full p-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all placeholder-gray-400 text-gray-800 font-medium resize-none shadow-sm"
                               placeholder="Enter description..."></textarea>
                </div>
            </div>

            <div class="space-y-3">
                <label class="flex items-center justify-between cursor-pointer p-3 bg-gray-50 border border-gray-200 rounded-lg group hover:border-purple-300 transition-all">
                    <span class="flex flex-col">
                        <span class="text-sm font-bold text-gray-700"><?php echo __('agency_label_sub_options'); ?></span>
                        <span class="text-[10px] text-gray-500 uppercase"><?php echo __('agency_hint_sub_options'); ?></span>
                    </span>
                    <div class="relative inline-flex items-center">
                        <input type="checkbox" name="has_sub_options" id="edit_has_sub_options" class="peer sr-only">
                        <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600 shadow-inner"></div>
                    </div>
                </label>

                <label class="flex items-center justify-between cursor-pointer p-3 bg-gray-50 border border-gray-200 rounded-lg group hover:border-indigo-300 transition-all">
                    <span class="flex flex-col">
                        <span class="text-sm font-bold text-gray-700"><?php echo __('agency_label_is_other'); ?></span>
                        <span class="text-[10px] text-gray-500"><?php echo __('agency_hint_is_other'); ?></span>
                    </span>
                    <div class="relative inline-flex items-center">
                        <input type="checkbox" name="is_other" id="edit_is_other" class="peer sr-only">
                        <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600 shadow-inner"></div>
                    </div>
                </label>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 mt-6">
                <button type="button" onclick="document.getElementById('editAgencyModal').classList.add('hidden')"
                    class="px-5 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-all shadow-sm">
                    <?php echo __('agency_btn_cancel'); ?>
                </button>
                <button type="submit"
                    class="px-5 py-2.5 bg-purple-600 text-white text-sm font-medium rounded-lg shadow-md hover:bg-purple-700 transition-all flex items-center gap-2">
                    <i class='bx bx-save'></i> <?php echo __('agency_btn_save'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(agency) {
        document.getElementById('edit_agency_id').value = agency.id;
        document.getElementById('edit_agency_name').value = agency.name;
        document.getElementById('edit_agency_desc').value = agency.description || '';
        document.getElementById('edit_has_sub_options').checked = (agency.has_sub_options == 1);
        document.getElementById('edit_is_other').checked = (agency.is_other == 1);
        document.getElementById('editAgencyModal').classList.remove('hidden');
    }
</script>

<?php require_once '../includes/footer.php'; ?>
