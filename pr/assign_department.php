<?php
// pr/assign_department.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit;
}

$complaint_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$db = Database::connect();

$stmt = $db->prepare("SELECT subject, assigned_dept_id FROM complaints WHERE id = ?");
$stmt->execute([$complaint_id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    echo "Complaint not found.";
    exit;
}

$departments = $db->query("SELECT * FROM departments")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dept_id = $_POST['department_id'];

    $note = $_POST['note'];
    $deadline = $_POST['deadline']; // Optional handling

    // Update Complaint
    $updateStmt = $db->prepare("UPDATE complaints SET assigned_dept_id = ?, status = 'Received' WHERE id = ?");
    $updateStmt->execute([$dept_id, $complaint_id]);

    // History
    $histStmt = $db->prepare("INSERT INTO complaint_history (complaint_id, action_by_user_id, action_description) VALUES (?, ?, ?)");
    $action_desc = "Assigned to department. Note: $note";
    if (!empty($deadline)) {
        $action_desc .= " Deadline: $deadline";
    }
    $histStmt->execute([$complaint_id, $_SESSION['user_id'], $action_desc]);

    header("Location: assigned.php"); // Redirect to Assigned list
    exit;
}
?>

<div class="max-w-xl mx-auto mt-10">
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Assign Department - Complaint #
                <?php echo $complaint_id; ?>
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500 mb-4">
                Subject:
                <?php echo htmlspecialchars($complaint['subject']); ?>
            </p>

            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label for="department_id" class="block text-sm font-medium text-gray-700">Department</label>
                        <select id="department_id" name="department_id" required
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($complaint['assigned_dept_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>



                    <div>
                        <label for="deadline" class="block text-sm font-medium text-gray-700">Target Resolution Date
                            (Optional)</label>
                        <input type="date" name="deadline" id="deadline"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="note" class="block text-sm font-medium text-gray-700">Assignment Note /
                            Instructions</label>
                        <textarea id="note" name="note" rows="3" required
                            class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md border p-2"></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <a href="complaint_details.php?id=<?php echo $complaint_id; ?>"
                            class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Assign Department
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>