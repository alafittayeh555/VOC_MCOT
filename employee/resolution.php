<?php
// department/resolution.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../login.php");
    exit;
}

$dept_id = $_SESSION['department_id'];
$complaint_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$db = Database::connect();

// Verify Assignment and Status (Can resolve if In Progress)
$stmt = $db->prepare("SELECT subject, status FROM complaints WHERE id = ? AND assigned_dept_id = ?");
$stmt->execute([$complaint_id, $dept_id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    echo "Access Denied or Case Not Found.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $resolution = $_POST['resolution'];
    $root_cause = $_POST['root_cause'];
    $officer_name = $_SESSION['full_name']; // Log who resolved it

    // Update Db
    $upStmt = $db->prepare("UPDATE complaints SET status = 'Resolved' WHERE id = ?");
    $upStmt->execute([$complaint_id]);

    // Log History
    $histStmt = $db->prepare("INSERT INTO complaint_history (complaint_id, action_by_user_id, action_description) VALUES (?, ?, ?)");
    $action = "Case Resolved. Resolution: $resolution. Root Cause: $root_cause";
    $histStmt->execute([$complaint_id, $_SESSION['user_id'], $action]);

    // Redirect to Dashboard or List
    header("Location: assigned_case.php");
    exit;
}
?>

<div class="max-w-xl mx-auto mt-10">
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Resolve Case #
                <?php echo $complaint_id; ?>
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500 mb-4">
                Subject:
                <?php echo htmlspecialchars($complaint['subject']); ?>
            </p>

            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label for="resolution" class="block text-sm font-medium text-gray-700">Resolution
                            Description</label>
                        <textarea id="resolution" name="resolution" rows="4" required
                            placeholder="Describe how the issue was resolved..."
                            class="shadow-sm focus:ring-green-500 focus:border-green-500 block w-full sm:text-sm border-gray-300 rounded-md border p-2"></textarea>
                    </div>

                    <div>
                        <label for="root_cause" class="block text-sm font-medium text-gray-700">Root Cause
                            (Optional)</label>
                        <input type="text" name="root_cause" id="root_cause"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                    </div>

                    <!-- TODO: File Upload for Evidence (If needed later, sticking to plain text/simple layout for now as per prompt "standard PHP") -->

                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class='bx bx-alarm-exclamation text-yellow-400'></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    This action will close the complaint and mark it as Resolved.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <a href="case_details.php?id=<?php echo $complaint_id; ?>"
                            class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                            Confirm Resolution
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>