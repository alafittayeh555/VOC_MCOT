<?php
// department/communication.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../login.php");
    exit;
}
$dept_id = $_SESSION['department_id'];
$db = Database::connect();
$active_complaint_id = isset($_GET['complaint_id']) ? (int) $_GET['complaint_id'] : 0;
$active_complaint = null;

// Handle New Message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $active_complaint_id > 0) {
    // Check access
    $chk = $db->prepare("SELECT id FROM complaints WHERE id = ? AND assigned_dept_id = ?");
    $chk->execute([$active_complaint_id, $dept_id]);
    if ($chk->fetch()) {
        $message = $_POST['message'];
        if (!empty($message)) {
            $stmt = $db->prepare("INSERT INTO complaint_history (complaint_id, action_by_user_id, action_description) VALUES (?, ?, ?)");
            $stmt->execute([$active_complaint_id, $_SESSION['user_id'], "Message: " . $message]);
            header("Location: communication.php?complaint_id=" . $active_complaint_id);
            exit;
        }
    }
}

// Fetch Active Complaint
if ($active_complaint_id) {
    $stmt = $db->prepare("SELECT c.* FROM complaints c WHERE c.id = ? AND c.assigned_dept_id = ?");
    $stmt->execute([$active_complaint_id, $dept_id]);
    $active_complaint = $stmt->fetch();

    if ($active_complaint) {
        $stmtHist = $db->prepare("SELECT h.*, u.full_name, r.role_name 
                                  FROM complaint_history h 
                                  JOIN users u ON h.action_by_user_id = u.id 
                                  JOIN roles r ON u.role_id = r.id 
                                  WHERE h.complaint_id = ? ORDER BY h.timestamp ASC");
        $stmtHist->execute([$active_complaint_id]);
        $messages = $stmtHist->fetchAll();
    }
}

// Fetch Dept Conversations (Active cases with recent updates)
$recent_complaints = $db->query("SELECT c.id, c.subject, c.updated_at FROM complaints c WHERE c.assigned_dept_id = $dept_id ORDER BY c.updated_at DESC LIMIT 10")->fetchAll();
?>

<div class="flex h-[calc(100vh-100px)] gap-4">
    <!-- Sidebar -->
    <div class="w-1/3 bg-white shadow rounded-lg overflow-hidden flex flex-col">
        <div class="p-4 border-b bg-gray-50">
            <h3 class="text-lg font-medium text-gray-900">Your Active Cases</h3>
        </div>
        <div class="overflow-y-auto flex-1">
            <ul class="divide-y divide-gray-200">
                <?php foreach ($recent_complaints as $c): ?>
                    <li>
                        <a href="communication.php?complaint_id=<?php echo $c['id']; ?>"
                            class="block hover:bg-gray-50 px-4 py-4 <?php echo ($active_complaint_id == $c['id']) ? 'bg-blue-50' : ''; ?>">
                            <div class="flex justify-between">
                                <p class="text-sm font-medium text-blue-600 truncate">#
                                    <?php echo $c['id']; ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo date('M j', strtotime($c['updated_at'])); ?>
                                </p>
                            </div>
                            <p class="mt-1 text-sm text-gray-900 truncate">
                                <?php echo htmlspecialchars($c['subject']); ?>
                            </p>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Chat -->
    <div class="w-2/3 bg-white shadow rounded-lg flex flex-col">
        <?php if ($active_complaint): ?>
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Case #
                        <?php echo $active_complaint['id']; ?>
                    </h3>
                    <p class="text-sm text-gray-500">
                        <?php echo htmlspecialchars($active_complaint['subject']); ?>
                    </p>
                </div>
                <a href="case_details.php?id=<?php echo $active_complaint['id']; ?>"
                    class="text-sm text-blue-600 hover:text-blue-800">View Case Details</a>
            </div>

            <!-- Messages -->
            <div class="flex-1 p-4 overflow-y-auto space-y-4 bg-gray-50">
                <?php foreach ($messages as $msg): ?>
                    <div
                        class="flex <?php echo ($msg['action_by_user_id'] == $_SESSION['user_id']) ? 'justify-end' : 'justify-start'; ?>">
                        <div
                            class="max-w-lg rounded-lg p-3 shadow-sm 
                            <?php echo ($msg['action_by_user_id'] == $_SESSION['user_id']) ? 'bg-blue-100 text-blue-900' : 'bg-white text-gray-900'; ?>">
                            <p class="text-xs text-gray-500 mb-1">
                                <?php echo htmlspecialchars($msg['full_name']); ?> •
                                <?php echo date('M j, H:i', strtotime($msg['timestamp'])); ?>
                            </p>
                            <p class="text-sm">
                                <?php echo nl2br(htmlspecialchars(strip_tags($msg['action_description']))); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Input -->
            <div class="p-4 border-t bg-white">
                <form method="POST" class="flex gap-2">
                    <input type="text" name="message" placeholder="Type a message to PR/Admins..." required
                        autocomplete="off"
                        class="flex-1 focus:ring-blue-500 focus:border-blue-500 block w-full rounded-md sm:text-sm border-gray-300 border p-2">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
                        Send
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="flex-1 flex items-center justify-center text-gray-500">
                Select a case to view communication history.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>