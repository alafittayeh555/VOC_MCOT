<?php
// department/reports.php
session_start();
require_once '../config/database.php';
require_once '../includes/language_handler.php';

// Helper for Thai Months
$thai_months_json = json_encode([
    'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
]);

// Check Perms
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 3 && $_SESSION['role_id'] != 5)) {
    header("Location: ../login.php");
    exit;
}

$dept_id = $_SESSION['department_id'];
$db = Database::connect();

// --- EXPORT LOGIC ---
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    ob_clean();
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="department_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF"; 
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    
    // Filter Logic for Export
    $start_date_filter = date('Y-m-01 00:00:00');
    $end_date_filter = date('Y-m-t 23:59:59');

    if (isset($_GET['start_date']) && isset($_GET['end_date']) && !empty($_GET['start_date']) && !empty($_GET['end_date'])) {
         $dt_start = DateTime::createFromFormat('d/m/Y', $_GET['start_date']);
         $dt_end = DateTime::createFromFormat('d/m/Y', $_GET['end_date']);
         if ($dt_start && $dt_end) {
             $start_date_filter = $dt_start->format('Y-m-d 00:00:00');
             $end_date_filter = $dt_end->format('Y-m-d 23:59:59');
         }
    } elseif (isset($_GET['month']) || isset($_GET['year'])) {
         $y = isset($_GET['year']) ? ((int)$_GET['year'] - 543) : date('Y');
         $m = $_GET['month'] ?? date('n');
         $start_date_filter = "$y-$m-01 00:00:00";
         $end_date_filter = date("Y-m-t 23:59:59", strtotime($start_date_filter));
    }

    // Get base URL for absolute paths in Excel
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $base_dir = dirname(dirname($_SERVER['SCRIPT_NAME']));
    $base_url = $protocol . "://" . $host . ($base_dir === '/' || $base_dir === '\\' ? '' : $base_dir) . '/';

    // Fetch Data for Excel
    $sql = "SELECT c.*, u.full_name as reporter_name,
            (SELECT GROUP_CONCAT(CONCAT(file_path, '::', file_name) SEPARATOR '||') FROM attachments WHERE complaint_id = c.id AND file_path LIKE '%/user/%') as user_attachments_data,
            (SELECT GROUP_CONCAT(CONCAT(file_path, '::', file_name) SEPARATOR '||') FROM attachments WHERE complaint_id = c.id AND file_path LIKE '%/emp/%') as emp_attachments_data
            FROM complaints c 
            LEFT JOIN users u ON c.user_id = u.id 
            WHERE c.assigned_dept_id = ?
            AND c.created_at BETWEEN ? AND ? 
            ORDER BY c.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$dept_id, $start_date_filter, $end_date_filter]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Translations for Excel
    $status_th = [
        'Pending' => 'รอดำเนินการ',
        'In Progress' => 'กำลังดำเนินการ',
        'Resolved' => 'เสร็จสิ้น',
        'Completed' => 'เสร็จสมบูรณ์',
        'Rejected' => 'ปฏิเสธ',
        'Cancelled' => 'ยกเลิก',
        'Processed' => 'ดำเนินการแล้ว'
    ];
    $type_th = [
        'Complaint' => 'ข้อร้องเรียน',
        'Suggestion' => 'ข้อเสนอแนะ',
        'Compliment' => 'คำชมเชย'
    ];

    echo '<table border="1">';
    echo '<tr>
            <th>เรื่อง</th>
            <th>รายละเอียด</th>
            <th>ประเภทเรื่อง</th>
            <th>ผู้แจ้ง</th>
            <th>เอกสารแนบประกอบ (ผู้แจ้ง)</th>
            <th>' . __('report_table_date_reported', 'Date Reported') . '</th>
            <th>' . __('report_table_action_date', 'Action Date') . '</th>
            <th>บันทึกการดำเนินการ</th>
            <th>เอกสารแนบประกอบ (พนักงาน)</th>
            <th>สถานะ</th>
          </tr>';
    foreach ($data as $row) {
        $reporter = $row['reporter_name'];
        if ($row['is_anonymous']) {
            $reporter = 'ไม่ระบุตัวตน';
        } elseif (empty($reporter)) {
            if (preg_match('/Name:\s*(.+)/', $row['description'], $matches)) {
                $reporter = trim($matches[1]);
            } else {
                $reporter = '-';
            }
        }
        
        $status_label = isset($status_th[$row['status']]) ? $status_th[$row['status']] : $row['status'];
        $type_label = isset($type_th[$row['complaint_type']]) ? $type_th[$row['complaint_type']] : ($row['complaint_type'] ?? '-');
        $date_handled = ($row['updated_at'] && $row['status'] != 'Pending') ? $row['updated_at'] : '-';

        $user_attachments_html = '-';
        if (!empty($row['user_attachments_data'])) {
            $att_list = explode('||', $row['user_attachments_data']);
            $att_links = [];
            $i = 1;
            $has_multiple = count($att_list) > 1;
            foreach ($att_list as $att) {
                $parts = explode('::', $att);
                if (count($parts) == 2) {
                    $path = $parts[0];
                    $name = $parts[1];
                    $full_url = $base_url . $path;
                    $prefix = $has_multiple ? $i . '. ' : '';
                    $att_links[] = $prefix . '<a href="' . htmlspecialchars($full_url) . '">' . htmlspecialchars($name) . '</a>';
                    $i++;
                }
            }
            $user_attachments_html = implode('<br>', $att_links);
        }

        $emp_attachments_html = '-';
        if (!empty($row['emp_attachments_data'])) {
            $att_list = explode('||', $row['emp_attachments_data']);
            $att_links = [];
            $i = 1;
            $has_multiple = count($att_list) > 1;
            foreach ($att_list as $att) {
                $parts = explode('::', $att);
                if (count($parts) == 2) {
                    $path = $parts[0];
                    $name = $parts[1];
                    $full_url = $base_url . $path;
                    $prefix = $has_multiple ? $i . '. ' : '';
                    $att_links[] = $prefix . '<a href="' . htmlspecialchars($full_url) . '">' . htmlspecialchars($name) . '</a>';
                    $i++;
                }
            }
            $emp_attachments_html = implode('<br>', $att_links);
        }

        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['subject']) . '</td>';
        echo '<td>' . htmlspecialchars($row['description']) . '</td>';
        echo '<td>' . htmlspecialchars($type_label) . '</td>';
        echo '<td>' . htmlspecialchars($reporter) . '</td>';
        echo '<td>' . $user_attachments_html . '</td>';
        echo '<td>' . $row['created_at'] . '</td>';
        echo '<td>' . $date_handled . '</td>';
        echo '<td>' . htmlspecialchars($row['employee_note'] ?? '-') . '</td>';
        echo '<td>' . $emp_attachments_html . '</td>';
        echo '<td>' . htmlspecialchars($status_label) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

require_once '../includes/header.php';

// --- FILTER LOGIC ---
$current_year = date('Y');
$current_month = date('n');
$filter_month = $_GET['month'] ?? $current_month;
$filter_year = isset($_GET['year']) ? $_GET['year'] : ($current_year + 543);
$filter_year_en = $filter_year - 543;

$start_date_val = "";
$end_date_val = "";
$is_filtered = isset($_GET['month']) || isset($_GET['year']) || (isset($_GET['start_date']) && !empty($_GET['start_date']));
$is_month_filter = isset($_GET['month']) || isset($_GET['year']);

if ($is_filtered && isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    // Custom Range
    $start_date_val = $_GET['start_date'];
    $end_date_val = $_GET['end_date'];
    $dt_s = DateTime::createFromFormat('d/m/Y', $start_date_val);
    $dt_e = DateTime::createFromFormat('d/m/Y', $end_date_val);
    $startDate = $dt_s ? $dt_s->format('Y-m-d 00:00:00') : date('Y-m-01 00:00:00');
    $endDate = $dt_e ? $dt_e->format('Y-m-d 23:59:59') : date('Y-m-t 23:59:59');
} else {
    // Month/Year
    $startDate = "$filter_year_en-$filter_month-01 00:00:00";
    $endDate = date("Y-m-t 23:59:59", strtotime($startDate));
}

// 1. Status Counts (Cards)
$sql_status = "SELECT status, COUNT(*) as count FROM complaints WHERE assigned_dept_id = ? AND created_at BETWEEN ? AND ? GROUP BY status";
$stmt_status = $db->prepare($sql_status);
$stmt_status->execute([$dept_id, $startDate, $endDate]);
$raw_status = $stmt_status->fetchAll(PDO::FETCH_KEY_PAIR);

$total_req = array_sum($raw_status);
$pending = $raw_status['Pending'] ?? 0;
$review = $raw_status['Review'] ?? 0;
$processing = ($raw_status['In Progress'] ?? 0) + ($raw_status['Processed'] ?? 0); 
$completed = ($raw_status['Resolved'] ?? 0) + ($raw_status['Completed'] ?? 0);
$rejected = ($raw_status['Rejected'] ?? 0) + ($raw_status['Cancelled'] ?? 0);

// 2. Complaint Type Breakdown (Table)
$sql_types = "SELECT complaint_type, COUNT(*) as count FROM complaints WHERE assigned_dept_id = ? AND created_at BETWEEN ? AND ? GROUP BY complaint_type";
$stmt_types = $db->prepare($sql_types);
$stmt_types->execute([$dept_id, $startDate, $endDate]);
$type_stats = $stmt_types->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Monthly Trends (Chart)
$chart_sql = "SELECT DATE_FORMAT(created_at, '%c') as m, complaint_type, COUNT(*) as cnt 
              FROM complaints 
              WHERE assigned_dept_id = ?
              AND YEAR(created_at) = ? 
              GROUP BY m, complaint_type ORDER BY CAST(m AS UNSIGNED)";
$stmt_chart = $db->prepare($chart_sql);
$stmt_chart->execute([$dept_id, $filter_year_en]);
$monthly_raw = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

// 4. Employee Breakdown (Table)
$sql_emp = "SELECT e.full_name as name, COUNT(*) as count 
            FROM complaints c
            JOIN employees e ON c.assigned_employee_id = e.id
            WHERE c.assigned_dept_id = ? AND c.created_at BETWEEN ? AND ?
            GROUP BY c.assigned_employee_id 
            ORDER BY count DESC";
$stmt_emp = $db->prepare($sql_emp);
$stmt_emp->execute([$dept_id, $startDate, $endDate]);
$emp_stats = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

// 5. Detailed Complaint List Table
$stmt_details = $db->prepare("
    SELECT 
        c.id, 
        c.subject, 
        c.status, 
        c.created_at, 
        c.updated_at,
        c.is_anonymous,
        c.description,
        c.complaint_type,
        u.full_name as complainer_name,
        e.full_name as handler_name
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN employees e ON c.assigned_employee_id = e.id
    WHERE c.assigned_dept_id = ? AND c.created_at BETWEEN ? AND ?
    ORDER BY c.created_at DESC
");
$stmt_details->execute([$dept_id, $startDate, $endDate]);
$complaint_details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);


$thai_months_long = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap');

    /* Reuse Admin Print Styles */
    @media print {
        #sidebar, nav, .btn-pdf, .btn-excel, .breadcrumb, .box-info .bx, .head-title .left h1, #filterSection {
            display: none !important;
        }
        #content { width: 100% !important; left: 0 !important; }
        .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
        .box-info, .table-data { box-shadow: none !important; border: 1px solid #ddd !important; }
        canvas { max-height: 100% !important; max-width: 100% !important; }
    }
    .print-header { display: none; }
    
    .btn-excel {
        height: 36px; padding: 0 16px; border-radius: 36px; background: #107c41; color: #fff;
        display: flex; align-items: center; justify-content: center; gap: 10px; font-weight: 500; text-decoration: none;
    }
    .dropdown-item {
        color: var(--dark); transition: all 0.2s;
    }
    .dropdown-item:hover { background-color: var(--grey); }
    
    .input-theme { background-color: var(--grey) !important; color: var(--dark) !important; }

    /* Fix flatpickr styling in dark mode */
    body.dark .flatpickr-calendar {
        background: var(--light);
        box-shadow: 0 0 10px rgba(0,0,0,0.5);
    }
    body.dark .flatpickr-day, 
    body.dark .flatpickr-monthSelect-month {
        color: var(--dark);
    }
    body.dark .flatpickr-day.selected, 
    body.dark .flatpickr-monthSelect-month.selected {
        color: #fff;
    }
    body.dark .flatpickr-month {
        color: var(--dark);
        fill: var(--dark);
    }
    body.dark span.flatpickr-weekday {
        color: var(--dark);
    }
</style>

<!-- Print Header -->
<div class="print-header">
    <h1 class="text-2xl font-bold"><?php echo __('report_title'); ?> (Department)</h1>
    <p class="text-gray-600"><?php echo date('d/m/Y H:i'); ?></p>
</div>

<!-- Header -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('menu_reports'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('menu_reports'); ?></a></li>
        </ul>
    </div>
    <div class="relative">
        <button id="exportDropdownBtn" class="h-[36px] px-4 rounded-[36px] bg-[#5552f9] text-white flex items-center justify-center gap-2 font-medium border-none cursor-pointer hover:bg-[#403ecc]">
            <i class='bx bxs-download'></i>
            <span><?php echo __('report_export'); ?></span>
            <i class='bx bx-chevron-down'></i>
        </button>
        <div id="exportDropdownMenu" class="hidden absolute right-0 mt-2 w-48 rounded-xl shadow-lg border overflow-hidden z-50 bg-white">
            <a href="?export=excel&<?php echo http_build_query(array_merge($_GET, ['export' => null])); ?>" class="dropdown-item flex items-center gap-2 px-4 py-3 text-sm">
                <i class='bx bxs-file-export text-[#107c41] text-lg'></i>
                <?php echo __('report_download_excel'); ?>
            </a>
            <button onclick="downloadPDF()" class="dropdown-item w-full flex items-center gap-2 px-4 py-3 text-sm text-left bg-transparent border-none cursor-pointer">
                <i class='bx bxs-file-pdf text-[#5552f9] text-lg'></i>
                <?php echo __('report_download_pdf'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div id="filterSection" class="p-6 rounded-2xl shadow-sm mb-6" style="background: var(--light); color: var(--dark);">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Month/Year -->
        <div>
            <label class="block text-xs font-bold mb-2 uppercase tracking-wider text-gray-500">
                <i class='bx bx-calendar mr-1'></i> <?php echo __('report_filter_month'); ?>
            </label>
            <input type="text" id="monthYearPicker" value="<?php echo $is_month_filter ? ($thai_months_long[$filter_month] . ' ' . $filter_year) : ''; ?>"
                   class="input-theme w-full h-12 border rounded-xl px-4 focus:outline-none focus:ring-2 focus:ring-[#5552f9]">
        </div>
        <!-- Start Date -->
        <div>
            <label class="block text-xs font-bold mb-2 uppercase tracking-wider text-gray-500">
                <i class='bx bx-time-five mr-1'></i> <?php echo __('report_start_date'); ?>
            </label>
            <input type="text" id="startDate" value="<?php echo htmlspecialchars($start_date_val); ?>"
                   class="input-theme w-full h-12 border rounded-xl px-4 focus:outline-none focus:ring-2 focus:ring-[#5552f9]">
        </div>
        <!-- End Date -->
        <div>
            <label class="block text-xs font-bold mb-2 uppercase tracking-wider text-gray-500">
                <i class='bx bx-time-five mr-1'></i> <?php echo __('report_end_date'); ?>
            </label>
            <input type="text" id="endDate" value="<?php echo htmlspecialchars($end_date_val); ?>"
                   class="input-theme w-full h-12 border rounded-xl px-4 focus:outline-none focus:ring-2 focus:ring-[#5552f9]">
        </div>
    </div>
</div>

<!-- Breakdown Lists Container -->
<style>
    .scrollable-list {
        max-height: 310px;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 8px;
    }
    .scrollable-list::-webkit-scrollbar {
        width: 6px;
    }
    .scrollable-list::-webkit-scrollbar-track {
        background: transparent;
    }
    .scrollable-list::-webkit-scrollbar-thumb {
        background-color: rgba(0,0,0,0.1);
        border-radius: 10px;
    }
    .scrollable-list::-webkit-scrollbar-thumb:hover {
        background-color: rgba(0,0,0,0.2);
    }
</style>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <!-- Type / Employee Breakdown Card (Toggleable) -->
    <div class="table-data" style="margin-top: 0; background: transparent; padding: 0;">
        <div class="todo" style="background: var(--light); padding: 24px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); height: 100%;">
            <!-- Header with Dropdown -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <h3 id="typeChartTitle" class="text-xl font-bold" style="color: var(--dark);"><?php echo __('chart_by_type', 'แยกตามประเภท'); ?></h3>
                <select id="typeViewSelect" onchange="switchTypeView(this.value)"
                    style="padding: 6px 12px; border-radius: 10px; border: 1px solid var(--grey); background: var(--light); color: var(--dark); font-size: 13px; font-weight: 600; cursor: pointer; outline: none;">
                    <option value="type"><?php echo __('chart_by_type', 'แยกตามประเภท'); ?></option>
                    <option value="employee"><?php echo __('report_by_employee', 'แยกพนักงาน'); ?></option>
                </select>
            </div>
            <div class="flex flex-col xl:flex-row gap-6 items-center">
                <!-- Donut Chart -->
                <div style="position: relative; width: 200px; height: 200px; flex-shrink: 0;">
                    <canvas id="typeDonutChart"></canvas>
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none;">
                        <span style="font-size: 16px; color: var(--dark-grey);">Total</span>
                        <span id="typeChartTotal" style="font-size: 32px; font-weight: bold; color: var(--dark); line-height: 1;"><?php echo $total_req; ?></span>
                    </div>
                </div>
                
                <!-- List (Type) -->
                <ul id="listViewType" class="todo-list w-full" style="padding-right: 4px;">
                    <li onclick="filterTable('all')" class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease; cursor: pointer;">
                        <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: #8B5CF6; margin-right: 12px; flex-shrink: 0;"></span>
                        <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $total_req > 0 ? '100%' : '0%'; ?></span>
                        <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo __('report_table_total', 'รวมทั้งหมด'); ?>
                        </p>
                    </li>
                    <li onclick="filterTable('type', 'Complaint')" class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease; cursor: pointer;">
                        <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: var(--orange); margin-right: 12px; flex-shrink: 0;"></span>
                        <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $total_req > 0 ? round((($type_stats['Complaint'] ?? 0) / $total_req) * 100) . '%' : '0%'; ?></span>
                        <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0;">
                            <?php echo __('type_complaint', 'ข้อร้องเรียน'); ?>
                        </p>
                        <span style="font-weight: 600; font-size: 14px; color: var(--dark);"><?php echo $type_stats['Complaint'] ?? 0; ?></span>
                    </li>
                    <li onclick="filterTable('type', 'Suggestion')" class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease; cursor: pointer;">
                        <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: #3AB0FF; margin-right: 12px; flex-shrink: 0;"></span>
                        <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $total_req > 0 ? round((($type_stats['Suggestion'] ?? 0) / $total_req) * 100) . '%' : '0%'; ?></span>
                        <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0;">
                            <?php echo __('type_suggestion', 'ข้อเสนอแนะ'); ?>
                        </p>
                        <span style="font-weight: 600; font-size: 14px; color: var(--dark);"><?php echo $type_stats['Suggestion'] ?? 0; ?></span>
                    </li>
                    <li onclick="filterTable('type', 'Compliment')" class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease; cursor: pointer;">
                        <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: var(--green); margin-right: 12px; flex-shrink: 0;"></span>
                        <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $total_req > 0 ? round((($type_stats['Compliment'] ?? 0) / $total_req) * 100) . '%' : '0%'; ?></span>
                        <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0;">
                            <?php echo __('type_compliment', 'คำชมเชย'); ?>
                        </p>
                        <span style="font-weight: 600; font-size: 14px; color: var(--dark);"><?php echo $type_stats['Compliment'] ?? 0; ?></span>
                    </li>
                </ul>

                <!-- List (Employee) — hidden by default -->
                <?php
                $emp_colors = ['#8B5CF6','#3AB0FF','#28C76F','#FD7238','#FFCE26','#E83E8C','#20C997'];
                $total_emp  = array_sum(array_column($emp_stats, 'count'));
                ?>
                <ul id="listViewEmployee" class="todo-list w-full" style="padding-right: 4px; display: none;">
                    <?php if (empty($emp_stats)): ?>
                        <li class="completed" style="background: var(--light); border: 1px solid var(--grey); border-radius: 8px; padding: 8px 16px; justify-content: center;">
                            <p style="color: var(--dark-grey);">ไม่มีข้อมูล / No Data</p>
                        </li>
                    <?php else: ?>
                        <?php foreach ($emp_stats as $ei => $emp):
                            $epct = $total_emp > 0 ? round(($emp['count'] / $total_emp) * 100) : 0;
                            $ecol = $emp_colors[$ei % count($emp_colors)];
                        ?>
                        <li onclick="filterTable('employee', <?php echo json_encode($emp['name']); ?>)" class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease; cursor: pointer;">
                            <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: <?php echo $ecol; ?>; margin-right: 12px; flex-shrink: 0;"></span>
                            <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $epct; ?>%</span>
                            <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($emp['name']); ?>"><?php echo htmlspecialchars($emp['name']); ?></p>
                            <span style="font-weight: 600; font-size: 14px; color: var(--dark);"><?php echo $emp['count']; ?></span>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- JS for type/employee toggle -->
    <script>
    const typeDatasets = {
        type: {
            labels: [<?php echo json_encode(__('type_complaint','ข้อร้องเรียน')); ?>, <?php echo json_encode(__('type_suggestion','ข้อเสนอแนะ')); ?>, <?php echo json_encode(__('type_compliment','คำชมเชย')); ?>],
            data:   [<?php echo $type_stats['Complaint'] ?? 0; ?>, <?php echo $type_stats['Suggestion'] ?? 0; ?>, <?php echo $type_stats['Compliment'] ?? 0; ?>],
            colors: ['#FD7238','#3AB0FF','#28C76F'],
            total:  <?php echo (int)$total_req; ?>,
            title:  <?php echo json_encode(__('chart_by_type','แยกตามประเภท')); ?>
        },
        employee: {
            labels: <?php echo json_encode(array_column($emp_stats, 'name')); ?>,
            data:   <?php echo json_encode(array_column($emp_stats, 'count')); ?>,
            colors: <?php echo json_encode(array_slice($emp_colors, 0, count($emp_stats))); ?>,
            total:  <?php echo (int)$total_emp; ?>,
            title:  <?php echo json_encode(__('report_by_employee','แยกพนักงาน')); ?>
        }
    };
    let typeChart = null;

    function switchTypeView(mode) {
        const d = typeDatasets[mode];
        if (typeChart) {
            typeChart.data.labels = d.labels;
            typeChart.data.datasets[0].data   = d.data;
            typeChart.data.datasets[0].backgroundColor = d.colors;
            typeChart.update();
        }
        document.getElementById('typeChartTotal').textContent = d.total;
        document.getElementById('typeChartTitle').textContent = d.title;
        document.getElementById('listViewType').style.display     = mode === 'type'     ? '' : 'none';
        document.getElementById('listViewEmployee').style.display = mode === 'employee' ? '' : 'none';
    }
    </script>


    <!-- Status Breakdown List -->
    <div class="table-data" style="margin-top: 0; background: transparent; padding: 0;">
        <div class="todo" style="background: var(--light); padding: 24px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); height: 100%;">
            <div class="head mb-4 text-2xl font-bold" style="color: var(--dark);">
                <h3><?php echo __('report_table_status', 'สถานะ'); ?></h3>
            </div>
            <div class="flex flex-col xl:flex-row gap-6 items-center">
                <!-- Donut Chart -->
                <div style="position: relative; width: 200px; height: 200px; flex-shrink: 0;">
                    <canvas id="statusDonutChart"></canvas>
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none;">
                        <span style="font-size: 16px; color: var(--dark-grey);">Total</span>
                        <span style="font-size: 32px; font-weight: bold; color: var(--dark); line-height: 1;"><?php echo $total_req; ?></span>
                    </div>
                </div>
                
                <!-- List -->
                <ul class="todo-list w-full" style="padding-right: 4px;">
                    <li onclick="filterTable('status', 'Pending')" class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease; cursor: pointer;">
                        <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: var(--yellow); margin-right: 12px; flex-shrink: 0;"></span>
                        <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $total_req > 0 ? round(($pending / $total_req) * 100) . '%' : '0%'; ?></span>
                        <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo __('status_pending'); ?>
                        </p>
                    </li>
                    <li onclick="filterTable('status', 'Review')" class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease; cursor: pointer;">
                        <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: var(--orange); margin-right: 12px; flex-shrink: 0;"></span>
                        <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $total_req > 0 ? round(($review / $total_req) * 100) . '%' : '0%'; ?></span>
                        <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo __('status_review', 'รอตรวจสอบ'); ?>
                        </p>
                    </li>
                    <li onclick="filterTable('status', 'Rejected', 'Cancelled')" class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease; cursor: pointer;">
                        <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: var(--red); margin-right: 12px; flex-shrink: 0;"></span>
                        <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $total_req > 0 ? round(($rejected / $total_req) * 100) . '%' : '0%'; ?></span>
                        <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo __('status_rejected', 'ปฏิเสธ/ยกเลิก'); ?>
                        </p>
                    </li>
                    <li onclick="filterTable('status', 'In Progress', 'Processed')" class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease; cursor: pointer;">
                        <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: #3AB0FF; margin-right: 12px; flex-shrink: 0;"></span>
                        <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $total_req > 0 ? round(($processing / $total_req) * 100) . '%' : '0%'; ?></span>
                        <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo __('status_processed', 'ดำเนินการแล้ว'); ?>
                        </p>
                    </li>
                    <li onclick="filterTable('status', 'Resolved', 'Completed')" class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease; cursor: pointer;">
                        <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: var(--green); margin-right: 12px; flex-shrink: 0;"></span>
                        <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $total_req > 0 ? round(($completed / $total_req) * 100) . '%' : '0%'; ?></span>
                        <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo __('status_completed', 'เสร็จสิ้น'); ?>
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
<style>
    .hover-lift:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
</style>

<!-- Detailed Complaint List Table -->
<div class="p-6 rounded-2xl shadow-sm mt-6" style="background: var(--light); color: var(--dark); font-family: 'Prompt', sans-serif;">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
            <i class='bx bx-list-ul text-xl'></i>
        </div>
        <h3 class="text-xl font-bold" style="color: var(--dark);"><?php echo __('report_title'); ?></h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left text-sm border-b" style="color: var(--dark-grey); border-color: var(--grey);">
                    <th class="py-4 px-3 font-semibold text-gray-400 tracking-wide uppercase">#</th>
                    <th class="py-4 px-3 font-semibold text-gray-400 tracking-wide uppercase"><?php echo __('report_table_subject'); ?></th>
                    <th class="py-4 px-3 font-semibold text-gray-400 tracking-wide uppercase"><?php echo __('assigned_th_reporter'); ?></th>
                    <th class="py-4 px-3 font-semibold text-gray-400 tracking-wide uppercase"><?php echo __('report_table_date_reported', 'Date Reported'); ?></th>
                    <th class="py-4 px-3 font-semibold text-gray-400 tracking-wide uppercase"><?php echo __('report_table_handler_name', 'ผู้ดำเนินการ'); ?></th>
                    <th class="py-4 px-3 font-semibold text-gray-400 tracking-wide uppercase"><?php echo __('report_table_action_date', 'Action Date'); ?></th>
                    <th class="py-4 px-3 font-semibold text-gray-400 tracking-wide text-center uppercase"><?php echo __('report_table_status'); ?></th>
                </tr>
            </thead>
            <tbody style="color: var(--dark);">
                <?php 
                $k = 1;
                if (count($complaint_details) > 0):
                    foreach ($complaint_details as $row): 
                        // Reporter Logic
                        $reporter = $row['complainer_name'];
                        if ($row['is_anonymous']) {
                            $reporter = __('submit_caller_anonymous');
                        } elseif (empty($reporter)) {
                             // Try parsing guest info if stored in description
                             if (preg_match('/Name:\s*(.+)/', $row['description'], $matches)) {
                                 $reporter = trim($matches[1]);
                             } else {
                                 $reporter = '-';
                             }
                        }

                        // Status Logic
                        $status_class = '';
                        switch($row['status']) {
                            case 'Pending': $status_class = 'text-yellow-600 bg-yellow-100'; break;
                            case 'In Progress': $status_class = 'text-blue-600 bg-blue-100'; break;
                            case 'Processed': $status_class = 'text-indigo-600 bg-indigo-100'; break;
                            case 'Resolved': 
                            case 'Completed': $status_class = 'text-green-600 bg-green-100'; break;
                            case 'Rejected': 
                            case 'Cancelled': $status_class = 'text-red-600 bg-red-100'; break;
                            default: $status_class = 'text-gray-600 bg-gray-100';
                        }
                        
                        $status_label = isset($lang['status_' . strtolower(str_replace(' ', '_', $row['status']))]) 
                                        ? $lang['status_' . strtolower(str_replace(' ', '_', $row['status']))] 
                                        : $row['status'];
                ?>
                <tr class="border-b last:border-0 transition hover:bg-gray-50 filter-row" 
                    style="border-color: var(--grey);"
                    data-type="<?php echo htmlspecialchars($row['complaint_type'] ?? ''); ?>"
                    data-status="<?php echo htmlspecialchars($row['status'] ?? ''); ?>"
                    data-employee="<?php echo htmlspecialchars($row['handler_name'] ?? ''); ?>">
                    <td class="py-5 px-3 text-sm" style="color: var(--dark-grey);"><?php echo $k++; ?></td>
                    <td class="py-5 px-3 text-sm font-semibold">
                        <?php echo htmlspecialchars($row['subject']); ?>
                    </td>
                    <td class="py-4 px-2 text-sm font-medium text-gray-700">
                        <?php echo htmlspecialchars($reporter); ?>
                    </td>
                    <td class="py-4 px-2 text-sm text-gray-500">
                        <?php echo date('d/m/Y', strtotime($row['created_at'])); ?>
                    </td>
                    <td class="py-4 px-2 text-sm font-medium text-gray-700">
                        <?php echo !empty($row['handler_name']) ? htmlspecialchars($row['handler_name']) : '-'; ?>
                    </td>
                    <td class="py-4 px-2 text-sm text-gray-500">
                        <?php echo ($row['updated_at'] && $row['status'] != 'Pending') ? date('d/m/Y', strtotime($row['updated_at'])) : '-'; ?>
                    </td>
                    <td class="py-4 px-2 text-center">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $status_class; ?>">
                            <?php echo $status_label; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; 
                else: ?>
                <tr>
                    <td colspan="7" class="py-6 text-center text-gray-500">
                        <?php echo __('table_empty'); ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>



<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>

<script>
    // Initialize Donut Chart
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('typeDonutChart').getContext('2d');
        const getCSSVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        
        const data = {
            labels: [
                '<?php echo __("type_complaint", "ข้อร้องเรียน"); ?>', 
                '<?php echo __("type_suggestion", "ข้อเสนอแนะ"); ?>', 
                '<?php echo __("type_compliment", "คำชมเชย"); ?>'
            ],
            datasets: [{
                data: [
                    <?php echo $type_stats['Complaint'] ?? 0; ?>, 
                    <?php echo $type_stats['Suggestion'] ?? 0; ?>, 
                    <?php echo $type_stats['Compliment'] ?? 0; ?>
                ],
                backgroundColor: [
                    getCSSVar('--orange') || '#FD7238',
                    '#3AB0FF',
                    getCSSVar('--green') || '#28C76F'
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        };

        typeChart = new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: {
                cutout: '60%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false // We use our custom HTML legend
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    });

    // Initialize Status Donut Chart
    document.addEventListener('DOMContentLoaded', function() {
        const ctxStatus = document.getElementById('statusDonutChart').getContext('2d');
        const getCSSVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        
        const dataStatus = {
            labels: [
                '<?php echo __("status_pending"); ?>', 
                '<?php echo __("status_review", "รอตรวจสอบ"); ?>', 
                '<?php echo __("status_rejected", "ปฏิเสธ/ยกเลิก"); ?>',
                '<?php echo __("status_processed", "ดำเนินการแล้ว"); ?>',
                '<?php echo __("status_completed", "เสร็จสิ้น"); ?>'
            ],
            datasets: [{
                data: [
                    <?php echo $pending; ?>, 
                    <?php echo $review; ?>, 
                    <?php echo $rejected; ?>,
                    <?php echo $processing; ?>,
                    <?php echo $completed; ?>
                ],
                backgroundColor: [
                    getCSSVar('--yellow') || '#FFCE26',
                    getCSSVar('--orange') || '#FD7238',
                    getCSSVar('--red') || '#DB504A',
                    '#3AB0FF',
                    getCSSVar('--green') || '#28C76F'
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        };

        new Chart(ctxStatus, {
            type: 'doughnut',
            data: dataStatus,
            options: {
                cutout: '60%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false // We use our custom HTML legend
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    });

    // Filtering logic via Breakdown Lists
    function filterTable(filterBy, ...filterValues) {
        const rows = document.querySelectorAll('.filter-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            let showRow = false;
            if (filterBy === 'all') {
                showRow = true;
            } else {
                const rowValue = row.getAttribute('data-' + filterBy);
                if (filterValues.includes(rowValue)) {
                    showRow = true;
                }
            }

            if (showRow) {
                row.style.display = '';
                visibleCount++;
                // Update the row number column
                const indexCell = row.querySelector('td:first-child');
                if (indexCell) indexCell.textContent = visibleCount;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Handle empty state gracefully if all rows are hidden
        let emptyRow = document.getElementById('emptyFilterRow');
        const tbody = document.querySelector('tbody[style*="color: var(--dark);"]');
        
        if (visibleCount === 0) {
            if (!emptyRow && tbody) {
                emptyRow = document.createElement('tr');
                emptyRow.id = 'emptyFilterRow';
                emptyRow.innerHTML = `<td colspan="7" class="py-6 text-center text-gray-500"><?php echo __('table_empty'); ?></td>`;
                tbody.appendChild(emptyRow);
            } else if (emptyRow) {
                emptyRow.style.display = '';
            }
        } else if (emptyRow) {
            emptyRow.style.display = 'none';
        }
    }

    // Toggle Dropdown
    const dropdownBtn = document.getElementById('exportDropdownBtn');
    const dropdownMenu = document.getElementById('exportDropdownMenu');
    dropdownBtn.addEventListener('click', (e) => { e.stopPropagation(); dropdownMenu.classList.toggle('hidden'); });
    document.addEventListener('click', (e) => { if (!dropdownBtn.contains(e.target)) dropdownMenu.classList.add('hidden'); });

    // Download PDF
    function downloadPDF() {
        const element = document.querySelector('main'); // Assuming content is wrapped in main or use #content
        const opt = {
            margin: 10,
            filename: 'dept_report.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }



    // Flatpickr
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr("#monthYearPicker", {
            plugins: [new monthSelectPlugin({ shorthand: false, dateFormat: "m/Y", altFormat: "F Y", theme: "light" })],
            altInput: true,
            locale: "th",
            defaultDate: "<?php echo $filter_month . '/' . $filter_year_en; ?>",
            onChange: function(dates, str) {
                const [m, y] = str.split('/');
                window.location.href = `?month=${m}&year=${parseInt(y)+543}`;
            }
        });

        const startP = flatpickr("#startDate", { 
            locale: "th", 
            dateFormat: "d/m/Y", 
            altInput: true, 
            altFormat: "j F Y",
            maxDate: "today",
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates[0]) {
                    endP.set('minDate', selectedDates[0]);
                } else {
                    endP.set('minDate', null);
                }
                checkRange();
            }
        });

        const endP = flatpickr("#endDate", { 
            locale: "th", 
            dateFormat: "d/m/Y", 
            altInput: true, 
            altFormat: "j F Y",
            maxDate: "today",
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates[0]) {
                    startP.set('maxDate', selectedDates[0]);
                } else {
                    startP.set('maxDate', "today");
                }
                checkRange();
            }
        });

        // Initialize min/max from existing values
        if (document.getElementById('startDate').value) {
            const [d, m, y] = document.getElementById('startDate').value.split('/');
            endP.set('minDate', new Date(y, m - 1, d));
        }
        if (document.getElementById('endDate').value) {
            const [d, m, y] = document.getElementById('endDate').value.split('/');
            startP.set('maxDate', new Date(y, m - 1, d));
        }

        function checkRange() {
            const s = document.getElementById('startDate').value;
            const e = document.getElementById('endDate').value;
            if(s && e) window.location.href = `?start_date=${s}&end_date=${e}`;
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>