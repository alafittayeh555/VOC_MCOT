<?php
// pr/reports.php
session_start();
require_once '../config/database.php';
// Add this helper for Thai Months if not exists or if we need to use it in JS
$thai_months_json = json_encode([
    'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
]);

// Check for PR Officer Role (ID 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();

// Export Logic
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Clear any previous output
    ob_clean();
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="complaint_raw_data_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    // Add BOM for UTF-8
    echo "\xEF\xBB\xBF"; 
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'; // Ensure Excel reads charset
    
    // START DATE & END DATE LOGIC FOR EXPORT
    $start_date_filter = date('Y-m-01 00:00:00'); // Default
    $end_date_filter = date('Y-m-t 23:59:59');

    if (isset($_GET['start_date']) && isset($_GET['end_date']) && !empty($_GET['start_date']) && !empty($_GET['end_date'])) {
         // Custom Range
         $dt_start = DateTime::createFromFormat('d/m/Y', $_GET['start_date']);
         $dt_end = DateTime::createFromFormat('d/m/Y', $_GET['end_date']);
         if ($dt_start && $dt_end) {
             $start_date_filter = $dt_start->format('Y-m-d 00:00:00');
             $end_date_filter = $dt_end->format('Y-m-d 23:59:59');
         }
    } elseif (isset($_GET['month']) || isset($_GET['year'])) {
         // Month/Year
         $y = isset($_GET['year']) ? ((int)$_GET['year'] - 543) : date('Y');
         $m = $_GET['month'] ?? date('n');
         $start_date_filter = "$y-$m-01 00:00:00";
         $end_date_filter = date("Y-m-t 23:59:59", strtotime($start_date_filter));
    }

    // Fetch Detailed Complaints
    $export_sql = "
        SELECT 
            c.subject, 
            c.description, 
            c.created_at, 
            c.complaint_type, 
            c.complaint_type, 
            c.is_anonymous,
            c.submission_channel,
            c.program as agency_name,
            u.full_name, 
            d.name as dept_name
        FROM complaints c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN departments d ON c.assigned_dept_id = d.id
        WHERE c.created_at BETWEEN :start AND :end
        ORDER BY c.created_at DESC
    ";
    
    $stmt_export = $db->prepare($export_sql);
    $stmt_export->execute([':start' => $start_date_filter, ':end' => $end_date_filter]);
    $export_data = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

    echo '<table border="1">';
    echo '<tr>
            <th style="background-color: #f0f0f0;">เรื่อง (Subject)</th>
            <th style="background-color: #f0f0f0;">รายละเอียด (Description)</th>
            <th style="background-color: #f0f0f0;">ผู้แจ้ง (Reporter)</th>
            <th style="background-color: #f0f0f0;">วันที่ (Date)</th>
            <th style="background-color: #f0f0f0;">หน่วยงาน (Agency)</th>
            <th style="background-color: #f0f0f0;">แผนก (Department)</th>
            <th style="background-color: #f0f0f0;">ประเภทเรื่อง (Type)</th>
            <th style="background-color: #f0f0f0;">ช่องทาง (Channel)</th>
          </tr>';
    
    foreach ($export_data as $row) {
        $reporter = ($row['is_anonymous'] == 1) ? 'ไม่ระบุตัวตน (Anonymous)' : $row['full_name'];
        $reporter = ($row['is_anonymous'] == 1) ? 'ไม่ระบุตัวตน (Anonymous)' : $row['full_name'];
        $dept = $row['dept_name'] ?? '-';
        $agency = $row['agency_name'] ?? '-';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['subject']) . '</td>';
        echo '<td>' . htmlspecialchars($row['description']) . '</td>';
        echo '<td>' . htmlspecialchars($reporter) . '</td>';
        echo '<td>' . $row['created_at'] . '</td>';
        echo '<td>' . htmlspecialchars($agency) . '</td>';
        echo '<td>' . htmlspecialchars($dept) . '</td>';
        echo '<td>' . htmlspecialchars($row['complaint_type']) . '</td>';
        echo '<td>' . htmlspecialchars($row['submission_channel']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

require_once '../includes/header.php';

// Data 1: Complaints by Category


// Data 2: Complaints by Department
// Date Filter Logic
$current_year = date('Y');
$current_month = date('n');

$filter_month = $_GET['month'] ?? $current_month;
$filter_year = isset($_GET['year']) ? $_GET['year'] : ($current_year + 543);
$filter_year_en = $filter_year - 543;

$start_date_val = "";
$end_date_val = "";

// Check if any filter is actually applied
$is_filtered = isset($_GET['month']) || isset($_GET['year']) || (isset($_GET['start_date']) && !empty($_GET['start_date']));
$is_month_filter = isset($_GET['month']) || isset($_GET['year']);

// Default date range = current month
$startDate = date('Y-m-01 00:00:00');
$endDate   = date('Y-m-t 23:59:59');

// Default empty arrays for when no filter is applied
$agency_stats      = [];
$agency_labels     = [];
$agency_complaints = [];
$agency_suggestions= [];
$agency_compliments= [];
$dept_stats        = [];
$complaint_details = [];
$dept_labels       = [];
$dept_complaints   = [];
$dept_suggestions  = [];
$dept_compliments  = [];
$total_system      = 0;
$total_pr          = 0;
$grand_total       = 0;
$percent_system    = 0;
$percent_pr        = 0;

if ($is_filtered) {
    if (isset($_GET['start_date']) && isset($_GET['end_date']) && !empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        // Custom Range Mode
        $start_date_val = $_GET['start_date'];
        $end_date_val = $_GET['end_date'];
        
        $dt_start = DateTime::createFromFormat('d/m/Y', $start_date_val);
        $dt_end = DateTime::createFromFormat('d/m/Y', $end_date_val);
        
        if ($dt_start && $dt_end) {
            $startDate = $dt_start->format('Y-m-d 00:00:00');
            $endDate = $dt_end->format('Y-m-d 23:59:59');
        } else {
            $startDate = date('Y-m-01 00:00:00');
            $endDate = date('Y-m-t 23:59:59');
        }
    } else {
        // Month/Year Mode
        $startDate = "$filter_year_en-$filter_month-01 00:00:00";
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));
    }

    // Data 2: Complaints by Agency (Detailed Breakdown)
    $stmt = $db->prepare("
        SELECT 
            a.name, 
            COALESCE(SUM(CASE WHEN c.complaint_type = 'Complaint' THEN 1 ELSE 0 END), 0) as complaint_count,
            COALESCE(SUM(CASE WHEN c.complaint_type = 'Suggestion' THEN 1 ELSE 0 END), 0) as suggestion_count,
            COALESCE(SUM(CASE WHEN c.complaint_type = 'Compliment' THEN 1 ELSE 0 END), 0) as compliment_count,
            COALESCE(SUM(CASE WHEN c.submission_channel = 'System' THEN 1 ELSE 0 END), 0) as system_count,
            COALESCE(SUM(CASE WHEN c.submission_channel = 'PR' THEN 1 ELSE 0 END), 0) as pr_count,
            COUNT(c.id) as total_count
        FROM agencies a 
        LEFT JOIN complaints c ON (c.program = a.name OR c.program LIKE CONCAT(a.name, ' - %')) 
        AND c.created_at BETWEEN :start_date AND :end_date
        GROUP BY a.id, a.name
        ORDER BY a.id ASC
    ");

    $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    $agency_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($agency_stats as $row) {
        $agency_labels[] = $row['name'];
        $agency_complaints[] = $row['complaint_count'];
        $agency_suggestions[] = $row['suggestion_count'];
        $agency_compliments[] = $row['compliment_count'];
    }

    // Data 3: Complaints by Department
    $stmt_dept = $db->prepare("
        SELECT 
            d.name, 
            COALESCE(SUM(CASE WHEN c.complaint_type = 'Complaint' THEN 1 ELSE 0 END), 0) as complaint_count,
            COALESCE(SUM(CASE WHEN c.complaint_type = 'Suggestion' THEN 1 ELSE 0 END), 0) as suggestion_count,
            COALESCE(SUM(CASE WHEN c.complaint_type = 'Compliment' THEN 1 ELSE 0 END), 0) as compliment_count,
            COALESCE(SUM(CASE WHEN c.submission_channel = 'System' THEN 1 ELSE 0 END), 0) as system_count,
            COALESCE(SUM(CASE WHEN c.submission_channel = 'PR' THEN 1 ELSE 0 END), 0) as pr_count,
            COUNT(c.id) as total_count
        FROM departments d 
        LEFT JOIN complaints c ON c.assigned_dept_id = d.id 
        AND c.created_at BETWEEN :start_date AND :end_date
        GROUP BY d.id, d.name
        ORDER BY total_count DESC
    ");

    $stmt_dept->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    $dept_stats = $stmt_dept->fetchAll(PDO::FETCH_ASSOC);

    $dept_labels = [];
    $dept_complaints = [];
    $dept_suggestions = [];
    $dept_compliments = [];

    foreach ($dept_stats as $row) {
        $dept_labels[]      = $row['name'];
        $dept_complaints[]  = $row['complaint_count'];
        $dept_suggestions[] = $row['suggestion_count'];
        $dept_compliments[] = $row['compliment_count'];
    }
}

// Data 3: Submission Channel Overview (always run after dates are set)
$stmt_channel = $db->prepare("
    SELECT 
        submission_channel, 
        COUNT(*) as count 
    FROM complaints 
    WHERE created_at BETWEEN :start_date AND :end_date
    GROUP BY submission_channel
");
$stmt_channel->execute([':start_date' => $startDate, ':end_date' => $endDate]);
$channel_data = $stmt_channel->fetchAll(PDO::FETCH_KEY_PAIR);

$total_system = $channel_data['System'] ?? 0;
$total_pr     = $channel_data['PR']     ?? 0;
if (isset($channel_data['Pr'])) $total_pr += $channel_data['Pr'];

$grand_total    = $total_system + $total_pr;
$percent_system = ($grand_total > 0) ? round(($total_system / $grand_total) * 100, 1) : 0;
$percent_pr     = ($grand_total > 0) ? round(($total_pr    / $grand_total) * 100, 1) : 0;

// Data 4: Detailed Complaint List
$stmt_details = $db->prepare("
    SELECT 
        c.id, 
        c.subject, 
        c.status, 
        c.created_at, 
        c.updated_at,
        c.is_anonymous,
        c.description,
        d.name as dept_name,
        u.full_name as complainer_name,
        e.full_name as handler_name
    FROM complaints c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON c.assigned_dept_id = d.id
    LEFT JOIN employees e ON c.assigned_employee_id = e.id
    WHERE c.created_at BETWEEN :start_date AND :end_date
    ORDER BY c.created_at DESC
");
$stmt_details->execute([':start_date' => $startDate, ':end_date' => $endDate]);
$complaint_details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);


// Thai Date Helpers
$thai_months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
$current_year_be = date('Y') + 543;
?>

<style>
    @media print {
        /* Hide unnecessary elements */
        #sidebar, nav, .btn-pdf, .btn-excel, .breadcrumb, .box-info .bx, .head-title .left h1 {
            display: none !important;
        }

        /* Adjust Layout for Print */
        #content {
            width: 100% !important;
            left: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        main {
            padding: 20px !important;
            font-family: 'Sarabun', sans-serif; /* Ensure Thai font readability */
        }

        /* Adjust Cards and Tables */
        .box-info, .table-data {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            margin-bottom: 20px !important;
        }

        /* Hide Filter Bar borders/inputs but keep content visible if needed, 
           or hide completely if interactive only. 
           User wants "neat", usually means showing the report title and data.
           Let's hide the interactive filter inputs but show the active date range if possible.
           For now, let's keep it clean by hiding the filter bar container's background/shadow
           but maybe keeping the text? 
           Actually, usually filters are for interaction. Let's hide the inputs border 
           to make it look like text.
        */
        .bg-white {
            background: none !important;
            box-shadow: none !important;
        }

        /* Ensure Charts Resize */
        canvas {
            max-height: 100% !important;
            max-width: 100% !important;
        }

        /* Force A4 Size (Optional but helpful) */
        @page {
            size: A4;
            margin: 1cm;
        }

        /* Header for Print */
        .print-header {
            display: block !important;
            text-align: center;
            margin-bottom: 20px;
        }
    }

    .print-header {
        display: none;
    }
    
    .btn-excel {
        height: 36px;
        padding: 0 16px;
        border-radius: 36px;
        background: #107c41; /* Excel Green */
        color: var(--light);
        display: flex;
        align-items: center;
        justify-content: center;
        grid-gap: 10px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    .btn-excel:hover {
        background: #0c5c30;
    }
    .btn-pdf {
        height: 36px;
        padding: 0 16px;
        border-radius: 36px;
        background: #5552f9; /* PDF Purple */
        color: var(--light);
        display: flex;
        align-items: center;
        justify-content: center;
        grid-gap: 10px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
    }
    .btn-pdf:hover {
        background: #403ecc;
    }
    .dropdown-item {
        color: var(--dark);
        transition: all 0.2s;
    }
    .dropdown-item:hover {
        background-color: var(--grey);
    }
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
        background-color: var(--grey) !important;
        color: var(--dark) !important;
    }
    .input-theme::placeholder {
        color: var(--dark-grey);
    }
</style>

<!-- Main Content -->
<div class="print-header">
    <h1 class="text-2xl font-bold"><?php echo __('report_title'); ?></h1>
    <p class="text-gray-600"><?php echo __('report_generated_on'); ?>: <?php echo date('d/m/Y H:i'); ?></p>
</div>

<div class="head-title">
    <div class="left">
        <h1><?php echo __('menu_reports'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('menu_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('menu_reports'); ?></a></li>
        </ul>
    </div>
    <div class="relative">
        <button id="exportDropdownBtn" class="h-[36px] px-4 rounded-[36px] bg-[#5552f9] text-white flex items-center justify-center gap-2 font-medium border-none cursor-pointer transition-all hover:bg-[#403ecc] focus:outline-none">
            <i class='bx bxs-download'></i>
            <span class="text"><?php echo __('report_export'); ?></span>
            <i class='bx bx-chevron-down'></i>
        </button>
        <!-- Dropdown Menu -->
        <div id="exportDropdownMenu" class="hidden absolute right-0 mt-2 w-48 rounded-xl shadow-lg border overflow-hidden z-50" style="background: var(--light); border-color: var(--grey);">
            <a href="?export=excel&<?php echo http_build_query(array_merge($_GET, ['export' => null])); ?>" class="dropdown-item flex items-center gap-2 px-4 py-3 text-sm transition-colors">
                <i class='bx bxs-file-export text-[#107c41] text-lg'></i>
                <?php echo __('report_download_excel'); ?>
            </a>
            <button onclick="downloadPDF()" class="dropdown-item w-full flex items-center gap-2 px-4 py-3 text-sm transition-colors text-left bg-transparent border-none cursor-pointer">
                <i class='bx bxs-file-pdf text-[#5552f9] text-lg'></i>
                <?php echo __('report_download_pdf'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="p-6 rounded-2xl shadow-sm mb-6" style="background: var(--light); color: var(--dark);">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Month & Year Group -->
        <div class="flex-1 w-full">
             <label class="block text-xs font-bold mb-2 uppercase tracking-wider" style="color: var(--dark-grey);">
                <i class='bx bx-calendar mr-1'></i> <?php echo __('report_filter_month'); ?>
            </label>
             <div class="relative w-full group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class='bx bx-calendar group-hover:text-[#5552f9] transition-colors' style="color: var(--dark-grey);"></i>
                </div>
                <input type="text" id="monthYearPicker" placeholder="Select Month/Year" 
                    value="<?php echo $is_month_filter ? ($thai_months[$filter_month] . ' ' . $filter_year) : ''; ?>"
                    class="input-theme w-full pl-10 pr-4 py-2.5 h-12 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#5552f9] focus:border-transparent transition-all <?php echo $is_month_filter ? 'border-[#5552f9] ring-1 ring-[#5552f9]' : 'border-gray-200'; ?>">
            </div>
        </div>



        <!-- Start Date -->
        <div class="flex-1 w-full">
            <label class="block text-xs font-bold mb-2 uppercase tracking-wider" style="color: var(--dark-grey);">
                <i class='bx bx-time-five mr-1'></i> <?php echo __('report_start_date'); ?>
            </label>
            <div class="relative w-full group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class='bx bx-calendar group-hover:text-[#5552f9] transition-colors' style="color: var(--dark-grey);"></i>
                </div>
                <input type="text" id="startDate" placeholder="<?php echo __('report_start_date'); ?>" value="<?php echo htmlspecialchars($start_date_val); ?>"
                    class="datepicker input-theme w-full pl-10 pr-4 py-2.5 h-12 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#5552f9] focus:border-transparent transition-all <?php echo ($is_filtered && !$is_month_filter) ? 'border-[#5552f9] ring-1 ring-[#5552f9]' : 'border-gray-200'; ?>">
            </div>
        </div>

        <!-- End Date + Search -->
        <div class="flex-1 w-full">
            <label class="block text-xs font-bold mb-2 uppercase tracking-wider" style="color: var(--dark-grey);">
                <i class='bx bx-time-five mr-1'></i> <?php echo __('report_end_date'); ?>
            </label>
                <div class="relative w-full group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class='bx bx-calendar-check group-hover:text-[#5552f9] transition-colors' style="color: var(--dark-grey);"></i>
                    </div>
                    <input type="text" id="endDate" placeholder="<?php echo __('report_end_date'); ?>" value="<?php echo htmlspecialchars($end_date_val); ?>"
                        class="datepicker input-theme w-full pl-10 pr-4 py-2.5 h-12 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#5552f9] focus:border-transparent transition-all <?php echo ($is_filtered && !$is_month_filter) ? 'border-[#5552f9] ring-1 ring-[#5552f9]' : 'border-gray-200'; ?>">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="box-info">
    <!-- Visual Summary Cards -->
</div>

<!-- Submission Channel Statistics -->
<div class="mt-6" style="display: flex; gap: 16px; flex-wrap: wrap;">

    <!-- System Card -->
    <div style="flex: 1; min-width: 180px; background: var(--light); border-radius: 16px; padding: 18px 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div style="width: 48px; height: 48px; border-radius: 14px; background: #ede9fe; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class='bx bx-laptop' style="font-size: 24px; color: #7c3aed;"></i>
        </div>
        <div>
            <p style="font-size: 12px; color: var(--dark-grey); margin: 0 0 4px;"><?php echo __('report_channel_system'); ?></p>
            <div style="display: flex; align-items: baseline; gap: 10px;">
                <span style="font-size: 28px; font-weight: 700; color: var(--dark); line-height: 1;"><?php echo number_format($total_system); ?></span>
                <span style="font-size: 12px; font-weight: 600; color: #16a34a; background: #dcfce7; padding: 2px 8px; border-radius: 20px;"><?php echo $percent_system; ?>%</span>
            </div>
        </div>
    </div>

    <!-- PR Card -->
    <div style="flex: 1; min-width: 180px; background: var(--light); border-radius: 16px; padding: 18px 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div style="width: 48px; height: 48px; border-radius: 14px; background: #fce7f3; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class='bx bx-user-voice' style="font-size: 24px; color: #be185d;"></i>
        </div>
        <div>
            <p style="font-size: 12px; color: var(--dark-grey); margin: 0 0 4px;"><?php echo __('report_channel_pr'); ?></p>
            <div style="display: flex; align-items: baseline; gap: 10px;">
                <span style="font-size: 28px; font-weight: 700; color: var(--dark); line-height: 1;"><?php echo number_format($total_pr); ?></span>
                <span style="font-size: 12px; font-weight: 600; color: #be185d; background: #fce7f3; padding: 2px 8px; border-radius: 20px;"><?php echo $percent_pr; ?>%</span>
            </div>
        </div>
    </div>

    <!-- Total Card -->
    <div style="flex: 1; min-width: 180px; background: var(--light); border-radius: 16px; padding: 18px 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div style="width: 48px; height: 48px; border-radius: 14px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class='bx bx-list-check' style="font-size: 24px; color: #475569;"></i>
        </div>
        <div>
            <p style="font-size: 12px; color: var(--dark-grey); margin: 0 0 4px;"><?php echo __('report_channel_total'); ?></p>
            <div style="display: flex; align-items: baseline; gap: 10px;">
                <span style="font-size: 28px; font-weight: 700; color: var(--dark); line-height: 1;"><?php echo number_format($grand_total); ?></span>
                <span style="font-size: 12px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 2px 8px; border-radius: 20px;">100%</span>
            </div>
        </div>
    </div>

</div>

    <!-- Tables Container Grid -->
<style>
    .scrollable-list {
        max-height: 250px;
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
    .hover-lift:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
</style>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-6">
    <!-- Agency / Type Breakdown Card (Toggleable) -->
    <div class="table-data" style="margin-top: 0; background: transparent; padding: 0;">
        <div class="todo" style="background: var(--light); padding: 24px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); height: 100%;">
            <!-- Header with Dropdown -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <h3 id="agencyChartTitle" class="text-xl font-bold" style="color: var(--dark);"><?php echo __('report_agency_complaints_title', 'แยกตามหน่วยงาน'); ?></h3>
                <select id="agencyViewSelect" onchange="switchAgencyView(this.value)"
                    style="padding: 6px 12px; border-radius: 10px; border: 1px solid var(--grey); background: var(--light); color: var(--dark); font-size: 13px; font-weight: 600; cursor: pointer; outline: none;">
                    <option value="agency"><?php echo __('nc_th_agency', 'หน่วยงาน'); ?></option>
                    <option value="type"><?php echo __('nc_th_type', 'ประเภท'); ?></option>
                    <option value="status"><?php echo __('filter_status_label', 'สถานะ'); ?></option>
                </select>
            </div>
            <div class="flex flex-col xl:flex-row gap-6 items-center">
                <?php
                // --- Agency data ---
                $agency_chart_stats = $agency_stats;
                usort($agency_chart_stats, function($a, $b) {
                    return $b['total_count'] <=> $a['total_count'];
                });
                $total_agency_complaints = 0;
                foreach ($agency_chart_stats as $stat) $total_agency_complaints += $stat['total_count'];

                $base_colors = ['#2e2b5f', '#5e54c8', '#8681e8', '#b5b2f2', '#e5e4fa', '#f3f3fd', '#d4d2f0'];
                $chart_labels = [];
                $chart_data   = [];
                $chart_colors = [];
                foreach ($agency_chart_stats as $index => $stat) {
                    $chart_labels[] = $stat['name'];
                    $chart_data[]   = $stat['total_count'];
                    $chart_colors[] = $base_colors[$index % count($base_colors)];
                }

                // --- Type data ---
                $type_labels = [__('nc_type_complaint','Complaint'), __('nc_type_suggestion','Suggestion'), __('nc_type_compliment','Compliment')];
                $type_colors = ['#5e54c8', '#28C76F', '#FD7238'];
                $type_counts = [0, 0, 0];
                foreach ($agency_stats as $row) {
                    $type_counts[0] += $row['complaint_count'];
                    $type_counts[1] += $row['suggestion_count'];
                    $type_counts[2] += $row['compliment_count'];
                }
                $total_type = array_sum($type_counts);

                // --- Status data ---
                $status_map = [
                    'Pending'     => [__('status_pending','Pending'),     '#f59e0b', 0],
                    'Received'    => [__('status_received','Received'),    '#3b82f6', 0],
                    'In Progress' => [__('status_in_progress','In Progress'), '#8b5cf6', 0],
                    'Review'      => [__('status_review','Review'),        '#06b6d4', 0],
                    'Processed'   => [__('status_processed','Processed'),  '#6366f1', 0],
                    'Resolved'    => [__('status_resolved','Resolved'),    '#10b981', 0],
                    'Completed'   => [__('status_completed','Completed'),  '#22c55e', 0],
                    'Rejected'    => [__('status_rejected','Rejected'),    '#ef4444', 0],
                ];
                // query status counts
                $stmt_status = $db->prepare("
                    SELECT status, COUNT(*) as cnt FROM complaints
                    WHERE created_at BETWEEN :s AND :e
                    GROUP BY status
                ");
                $stmt_status->execute([':s' => $startDate, ':e' => $endDate]);
                foreach ($stmt_status->fetchAll(PDO::FETCH_ASSOC) as $sr) {
                    if (isset($status_map[$sr['status']])) {
                        $status_map[$sr['status']][2] = (int)$sr['cnt'];
                    }
                }
                // filter to non-zero only
                $status_map = array_filter($status_map, fn($v) => $v[2] > 0);
                $status_labels = array_column(array_values($status_map), 0);
                $status_colors = array_column(array_values($status_map), 1);
                $status_counts = array_column(array_values($status_map), 2);
                $total_status  = array_sum($status_counts);
                ?>
                <!-- Donut Chart -->
                <div style="position: relative; width: 260px; height: 260px; flex-shrink: 0;">
                    <canvas id="agencyDonutChart"></canvas>
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none;">
                        <span style="font-size: 18px; color: var(--dark-grey);">Total</span>
                        <span id="agencyChartTotal" style="font-size: 36px; font-weight: bold; color: var(--dark); line-height: 1;"><?php echo $total_agency_complaints; ?></span>
                    </div>
                </div>

                <!-- List (Agency) -->
                <ul id="listViewAgency" class="todo-list w-full scrollable-list" style="padding-right: 4px;">
                    <?php if (count($agency_chart_stats) == 0): ?>
                        <li class="completed" style="background: var(--light); border: 1px solid var(--grey); border-radius: 8px; padding: 8px 16px; justify-content: center;">
                            <p style="color: var(--dark-grey);">ไม่มีข้อมูล / No Data</p>
                        </li>
                    <?php else: ?>
                        <?php foreach ($agency_chart_stats as $i => $stat):
                            $pct   = $total_agency_complaints > 0 ? round(($stat['total_count'] / $total_agency_complaints) * 100) : 0;
                            $color = $chart_colors[$i % count($chart_colors)];
                        ?>
                        <li class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease;">
                            <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: <?php echo $color; ?>; margin-right: 12px; flex-shrink: 0;"></span>
                            <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $pct; ?>%</span>
                            <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($stat['name']); ?>">
                                <?php echo htmlspecialchars($stat['name']); ?>
                            </p>
                            <span style="font-weight: 600; font-size: 14px; color: var(--dark);"><?php echo $stat['total_count']; ?></span>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <!-- List (Type) — hidden by default -->
                <ul id="listViewType" class="todo-list w-full scrollable-list" style="padding-right: 4px; display: none;">
                    <?php foreach ($type_labels as $ti => $tlabel):
                        $tc   = $type_counts[$ti];
                        $tpct = $total_type > 0 ? round(($tc / $total_type) * 100) : 0;
                        $tcol = $type_colors[$ti];
                    ?>
                    <li class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease;">
                        <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: <?php echo $tcol; ?>; margin-right: 12px; flex-shrink: 0;"></span>
                        <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $tpct; ?>%</span>
                        <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0;"><?php echo htmlspecialchars($tlabel); ?></p>
                        <span style="font-weight: 600; font-size: 14px; color: var(--dark);"><?php echo $tc; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <!-- List (Status) — hidden by default -->
                <ul id="listViewStatus" class="todo-list w-full scrollable-list" style="padding-right: 4px; display: none;">
                    <?php if (empty($status_labels)): ?>
                        <li class="completed" style="background: var(--light); border: 1px solid var(--grey); border-radius: 8px; padding: 8px 16px; justify-content: center;">
                            <p style="color: var(--dark-grey);">ไม่มีข้อมูล / No Data</p>
                        </li>
                    <?php else: ?>
                        <?php foreach ($status_labels as $si => $slabel):
                            $sc   = $status_counts[$si];
                            $spct = $total_status > 0 ? round(($sc / $total_status) * 100) : 0;
                            $scol = $status_colors[$si];
                        ?>
                        <li class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease;">
                            <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: <?php echo $scol; ?>; margin-right: 12px; flex-shrink: 0;"></span>
                            <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $spct; ?>%</span>
                            <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0;"><?php echo htmlspecialchars($slabel); ?></p>
                            <span style="font-weight: 600; font-size: 14px; color: var(--dark);"><?php echo $sc; ?></span>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- JS data for toggle -->
    <script>
    const agencyDatasets = {
        agency: {
            labels: <?php echo json_encode($chart_labels); ?>,
            data:   <?php echo json_encode($chart_data); ?>,
            colors: <?php echo json_encode($chart_colors); ?>,
            total:  <?php echo (int)$total_agency_complaints; ?>,
            title:  <?php echo json_encode(__('report_agency_complaints_title','แยกตามหน่วยงาน')); ?>
        },
        type: {
            labels: <?php echo json_encode($type_labels); ?>,
            data:   <?php echo json_encode($type_counts); ?>,
            colors: <?php echo json_encode($type_colors); ?>,
            total:  <?php echo (int)$total_type; ?>,
            title:  <?php echo json_encode(__('nc_th_type','แยกตามประเภท')); ?>
        },
        status: {
            labels: <?php echo json_encode($status_labels ?? []); ?>,
            data:   <?php echo json_encode($status_counts ?? []); ?>,
            colors: <?php echo json_encode($status_colors ?? []); ?>,
            total:  <?php echo (int)($total_status ?? 0); ?>,
            title:  <?php echo json_encode(__('filter_status_label','แยกตามสถานะ')); ?>
        }
    };
    let agencyChart = null;

    function switchAgencyView(mode) {
        const d = agencyDatasets[mode];
        if (agencyChart) {
            agencyChart.data.labels = d.labels;
            agencyChart.data.datasets[0].data = d.data;
            agencyChart.data.datasets[0].backgroundColor = d.colors;
            agencyChart.update();
        }
        document.getElementById('agencyChartTotal').textContent = d.total;
        document.getElementById('agencyChartTitle').textContent = d.title;
        document.getElementById('listViewAgency').style.display = mode === 'agency' ? '' : 'none';
        document.getElementById('listViewType').style.display   = mode === 'type'   ? '' : 'none';
        document.getElementById('listViewStatus').style.display = mode === 'status' ? '' : 'none';
    }
    </script>


    <!-- Department Complaint Breakdown List -->
    <div class="table-data" style="margin-top: 0; background: transparent; padding: 0;">
        <div class="todo" style="background: var(--light); padding: 24px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); height: 100%;">
            <div class="head mb-4 text-2xl font-bold" style="color: var(--dark);">
                <h3><?php echo __('report_department_complaints_title', 'แยกตามแผนก'); ?></h3>
            </div>
            <div class="flex flex-col xl:flex-row gap-6 items-center">
                <?php
                // Prepare Data
                $dept_chart_stats = $dept_stats;
                usort($dept_chart_stats, function($a, $b) {
                    return $b['total_count'] <=> $a['total_count'];
                });
                
                $total_dept_complaints = 0;
                foreach ($dept_chart_stats as $stat) {
                    $total_dept_complaints += $stat['total_count'];
                }

                $dept_chart_labels = [];
                $dept_chart_data = [];
                $dept_chart_colors = [];
                // Rich palette for departments
                $dept_base_colors = ['#8B5CF6', '#3AB0FF', '#28C76F', '#FD7238', '#FFCE26', '#E83E8C', '#20C997', '#6610F2', '#FD7E14', '#0D6EFD'];

                foreach ($dept_chart_stats as $index => $stat) {
                    $dept_chart_labels[] = $stat['name'];
                    $dept_chart_data[] = $stat['total_count'];
                    $dept_chart_colors[] = $dept_base_colors[$index % count($dept_base_colors)];
                }
                ?>
                <!-- Donut Chart -->
                <div style="position: relative; width: 260px; height: 260px; flex-shrink: 0;">
                    <canvas id="deptDonutChart"></canvas>
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none;">
                        <span style="font-size: 18px; color: var(--dark-grey);">Total</span>
                        <span style="font-size: 36px; font-weight: bold; color: var(--dark); line-height: 1;"><?php echo $total_dept_complaints; ?></span>
                    </div>
                </div>
                
                <!-- List -->
                <ul class="todo-list w-full scrollable-list" style="padding-right: 4px;">
                    <?php if (count($dept_chart_stats) == 0): ?>
                        <li class="completed" style="background: var(--light); border: 1px solid var(--grey); border-radius: 8px; padding: 8px 16px; justify-content: center;">
                            <p style="color: var(--dark-grey);">ไม่มีข้อมูล / No Data</p>
                        </li>
                    <?php else: ?>
                        <?php foreach ($dept_chart_stats as $i => $stat): 
                            $pct = $total_dept_complaints > 0 ? round(($stat['total_count'] / $total_dept_complaints) * 100) : 0;
                            $color = $dept_chart_colors[$i % count($dept_chart_colors)];
                        ?>
                        <li class="completed hover-lift" style="background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-radius: 8px; margin-bottom: 10px; padding: 8px 16px; display: flex; align-items: center; transition: all 0.3s ease;">
                            <span style="display: inline-block; width: 16px; height: 16px; border-radius: 4px; background: <?php echo $color; ?>; margin-right: 12px; flex-shrink: 0;"></span>
                            <span style="font-weight: 700; font-size: 16px; width: 50px; color: var(--dark);"><?php echo $pct; ?>%</span>
                            <p style="font-weight: 500; font-size: 14px; flex-grow: 1; color: var(--dark-grey); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($stat['name']); ?>">
                                <?php echo htmlspecialchars($stat['name']); ?>
                            </p>
                            <span style="font-weight: 600; font-size: 14px; color: var(--dark);"><?php echo $stat['total_count']; ?></span>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Complaint List Table -->
<div class="p-6 rounded-2xl shadow-sm mt-6" style="background: var(--light); color: var(--dark);">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
            <i class='bx bx-list-ul text-xl'></i>
        </div>
        <h3 class="text-xl font-bold" style="color: var(--dark);"><?php echo __('report_title'); ?> (Detailed)</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left text-sm border-b" style="color: var(--dark-grey); border-color: var(--grey);">
                    <th class="py-3 px-2 font-normal">#</th>
                    <th class="py-3 px-2 font-normal"><?php echo __('report_table_subject'); ?></th>
                    <th class="py-3 px-2 font-normal"><?php echo __('report_table_agency'); ?></th>
                    <th class="py-3 px-2 font-normal"><?php echo __('report_table_handler'); ?></th>
                    <th class="py-3 px-2 font-normal"><?php echo __('report_table_date_handled'); ?></th>
                    <th class="py-3 px-2 font-normal text-center"><?php echo __('report_table_status'); ?></th>
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
                <tr class="border-b last:border-0 transition hover:bg-gray-50" style="border-color: var(--grey);">
                    <td class="py-4 px-2" style="color: var(--dark-grey);"><?php echo $k++; ?></td>
                    <td class="py-4 px-2 font-medium">
                        <?php echo htmlspecialchars($row['subject']); ?>
                    </td>
                    <td class="py-4 px-2 text-sm text-gray-600">
                        <?php echo htmlspecialchars($row['dept_name'] ?? '-'); ?>
                    </td>
                    <td class="py-4 px-2 text-sm font-medium text-gray-700">
                        <?php echo htmlspecialchars($row['handler_name'] ?? '-'); ?>
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
                    <td colspan="6" class="py-6 text-center text-gray-500">
                        <?php echo __('table_empty'); ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctxAgency = document.getElementById('agencyDonutChart');
        if (ctxAgency) {
            agencyChart = new Chart(ctxAgency.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($chart_labels ?? []); ?>,
                    datasets: [{
                        data: <?php echo json_encode($chart_data ?? []); ?>,
                        backgroundColor: <?php echo json_encode($chart_colors ?? []); ?>,
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    cutout: '60%',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) label += ': ';
                                    if (context.parsed !== null) label += context.parsed;
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        const ctxDept = document.getElementById('deptDonutChart');
        if (ctxDept) {
            new Chart(ctxDept.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($dept_chart_labels ?? []); ?>,
                    datasets: [{
                        data: <?php echo json_encode($dept_chart_data ?? []); ?>,
                        backgroundColor: <?php echo json_encode($dept_chart_colors ?? []); ?>,
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    cutout: '60%',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) label += ': ';
                                    if (context.parsed !== null) label += context.parsed;
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
    });

    function downloadPDF() {
        // Elements to hide during PDF generation
        const sidebar = document.getElementById('sidebar');
        const nav = document.querySelector('nav');
        const buttons = document.querySelector('.head-title .flex'); // The export buttons div
        const breadcrumb = document.querySelector('.breadcrumb');
        const filterBar = document.querySelector('.p-6.rounded-2xl.shadow-sm.mb-6');
        const printHeader = document.querySelector('.print-header');

        // Show header, hide others
        if(printHeader) printHeader.style.display = 'block';
        if(sidebar) sidebar.style.display = 'none';
        if(nav) nav.style.display = 'none';
        if(buttons) buttons.style.display = 'none';
        if(breadcrumb) breadcrumb.style.display = 'none';
        if(filterBar) filterBar.style.display = 'none';

        // Use the 'main' content or specific wrapper
        const element = document.querySelector('main');
        
        // Optimize element styles for capture
        const originalPadding = element.style.padding;
        element.style.padding = '20px';

        const opt = {
            margin:       [10, 10, 10, 10],
            filename:     'complaint_report_<?php echo date('Y-m-d'); ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, logging: true, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        // Generate
        html2pdf().set(opt).from(element).save().then(function(){
            // Restore visibility
            if(printHeader) printHeader.style.display = 'none';
            if(sidebar) sidebar.style.display = '';
            if(nav) nav.style.display = '';
            if(buttons) buttons.style.display = '';
            if(breadcrumb) breadcrumb.style.display = '';
            if(filterBar) filterBar.style.display = '';
            element.style.padding = originalPadding;
        });
    }

    // Flatpickr Initialization
    document.addEventListener('DOMContentLoaded', function() {
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');

        // Helper to check range and redirect
        function checkAndRedirect() {
             // Flatpickr updates the input value AFTER onChange usually, but let's grab values 
             // directly or wait a tick. Actually in onChange (below), we get selectedDates/dateStr.
             // But we have 2 separate inputs.
             // We can read the values from the inputs directly.
             const start = startDateInput.value;
             const end = endDateInput.value;

             if (start && end) {
                 window.location.href = '?start_date=' + encodeURIComponent(start) + '&end_date=' + encodeURIComponent(end);
             }
        }

        // Initialize End Date Picker first
        const endPicker = flatpickr("#endDate", {
            locale: "th",
            dateFormat: "d/m/Y",
            altInput: true,
            altFormat: "j F Y",
            allowInput: true,
            disableMobile: "true",
            maxDate: "today",
            minDate: startDateInput.value, // Set initial minDate based on Start Date
            onChange: function(selectedDates, dateStr, instance) {
                setTimeout(checkAndRedirect, 100); 
            }
        });

        // Initialize Start Date Picker
        const startPicker = flatpickr("#startDate", {
            locale: "th",
            dateFormat: "d/m/Y",
            altInput: true,
            altFormat: "j F Y",
            allowInput: true,
            disableMobile: "true",
            maxDate: "today",
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates[0]) {
                    // Update End Date's minDate
                    endPicker.set('minDate', selectedDates[0]);
                    
                    // If End Date is selected and is now earlier than Start Date, clear it
                    if (endPicker.selectedDates[0] && endPicker.selectedDates[0] < selectedDates[0]) {
                        endPicker.clear();
                    }
                }
                setTimeout(checkAndRedirect, 100); 
            }
        });

        // Month & Year Selection via Flatpickr
        flatpickr("#monthYearPicker", {
            plugins: [
                new monthSelectPlugin({
                    shorthand: false, 
                    dateFormat: "m/Y", // format for value
                    altFormat: "F Y", // user facing
                    theme: "light"
                })
            ],
            altInput: true,
            defaultDate: "<?php echo $is_month_filter ? ($filter_month . '/' . $filter_year_en) : ''; ?>",
            maxDate: "today",
            locale: "th",
            onChange: function(selectedDates, dateStr, instance) {
                // dateStr is m/Y e.g. 01/2026
                const parts = dateStr.split('/');
                const m = parts[0]; 
                const y_ad = parseInt(parts[1]); 
                const y_be = y_ad + 543;

                window.location.href = '?month=' + m + '&year=' + y_be;
            }
        });

        // Dropdown Logic
        const dropdownBtn = document.getElementById('exportDropdownBtn');
        const dropdownMenu = document.getElementById('exportDropdownMenu');

        if(dropdownBtn && dropdownMenu) {
            dropdownBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.classList.add('hidden');
                }
            });
        }
    });
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>

<?php require_once '../includes/footer.php'; ?>
