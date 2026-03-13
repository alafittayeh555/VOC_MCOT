<?php
// department/assigned.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../login.php");
    exit;
}

$dept_id = $_SESSION['department_id'];
$db = Database::connect();

// Filters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch Assigned Complaints
// Fetch Assigned Complaints (Assigned to Employees or Processed)
$sql = "SELECT c.*, u.full_name as complainer, e.full_name as emp_name 
        FROM complaints c 
        LEFT JOIN users u ON c.user_id = u.id 
        LEFT JOIN employees e ON c.assigned_employee_id = e.id
        WHERE c.assigned_dept_id = ? 
        AND c.assigned_employee_id IS NOT NULL 
        AND c.status NOT IN ('Cancelled', 'Completed')";

$params = [$dept_id];

if ($status) {
    if ($status === 'Processed_Resolved') {
        $sql .= " AND c.status IN ('Processed', 'Resolved', 'Completed')";
    } else {
        $sql .= " AND c.status = ?";
        $params[] = $status;
    }
}
if ($search) {
    $sql .= " AND (c.subject LIKE ? OR u.full_name LIKE ? OR e.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Order by date DESC
$sql .= " ORDER BY c.updated_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();
?>

<!-- Main Content -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('assigned_title', 'Assigned'); ?></h1>

        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('menu_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('assigned_title', 'Assigned'); ?></a></li>

        </ul>
    </div>
</div>

<div class="table-data" style="margin-top: 0; background: transparent; padding: 0;">
    <div class="order" style="background: transparent; padding: 0;">
        
        <!-- Toolbar for Filters and Search -->
        <div class="assigned-toolbar">
            <!-- Search -->
            <form method="GET" style="display: flex; align-items: center; flex: 1; margin-right: 15px;">
                 <div class="filter-pill" style="width: 100%;">
                     <i class='bx bx-search'></i>
                     <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                            placeholder="<?php echo __('assigned_search_placeholder', 'Search...'); ?>" 
                            class="assigned-input" style="width: 100%;">
                 </div>
            </form>

            <!-- Status Filter -->
            <form method="GET" id="filterForm" style="display: flex; gap: 10px; align-items: center; flex-wrap: nowrap;">
                <!-- Preserve search if set -->
                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>

                <div class="filter-pill select-wrapper">
                    <i class='bx bx-show'></i>
                    <select name="status" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('filter_all_statuses', 'All Statuses'); ?></option>
                        <option value="Pending" <?php echo $status == 'Pending' ? 'selected' : ''; ?>><?php echo __('status_pending', 'Pending'); ?></option>
                        <option value="Review" <?php echo $status == 'Review' ? 'selected' : ''; ?>><?php echo __('status_review', 'Review'); ?></option>
                        <option value="Rejected" <?php echo $status == 'Rejected' ? 'selected' : ''; ?>><?php echo __('status_rejected', 'Rejected'); ?></option>

                    </select>
                </div>

                <!-- Reset Button -->
                <?php if($status || $search): ?>
                    <a href="assigned.php" class="reset-btn" title="<?php echo __('assigned_btn_reset', 'Reset'); ?>">
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
                        <th style="width: 40%; text-align: left; padding-left: 20px;"><?php echo __('assigned_th_subject', 'Subject'); ?></th>
                        <th style="width: 20%; text-align: center;"><?php echo __('assigned_th_reporter', 'Reporter'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('table_th_date_reported'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('table_th_assigned_to'); ?></th>

                        <th style="width: 15%; text-align: center;"><?php echo __('assigned_th_status', 'Status'); ?></th>
                        <th style="width: 10%; text-align: center;"><?php echo __('assigned_th_action', 'Action'); ?></th>
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
                                <td style="text-align: center;">
                                    <?php 
                                    // Check if description contains Caller Information
                                    $has_caller_info = strpos($complaint['description'], '[Caller Information]') !== false;
                                    
                                    if ($has_caller_info) {
                                        if (preg_match('/Name:\s*(.+)/', $complaint['description'], $matches)) {
                                            echo '<span title="Reported via Phone/Walk-in" style="font-weight: 500;"><i class="bx bxs-phone-call mr-1" style="color: var(--blue);"></i>' . htmlspecialchars(trim($matches[1])) . '</span>';
                                        } else {
                                            echo htmlspecialchars($complaint['complainer'] ?? 'Anonymous');
                                        }
                                    } elseif (!empty($complaint['is_anonymous'])) {
                                        echo '<span style="color: var(--dark); font-style: italic;">' . __('assigned_anonymous') . '</span>';
                                    } else {
                                        echo '<span style="color: var(--dark); font-weight: 400;">' . htmlspecialchars($complaint['complainer'] ?? __('assigned_anonymous')) . '</span>';
                                    }

                                    ?>
                                </td>
                                <td style="text-align: center; color: var(--dark); font-weight: 400;">
                                    <?php echo date('d/m/Y', strtotime($complaint['created_at'])); ?>
                                </td>
                                <td style="text-align: center; color: var(--dark-grey); font-size: 13px;">
                                    <?php echo htmlspecialchars($complaint['emp_name'] ?? __('unknown')); ?>

                                </td>
                                <td style="text-align: center;">
                                    <span class="status <?php
                                    echo ($complaint['status'] == 'Resolved' || $complaint['status'] == 'Completed') ? 'completed' :
                                        (($complaint['status'] == 'Processed') ? 'processed' :
                                        (($complaint['status'] == 'Rejected') ? 'rejected' :
                                        (($complaint['status'] == 'Review') ? 'internalreview' : 
                                        (($complaint['status'] == 'In Progress') ? 'inprogress' :
                                            (($complaint['status'] == 'Pending') ? 'pending' : 'pending'))))); 
                                    ?>" style="padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <?php 
                                            // Handle space in key
                                            $status_key = 'status_' . strtolower(str_replace(' ', '_', $complaint['status']));
                                            echo __($status_key, $complaint['status']); 
                                        ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <a href="assigned_details.php?id=<?php echo $complaint['id']; ?>" style="color: var(--dark-grey); font-size: 20px; margin: 0 5px;" title="<?php echo __('details_view_details'); ?>">

                                        <i class='bx bx-show'></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding: 50px;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                                    <i class='bx bx-folder-open' style="font-size: 64px; color: #cbd5e1; margin-bottom: 15px;"></i>
                                    <p style="color: var(--dark-grey); font-size: 16px; margin: 0; white-space: nowrap;"><?php echo __('dept_no_assigned_complaints'); ?></p>

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