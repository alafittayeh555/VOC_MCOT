<?php
// department/new_complaints.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../login.php");
    exit;
}

$dept_id = $_SESSION['department_id'];
$db = Database::connect();

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';


// Fetch NEW Complaints (Assigned to Dept, but NOT assigned to Employee)
$sql = "SELECT c.*, u.full_name as complainer 
        FROM complaints c 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE c.assigned_dept_id = ? 
        AND c.assigned_employee_id IS NULL
        AND c.status NOT IN ('Resolved', 'Completed', 'Rejected', 'Cancelled')"; // Keep Processed? Usually Processed means done by Dept, so maybe exclude Processed too.

// If checking logic: 'Received' is the main status.
$sql .= " AND c.status IN ('Received', 'Pending', 'In Progress')";

$params = [$dept_id];

if ($search) {
    $sql .= " AND (c.subject LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type_filter) {
    $sql .= " AND c.complaint_type = ?";
    $params[] = $type_filter;
}


// Order by date DESC (Newest first)
$sql .= " ORDER BY c.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();
?>

<!-- Main Content -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('menu_new_complaints'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('menu_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('menu_new_complaints'); ?></a></li>
        </ul>
    </div>
</div>

<div class="table-data" style="margin-top: 0; background: transparent; padding: 0;">
    <div class="order" style="background: transparent; padding: 0;">
        
        <!-- Toolbar for Filters and Search -->
        <div class="assigned-toolbar">
            <!-- Search -->
            <form method="GET" style="display: flex; align-items: center; flex: 1; margin-right: 15px;">
                 <?php if ($type_filter): ?><input type="hidden" name="type" value="<?php echo htmlspecialchars($type_filter); ?>"><?php endif; ?>
                 <div class="filter-pill" style="width: 100%;">
                     <i class='bx bx-search'></i>
                     <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                            placeholder="<?php echo __('assigned_search_placeholder', 'Search...'); ?>" 
                            class="assigned-input" style="width: 100%;">
                 </div>
            </form>

            <!-- Filters -->
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: nowrap;">
                <?php if ($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>

                <div class="filter-pill select-wrapper">
                    <i class='bx bx-grid-alt'></i>
                    <select name="type" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('filter_all_types'); ?></option>
                        <option value="Complaint" <?php echo $type_filter == 'Complaint' ? 'selected' : ''; ?>><?php echo __('type_complaint'); ?></option>
                        <option value="Suggestion" <?php echo $type_filter == 'Suggestion' ? 'selected' : ''; ?>><?php echo __('type_suggestion'); ?></option>
                        <option value="Compliment" <?php echo $type_filter == 'Compliment' ? 'selected' : ''; ?>><?php echo __('type_compliment'); ?></option>
                    </select>
                </div>

                <?php if ($type_filter || $search): ?>
                    <a href="new_complaints.php" class="reset-btn" title="<?php echo __('btn_refresh'); ?>">
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
                        <th style="width: 35%; text-align: left; padding-left: 20px;"><?php echo __('assigned_th_subject', 'Subject'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('complaint_form_type', 'Type'); ?></th>
                        <th style="width: 20%; text-align: center;"><?php echo __('assigned_th_reporter', 'Reporter'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('table_th_date_reported'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('assigned_th_action', 'Action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($complaints) > 0): ?>
                        <?php foreach ($complaints as $index => $complaint): ?>
                            <tr>
                                <td style="padding-left: 20px;">
                                    <div style="display: flex; align-items: flex-start; font-weight: 400; color: var(--dark); max-width: 300px;" title="<?php echo htmlspecialchars($complaint['subject']); ?>">
                                        <span style="margin-right: 8px; flex-shrink: 0;"><?php echo ($index + 1); ?></span>
                                        <span style="white-space: normal; word-wrap: break-word; word-break: break-word; line-height: 1.4;"><?php echo htmlspecialchars($complaint['subject']); ?></span>
                                    </div>
                                </td>
                                <td style="text-align: center; color: var(--dark-grey); font-size: 14px;">
                                    <?php 
                                    $type_lang = '-';
                                    if (!empty($complaint['complaint_type'])) {
                                        $type_key = 'type_' . strtolower(str_replace(' ', '_', $complaint['complaint_type']));
                                        $type_lang = isset($lang[$type_key]) ? $lang[$type_key] : $complaint['complaint_type'];
                                    }
                                    echo htmlspecialchars($type_lang); 
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php 
                                    $has_caller_info = strpos($complaint['description'], '[Caller Information]') !== false;
                                    if ($has_caller_info) {
                                        if (preg_match('/Name:\s*(.+)/', $complaint['description'], $matches)) {
                                            echo '<span title="Reported via Phone/Walk-in" style="font-weight: 500;"><i class="bx bxs-phone-call mr-1" style="color: var(--blue);"></i>' . htmlspecialchars(trim($matches[1])) . '</span>';
                                        } else {
                                            echo htmlspecialchars($complaint['complainer'] ?? 'Anonymous');
                                        }
                                    } elseif (!empty($complaint['is_anonymous'])) {
                                        echo '<span style="color: var(--dark); font-style: italic;">' . __('submit_caller_anonymous') . '</span>';
                                    } else {
                                        echo '<span style="color: var(--dark); font-weight: 400;">' . htmlspecialchars($complaint['complainer'] ?? 'Anonymous') . '</span>';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center; color: var(--dark); font-weight: 400;">
                                    <?php echo date('d/m/Y', strtotime($complaint['created_at'])); ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="new_complaints_details.php?id=<?php echo $complaint['id']; ?>" class="btn-action" style="color: var(--blue); font-size: 20px; margin: 0 5px;" title="<?php echo __('btn_view_assign'); ?>">
                                        <i class='bx bxs-edit'></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding: 50px;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                                    <i class='bx bx-check-circle' style="font-size: 64px; color: #cbd5e1; margin-bottom: 15px;"></i>
                                    <p style="color: var(--dark-grey); font-size: 16px; margin: 0; white-space: nowrap;"><?php echo __('dept_no_new_complaints'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
