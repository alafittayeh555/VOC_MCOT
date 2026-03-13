<?php
// admin/dashboard.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();
$stats = [];
$stats['total_users'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['total_employees'] = $db->query("SELECT COUNT(*) FROM users WHERE role_id IN (1, 2, 3)")->fetchColumn();
$stats['total_gen_users'] = $db->query("SELECT COUNT(*) FROM users WHERE role_id = 4")->fetchColumn();


$stats['resolved_complaints'] = $db->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn();



// End of data preparation
?>

<!-- Admin Dashboard -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('admin_dash_title'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="#"><?php echo __('admin_dash_title'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('admin_dash_overview'); ?></a></li>
        </ul>
    </div>
</div>

<ul class="box-info">
    <li onclick="window.location.href='users.php'" style="cursor: pointer;">
        <i class='bx bxs-group' style="background: var(--light-blue); color: var(--blue);"></i>
        <span class="text">
            <h3><?php echo $stats['total_users']; ?></h3>
            <p><?php echo __('admin_stat_users'); ?></p>
        </span>
    </li>
    <li onclick="window.location.href='users.php?role=employee'" style="cursor: pointer;">
        <i class='bx bxs-id-card' style="background: var(--light-orange); color: var(--orange);"></i>
        <span class="text">
            <h3><?php echo $stats['total_employees']; ?></h3>
            <p>Employees</p>
        </span>
    </li>
     <li onclick="window.location.href='users.php?role=4'" style="cursor: pointer;">
        <i class='bx bxs-user-account' style="background: var(--light-green); color: var(--green);"></i>
        <span class="text">
            <h3><?php echo $stats['total_gen_users']; ?></h3>
            <p>General Users</p>
        </span>
    </li>


</ul>



<?php require_once '../includes/footer.php'; ?>