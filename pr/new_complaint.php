<?php
// pr/new_complaint.php
require_once '../includes/header.php';
require_once '../config/database.php';

// Check for PR Officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();

// Filters
$type_filter    = isset($_GET['type'])    ? $_GET['type']           : '';
$channel_filter = isset($_GET['channel']) ? $_GET['channel']        : '';
$program_filter = isset($_GET['program']) ? $_GET['program']        : '';
$search         = isset($_GET['search'])  ? trim($_GET['search'])   : '';

// Fetch distinct channels for filter dropdown
$channels = $db->query("SELECT DISTINCT submission_channel FROM complaints WHERE status = 'Pending' AND submission_channel IS NOT NULL ORDER BY submission_channel")->fetchAll();

// Fetch agencies from agencies table for filter dropdown
$agencies_all = $db->query("SELECT a.*, 
    (SELECT GROUP_CONCAT(ao.name ORDER BY ao.name SEPARATOR '|||') FROM agency_options ao WHERE ao.agency_id = a.id) as sub_options
    FROM agencies a ORDER BY a.id ASC")->fetchAll();

// Fetch Pending Complaints
$sql = "SELECT c.*, u.full_name as complainer, d.name as assigned_dept 
        FROM complaints c 
        LEFT JOIN users u ON c.user_id = u.id 
        LEFT JOIN departments d ON c.assigned_dept_id = d.id 
        WHERE c.status = 'Pending'";

$params = [];

if ($type_filter) {
    $sql .= " AND c.complaint_type = ?";
    $params[] = $type_filter;
}
if ($channel_filter) {
    $sql .= " AND c.submission_channel = ?";
    $params[] = $channel_filter;
}
if ($program_filter) {
    if ($program_filter === '__other__') {
        $sql .= " AND c.program LIKE 'other%'";
    } else {
        // ตรวจว่า agency นี้มี sub-options หรือไม่
        $ag_check = $db->prepare("SELECT has_sub_options FROM agencies WHERE name = ?");
        $ag_check->execute([$program_filter]);
        $ag_row = $ag_check->fetch();
        if ($ag_row && $ag_row['has_sub_options']) {
            $sql .= " AND c.program LIKE ?";
            $params[] = $program_filter . ' - %';
        } else {
            $sql .= " AND c.program = ?";
            $params[] = $program_filter;
        }
    }
}
if ($search) {
    $sql .= " AND (c.subject LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY c.created_at ASC";

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
                <div class="filter-pill" style="width: 100%;">
                    <i class='bx bx-search'></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="<?php echo __('assigned_search_placeholder'); ?>"
                           class="assigned-input" style="width: 100%;">
                </div>
            </form>

            <!-- Filters -->
            <form method="GET" id="filterForm" style="display: flex; gap: 10px; align-items: center; flex-wrap: nowrap;">
                <?php if ($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>

                <!-- ประเภท -->
                <div class="filter-pill select-wrapper">
                    <i class='bx bx-grid-alt'></i>
                    <select name="type" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('nc_filter_all_types'); ?></option>
                        <option value="complaint"  <?php echo $type_filter == 'complaint'  ? 'selected' : ''; ?>><?php echo __('nc_type_complaint'); ?></option>
                        <option value="suggestion" <?php echo $type_filter == 'suggestion' ? 'selected' : ''; ?>><?php echo __('nc_type_suggestion'); ?></option>
                        <option value="compliment" <?php echo $type_filter == 'compliment' ? 'selected' : ''; ?>><?php echo __('nc_type_compliment'); ?></option>
                    </select>
                </div>

                <!-- ช่องทาง -->
                <div class="filter-pill select-wrapper">
                    <i class='bx bx-laptop'></i>
                    <select name="channel" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('nc_filter_all_channels'); ?></option>
                        <?php foreach ($channels as $ch): ?>
                            <option value="<?php echo htmlspecialchars($ch['submission_channel']); ?>" <?php echo $channel_filter == $ch['submission_channel'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ch['submission_channel']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- หน่วยงาน -->
                <div class="filter-pill select-wrapper">
                    <i class='bx bxs-business'></i>
                    <select name="program" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo __('nc_filter_all_agencies'); ?></option>
                        <?php foreach ($agencies_all as $ag):
                            if ($ag['is_other']) {
                                echo '<option value="__other__"' . ($program_filter === '__other__' ? ' selected' : '') . '>' . __('nc_filter_other') . '</option>';
                                continue;
                            }
                            $sel = ($program_filter === $ag['name']) ? ' selected' : '';
                            echo '<option value="' . htmlspecialchars($ag['name']) . '"' . $sel . '>' . htmlspecialchars($ag['name']) . '</option>';
                        endforeach; ?>
                    </select>
                </div>

                <?php if ($type_filter || $channel_filter || $program_filter || $search): ?>
                    <a href="new_complaint.php" class="reset-btn" title="<?php echo __('nc_reset_title'); ?>">
                        <i class='bx bx-refresh' style="font-size: 24px;"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table Card -->
        <div class="assigned-table-card">
            <table style="table-layout: fixed; width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 35%; text-align: left; padding-left: 20px;"><?php echo __('nc_th_subject'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('nc_th_type'); ?></th>
                        <th style="width: 10%; text-align: center;"><?php echo __('nc_th_channel'); ?></th>
                        <th style="width: 10%; text-align: center;"><?php echo __('nc_th_agency'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo __('nc_th_reporter'); ?></th>
                        <th style="width: 10%; text-align: center;"><?php echo __('nc_th_date'); ?></th>
                        <th style="width: 5%;  text-align: center;"><?php echo __('nc_th_action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($complaints) > 0): ?>
                        <?php foreach ($complaints as $index => $complaint): ?>
                            <tr>
                                <td style="padding-left: 20px;">
                                    <div style="display: flex; align-items: flex-start; font-weight: 400; color: var(--dark);">
                                        <span style="margin-right: 8px; flex-shrink: 0;"><?php echo ($index + 1); ?></span>
                                        <span style="word-break: break-word; overflow-wrap: break-word; line-height: 1.5;"><?php echo htmlspecialchars($complaint['subject']); ?></span>
                                    </div>
                                </td>
                                <td style="text-align: center; color: var(--dark); font-weight: 400;">
                                    <?php
                                    $type_map = [
                                        'complaint'  => __('nc_type_complaint'),
                                        'suggestion' => __('nc_type_suggestion'),
                                        'compliment' => __('nc_type_compliment'),
                                    ];
                                    $ctype = strtolower($complaint['complaint_type'] ?? '');
                                    echo htmlspecialchars($type_map[$ctype] ?? ucfirst($ctype));
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge-channel <?php echo strtolower($complaint['submission_channel'] ?? 'system'); ?>">
                                        <?php echo htmlspecialchars($complaint['submission_channel'] ?? 'System'); ?>
                                    </span>
                                </td>
                                <td style="text-align: center; color: var(--dark); font-weight: 400;">
                                    <?php
                                    $program = $complaint['program'] ?? '-';
                                    if (stripos($program, 'other') === 0) {
                                        echo __('nc_filter_other');
                                    } elseif (strpos($program, ' - ') !== false) {
                                        // แสดงเฉพาะชื่อ Agency (ก่อน " - ") ไม่แสดง sub-option
                                        echo htmlspecialchars(explode(' - ', $program)[0]);
                                    } else {
                                        echo htmlspecialchars($program);
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php
                                    $has_caller_info = strpos($complaint['description'], '[Caller Information]') !== false;
                                    if ($has_caller_info) {
                                        if (preg_match('/Name:\s*(.+)/', $complaint['description'], $matches)) {
                                            echo '<span title="Reported via Phone/Walk-in" style="font-weight: 500;"><i class="bx bxs-phone-call mr-1" style="color: var(--blue);"></i>' . htmlspecialchars(trim($matches[1])) . '</span>';
                                        } else {
                                            echo htmlspecialchars($complaint['complainer'] ?? __('nc_anonymous'));
                                        }
                                    } elseif (!empty($complaint['is_anonymous'])) {
                                        echo '<span style="color: var(--dark); font-style: italic;">' . __('nc_anonymous') . '</span>';
                                    } else {
                                        echo '<span style="color: var(--dark); font-weight: 400;">' . htmlspecialchars($complaint['complainer'] ?? __('nc_anonymous')) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center; color: var(--dark); font-weight: 400;">
                                    <?php echo date('d M Y', strtotime($complaint['created_at'])); ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="complaint_details.php?id=<?php echo $complaint['id']; ?>" style="color: var(--dark-grey); font-size: 20px; margin: 0 5px;" title="<?php echo __('table_link_details'); ?>">
                                        <i class='bx bx-show'></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="padding: 50px;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                                    <i class='bx bx-folder-open' style="font-size: 64px; color: #cbd5e1; margin-bottom: 15px;"></i>
                                    <p style="color: var(--dark-grey); font-size: 16px; margin: 0; white-space: nowrap;"><?php echo __('nc_empty'); ?></p>
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