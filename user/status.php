<?php
// user/status.php
$hide_header = false;
require_once '../includes/header_landing.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$db = Database::connect();

// Fetch Agencies for Filter
$agencies_list = $db->query("SELECT * FROM agencies ORDER BY name ASC")->fetchAll();

// Filters
$agency       = isset($_GET['agency'])        ? $_GET['agency']                 : '';
$search       = isset($_GET['search'])        ? trim($_GET['search'])           : '';
$submit_date  = isset($_GET['submit_date'])   ? $_GET['submit_date']            : '';
$complete_date= isset($_GET['complete_date']) ? $_GET['complete_date']          : '';

// Query
$sql = "SELECT c.*, d.name as department_name,
        COALESCE(
            (SELECT u2.full_name
             FROM complaint_history h
             JOIN users u2 ON h.action_by_user_id = u2.id
             WHERE h.complaint_id = c.id AND u2.role_id IN (1,2,3)
             ORDER BY h.timestamp DESC LIMIT 1)
        ) as completed_by_name
        FROM complaints c
        LEFT JOIN departments d ON c.assigned_dept_id = d.id
        WHERE c.user_id = ? AND c.status NOT IN ('Completed', 'Cancelled') ";

$params = [$user_id];

if ($agency)        { $sql .= " AND c.program LIKE ?";        $params[] = $agency . '%'; }
if ($search)        { $sql .= " AND (c.subject LIKE ?)";     $params[] = "%$search%"; }
if ($submit_date)   { $sql .= " AND DATE(c.created_at) = ?"; $params[] = $submit_date; }
if ($complete_date) { $sql .= " AND DATE(c.updated_at) = ?"; $params[] = $complete_date; }

$sql .= " ORDER BY c.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();
?>

<style>
    :root {
        --poppins: 'Prompt', sans-serif;
        --light: #F9F9F9;
        --blue: #6C5CE7;
        --light-blue: #E0D9FC;
        --grey: #eee;
        --dark-grey: #AAAAAA;
        --dark: #342E37;
        --red: #DB504A;
        --yellow: #FFCE26;
        --light-yellow: #FFF2C6;
        --orange: #FD7238;
        --light-orange: #FFE0D3;
        --green: #2ECC71;
        --light-green: #D5F5E3;
    }

    body { font-family: var(--poppins); background: #f3f4f6; }
    a { text-decoration: none; }

    /* ===== Container ===== */
    .history-container {
        padding: 24px 16px;
        max-width: 1200px;
        margin: 0 auto;
    }
    @media (min-width: 640px) { .history-container { padding: 36px 24px; } }

    /* ===== Page Header ===== */
    .head-title {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .head-title .left h1 {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 6px;
        color: var(--dark);
        line-height: 1.2;
    }
    @media (min-width: 640px) { .head-title .left h1 { font-size: 32px; } }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        list-style: none;
        padding: 0;
        margin: 0;
        flex-wrap: wrap;
    }
    .breadcrumb li { color: var(--dark); font-size: 13px; }
    .breadcrumb li a { color: var(--dark-grey); pointer-events: none; }
    .breadcrumb li a.active { color: var(--blue); pointer-events: unset; }

    .btn-new {
        background: var(--blue);
        color: white;
        padding: 10px 18px;
        border-radius: 30px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 600;
        white-space: nowrap;
        transition: opacity 0.2s;
    }
    .btn-new:hover { opacity: 0.85; color: white; }

    /* ===== Toolbar ===== */
    .assigned-toolbar {
        background: #fff;
        border-radius: 16px;
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    @media (min-width: 640px) {
        .assigned-toolbar {
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center;
            padding: 15px 20px;
            gap: 12px;
        }
    }
    .filter-pill {
        background: #f1f5f9;
        border-radius: 10px;
        padding: 0 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        height: 44px;
        width: 100%;
    }
    @media (min-width: 640px) {
        .filter-pill { width: auto; min-width: 150px; flex: 1; }
        .filter-pill.search { flex: 2; }
    }
    .filter-pill i { color: var(--dark-grey); font-size: 18px; flex-shrink: 0; }
    .assigned-input, .assigned-select {
        background: transparent;
        border: none;
        font-size: 14px;
        outline: none;
        color: var(--dark);
        width: 100%;
        height: 100%;
    }
    .reset-btn {
        background: #f1f5f9;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--red);
        height: 44px;
        width: 44px;
        flex-shrink: 0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: all 0.3s;
        align-self: flex-end;
    }
    @media (min-width: 640px) { .reset-btn { align-self: auto; } }
    .reset-btn:hover { transform: scale(1.05); }

    /* ===== Status Pills ===== */
    .status {
        font-size: 11px;
        padding: 5px 11px;
        color: #fff;
        border-radius: 30px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
        white-space: nowrap;
    }
    .status.completed  { background: #2196F3; } /* Blue/Completed & Processed */
    .status.inprogress { background: #FFCE26; color: #342E37; } /* Yellow/Pending */
    .status.pending    { background: #AB47BC; } /* Purple/New */
    .status.awaiting   { background: #94A3B8; } /* Gray/Awaiting */
    .status.received   { background: #AB47BC; } /* Purple/Received */
    .status.review     { background: #FD7238; } /* Orange/Review */
    .status.rejected   { background: #DB504A; } /* Red */

    /* ===== Desktop Table ===== */
    .desktop-table-card {
        background: #fff;
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        overflow-x: auto;
        display: none;
    }
    @media (min-width: 640px) { .desktop-table-card { display: block; } }

    .desktop-table-card table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
    }
    .desktop-table-card table thead th {
        font-weight: 600;
        color: var(--dark-grey);
        padding: 12px 15px;
        font-size: 13px;
        text-align: left;
    }
    .desktop-table-card table tbody tr {
        background: #f8fafc;
        transition: all 0.25s;
    }
    .desktop-table-card table tbody tr:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }
    .desktop-table-card table tbody td {
        padding: 14px 15px;
        font-size: 14px;
        color: var(--dark);
        vertical-align: middle;
    }
    .desktop-table-card table tbody tr td:first-child { border-radius: 12px 0 0 12px; padding-left: 20px; }
    .desktop-table-card table tbody tr td:last-child  { border-radius: 0 12px 12px 0; }

    /* ===== Mobile Card List ===== */
    .mobile-card-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    @media (min-width: 640px) { .mobile-card-list { display: none; } }

    .m-complaint-card {
        background: #fff;
        border-radius: 16px;
        padding: 16px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border-left: 4px solid var(--blue);
    }
    .m-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }
    .m-card-index {
        font-size: 12px;
        font-weight: 700;
        color: var(--dark-grey);
        background: #f1f5f9;
        border-radius: 6px;
        padding: 2px 8px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .m-card-subject {
        font-size: 15px;
        font-weight: 600;
        color: var(--dark);
        line-height: 1.4;
        flex: 1;
    }
    .m-card-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 12px;
    }
    .m-meta-item { display: flex; flex-direction: column; gap: 2px; }
    .m-meta-label { font-size: 11px; color: var(--dark-grey); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
    .m-meta-value { font-size: 13px; color: var(--dark); font-weight: 500; }
    .m-card-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }
    .m-view-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--light-blue);
        color: var(--blue);
        font-size: 13px;
        font-weight: 700;
        padding: 8px 16px;
        border-radius: 10px;
        transition: all 0.2s;
    }
    .m-view-btn:hover { background: var(--blue); color: white; }

    /* ===== Empty State ===== */
    .empty-state {
        background: #fff;
        border-radius: 20px;
        padding: 50px 20px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
    }
    .empty-state i { font-size: 60px; color: #cbd5e1; display: block; margin-bottom: 12px; }
    /* Flatpickr Customization */
    .datepicker {
        background-color: transparent !important;
        color: var(--dark) !important;
        border: none;
        width: 100%;
        height: 100%;
        cursor: pointer;
        padding-left: 38px !important;
    }
    .datepicker::placeholder { color: var(--dark-grey); }
    
    /* Toolbar adjustment */
    .filter-pill.datepicker-wrap {
        padding-left: 10px;
        position: relative;
        min-width: 180px;
    }
</style>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<div class="history-container">

    <!-- Header -->
    <div class="head-title">
        <div class="left">
            <h1><?php echo __('menu_status', 'Status'); ?></h1>
        </div>
        <a href="../index.php" class="btn-new" style="background: var(--dark-grey);">
            <i class='bx bx-chevron-left'></i>
            <span><?php echo __('btn_back', 'Back'); ?></span>
        </a>
    </div>

    <!-- Toolbar -->
    <div class="assigned-toolbar">
        <form method="GET" style="display:contents;">
            <div class="filter-pill search">
                <i class='bx bx-search'></i>
                <input type="text" name="search"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="<?php echo __('assigned_search_placeholder', 'Search...'); ?>"
                       class="assigned-input">
                <input type="hidden" name="lang" value="<?php echo htmlspecialchars($curr_lang); ?>">
                <?php if($agency):      ?><input type="hidden" name="agency"        value="<?php echo htmlspecialchars($agency); ?>"><?php endif; ?>
                <?php if($submit_date): ?><input type="hidden" name="submit_date"   value="<?php echo htmlspecialchars($submit_date); ?>"><?php endif; ?>
            </div>

            <!-- Agency Filter -->
            <div class="filter-pill">
                <i class='bx bx-building'></i>
                <select name="agency" class="assigned-select" onchange="this.form.submit()">
                    <option value=""><?php echo __('nc_filter_all_agencies', 'All Agencies'); ?></option>
                    <?php foreach ($agencies_list as $ag): ?>
                        <option value="<?php echo htmlspecialchars($ag['name']); ?>" <?php echo $agency === $ag['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ag['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date (Flatpickr style) -->
            <div class="filter-pill datepicker-wrap">
                <i class='bx bx-calendar' style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); z-index: 10; pointer-events: none;"></i>
                <input type="text"
                       id="dateSubmit"
                       name="submit_date"
                       value="<?php echo htmlspecialchars($submit_date); ?>"
                       class="assigned-input datepicker"
                       placeholder="<?php echo __('filter_submitted', 'Submitted'); ?>"
                       title="<?php echo __('filter_submitted', 'Submitted'); ?>">
            </div>

            <?php if($agency || $search || $submit_date): ?>
                <a href="status.php" class="reset-btn" title="<?php echo __('btn_reset', 'Reset'); ?>">
                    <i class='bx bx-refresh' style="font-size:22px;"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (count($complaints) > 0): ?>

        <!-- ===== DESKTOP TABLE ===== -->
        <div class="desktop-table-card">
            <table>
                <thead>
                    <tr>
                        <th style="width:35%;"><?php echo __('history_th_subject'); ?></th>
                        <th style="text-align:center;"><?php echo __('nc_th_agency', 'Agency'); ?></th>
                        <th style="text-align:center;"><?php echo __('history_th_status'); ?></th>
                        <th style="text-align:center;"><?php echo __('history_th_date'); ?></th>
                        <th style="text-align:center;"><?php echo __('table_th_action', 'Action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $index => $complaint): ?>
                        <?php
                            $s = strtolower(str_replace(' ', '', $complaint['status']));
                            $sc = 'pending';
                            $status_key = 'status_' . strtolower(str_replace(' ', '_', $complaint['status']));

                            if ($s == 'completed') {
                                $sc = 'completed';
                                $status_key = 'status_completed';
                            } elseif ($s == 'resolved' || $s == 'processed') {
                                $sc = 'completed';
                                $status_key = 'status_processed';
                            } elseif ($s == 'rejected') {
                                $sc = 'rejected';
                                $status_key = 'status_rejected';
                            } elseif ($s == 'review') {
                                $sc = 'review';
                                $status_key = 'status_review';
                            } elseif (empty($complaint['assigned_dept_id'])) {
                                $sc = 'awaiting';
                                $status_key = 'status_awaiting_receipt';
                            } elseif (empty($complaint['assigned_employee_id']) && $s != 'inprogress') {
                                $sc = 'received';
                                $status_key = 'status_received';
                            } else {
                                $sc = 'inprogress';
                                $status_key = 'status_pending';
                            }

                            $parts = explode(' - ', $complaint['program'] ?? '-');
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:flex-start;gap:8px;font-weight:500;">
                                    <span style="flex-shrink:0;color:var(--dark-grey);"><?php echo $index+1; ?></span>
                                    <span style="word-break:break-word;line-height:1.4;"><?php echo htmlspecialchars($complaint['subject']); ?></span>
                                </div>
                            </td>
                            <td style="text-align:center;"><?php echo htmlspecialchars($parts[0]); ?></td>
                            <td style="text-align:center;"><span class="status <?php echo $sc; ?>"><?php echo __($status_key); ?></span></td>
                            <td style="text-align:center;"><?php echo date('d/m/Y', strtotime($complaint['created_at'])); ?></td>
                            <td style="text-align:center;">
                                <a href="status_details.php?id=<?php echo $complaint['id']; ?>" style="color:var(--dark-grey);font-size:20px;" title="<?php echo __('details_view_details', 'View Details'); ?>">
                                    <i class='bx bx-show'></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ===== MOBILE CARDS ===== -->
        <div class="mobile-card-list">
            <?php foreach ($complaints as $index => $complaint): ?>
                    <?php
                        $s = strtolower(str_replace(' ', '', $complaint['status']));
                        $sc = 'pending';
                        $status_key = 'status_' . strtolower(str_replace(' ', '_', $complaint['status']));

                        if ($s == 'completed') {
                            $sc = 'completed';
                            $status_key = 'status_completed';
                        } elseif ($s == 'resolved' || $s == 'processed') {
                            $sc = 'completed';
                            $status_key = 'status_processed';
                        } elseif ($s == 'rejected') {
                            $sc = 'rejected';
                            $status_key = 'status_rejected';
                        } elseif ($s == 'review') {
                            $sc = 'review';
                            $status_key = 'status_review';
                        } elseif (empty($complaint['assigned_dept_id'])) {
                            $sc = 'awaiting';
                            $status_key = 'status_awaiting_receipt';
                        } elseif (empty($complaint['assigned_employee_id']) && $s != 'inprogress') {
                            $sc = 'received';
                            $status_key = 'status_received';
                        } else {
                            $sc = 'inprogress';
                            $status_key = 'status_pending';
                        }

                        $parts = explode(' - ', $complaint['program'] ?? '-');
                        $border_colors = ['completed'=>'#2196F3','inprogress'=>'#FFCE26','rejected'=>'#DB504A','pending'=>'#AB47BC','received'=>'#AB47BC','review'=>'#FD7238','awaiting'=>'#94A3B8'];
                        $border = $border_colors[$sc] ?? '#6C5CE7';
                    ?>
                <div class="m-complaint-card" style="border-left-color: <?php echo $border; ?>;">
                    <div class="m-card-header">
                        <span class="m-card-index">#<?php echo $index+1; ?></span>
                        <div class="m-card-subject"><?php echo htmlspecialchars($complaint['subject']); ?></div>
                        <span class="status <?php echo $sc; ?>"><?php echo __($status_key); ?></span>
                    </div>
                    <div class="m-card-meta">
                        <div class="m-meta-item">
                            <div class="m-meta-label"><?php echo __('nc_th_agency', 'Agency'); ?></div>
                            <div class="m-meta-value"><?php echo htmlspecialchars($parts[0]); ?></div>
                        </div>
                        <div class="m-meta-item">
                            <div class="m-meta-label"><?php echo __('history_th_date'); ?></div>
                            <div class="m-meta-value"><?php echo date('d/m/Y', strtotime($complaint['created_at'])); ?></div>
                        </div>
                    </div>
                    <div class="m-card-footer">
                        <a href="status_details.php?id=<?php echo $complaint['id']; ?>" class="m-view-btn">
                            <i class='bx bx-show'></i> <?php echo __('details_view_details', 'View Details'); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div class="empty-state">
            <i class='bx bx-history'></i>
            <p><?php echo __('history_empty'); ?></p>
        </div>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr("#dateSubmit", {
            locale: "th",
            dateFormat: "Y-m-d", // Format for server-side processing
            altInput: true,
            altFormat: "j F Y", // Display format e.g., '1 มีนาคม 2026'
            allowInput: true,
            disableMobile: "true",
            maxDate: "today",
            onChange: function(selectedDates, dateStr, instance) {
                if (instance.element && instance.element.form) {
                    instance.element.form.submit();
                }
            }
        });
    });
</script>