<?php
// pr/dashboard.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();

// Global Stats for PR
$stats = [];
// 1. New (Unassigned) - Complaints that haven't been assigned to any department yet
$stats['new'] = $db->query("SELECT COUNT(*) FROM complaints WHERE (assigned_dept_id IS NULL OR assigned_dept_id = 0) AND status NOT IN ('Resolved', 'Completed', 'Rejected', 'Cancelled')")->fetchColumn();
// 2. Pending - Assigned but still in progress
$stats['pending'] = $db->query("SELECT COUNT(*) FROM complaints WHERE status = 'Pending'")->fetchColumn(); 
// 3. Review - Waiting for final review
$stats['review'] = $db->query("SELECT COUNT(*) FROM complaints WHERE status = 'Review'")->fetchColumn();
// 4. Resolved - Total cases handled
$stats['resolved'] = $db->query("SELECT COUNT(*) FROM complaints WHERE status IN ('Resolved', 'Completed')")->fetchColumn();

// Complaint Type Breakdown (Global)
$sql_types = "SELECT complaint_type, COUNT(*) as count FROM complaints GROUP BY complaint_type";
$type_stats = $db->query($sql_types)->fetchAll(PDO::FETCH_KEY_PAIR);

// --- CHART DATA (Current Year) ---
$current_year_en = date('Y');
$current_year_th = $current_year_en + 543;

$chart_sql = "SELECT DATE_FORMAT(created_at, '%c') as m, complaint_type, COUNT(*) as cnt 
              FROM complaints 
              WHERE YEAR(created_at) = ? 
              GROUP BY m, complaint_type ORDER BY CAST(m AS UNSIGNED)";
$stmt_chart = $db->prepare($chart_sql);
$stmt_chart->execute([$current_year_en]);
$monthly_raw = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

$chart_data_complaint = array_fill(0, 12, 0);
$chart_data_suggestion = array_fill(0, 12, 0);
$chart_data_compliment = array_fill(0, 12, 0);

foreach ($monthly_raw as $row) {
    $m_index = intval($row['m']) - 1;
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
?>

<div class="head-title">
    <div class="left">
        <h1><?php echo __('admin_dash_title', 'Dashboard'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="#"><?php echo __('admin_dash_title', 'Dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('admin_dash_overview', 'Overview'); ?></a></li>
        </ul>
    </div>
</div>

<style>
    .box-info li { transition: all 0.3s ease; cursor: pointer; }
    .box-info li:hover { transform: translateY(-5px); box-shadow: 0 6px 15px rgba(0,0,0,0.1); }
</style>

<ul class="box-info">
    <li onclick="window.location.href='new_complaint.php'">
        <i class='bx bxs-bell-ring' style="background: #EAE0F5; color: #8B5CF6;"></i>
        <span class="text">
            <h3><?php echo $stats['new']; ?></h3>
            <p><?php echo __('menu_new_complaints', 'New Complaints'); ?></p>
        </span>
    </li>
    <li onclick="window.location.href='assigned.php?status=Pending'">
        <i class='bx bx-time-five' style="background: var(--light-yellow); color: var(--yellow);"></i>
        <span class="text">
            <h3><?php echo $stats['pending']; ?></h3>
            <p><?php echo __('status_pending', 'Pending'); ?></p>
        </span>
    </li>
    <li onclick="window.location.href='assigned.php?status=Review'">
        <i class='bx bx-search-alt-2' style="background: var(--light-orange); color: var(--orange);"></i>
        <span class="text">
            <h3><?php echo $stats['review']; ?></h3>
            <p><?php echo __('status_review', 'Review'); ?></p>
        </span>
    </li>
    <li onclick="window.location.href='history.php'">
        <i class='bx bx-check-double' style="background: var(--light-green); color: var(--green);"></i>
        <span class="text">
            <h3><?php echo $stats['resolved']; ?></h3>
            <p><?php echo __('status_resolved', 'Resolved'); ?></p>
        </span>
    </li>
</ul>

<div class="table-data" style="margin-top: 24px;">
    <!-- Chart -->
    <div class="order" style="flex: 2 1 600px;">
        <div class="head">
            <h3><?php echo __('chart_complaint_trends', 'Complaint Trends'); ?> <?php echo $lang_code === 'en' ? $current_year_en : $current_year_th; ?></h3>
        </div>
        <div style="height: 350px;">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>
    
    <!-- Type breakdown -->
    <div class="todo" style="flex: 1 1 300px; max-width: 400px;">
        <div class="head">
            <h3><?php echo __('chart_by_type', 'แยกตามประเภท'); ?></h3>
        </div>
        <ul class="todo-list">
            <li style="border-left: 6px solid var(--orange); border-radius: 8px; margin-bottom: 12px; padding: 20px 24px; background: var(--grey);">
                <p style="font-weight: 600; font-size: 16px; flex-grow: 1;"><?php echo __('type_complaint', 'ข้อร้องเรียน'); ?></p>
                <span style="font-weight: 700; font-size: 18px; color: var(--dark);"><?php echo $type_stats['Complaint'] ?? 0; ?></span>
            </li>
            <li style="border-left: 6px solid #3AB0FF; border-radius: 8px; margin-bottom: 12px; padding: 20px 24px; background: var(--grey);">
                <p style="font-weight: 600; font-size: 16px; flex-grow: 1;"><?php echo __('type_suggestion', 'ข้อเสนอแนะ / ติชม'); ?></p>
                <span style="font-weight: 700; font-size: 18px; color: var(--dark);"><?php echo $type_stats['Suggestion'] ?? 0; ?></span>
            </li>
            <li style="border-left: 6px solid var(--green); border-radius: 8px; margin-bottom: 12px; padding: 20px 24px; background: var(--grey);">
                <p style="font-weight: 600; font-size: 16px; flex-grow: 1;"><?php echo __('type_compliment', 'คำชมเชย'); ?></p>
                <span style="font-weight: 700; font-size: 18px; color: var(--dark);"><?php echo $type_stats['Compliment'] ?? 0; ?></span>
            </li>
        </ul>

        <!-- Quick Actions -->
        <div style="margin-top: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--dark);"><?php echo __('details_section_action', 'Quick Actions'); ?></h3>
            <div class="grid grid-cols-1 gap-3">
                <a href="Complaint_Suggestion.php" class="flex items-center gap-3 p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-all border border-gray-100 group">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
                        <i class='bx bx-edit-alt text-xl'></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 text-sm"><?php echo __('menu_complaint_suggestion'); ?></p>
                        <p class="text-xs text-gray-500">Submit new entry</p>
                    </div>
                </a>
                <a href="reports.php" class="flex items-center gap-3 p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-all border border-gray-100 group">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                        <i class='bx bx-bar-chart-alt-2 text-xl'></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 text-sm"><?php echo __('menu_reports'); ?></p>
                        <p class="text-xs text-gray-500">View analytics</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('monthlyChart');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: '<?php echo __('type_complaint', 'Complaint'); ?>',
                        data: <?php echo json_encode($chart_data_complaint); ?>,
                        backgroundColor: '#FD7238',
                        borderRadius: 6
                    },
                    {
                        label: '<?php echo __('type_suggestion', 'Suggestion'); ?>',
                        data: <?php echo json_encode($chart_data_suggestion); ?>,
                        backgroundColor: '#3AB0FF',
                        borderRadius: 6
                    },
                    {
                        label: '<?php echo __('type_compliment', 'Compliment'); ?>',
                        data: <?php echo json_encode($chart_data_compliment); ?>,
                        backgroundColor: '#38E54D',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                },
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, padding: 20 } }
                }
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>