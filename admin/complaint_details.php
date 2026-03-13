<?php
// admin/complaint_details.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: complaints.php");
    exit;
}

$complaint_id = $_GET['id'];
$db = Database::connect();
$message = '';
$error = '';

// Handle Updates logic moved to ../controllers/complaint_action.php

// Fetch Complaint Details
$stmt = $db->prepare("
    SELECT c.*, 
           u.full_name as complainer, 
           u.email as complainer_email, 
           u_dept.name as complainer_dept,
           d.name as department 
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN departments u_dept ON u.department_id = u_dept.id
    LEFT JOIN departments d ON c.assigned_dept_id = d.id
    WHERE c.id = ?
");
$stmt->execute([$complaint_id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    echo "Complaint not found.";
    exit;
}

// Fetch History
$stmt = $db->prepare("
    SELECT h.*, u.full_name as actor 
    FROM complaint_history h
    LEFT JOIN users u ON h.action_by_user_id = u.id
    WHERE h.complaint_id = ?
    ORDER BY h.timestamp DESC
");
$stmt->execute([$complaint_id]);
$history = $stmt->fetchAll();

// Fetch Attachments
$stmt = $db->prepare("SELECT * FROM attachments WHERE complaint_id = ?");
$stmt->execute([$complaint_id]);
$attachments = $stmt->fetchAll();

// Fetch Options
$departments = $db->query("SELECT * FROM departments")->fetchAll();
?>

<!-- Main Content -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('details_page_title'); ?> #<?php echo $complaint['id']; ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('details_breadcrumb_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a href="complaints.php"><?php echo __('details_breadcrumb_complaints'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('details_breadcrumb_details'); ?></a></li>
        </ul>
    </div>
</div>

<?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="table-data" style="align-items: flex-start;">

    <!-- Left Column: Details & Actions -->
    <div class="order" style="flex: 2;">
        <div class="head">
            <h3><?php echo __('details_subject'); ?>: <?php echo htmlspecialchars($complaint['subject']); ?></h3>
        </div>

        <div class="mb-6">
            <h4 class="text-gray-600 text-sm uppercase font-bold mb-2"><?php echo __('details_section_complainant'); ?>
            </h4>
            <div class="bg-white p-4 rounded-lg border border-gray-200 text-sm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="block text-gray-500 font-medium"><?php echo __('details_label_name'); ?></span>
                        <span
                            class="text-gray-900 font-bold text-lg"><?php echo htmlspecialchars($complaint['complainer'] ?? __('guest')); ?></span>
                    </div>
                    <div>
                        <span class="block text-gray-500 font-medium"><?php echo __('details_label_email'); ?></span>
                        <span
                            class="text-gray-900"><?php echo htmlspecialchars($complaint['complainer_email'] ?? '-'); ?></span>
                    </div>
                    <?php if (!empty($complaint['complainer_dept'])): ?>
                        <div class="col-span-2">
                            <span
                                class="block text-gray-500 font-medium"><?php echo __('details_label_department'); ?></span>
                            <span
                                class="text-gray-900"><?php echo htmlspecialchars($complaint['complainer_dept']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mb-6">
            <h4 class="text-gray-600 text-sm uppercase font-bold mb-2"><?php echo __('details_description'); ?></h4>
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-gray-800 whitespace-pre-wrap">
                <?php echo htmlspecialchars($complaint['description']); ?>
            </div>
        </div>

        <div class="mb-6">
            <h4 class="text-gray-600 text-sm uppercase font-bold mb-2"><?php echo __('details_attachments'); ?></h4>
            <?php if (count($attachments) > 0): ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($attachments as $att): ?>
                        <?php 
                        $fileExt = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                        $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']);
                        ?>
                        <?php if ($isImage): ?>
                            <?php 
                            // Determine Path (Old vs New)
                            $filePath = $att['file_path'];
                            if (strpos($filePath, 'assets/') === false) {
                                $filePath = 'assets/uploads/complaints-file/' . $filePath;
                            }
                            ?>
                            <div class="mb-2">
                                <a href="../<?php echo $filePath; ?>" target="_blank">
                                    <img src="../<?php echo $filePath; ?>" alt="Attachment" class="max-w-xs md:max-w-md rounded-lg shadow-md border hover:opacity-90 transition">
                                </a>
                            </div>
                        <?php else: ?>
                            <?php 
                             // Determine Path (Old vs New)
                             $filePath = $att['file_path'];
                             if (strpos($filePath, 'assets/') === false) {
                                 $filePath = 'assets/uploads/complaints-file/' . $filePath;
                             }
                            ?>
                            <a href="../<?php echo $filePath; ?>" target="_blank"
                                class="flex items-center px-3 py-2 bg-white border border-gray-300 rounded shadow-sm text-sm hover:bg-gray-50">
                                <i class='bx bxs-file-pdf mr-2 text-red-500'></i> <?php echo __('details_btn_view_file'); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-400 italic text-sm"><?php echo __('details_no_attachments'); ?></p>
            <?php endif; ?>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200">
            <h4 class="text-gray-800 text-lg font-bold mb-4"><?php echo __('details_section_update'); ?></h4>
            <form action="../controllers/complaint_action.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                <!-- action param not needed as controller checks isset POST vars -->

                <div>
                    <label
                        class="block text-sm font-medium text-gray-700"><?php echo __('details_label_status'); ?></label>
                    <select name="status"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="Pending" <?php echo $complaint['status'] == 'Pending' ? 'selected' : ''; ?>>
                            <?php echo __('status_pending'); ?>
                        </option>
                        <option value="In Progress" <?php echo $complaint['status'] == 'In Progress' ? 'selected' : ''; ?>><?php echo __('status_in_progress'); ?></option>
                        <option value="Resolved" <?php echo $complaint['status'] == 'Resolved' ? 'selected' : ''; ?>>
                            <?php echo __('status_resolved'); ?>
                        </option>
                        <option value="Rejected" <?php echo $complaint['status'] == 'Rejected' ? 'selected' : ''; ?>>
                            <?php echo __('status_rejected'); ?>
                        </option>
                    </select>
                </div>

                <!-- Priority Input Removed as per request (Auto/Default) -->


                <div>
                    <label class="block text-sm font-medium text-gray-700"><?php echo __('details_label_assign_dept'); ?></label>
                    <div class="mt-1 block w-full border border-gray-200 bg-gray-50 rounded-md py-2 px-3 text-gray-600 sm:text-sm">
                        <?php echo !empty($complaint['department']) ? htmlspecialchars($complaint['department']) : 'Unassigned'; ?>
                        <span class="text-xs text-gray-400 ml-2">(Managed by PR)</span>
                    </div>
                </div>

                <div class="col-span-2">
                    <label
                        class="block text-sm font-medium text-gray-700"><?php echo __('details_label_internal_note'); ?></label>
                    <textarea name="note" rows="3"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        placeholder="<?php echo __('details_placeholder_note'); ?>"></textarea>
                </div>

                <div class="col-span-2 text-right">
                    <button type="submit"
                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <?php echo __('details_btn_update'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Info & History -->
    <div class="todo" style="flex: 1;">
        <div class="p-4 border-b border-gray-200">
            <h3 class="font-bold text-lg mb-4"><?php echo __('details_section_meta'); ?></h3>
            <div class="space-y-3 text-sm">

                <div class="flex justify-between">
                    <span class="text-gray-500"><?php echo __('details_meta_submitted'); ?>:</span>
                    <span
                        class="font-medium"><?php echo date('M j, Y H:i', strtotime($complaint['created_at'])); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500"><?php echo __('details_meta_user'); ?>:</span>
                    <span
                        class="font-medium"><?php echo htmlspecialchars($complaint['complainer'] ?? __('guest')); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500"><?php echo __('details_meta_email'); ?>:</span>
                    <span
                        class="font-medium"><?php echo htmlspecialchars($complaint['complainer_email'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <div class="p-4">
            <h3 class="font-bold text-lg mb-4"><?php echo __('details_section_history'); ?></h3>
            <ul class="todo-list">
                <?php foreach ($history as $h): ?>
                    <li class="completed" style="flex-direction: column; align-items: flex-start; gap: 4px; padding: 10px;">
                        <span class="text-xs text-gray-400"><?php echo date('M j, H:i', strtotime($h['timestamp'])); ?> -
                            <?php echo htmlspecialchars($h['actor']); ?></span>
                        <p class="text-sm"><?php echo htmlspecialchars(strip_tags($h['action_description'])); ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>