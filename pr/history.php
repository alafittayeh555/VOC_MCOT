<?php
// pr/history.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();

// Filters Only for history (Search and Dept) - Status is inherently 'Completed'
$dept = isset($_GET['dept']) ? $_GET['dept'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$submit_date = isset($_GET['submit_date']) ? $_GET['submit_date'] : '';
$complete_date = isset($_GET['complete_date']) ? $_GET['complete_date'] : '';

// Fetch History Complaints (Resolved or Completed)
// Assuming 'Resolved' and 'Completed' are the statuses for history
$status_filter = ['Resolved', 'Completed'];

$sql = "SELECT c.*, u.full_name as complainer, d.name as assigned_dept, e.full_name as completed_by_name
        FROM complaints c 
        LEFT JOIN users u ON c.user_id = u.id 
        LEFT JOIN departments d ON c.assigned_dept_id = d.id 
        LEFT JOIN employees e ON c.assigned_employee_id = e.id
        WHERE c.status IN ('Resolved', 'Completed', 'Rejected')";

$params = [];

if ($dept) {
    $sql .= " AND c.assigned_dept_id = ?";
    $params[] = $dept;
}
if ($search) {
    $sql .= " AND (c.subject LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
// Date Submitted Filter
if ($submit_date) {
    $sql .= " AND DATE(c.created_at) = ?";
    $params[] = $submit_date;
}
// Date Completed Filter
if ($complete_date) {
    $sql .= " AND DATE(c.updated_at) = ?";
    $params[] = $complete_date;
}

$sql .= " ORDER BY c.updated_at DESC"; // Show latest completed first

$stmt = $db->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Fetch Departments for Filter
$departments = $db->query("SELECT * FROM departments")->fetchAll();
?>

<style>
    /* Fix flatpickr styling in dark mode */
    body.dark .flatpickr-calendar {
        background: var(--light);
        box-shadow: 0 0 10px rgba(0,0,0,0.5);
    }
    body.dark .flatpickr-day {
        color: var(--dark);
    }
    body.dark .flatpickr-day.selected {
        color: #fff;
    }
    body.dark .flatpickr-month {
        color: var(--dark);
        fill: var(--dark);
    }
    body.dark span.flatpickr-weekday {
        color: var(--dark);
    }
    .input-theme {
        background-color: transparent !important;
        color: var(--dark) !important;
        border: 1px solid transparent; /* Match reports style */
        width: 100%;
        height: 100%;
    }
    .input-theme::placeholder {
        color: var(--dark-grey);
    }
    /* Adjusted for toolbar context */
    .assigned-input.datepicker {
        padding-left: 35px !important; /* Make room for icon if absolute, currently icon is outside */
        border-radius: 36px; /* Match toolbar pills */
    }
</style>

<!-- Main Content -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('sidebar_history', 'Complaint History'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('menu_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('sidebar_history', 'Complaint History'); ?></a></li>
        </ul>
    </div>
</div>

<div class="table-data" style="margin-top: 0; background: transparent; padding: 0;">
    <div class="order" style="background: transparent; padding: 0;">
        
        <!-- Toolbar for Filters and Search -->
        <div class="assigned-toolbar">
            <!-- Search -->
            <!-- Search -->
            <form method="GET" style="display: flex; align-items: center; flex: 1; margin-right: 15px;">
                 <div class="filter-pill" style="width: 100%;">
                     <i class='bx bx-search'></i>
                     <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                            placeholder="<?php echo __('assigned_search_placeholder'); ?>" 
                            class="assigned-input" style="width: 100%;">
                     <?php if($dept): ?><input type="hidden" name="dept" value="<?php echo htmlspecialchars($dept); ?>"><?php endif; ?>
                     <?php if($submit_date): ?><input type="hidden" name="submit_date" value="<?php echo htmlspecialchars($submit_date); ?>"><?php endif; ?>
                     <?php if($complete_date): ?><input type="hidden" name="complete_date" value="<?php echo htmlspecialchars($complete_date); ?>"><?php endif; ?>
                 </div>
            </form>

            <!-- Dept Filter -->
            <form method="GET" style="display: flex; align-items: center; margin-right: 10px;">
                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                <div class="filter-pill select-wrapper" style="min-width: 150px;">
                    <i class='bx bx-grid-alt'></i>
                    <select name="dept" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('filter_all_depts'); ?></option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['id']; ?>" <?php echo $dept == $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <!-- Date Submitted -->
            <form method="GET" style="display: flex; align-items: center; margin-right: 10px;">
                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                <?php if($dept): ?><input type="hidden" name="dept" value="<?php echo htmlspecialchars($dept); ?>"><?php endif; ?>
                <div class="filter-pill" style="padding-left: 10px; position: relative;">
                    <i class='bx bx-calendar' style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); z-index: 10; pointer-events: none; color: var(--dark-grey);"></i>
                    <input type="text" 
                           id="submitDate"
                           name="submit_date" 
                           value="<?php echo htmlspecialchars($submit_date); ?>" 
                           class="assigned-input datepicker input-theme" 
                           style="padding-left: 35px; width: 140px;" 
                           placeholder="<?php echo __('assigned_th_date'); ?>"
                           title="<?php echo __('assigned_th_date'); ?>">
                </div>
            </form>

            <!-- Date Completed -->
            <form method="GET" style="display: flex; align-items: center; margin-right: 10px;">
                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                <?php if($dept): ?><input type="hidden" name="dept" value="<?php echo htmlspecialchars($dept); ?>"><?php endif; ?>
                <div class="filter-pill" style="padding-left: 10px; position: relative;">
                    <i class='bx bx-calendar-check' style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); z-index: 10; pointer-events: none; color: var(--dark-grey);"></i>
                    <input type="text" 
                           id="completeDate"
                           name="complete_date" 
                           value="<?php echo htmlspecialchars($complete_date); ?>" 
                           class="assigned-input datepicker input-theme" 
                           style="padding-left: 35px; width: 140px;" 
                           placeholder="<?php echo __('assigned_th_date_completed'); ?>"
                           title="<?php echo __('assigned_th_date_completed'); ?>">
                </div>
            </form>

            <!-- Reset Button -->
            <?php if($dept || $search || $submit_date || $complete_date): ?>
                <a href="history.php" class="reset-btn" title="<?php echo __('assigned_btn_reset'); ?>">
                    <i class='bx bx-refresh' style="font-size: 24px;"></i>
                </a>
            <?php endif; ?>
        </div>

        <!-- Table Card -->
        <div class="assigned-table-card">
            <table>
                <thead>
                    <tr>
                        <th style="width: 30%; text-align: left; padding-left: 20px;"><?php echo __('assigned_th_subject'); ?></th>
                        <th style="width: 10%; text-align: center;"><?php echo __('assigned_th_dept'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('assigned_th_reporter'); ?></th>
                        <th style="width: 10%; text-align: center;"><?php echo __('assigned_th_date'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('table_completed_by'); ?></th>
                        <th style="width: 10%; text-align: center;"><?php echo __('assigned_th_date_completed'); ?></th>
                        <th style="width: 5%; text-align: center;"><?php echo __('assigned_th_action'); ?></th>
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
                                <td style="text-align: center; color: var(--dark); font-weight: 400;"><?php echo htmlspecialchars($complaint['assigned_dept'] ?? '-'); ?></td>
                                <td style="text-align: center;">
                                    <?php 
                                    $has_caller_info = strpos($complaint['description'], '[Caller Information]') !== false;
                                    
                                    if ($has_caller_info) {
                                        if (preg_match('/Name:\s*(.+)/', $complaint['description'], $matches)) {
                                            echo '<span title="Reported via Phone/Walk-in" style="font-weight: 500;"><i class="bx bxs-phone-call mr-1" style="color: var(--blue);"></i>' . htmlspecialchars(trim($matches[1])) . '</span>';
                                        } else {
                                            echo htmlspecialchars($complaint['complainer'] ?? __('assigned_anonymous'));
                                        }
                                    } elseif (!empty($complaint['is_anonymous'])) {
                                        echo '<span style="color: var(--dark); font-style: italic;">' . __('assigned_anonymous') . '</span>';
                                    } else {
                                        echo '<span style="color: var(--dark); font-weight: 400;">' . htmlspecialchars($complaint['complainer'] ?? __('assigned_anonymous')) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="font-size: 13px; color: var(--dark); font-weight: 400;">
                                        <?php echo date('d/m/Y', strtotime($complaint['created_at'])); ?>
                                    </div>
                                </td>
                                <td style="text-align: center; color: var(--dark); font-weight: 400;"><?php echo htmlspecialchars($complaint['completed_by_name'] ?? '-'); ?></td>
                                <td style="text-align: center;">
                                    <div style="font-size: 13px; color: var(--dark); font-weight: 400;">
                                        <?php echo date('d/m/Y', strtotime($complaint['updated_at'])); ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <a href="history_details.php?id=<?php echo $complaint['id']; ?>" style="color: var(--dark-grey); font-size: 20px; margin: 0 5px;" title="<?php echo __('table_link_details'); ?>">
                                        <i class='bx bx-show'></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="padding: 50px;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                                    <i class='bx bx-history' style="font-size: 64px; color: #cbd5e1; margin-bottom: 15px;"></i>
                                    <p style="color: var(--dark-grey); font-size: 16px; margin: 0; white-space: nowrap;"><?php echo __('history_empty', 'No history found'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const commonConfig = {
            locale: "th",
            dateFormat: "Y-m-d", // Server expects Y-m-d based on existing PHP logic
            altInput: true,
            altFormat: "j F Y", // Display format like '11 กุมภาพันธ์ 2026'
            allowInput: true,
            disableMobile: "true",
            maxDate: "today",
            onChange: function(selectedDates, dateStr, instance) {
                // Auto-submit form when date is picked
                // Submit the form that *contains* the input element
                if (instance.element && instance.element.form) {
                    instance.element.form.submit();
                }
            }
        };

        flatpickr("#submitDate", commonConfig);
        flatpickr("#completeDate", commonConfig);
    });
</script>

<?php require_once '../includes/footer.php'; ?>
