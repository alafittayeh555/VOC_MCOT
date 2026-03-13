<?php
// admin/agency_options.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['agency_id'])) {
    header("Location: agencies.php");
    exit;
}

$agency_id = $_GET['agency_id'];
$db = Database::connect();
$message = '';
$error = '';

// Fetch Agency Name
$stmt = $db->prepare("SELECT * FROM agencies WHERE id = ?");
$stmt->execute([$agency_id]);
$agency = $stmt->fetch();

if (!$agency) {
    die("Agency not found.");
}

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // 1. Add Option
    if ($_POST['action'] == 'add_option') {
        $name = trim($_POST['name']);
        
        try {
            $stmt = $db->prepare("INSERT INTO agency_options (agency_id, name) VALUES (?, ?)");
            $stmt->execute([$agency_id, $name]);
            $message = "Option created successfully.";
        } catch (PDOException $e) {
            $error = "Error creating option: " . $e->getMessage();
        }
    }

    // 2. Edit Option
    elseif ($_POST['action'] == 'edit_option') {
        $id = $_POST['option_id'];
        $name = trim($_POST['name']);

        try {
            $stmt = $db->prepare("UPDATE agency_options SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            $message = "Option updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating option: " . $e->getMessage();
        }
    }

    // 3. Delete Option
    elseif ($_POST['action'] == 'delete_option') {
        $id = $_POST['option_id'];

        try {
            $stmt = $db->prepare("DELETE FROM agency_options WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Option deleted successfully.";
        } catch (PDOException $e) {
            $error = "Error deleting option: " . $e->getMessage();
        }
    }
}

// Fetch Options
$stmt = $db->prepare("SELECT * FROM agency_options WHERE agency_id = ? ORDER BY id ASC");
$stmt->execute([$agency_id]);
$options = $stmt->fetchAll();
?>

<!-- Main Content -->
<div class="head-title">
    <div class="left">
        <h1>Manage Options: <?php echo htmlspecialchars($agency['name']); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a href="agencies.php">Agencies</a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#">Options</a></li>
        </ul>
    </div>
    <button onclick="document.getElementById('addOptionModal').classList.remove('hidden')" class="btn-download">
        <i class='bx bxs-plus-circle'></i>
        <span class="text">Add Option</span>
    </button>
</div>

<?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-4" role="alert">
        <span class="block sm:inline"><?php echo $message; ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4" role="alert">
        <span class="block sm:inline"><?php echo $error; ?></span>
    </div>
<?php endif; ?>

<div class="table-data">
    <div class="order">
        <div class="head">
            <h3>Options List</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Option Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($options)): ?>
                    <tr><td colspan="3" class="text-center py-4">No options found.</td></tr>
                <?php else: ?>
                    <?php foreach ($options as $index => $opt): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <p class="font-bold"><?php echo htmlspecialchars($opt['name']); ?></p>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <button onclick='openEditModal(<?php echo json_encode($opt); ?>)' 
                                        class="text-blue-500 hover:text-blue-700" title="Edit">
                                        <i class='bx bxs-edit'></i>
                                    </button>
                                    
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this option?');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_option">
                                        <input type="hidden" name="option_id" value="<?php echo $opt['id']; ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700 ml-2" title="Delete">
                                            <i class='bx bxs-trash'></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Option Modal -->
<div id="addOptionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center">
    <div class="relative p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Add New Option</h3>
            <form class="mt-2 text-left" method="POST">
                <input type="hidden" name="action" value="add_option">

                <div class="mt-2">
                    <label class="block text-sm font-medium text-gray-700">Option Name</label>
                    <input type="text" name="name" required class="mt-1 p-2 w-full border rounded-md" placeholder="e.g., Station 99.5">
                </div>

                <div class="items-center px-4 py-3">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        Create
                    </button>
                </div>
            </form>
            <button onclick="document.getElementById('addOptionModal').classList.add('hidden')"
                class="mt-3 px-4 py-2 bg-gray-300 text-gray-700 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Edit Option Modal -->
<div id="editOptionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center">
    <div class="relative p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Option</h3>
            <form class="mt-2 text-left" method="POST">
                <input type="hidden" name="action" value="edit_option">
                <input type="hidden" name="option_id" id="edit_option_id">

                <div class="mt-2">
                    <label class="block text-sm font-medium text-gray-700">Option Name</label>
                    <input type="text" name="name" id="edit_option_name" required class="mt-1 p-2 w-full border rounded-md">
                </div>

                <div class="items-center px-4 py-3">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        Save Changes
                    </button>
                </div>
            </form>
            <button onclick="document.getElementById('editOptionModal').classList.add('hidden')"
                class="mt-3 px-4 py-2 bg-gray-300 text-gray-700 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
    function openEditModal(option) {
        document.getElementById('edit_option_id').value = option.id;
        document.getElementById('edit_option_name').value = option.name;
        document.getElementById('editOptionModal').classList.remove('hidden');
    }
</script>

<?php require_once '../includes/footer.php'; ?>
