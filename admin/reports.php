<?php
// admin/reports.php
session_start();
require_once '../config/database.php';
// Add this helper for Thai Months if not exists or if we need to use it in JS
$thai_months_json = json_encode([
    'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
]);

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
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
            c.is_anonymous,
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

$agency_stats = [];
$agency_labels = [];
$agency_complaints = [];
$agency_suggestions = [];
$agency_compliments = [];

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
}





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

    <!-- Departments Complaint Table -->
<div class="p-6 rounded-2xl shadow-sm mt-6" style="background: var(--light); color: var(--dark);">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
            <i class='bx bxs-building-house text-xl'></i>
        </div>
        <h3 class="text-xl font-bold" style="color: var(--dark);"><?php echo __('report_agency_complaints_title'); ?></h3>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left text-sm border-b" style="color: var(--dark-grey); border-color: var(--grey);">
                    <th class="py-3 px-2 font-normal w-12">#</th>
                    <th class="py-3 px-2 font-normal"><?php echo __('report_table_agency_name'); ?></th>
                    <th class="py-3 px-2 font-normal text-right text-orange-600"><?php echo __('report_table_complaint'); ?></th>
                    <th class="py-3 px-2 font-normal text-right text-blue-600"><?php echo __('report_table_suggestion'); ?></th>
                    <th class="py-3 px-2 font-normal text-right text-green-600"><?php echo __('report_table_compliment'); ?></th>
                    <th class="py-3 px-2 font-normal text-right"><?php echo __('report_table_total'); ?></th>
                </tr>
            </thead>
            <tbody style="color: var(--dark);">
                <?php 
                $i = 1;
                foreach ($agency_stats as $row): 
                ?>
                <tr class="border-b last:border-0 transition" style="border-color: var(--grey);">
                    <td class="py-4 px-2" style="color: var(--dark-grey);"><?php echo $i++; ?></td>
                    <td class="py-4 px-2 font-medium"><?php echo htmlspecialchars($row['name']); ?></td>
                    <td class="py-4 px-2 text-right font-medium text-orange-600"><?php echo $row['complaint_count']; ?></td>
                    <td class="py-4 px-2 text-right font-medium text-blue-600"><?php echo $row['suggestion_count']; ?></td>
                    <td class="py-4 px-2 text-right font-medium text-green-600"><?php echo $row['compliment_count']; ?></td>
                    <td class="py-4 px-2 text-right font-bold" style="color: var(--dark);"><?php echo $row['total_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="table-data mt-6">
    <div class="order">
        <div class="head">
            <h3><?php echo __('report_chart_title'); ?></h3>
        </div>
        <div style="max-height: 400px; display: flex; justify-content: center;">
            <canvas id="deptChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Department Chart
    const ctxDept = document.getElementById('deptChart').getContext('2d');
    new Chart(ctxDept, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($agency_labels); ?>,
            datasets: [
                {
                    label: 'Complaint',
                    data: <?php echo json_encode($agency_complaints); ?>,
                    borderColor: '#FD7238', // Orange
                    backgroundColor: 'rgba(253, 114, 56, 0.2)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#FD7238',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Suggestion',
                    data: <?php echo json_encode($agency_suggestions); ?>,
                    borderColor: '#3C91E6', // Blue
                    backgroundColor: 'rgba(60, 145, 230, 0.2)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#3C91E6',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Compliment',
                    data: <?php echo json_encode($agency_compliments); ?>,
                    borderColor: '#10b981', // Green
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#10b981',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { 
                    display: true,
                    position: 'bottom' 
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f0f0f0'
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
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