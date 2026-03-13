<?php
// department/dashboard.php
require_once '../includes/header.php';
require_once '../config/database.php';

// Check for Employee role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 5) {
    header("Location: ../login.php");
    exit;
}

$dept_id = $_SESSION['department_id'];
$db = Database::connect();

// Stats
$stats = [];
$user_id = $_SESSION['user_id'];
$stats['assigned'] = $db->query("SELECT COUNT(*) FROM complaints WHERE assigned_dept_id = $dept_id AND assigned_employee_id = $user_id")->fetchColumn();
$stats['pending'] = $db->query("SELECT COUNT(*) FROM complaints WHERE assigned_dept_id = $dept_id AND assigned_employee_id = $user_id AND status = 'Pending'")->fetchColumn(); 
$stats['review'] = $db->query("SELECT COUNT(*) FROM complaints WHERE assigned_dept_id = $dept_id AND assigned_employee_id = $user_id AND status = 'Review'")->fetchColumn();
$stats['rejected'] = $db->query("SELECT COUNT(*) FROM complaints WHERE assigned_dept_id = $dept_id AND assigned_employee_id = $user_id AND status = 'Rejected'")->fetchColumn();
$stats['processed'] = $db->query("SELECT COUNT(*) FROM complaints WHERE assigned_dept_id = $dept_id AND assigned_employee_id = $user_id AND status NOT IN ('Pending', 'Review', 'Rejected')")->fetchColumn();

// Removed Overdue / In Progress to match exact new requirements for Employee dashboard

// --- CHART DATA (Current Year) ---
$current_year_en = date('Y');
$current_year_th = $current_year_en + 543;

// 1. Monthly Trends (Chart) for assigned complaints by type
$chart_sql = "SELECT DATE_FORMAT(created_at, '%c') as m, complaint_type, COUNT(*) as cnt 
              FROM complaints 
              WHERE assigned_dept_id = ? AND assigned_employee_id = ? 
              AND YEAR(created_at) = ? 
              GROUP BY m, complaint_type ORDER BY CAST(m AS UNSIGNED)";
$stmt_chart = $db->prepare($chart_sql);
$stmt_chart->execute([$dept_id, $user_id, $current_year_en]);
$monthly_raw = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

$chart_data_complaint = array_fill(0, 12, 0);
$chart_data_suggestion = array_fill(0, 12, 0);
$chart_data_compliment = array_fill(0, 12, 0);

foreach ($monthly_raw as $row) {
    $m_index = intval($row['m']) - 1; // 0 to 11
    if ($row['complaint_type'] === 'Complaint') $chart_data_complaint[$m_index] = intval($row['cnt']);
    if ($row['complaint_type'] === 'Suggestion') $chart_data_suggestion[$m_index] = intval($row['cnt']);
    if ($row['complaint_type'] === 'Compliment') $chart_data_compliment[$m_index] = intval($row['cnt']);
}

$lang_code = $_SESSION['lang'] ?? 'th';
if ($lang_code === 'en') {
    $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
} else {
    $labels = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
}

// 2. Complaint Type Breakdown (By Type)
$sql_types = "SELECT complaint_type, COUNT(*) as count 
              FROM complaints 
              WHERE assigned_dept_id = ? AND assigned_employee_id = ?
              GROUP BY complaint_type";
$stmt_types = $db->prepare($sql_types);
$stmt_types->execute([$dept_id, $user_id]);
$type_stats = $stmt_types->fetchAll(PDO::FETCH_KEY_PAIR);


?>

<div class="head-title">
    <div class="left">
        <h1><?php echo __('emp_dashboard_title'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="#"><?php echo __('menu_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('admin_dash_overview'); ?></a></li>
        </ul>
    </div>
</div>

<style>
    .box-info li {
        transition: all 0.3s ease;
    }
    .box-info li:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    }
</style>
<ul class="box-info">
    <li onclick="window.location.href='assigned_case.php?status=Pending'" style="cursor: pointer;">
        <i class='bx bx-time-five' style="background: var(--light-yellow); color: var(--yellow);"></i>
        <span class="text">
            <h3><?php echo $stats['pending']; ?></h3>
            <p><?php echo __('status_pending'); ?></p>
        </span>
    </li>
    <li onclick="window.location.href='assigned_case.php?status=Review'" style="cursor: pointer;">
        <i class='bx bx-search-alt-2' style="background: var(--light-orange); color: var(--orange);"></i>
        <span class="text">
            <h3><?php echo $stats['review']; ?></h3>
            <p><?php echo __('status_review'); ?></p>
        </span>
    </li>
    <li onclick="window.location.href='assigned_case.php?status=Rejected'" style="cursor: pointer;">
        <i class='bx bx-x-circle' style="background: #FEE2E2; color: var(--red);"></i>
        <span class="text">
            <h3><?php echo $stats['rejected']; ?></h3>
            <p><?php echo __('status_rejected'); ?></p>
        </span>
    </li>
    <li onclick="window.location.href='history.php'" style="cursor: pointer;">
        <i class='bx bx-check-circle' style="background: #e0f2fe; color: #3AB0FF;"></i>
        <span class="text">
            <h3><?php echo $stats['processed']; ?></h3>
            <p><?php echo __('status_processed', 'ดำเนินการแล้ว'); ?></p>
        </span>
    </li>
</ul>

<div class="table-data" style="margin-top: 24px;">
    <!-- Chart -->
    <div class="order" style="flex: 2 1 600px;">
        <div class="head">
            <h3><?php echo __('admin_stat_complaints'); ?> <?php echo $lang_code === 'en' ? $current_year_en : $current_year_th; ?></h3>
        </div>
        <div style="height: 300px;">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>
    
    <!-- Type breakdown Table -->
    <div class="todo" style="flex: 1 1 350px; max-width: 450px;">
        <div class="head">
            <h3><?php echo __('chart_by_type'); ?></h3>
        </div>
        <ul class="todo-list">
            <li class="completed" style="background: var(--grey); border-left: 6px solid var(--orange); border-radius: 8px; margin-bottom: 12px; padding: 20px 24px;">
                <p style="font-weight: 600; font-size: 16px; flex-grow: 1;"><?php echo __('type_complaint'); ?></p>
                <span style="font-weight: 700; font-size: 18px; color: var(--dark); float: right;"><?php echo $type_stats['Complaint'] ?? 0; ?></span>
            </li>
            <li class="completed" style="background: var(--grey); border-left: 6px solid #3AB0FF; border-radius: 8px; margin-bottom: 12px; padding: 20px 24px;">
                <p style="font-weight: 600; font-size: 16px; flex-grow: 1;"><?php echo __('type_suggestion'); ?></p>
                <span style="font-weight: 700; font-size: 18px; color: var(--dark); float: right;"><?php echo $type_stats['Suggestion'] ?? 0; ?></span>
            </li>
            <li class="completed" style="background: var(--grey); border-left: 6px solid var(--green); border-radius: 8px; margin-bottom: 12px; padding: 20px 24px;">
                <p style="font-weight: 600; font-size: 16px; flex-grow: 1;"><?php echo __('type_compliment'); ?></p>
                <span style="font-weight: 700; font-size: 18px; color: var(--dark); float: right;"><?php echo $type_stats['Compliment'] ?? 0; ?></span>
            </li>
        </ul>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Chart.js initialization
        const canvas = document.getElementById('monthlyChart');
        const style = getComputedStyle(document.body);
        const orangeColor = style.getPropertyValue('--orange').trim() || '#FD7238';
        const blueColor = '#3AB0FF'; // Changed to explicit light blue
        const greenColor = style.getPropertyValue('--green').trim() || '#DB504A';
        
        if (canvas) {
            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [
                        {
                            label: '<?php echo __('type_complaint'); ?>',
                            data: <?php echo json_encode($chart_data_complaint); ?>,
                            backgroundColor: orangeColor,
                            borderRadius: 4
                        },
                        {
                            label: '<?php echo __('type_suggestion'); ?>',
                            data: <?php echo json_encode($chart_data_suggestion); ?>,
                            backgroundColor: blueColor,
                            borderRadius: 4
                        },
                        {
                            label: '<?php echo __('type_compliment'); ?>',
                            data: <?php echo json_encode($chart_data_compliment); ?>,
                            backgroundColor: greenColor,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { 
                        x: {
                            stacked: false, // Set to true if you want stacked bars
                        },
                        y: { 
                            beginAtZero: true, 
                            ticks: { precision: 0 },
                            stacked: false
                        } 
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>