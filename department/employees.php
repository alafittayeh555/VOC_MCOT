<?php
// department/employees.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();
$user_id = $_SESSION['user_id'];

// Get Current User's Department
$stmt_dept = $db->prepare("SELECT department_id FROM employees WHERE id = ?");
$stmt_dept->execute([$user_id]);
$current_dept_id = $stmt_dept->fetchColumn();

if (!$current_dept_id) {
    echo "<div class='p-4'>Error: You are not assigned to a department.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Fetch Department Name
$stmt_dept_name = $db->prepare("SELECT name FROM departments WHERE id = ?");
$stmt_dept_name->execute([$current_dept_id]);
$dept_name = $stmt_dept_name->fetchColumn();


// Filter Logic
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = ["u.department_id = ?", "u.role_id = 5"];
$params = [$current_dept_id];

if (!empty($search)) {
    $where[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== '') {
    $where[] = "u.is_active = ?";
    $params[] = $status_filter;
}

$sql = "SELECT u.*, r.role_name 
        FROM employees u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE " . implode(' AND ', $where) . " 
        ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();
?>

<!-- Main Content -->
<div class="head-title">
    <div class="left">
        <h1><?php echo $lang['employees_title']; ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo $lang['breadcrumb_dashboard']; ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo $lang['employees_breadcrumb_active']; ?></a></li>
        </ul>
    </div>
</div>

<div class="table-data" style="margin-top: 0; background: transparent; padding: 0;">
    <div class="order" style="background: transparent; padding: 0;">
        
        <!-- Toolbar -->
        <div class="assigned-toolbar">
            <!-- Search -->
            <form method="GET" style="display: flex; align-items: center; flex: 1; margin-right: 15px;">
                <div class="filter-pill" style="width: 100%;">
                    <i class='bx bx-search'></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo $lang['employees_search_placeholder']; ?>" 
                           class="assigned-input" style="width: 100%;">
                    <?php if($status_filter !== ''): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>"><?php endif; ?>
                </div>
            </form>

            <!-- Filters -->
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: nowrap;">
                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                
                <div class="filter-pill select-wrapper" style="min-width: 140px;">
                    <i class='bx bxs-toggle-left'></i>
                    <select name="status" class="assigned-select" onchange="this.form.submit()">
                        <option value=""><?php echo $lang['employees_all_status']; ?></option>
                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>><?php echo $lang['employees_active']; ?></option>
                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>><?php echo $lang['employees_inactive']; ?></option>
                    </select>
                </div>

                <!-- Reset -->
                <?php if($status_filter !== '' || $search): ?>
                    <a href="employees.php" class="reset-btn" title="<?php echo $lang['filter_reset']; ?>">
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
                        <th style="width: 30%; text-align: left; padding-left: 20px;"><?php echo $lang['employees_th_name']; ?></th>
                        <th style="width: 15%; text-align: left;"><?php echo $lang['employees_th_id']; ?></th>
                        <th style="width: 25%; text-align: left;"><?php echo $lang['employees_th_email']; ?></th>
                        <th style="width: 15%; text-align: left;"><?php echo $lang['employees_th_phone']; ?></th>
                        <th style="width: 15%; text-align: center;"><?php echo $lang['assigned_th_status']; ?></th>
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
                                            <small style="color: var(--dark-grey); font-size: 12px; opacity: 0.8; font-weight: 500;">@<?php echo htmlspecialchars($u['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: left;">
                                    <span style="font-weight: 600; color: var(--dark); font-size: 13px; letter-spacing: 0.5px; background: #F1F5F9; padding: 4px 10px; border-radius: 8px; font-family: monospace;">
                                        <?php echo htmlspecialchars($u['employee_id'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td style="text-align: left;">
                                    <span style="color: var(--dark); font-size: 13px; font-weight: 500;">
                                        <?php echo htmlspecialchars($u['email']); ?>
                                    </span>
                                </td>
                                <td style="text-align: left;">
                                    <?php if (!empty($u['phone'])): ?>
                                        <span style="color: var(--dark); font-size: 13px; font-weight: 500;">
                                            <i class='bx bx-phone' style="vertical-align: middle; margin-right: 4px; font-size: 16px; color: var(--blue);"></i>
                                            <?php echo htmlspecialchars($u['phone']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--dark-grey); font-size: 12px; opacity: 0.6;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="status <?php echo $u['is_active'] ? 'completed' : 'pending'; ?>" 
                                          style="padding: 6px 16px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block;">
                                        <?php echo $u['is_active'] ? $lang['employees_active'] : $lang['employees_inactive']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding: 60px 20px;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                                    <i class='bx bx-user-x' style="font-size: 64px; color: #cbd5e1; margin-bottom: 20px;"></i>
                                    <h3 style="color: var(--dark); font-size: 18px; margin: 0 0 8px 0; font-weight: 600;"><?php echo $lang['employees_no_members_found']; ?></h3>
                                    <p style="color: var(--dark-grey); font-size: 14px; margin: 0;"><?php echo $lang['employees_no_members_desc']; ?></p>
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
