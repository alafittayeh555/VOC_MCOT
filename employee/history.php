<?php
// employee/history.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 5) {
    header("Location: ../login.php");
    exit;
}

$dept_id = $_SESSION['department_id'];
$db = Database::connect();

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_reported = isset($_GET['date_reported']) ? $_GET['date_reported'] : '';
$date_completed = isset($_GET['date_completed']) ? $_GET['date_completed'] : '';

// Fetch History Complaints (Resolved or Completed)
$sql = "SELECT c.*, u.full_name as complainer 
        FROM complaints c 
        LEFT JOIN users u ON c.user_id = u.id 
        JOIN complaint_history ch ON c.id = ch.complaint_id
        WHERE c.assigned_dept_id = ? 
        AND c.status IN ('Resolved', 'Completed')
        AND ch.action_by_user_id = ?";

$params = [$dept_id, $_SESSION['user_id']];


if ($search) {
    $sql .= " AND (c.subject LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($date_reported) {
    $sql .= " AND DATE(c.created_at) = ?";
    $params[] = $date_reported;
}
if ($date_completed) {
    $sql .= " AND DATE(c.updated_at) = ?";
    $params[] = $date_completed;
}

// Order by most recently updated/completed
// Group by complaint ID to avoid duplicates from multiple history entries
$sql .= " GROUP BY c.id ORDER BY c.updated_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();
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
            <li><a href="dashboard.php"><?php echo __('menu_dashboard', 'Dashboard'); ?></a></li>
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
            <form method="GET" style="display: flex; align-items: center; flex: 1; margin-right: 15px;">
                 <div class="filter-pill" style="width: 100%;">
                     <i class='bx bx-search'></i>
                     <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                            placeholder="<?php echo __('assigned_search_placeholder', 'Search...'); ?>" 
                            class="assigned-input" style="width: 100%;">
                     <?php if($date_reported): ?><input type="hidden" name="date_reported" value="<?php echo htmlspecialchars($date_reported); ?>"><?php endif; ?>
                     <?php if($date_completed): ?><input type="hidden" name="date_completed" value="<?php echo htmlspecialchars($date_completed); ?>"><?php endif; ?>
                 </div>
            </form>

            <!-- Reported Date Filter -->
            <form method="GET" style="display: flex; align-items: center; margin-right: 10px;">
                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                <div class="filter-pill" style="padding-left: 10px; position: relative;">
                    <i class='bx bx-calendar' style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); z-index: 10; pointer-events: none; color: var(--dark-grey);"></i>
                    <input type="text" 
                           id="dateReported"
                           name="date_reported" 
                           value="<?php echo htmlspecialchars($date_reported); ?>" 
                           class="assigned-input datepicker input-theme" 
                           style="padding-left: 35px; width: 140px;" 
                           placeholder="<?php echo __('report_table_date_reported', 'Date Reported'); ?>"
                           title="<?php echo __('report_table_date_reported', 'Date Reported'); ?>">
                </div>
            </form>

            <!-- Completed Date Filter -->
            <form method="GET" style="display: flex; align-items: center; margin-right: 10px;">
                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                <?php if($date_reported): ?><input type="hidden" name="date_reported" value="<?php echo htmlspecialchars($date_reported); ?>"><?php endif; ?>
                <div class="filter-pill" style="padding-left: 10px; position: relative;">
                    <i class='bx bx-calendar-check' style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); z-index: 10; pointer-events: none; color: var(--dark-grey);"></i>
                    <input type="text" 
                           id="dateCompleted"
                           name="date_completed" 
                           value="<?php echo htmlspecialchars($date_completed); ?>" 
                           class="assigned-input datepicker input-theme" 
                           style="padding-left: 35px; width: 140px;" 
                           placeholder="<?php echo __('report_table_action_date', 'Action Date'); ?>"
                           title="<?php echo __('report_table_action_date', 'Action Date'); ?>">
                </div>
            </form>

            <!-- Reset Button -->
            <?php if($search || $date_reported || $date_completed): ?>
                <a href="history.php" class="reset-btn" title="<?php echo __('assigned_btn_reset', 'Reset Filters'); ?>">
                    <i class='bx bx-refresh' style="font-size: 24px;"></i>
                </a>
            <?php endif; ?>
        </div>

        <!-- Table Card -->
        <div class="assigned-table-card">
            <table>
                <thead>
                    <tr>
                        <th style="width: 35%; text-align: left; padding-left: 20px;"><?php echo __('assigned_th_subject', 'Subject'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('assigned_th_reporter', 'Reporter'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('report_table_date_reported', 'Date Reported'); ?></th>
                        <th style="width: 20%; text-align: center;"><?php echo __('report_table_action_date', 'Action Date'); ?></th>
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
                                        echo '<span style="color: var(--dark); font-style: italic;">Anonymous</span>';
                                    } else {
                                        echo '<span style="color: var(--dark); font-weight: 400;">' . htmlspecialchars($complaint['complainer'] ?? 'Anonymous') . '</span>';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="font-size: 13px; color: var(--dark); font-weight: 400;">
                                        <?php echo date('d/m/Y', strtotime($complaint['created_at'])); ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <div style="font-size: 13px; color: var(--dark); font-weight: 400;">
                                        <?php echo date('d/m/Y', strtotime($complaint['updated_at'])); ?>
                                    </div>
                                </td>

                                <td style="text-align: center;">
                                    <a href="history_details.php?id=<?php echo $complaint['id']; ?>" style="color: var(--dark-grey); font-size: 20px; margin: 0 5px;" title="View Details">
                                        <i class='bx bx-show'></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding: 50px;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                                    <i class='bx bx-history' style="font-size: 64px; color: #cbd5e1; margin-bottom: 15px;"></i>
                                    <p style="color: var(--dark-grey); font-size: 16px; margin: 0; white-space: nowrap;">No history records found</p>
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

        flatpickr("#dateReported", commonConfig);
        flatpickr("#dateCompleted", commonConfig);
    });
</script>
