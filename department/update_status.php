<?php
// department/update_status.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../login.php");
    exit;
}

$dept_id = $_SESSION['department_id'];
$complaint_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$db = Database::connect();

// Verify Assignment
$stmt = $db->prepare("SELECT subject, status FROM complaints WHERE id = ? AND assigned_dept_id = ?");
$stmt->execute([$complaint_id, $dept_id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    echo "Access Denied or Case Not Found.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = $_POST['status'];
    $note = $_POST['note'];

    // Update Db
    $upStmt = $db->prepare("UPDATE complaints SET status = ? WHERE id = ?");
    $upStmt->execute([$status, $complaint_id]);

    // Log History
    $histStmt = $db->prepare("INSERT INTO complaint_history (complaint_id, action_by_user_id, action_description) VALUES (?, ?, ?)");
    $action = "Progress Update: Status set to $status. Note: $note";
    $histStmt->execute([$complaint_id, $_SESSION['user_id'], $action]);

    header("Location: new_complaints_details.php?id=" . $complaint_id);
    exit;
}
?>

<div class="max-w-xl mx-auto mt-10">
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Update Case Progress #
                <?php echo $complaint_id; ?>
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500 mb-4">
                Subject:
                <?php echo htmlspecialchars($complaint['subject']); ?>
            </p>

            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border">
                            <option value="In Progress" <?php echo ($complaint['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Pending" <?php echo ($complaint['status'] == 'Pending') ? 'selected' : ''; ?>
                                >Pending Additional Info</option>
                            <!-- Pending usually means waiting on new/PR, but here context implies "Working on it" or "Waiting". -->
                        </select>
                    </div>

                    <div>
                        <label for="note" class="block text-sm font-medium text-gray-700">Progress Note</label>
                        <textarea id="note" name="note" rows="4" required
                            placeholder="Describe current progress or blockers..."
                            class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md border p-2"></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <a href="new_complaints_details.php?id=<?php echo $complaint_id; ?>"
                            class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Save Update
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>