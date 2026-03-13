<?php
// user/dashboard.php
$hide_header = false;
require_once '../includes/header_landing.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    header("Location: ../login.php");
    exit;
}

// Redirect dashboard to status.php
header("Location: ../index.php");
exit;

$user_id = $_SESSION['user_id'];
$db = Database::connect();

// Fetch counts
$total_tickets = $db->query("SELECT COUNT(*) FROM complaints WHERE user_id = $user_id")->fetchColumn();
$active_tickets = $db->query("SELECT COUNT(*) FROM complaints WHERE user_id = $user_id AND status IN ('Pending', 'In Progress', 'Received', 'Review')")->fetchColumn();
$resolved_tickets = $db->query("SELECT COUNT(*) FROM complaints WHERE user_id = $user_id AND status IN ('Resolved', 'Completed', 'Processed')")->fetchColumn();

// Fetch 3 most recent tickets
$recent_stmt = $db->prepare("SELECT c.*, d.name as department_name 
                            FROM complaints c 
                            LEFT JOIN departments d ON c.assigned_dept_id = d.id 
                            WHERE c.user_id = ? 
                            ORDER BY c.created_at DESC LIMIT 3");
$recent_stmt->execute([$user_id]);
$recent_tickets = $recent_stmt->fetchAll();
?>

<style>
    @keyframes float {
        0%, 100% { transform: translateY(0) scale(1); }
        50% { transform: translateY(-20px) scale(1.05); }
    }
    .animate-float { animation: float 6s ease-in-out infinite; }
    .animate-float-delayed { animation: float 8s ease-in-out infinite -2s; }
    .hidden-ticket { display: none; }
</style>

<div class="container mx-auto px-4 py-8">
    <!-- Hero Section -->
    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-purple-800 rounded-3xl p-8 mb-10 shadow-2xl">
        <!-- Abstract Background Shapes -->
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-80 h-80 bg-white opacity-10 rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-64 h-64 bg-blue-400 opacity-10 rounded-full blur-3xl animate-float-delayed"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="text-center md:text-left">
                <span class="inline-block px-4 py-1.5 bg-blue-500/30 backdrop-blur-md rounded-full text-blue-100 text-xs font-bold uppercase tracking-wider mb-4 border border-blue-400/20">
                    Welcome Back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?> 👋
                </span>
                <h1 class="text-4xl md:text-5xl font-black text-white leading-tight mb-4">
                    How can we <span class="text-blue-300">help you</span> today?
                </h1>
                <p class="text-blue-100/80 text-lg max-w-md mx-auto md:mx-0">
                    Submit new complaints or suggestions and track their progress in real-time.
                </p>
            </div>
            
            <!-- Quick Glass Stats -->
            <div class="grid grid-cols-2 gap-4 w-full md:w-auto">
                <div class="bg-white/10 backdrop-blur-xl border border-white/20 p-6 rounded-2xl text-center min-w-[140px] transform hover:scale-105 transition-all">
                    <p class="text-blue-200 text-xs font-bold uppercase mb-1">Total</p>
                    <h3 class="text-3xl font-black text-white"><?php echo $total_tickets; ?></h3>
                </div>
                <div class="bg-emerald-500/20 backdrop-blur-xl border border-emerald-400/20 p-6 rounded-2xl text-center min-w-[140px] transform hover:scale-105 transition-all">
                    <p class="text-emerald-200 text-xs font-bold uppercase mb-1">Resolved</p>
                    <h3 class="text-3xl font-black text-white"><?php echo $resolved_tickets; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-10">
        
        <!-- Left Column: Actions & Stats -->
        <div class="lg:col-span-4 space-y-8">
            <!-- Action Cards -->
            <div class="grid grid-cols-1 gap-4">
                <a href="Complaint_Suggestion.php" class="group relative overflow-hidden bg-white dark:bg-gray-800 p-6 rounded-3xl shadow-xl hover:shadow-2xl transition-all duration-300 border border-gray-100 dark:border-gray-700 flex items-center gap-6">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 dark:bg-blue-900/20 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
                    <div class="relative z-10 w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-blue-500/30 group-hover:rotate-6 transition-transform">
                        <i class='bx bxs-plus-circle'></i>
                    </div>
                    <div class="relative z-10">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white group-hover:text-blue-600 transition-colors">New Complaint</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Add a new ticket</p>
                    </div>
                    <i class='bx bx-chevron-right absolute right-6 text-gray-300 group-hover:translate-x-2 transition-transform'></i>
                </a>

                <a href="history.php" class="group relative overflow-hidden bg-white dark:bg-gray-800 p-6 rounded-3xl shadow-xl hover:shadow-2xl transition-all duration-300 border border-gray-100 dark:border-gray-700 flex items-center gap-6">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-purple-50 dark:bg-purple-900/20 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
                    <div class="relative z-10 w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-purple-500/30 group-hover:rotate-6 transition-transform">
                        <i class='bx bxs-time-five'></i>
                    </div>
                    <div class="relative z-10">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white group-hover:text-purple-600 transition-colors">Track History</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">View all records</p>
                    </div>
                    <i class='bx bx-chevron-right absolute right-6 text-gray-300 group-hover:translate-x-2 transition-transform'></i>
                </a>
            </div>

            <!-- Detailed Status -->
            <div class="bg-gray-900 rounded-3xl p-8 text-white shadow-2xl relative overflow-hidden">
                <h4 class="text-lg font-bold mb-6 flex items-center gap-2">
                    <i class='bx bxs-pie-chart-alt-2 text-blue-400'></i> Activity Summary
                </h4>
                <div class="space-y-6">
                    <div>
                        <div class="flex justify-between text-sm mb-2 text-gray-400">
                            <span>Active / In Progress</span>
                            <span class="text-blue-400 font-bold"><?php echo $active_tickets; ?></span>
                        </div>
                        <div class="h-2 w-full bg-gray-800 rounded-full overflow-hidden">
                            <?php $active_pct = $total_tickets > 0 ? ($active_tickets / $total_tickets) * 100 : 0; ?>
                            <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo $active_pct; ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-2 text-gray-400">
                            <span>Completed & Resolved</span>
                            <span class="text-emerald-400 font-bold"><?php echo $resolved_tickets; ?></span>
                        </div>
                        <div class="h-2 w-full bg-gray-800 rounded-full overflow-hidden">
                            <?php $resolved_pct = $total_tickets > 0 ? ($resolved_tickets / $total_tickets) * 100 : 0; ?>
                            <div class="h-full bg-emerald-500 rounded-full" style="width: <?php echo $resolved_pct; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Recent Activity -->
        <div class="lg:col-span-8">
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden h-full">
                <div class="p-8 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
                        <i class='bx bx-list-ul text-blue-600'></i> <?php echo __('user_recent_complaints', 'Recent Complaints'); ?>
                    </h3>
                    <a href="status.php" class="text-sm font-bold text-blue-600 hover:text-blue-700"><?php echo __('user_view_all', 'View All'); ?></a>
                </div>
                <div class="p-0">
                    <?php if (empty($recent_tickets)): ?>
                        <div class="flex flex-col items-center justify-center py-20 px-8 text-center text-gray-400">
                            <div class="w-20 h-20 bg-gray-50 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                                <i class='bx bx-ghost text-4xl opacity-50'></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-600 dark:text-gray-300"><?php echo __('user_no_recent_activity', 'No recent activity'); ?></h4>
                            <p class="text-sm"><?php echo __('user_no_complaints_yet', "You haven't submitted any complaints yet."); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php foreach ($recent_tickets as $ticket): 
                                $status = $ticket['status'];
                                $status_key = 'status_' . strtolower(str_replace(' ', '_', $status));
                                $badge_class = 'bg-gray-100 text-gray-600';
                                if (in_array($status, ['Pending', 'Received'])) $badge_class = 'bg-purple-100 text-purple-600';
                                elseif (in_array($status, ['In Progress', 'Review'])) $badge_class = 'bg-blue-100 text-blue-600';
                                elseif (in_array($status, ['Resolved', 'Completed', 'Processed'])) $badge_class = 'bg-emerald-100 text-emerald-600';
                                elseif ($status == 'Rejected') $badge_class = 'bg-red-100 text-red-600';
                            ?>
                                <a href="complaint_details.php?id=<?php echo $ticket['id']; ?>" class="flex items-center gap-6 p-6 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all group">
                                    <div class="hidden sm:flex w-12 h-12 bg-gray-50 dark:bg-gray-700 rounded-xl items-center justify-center text-gray-400 group-hover:text-blue-600 group-hover:bg-blue-50 transition-all">
                                        <i class='bx bxs-receipt text-xl'></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-3 mb-1">
                                            <span class="text-xs font-bold text-gray-400 uppercase tracking-tighter"><?php echo date('d M Y', strtotime($ticket['created_at'])); ?></span>
                                            <span class="px-2.5 py-0.5 <?php echo $badge_class; ?> rounded-full text-[10px] font-black uppercase tracking-wider">
                                                <?php echo __($status_key, $status); ?>
                                            </span>
                                        </div>
                                        <h4 class="text-base font-bold text-gray-800 dark:text-white truncate group-hover:text-blue-600 transition-colors">
                                            <?php echo htmlspecialchars($ticket['subject']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($ticket['department_name'] ?? 'General'); ?></p>
                                    </div>
                                    <i class='bx bx-right-arrow-alt text-2xl text-gray-300 group-hover:text-blue-600 group-hover:translate-x-2 transition-all'></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="relative overflow-hidden bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800/50 p-6 rounded-3xl">
        <div class="flex items-center gap-4 relative z-10">
            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-800/30 rounded-2xl flex items-center justify-center text-blue-600 dark:text-blue-400 flex-shrink-0">
                <i class='bx bxs-info-circle text-2xl'></i>
            </div>
            <div>
                <h4 class="font-bold text-blue-900 dark:text-blue-100 leading-tight">Need help?</h4>
                <p class="text-blue-700/70 dark:text-blue-400/70 text-sm">Track your tickets in real-time or contact support for urgent issues.</p>
            </div>
        </div>
        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-blue-100 dark:bg-blue-800/20 rounded-full blur-2xl"></div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>