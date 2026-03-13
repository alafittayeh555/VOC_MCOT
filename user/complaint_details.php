<?php
// user/complaint_details.php
$hide_header = false;
require_once '../includes/header_landing.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    header("Location: ../login.php");
    exit;
}

$complaint_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$db = Database::connect();

// Fetch Complaint Details (SECURE: Ensure user_id matches)
$sql = "SELECT c.*, u.full_name as complainer, u.email, u.phone, u.occupation,
               d.name as department_name
        FROM complaints c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN departments d ON c.assigned_dept_id = d.id
        WHERE c.id = ? AND c.user_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$complaint_id, $user_id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    header("Location: dashboard.php");
    exit;
}

// Fetch Attachments
$stmtAtt = $db->prepare("SELECT * FROM attachments WHERE complaint_id = ?");
$stmtAtt->execute([$complaint_id]);
$attachments = $stmtAtt->fetchAll();

// Fetch History (Timeline)
$stmtHist = $db->prepare("SELECT h.*, u.full_name, r.role_name
                          FROM complaint_history h
                          JOIN users u ON h.action_by_user_id = u.id
                          JOIN roles r ON u.role_id = r.id
                          WHERE h.complaint_id = ? ORDER BY h.timestamp ASC");
$stmtHist->execute([$complaint_id]);
$history = $stmtHist->fetchAll();

// Status mapping
$status = $complaint['status'];
$status_key = 'status_' . strtolower(str_replace(' ', '_', $status));

$status_config = [
    'badge'  => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    'dot'    => 'bg-gray-400',
    'icon'   => 'bx-time',
    'label'  => $status,
];
if (in_array($status, ['Pending', 'Received'])) {
    $status_config = ['badge'=>'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300','dot'=>'bg-purple-500','icon'=>'bx-time-five','label'=>$status];
} elseif (in_array($status, ['In Progress', 'Review'])) {
    $status_config = ['badge'=>'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300','dot'=>'bg-blue-500','icon'=>'bx-loader-alt','label'=>$status];
} elseif (in_array($status, ['Resolved', 'Completed', 'Processed'])) {
    $status_config = ['badge'=>'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300','dot'=>'bg-emerald-500','icon'=>'bxs-check-circle','label'=>$status];
} elseif ($status === 'Rejected') {
    $status_config = ['badge'=>'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300','dot'=>'bg-red-500','icon'=>'bxs-x-circle','label'=>$status];
}

$type_icon = [
    'Complaint'  => ['icon'=>'bxs-megaphone',    'color'=>'from-orange-500 to-red-500',   'bg'=>'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-300'],
    'Suggestion' => ['icon'=>'bxs-bulb',          'color'=>'from-blue-500 to-indigo-500',  'bg'=>'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300'],
    'Compliment' => ['icon'=>'bxs-heart',         'color'=>'from-pink-500 to-rose-500',    'bg'=>'bg-pink-100 text-pink-600 dark:bg-pink-900/30 dark:text-pink-300'],
];
$type_cfg = $type_icon[$complaint['complaint_type']] ?? $type_icon['Complaint'];

// Clean description
$clean_description = preg_replace('/\[Caller Information\].*?----------------------------------------\s*/s', '', $complaint['description']);
$clean_description = trim($clean_description);
?>

<div class="container mx-auto px-4 py-8 max-w-6xl">

    <!-- Back + Breadcrumb -->
    <div class="flex items-center justify-between mb-8">
        <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <a href="dashboard.php" class="hover:text-blue-600 transition-colors font-medium">Dashboard</a>
            <i class='bx bx-chevron-right'></i>
            <a href="status.php" class="hover:text-blue-600 transition-colors font-medium"><?php echo __('history_title', 'History'); ?></a>
            <i class='bx bx-chevron-right'></i>
            <span class="text-gray-800 dark:text-white font-bold"><?php echo __('table_link_details', 'Details'); ?></span>
        </nav>
        <a href="status.php"
           class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 rounded-xl text-sm font-bold hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm">
            <i class='bx bx-arrow-back'></i>
            <?php echo __('btn_back', 'Back'); ?>
        </a>
    </div>

    <!-- Hero Card -->
    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-purple-800 rounded-3xl p-8 mb-8 shadow-2xl">
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-80 h-80 bg-white opacity-5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-48 h-48 bg-blue-400 opacity-10 rounded-full blur-2xl"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex-1">
                <!-- Type Badge -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/10 backdrop-blur-md border border-white/20 rounded-full text-white/80 text-xs font-bold uppercase tracking-widest mb-4">
                    <i class='bx <?php echo $type_cfg["icon"]; ?>'></i>
                    <?php echo $complaint['complaint_type']; ?>
                </span>
                <h1 class="text-2xl md:text-3xl font-black text-white leading-tight mb-3">
                    <?php echo htmlspecialchars($complaint['subject']); ?>
                </h1>
                <div class="flex flex-wrap items-center gap-3 text-blue-100/70 text-sm">
                    <span class="flex items-center gap-1">
                        <i class='bx bxs-calendar'></i>
                        <?php echo date('d M Y', strtotime($complaint['created_at'])); ?>
                    </span>
                    <span class="text-blue-300/40">•</span>
                    <span class="flex items-center gap-1">
                        <i class='bx bxs-buildings'></i>
                        <?php 
                        $agency_parts = explode(' - ', $complaint['program'] ?? 'General');
                        echo htmlspecialchars($agency_parts[0]);
                        ?>
                    </span>
                    <span class="text-blue-300/40">•</span>
                    <span class="flex items-center gap-1">
                        <i class='bx bxs-id-card'></i>
                        #<?php echo $complaint['id']; ?>
                    </span>
                </div>
            </div>
            <!-- Status Badge -->
            <div class="flex-shrink-0">
                <div class="bg-white/10 backdrop-blur-xl border border-white/20 px-6 py-4 rounded-2xl text-center">
                    <p class="text-blue-200/60 text-xs font-bold uppercase tracking-widest mb-1">Status</p>
                    <div class="flex items-center gap-2 justify-center">
                        <span class="w-2.5 h-2.5 <?php echo $status_config['dot']; ?> rounded-full animate-pulse"></span>
                        <span class="text-white font-black text-lg"><?php echo __($status_key, $status); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- Left: Main Details -->
        <div class="lg:col-span-8 space-y-6">

            <!-- Description Card -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                    <div class="w-9 h-9 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center text-blue-600 dark:text-blue-400">
                        <i class='bx bx-align-left text-xl'></i>
                    </div>
                    <h3 class="font-bold text-gray-800 dark:text-white text-lg"><?php echo __('submit_desc_label', 'Description'); ?></h3>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-wrap text-sm">
                        <?php echo htmlspecialchars($clean_description ?: '-'); ?>
                    </p>
                </div>
            </div>

            <!-- Attachments Card -->
            <?php if (!empty($attachments)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center text-purple-600 dark:text-purple-400">
                            <i class='bx bx-paperclip text-xl'></i>
                        </div>
                        <h3 class="font-bold text-gray-800 dark:text-white text-lg">Attachments</h3>
                    </div>
                    <span class="px-2.5 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-xs font-black rounded-full">
                        <?php echo count($attachments); ?>
                    </span>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <?php foreach ($attachments as $att):
                            $fileExt = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                            $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            $isPdf   = $fileExt === 'pdf';
                            $filePath = $att['file_path'];
                            if (strpos($filePath, 'assets/') === false) {
                                $filePath = 'assets/uploads/complaints-file/' . $filePath;
                            }
                        ?>
                        <a href="../<?php echo htmlspecialchars($filePath); ?>" target="_blank"
                           class="group relative overflow-hidden bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-2xl hover:border-blue-400 dark:hover:border-blue-500 transition-all hover:shadow-lg">
                            <div class="h-28 flex items-center justify-center overflow-hidden bg-gray-100 dark:bg-gray-700">
                                <?php if ($isImage): ?>
                                    <img src="../<?php echo htmlspecialchars($filePath); ?>"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" alt="attachment">
                                <?php elseif ($isPdf): ?>
                                    <i class='bx bxs-file-pdf text-5xl text-red-400'></i>
                                <?php else: ?>
                                    <i class='bx bxs-file text-5xl text-gray-400'></i>
                                <?php endif; ?>
                            </div>
                            <div class="p-3">
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-200 truncate">
                                    <?php echo htmlspecialchars($att['file_name'] ?? 'File'); ?>
                                </p>
                                <p class="text-[10px] text-gray-400 uppercase font-bold mt-0.5"><?php echo $fileExt; ?></p>
                            </div>
                            <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <span class="px-2 py-1 bg-blue-600 text-white text-[10px] font-bold rounded-lg">
                                    <i class='bx bx-link-external'></i>
                                </span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Right Column: Info + Timeline -->
        <div class="lg:col-span-4 space-y-6">

            <!-- Complaint Info -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                    <div class="w-9 h-9 bg-gray-100 dark:bg-gray-700 rounded-xl flex items-center justify-center text-gray-500 dark:text-gray-400">
                        <i class='bx bxs-info-circle text-xl'></i>
                    </div>
                    <h3 class="font-bold text-gray-800 dark:text-white">Information</h3>
                </div>
                <div class="p-5 space-y-4">
                    <!-- Type -->
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Type</span>
                        <span class="flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full <?php echo $type_cfg['bg']; ?>">
                            <i class='bx <?php echo $type_cfg["icon"]; ?>'></i>
                            <?php echo $complaint['complaint_type']; ?>
                        </span>
                    </div>
                    <!-- Status -->
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Status</span>
                        <span class="flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full <?php echo $status_config['badge']; ?>">
                            <span class="w-1.5 h-1.5 <?php echo $status_config['dot']; ?> rounded-full"></span>
                            <?php echo __($status_key, $status); ?>
                        </span>
                    </div>
                    <!-- Department -->
                    <?php if (!empty($complaint['department_name'])): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Dept.</span>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200">
                            <?php echo htmlspecialchars($complaint['department_name']); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <!-- Agency -->
                    <?php $agency = explode(' - ', $complaint['program'] ?? ''); if (!empty($agency[0])): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Agency</span>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 text-right max-w-[150px] truncate" title="<?php echo htmlspecialchars($agency[0]); ?>">
                            <?php echo htmlspecialchars($agency[0]); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <!-- Submitted -->
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Submitted</span>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200">
                            <?php echo date('d M Y', strtotime($complaint['created_at'])); ?>
                        </span>
                    </div>
                    <!-- Anonymous -->
                    <?php if (!empty($complaint['is_anonymous'])): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Anonymous</span>
                        <span class="flex items-center gap-1 px-2 py-0.5 bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300 text-xs font-bold rounded-full">
                            <i class='bx bxs-user-x'></i> Yes
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Activity Timeline -->
            <?php if (!empty($history)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                    <div class="w-9 h-9 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <i class='bx bxs-time text-xl'></i>
                    </div>
                    <h3 class="font-bold text-gray-800 dark:text-white">Activity Timeline</h3>
                </div>
                <div class="p-5">
                    <div class="relative">
                        <!-- Vertical line -->
                        <div class="absolute left-3.5 top-0 bottom-0 w-0.5 bg-gray-100 dark:bg-gray-700"></div>
                        <div class="space-y-5">
                            <?php foreach ($history as $i => $h):
                                $isLast = $i === count($history) - 1;
                            ?>
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center border-2 border-white dark:border-gray-800 shadow
                                    <?php echo $isLast ? 'bg-blue-500' : 'bg-gray-200 dark:bg-gray-600'; ?>">
                                    <i class='bx <?php echo $isLast ? 'bx-check' : 'bxs-circle'; ?> text-xs <?php echo $isLast ? 'text-white' : 'text-gray-400 dark:text-gray-300'; ?>'></i>
                                </div>
                                <div class="flex-1 pb-1">
                                    <p class="text-xs font-bold text-gray-700 dark:text-gray-200 leading-snug">
                                        <?php echo htmlspecialchars(strip_tags($h['action_description'])); ?>
                                    </p>
                                    <div class="mt-1 flex items-center gap-2 flex-wrap">
                                        <span class="text-[10px] font-bold text-gray-400"><?php echo date('d M Y H:i', strtotime($h['timestamp'])); ?></span>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-md">
                                            <?php echo htmlspecialchars($h['role_name'] ?? 'System'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 text-center text-gray-400">
                <i class='bx bx-history text-4xl mb-2 block'></i>
                <p class="text-sm font-medium">No activity yet</p>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Bottom Nav -->
    <div class="mt-8 flex justify-between items-center">
        <a href="status.php"
           class="flex items-center gap-2 px-5 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 rounded-2xl text-sm font-bold hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm">
            <i class='bx bx-arrow-back'></i>
            <?php echo __('btn_back', 'Back to History'); ?>
        </a>
        <a href="Complaint_Suggestion.php"
           class="flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl text-sm font-bold hover:from-blue-700 hover:to-indigo-700 transition-all shadow-lg shadow-blue-500/20">
            <i class='bx bxs-plus-circle'></i>
            New Complaint
        </a>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
