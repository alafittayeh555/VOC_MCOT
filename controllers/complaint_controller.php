<?php
// controllers/complaint_controller.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit;
    }

    $category_id = $_POST['category_id'];
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $user_id = $_SESSION['user_id'];

    if (empty($subject) || empty($description)) {
        // Handle error (simple back redirect for now)
        header("Location: ../complaint_submit.php?error=MissingFields");
        exit;
    }

    $db = Database::connect();

    try {
        $db->beginTransaction();

        // 1. Insert Complaint
        $stmt = $db->prepare("INSERT INTO complaints (user_id, category_id, subject, description, status) VALUES (?, ?, ?, ?, 'Pending')");
        $stmt->execute([$user_id, $category_id, $subject, $description]);
        $complaint_id = $db->lastInsertId();

        // 2. Insert History Log
        $stmtHist = $db->prepare("INSERT INTO complaint_history (complaint_id, action_by_user_id, action_description) VALUES (?, ?, ?)");
        $stmtHist->execute([$complaint_id, $user_id, "Complaint submitted"]);

        // 3. Handle Attachment
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/file/user/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            // Security: limit file extensions and generate random name
            $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
            $fileInfo = pathinfo($_FILES['attachment']['name']);
            $ext = strtolower($fileInfo['extension']);

            if (in_array($ext, $allowed)) {
                $newFileName = uniqid() . '.' . $ext;
                $destPath = $uploadDir . $newFileName;

                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destPath)) {
                    $dbPath = "assets/file/user/" . $newFileName;
                    $stmtAtt = $db->prepare("INSERT INTO attachments (complaint_id, file_path) VALUES (?, ?)");
                    $stmtAtt->execute([$complaint_id, $dbPath]);
                }
            }
        }

        $db->commit();
        header("Location: ../dashboard.php?success=ComplaintSubmitted");
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        die("Error submitting complaint: " . $e->getMessage());
    }
}
?>