<?php
// controllers/complaint_action.php
session_start();
require_once '../config/database.php';
require_once '../config/db_setup.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ----------- ACTION: SUBMIT COMPLAINT -----------
    if (isset($_POST['action']) && $_POST['action'] === 'submit_complaint') {
        $user_id = $_SESSION['user_id'];
        $complaint_type = $_POST['complaint_type'];
        $agencies = isset($_POST['agencies']) ? $_POST['agencies'] : 'General';
        
        // Handle "Other" Custom Input
        if (!empty($_POST['agencies_other'])) {
            $agencies .= " (" . trim($_POST['agencies_other']) . ")";
        }

        // Handle Radio Station Sub-selection
        if (!empty($_POST['radio_station'])) {
            $agencies .= " - " . trim($_POST['radio_station']);
        }

        // $category_id = $_POST['category_id']; // Removed
        $subject = trim($_POST['subject']);
        $description = trim($_POST['description']);
        
        // If PR Officer submitted on behalf of someone (Guest Info Provided)
        if (isset($_POST['guest_name']) && !empty($_POST['guest_name'])) {
            $guest_info = "[Caller Information]\n";
            $guest_info .= "Name: " . htmlspecialchars($_POST['guest_name']) . "\n";
            $guest_info .= "Phone: " . htmlspecialchars($_POST['guest_phone']) . "\n";
            $guest_occupation = $_POST['guest_occupation'] ?? '';
            if ($guest_occupation === 'อื่น ๆ (Other)' && !empty($_POST['guest_occupation_other'])) {
                $guest_occupation = trim($_POST['guest_occupation_other']);
            }
            if (!empty($guest_occupation)) $guest_info .= "Occupation: " . htmlspecialchars($guest_occupation) . "\n";
            if (!empty($_POST['guest_email'])) $guest_info .= "Email: " . htmlspecialchars($_POST['guest_email']) . "\n";
            $guest_info .= "----------------------------------------\n\n";
            
            $description = $guest_info . $description;
        }
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

        // Basic Validation
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        
        // If PR Officer (role 2) and NOT anonymous, validate guest fields
        if ($_SESSION['role_id'] == 2 && !$is_anonymous) {
            if (empty($_POST['guest_name']) || empty($_POST['guest_phone']) || empty($_POST['guest_occupation']) || empty($_POST['guest_email'])) {
                $_SESSION['error'] = "Please fill in all caller information (Name, Phone, Occupation, Email).";
                header("Location: ../pr/Complaint_Suggestion.php");
                exit;
            }
        }

        if (empty($subject) || empty($description)) {
            $_SESSION['error'] = "Please fill in all required fields.";
            $redirect_back = ($_SESSION['role_id'] == 2) ? "../pr/Complaint_Suggestion.php" : "../user/Complaint_Suggestion.php";
            header("Location: $redirect_back");
            exit;
        }

        try {
            $db->beginTransaction();

            // Determine Submission Channel & User ID for DB (FK Constraint Logic)
            $submission_channel = ($_SESSION['role_id'] == 2) ? 'PR' : 'System';
            
            // Determine Reporter Info
            $reporter_email = null;
            $reporter_name = null;
            if ($_SESSION['role_id'] == 4) {
                $reporter_email = $_SESSION['email'] ?? null;
                $reporter_name = $_SESSION['full_name'] ?? null;
            } else if ($_SESSION['role_id'] == 2 && !empty($_POST['guest_email'])) {
                $reporter_email = trim($_POST['guest_email']);
                $reporter_name = trim($_POST['guest_name'] ?? '');
            }

            // If user is PR (or any employee), they are NOT in 'users' table, so user_id FK fails.
            // We set user_id to NULL. The actual submitter is tracked in history.
            $db_user_id = ($_SESSION['role_id'] == 4) ? $user_id : null;

            // Insert Complaint
            $stmt = $db->prepare("INSERT INTO complaints (user_id, reporter_email, reporter_name, subject, description, status, submission_channel, complaint_type, program, is_anonymous) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?)");
            $stmt->execute([$db_user_id, $reporter_email, $reporter_name, $subject, $description, $submission_channel, $complaint_type, $agencies, $is_anonymous]);
            $complaint_id = $db->lastInsertId();

            // Handle File Uploads
            if (isset($_FILES['attachments'])) {
                // User Uploads -> assets/file/user/
                $target_dir = "../assets/file/user/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $total_files = count($_FILES['attachments']['name']);
                if ($total_files > 5) {
                    throw new Exception("You can only upload a maximum of 5 files.");
                }

                $upload_errors = [];
                $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf', 'docx'];
                
                for ($i = 0; $i < $total_files; $i++) {
                    $fileName = $_FILES['attachments']['name'][$i];
                    $tmpName = $_FILES['attachments']['tmp_name'][$i];
                    $fileSize = $_FILES['attachments']['size'][$i];
                    $error = $_FILES['attachments']['error'][$i];

                    if ($error === UPLOAD_ERR_OK && $fileName != "") {
                        // Validate size (5MB)
                        if ($fileSize > 5 * 1024 * 1024) {
                            $upload_errors[] = "File $fileName is too large (Max 5MB).";
                            continue;
                        }

                        // Validate Extension (Security)
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        if (!in_array($fileExt, $allowed_exts)) {
                             $upload_errors[] = "File $fileName has an invalid extension. Only JPG, JPEG, PNG, PDF, DOCX are allowed.";
                             continue;
                        }

                        // Generate unique name (Safe ASCII)
                        $newFileName = uniqid('doc_', true) . '.' . $fileExt;
                        $target_path = $target_dir . $newFileName;

                        if (move_uploaded_file($tmpName, $target_path)) {
                            // Save to DB (Store relative path: assets/file/user/filename.ext)
                            $dbPath = "assets/file/user/" . $newFileName;
                            $stmtAtt = $db->prepare("INSERT INTO attachments (complaint_id, file_path, file_name, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                            $fileType = $_FILES['attachments']['type'][$i]; // Simple mime type
                            
                            // Fix: Truncate long MIME types (e.g. DOCX) to fit DB column (likely VARCHAR(50))
                            if (strlen($fileType) > 50) {
                                $fileType = substr($fileType, 0, 50);
                            }
                            
                            $stmtAtt->execute([$complaint_id, $dbPath, $fileName, $fileType, $fileSize]);
                        } else {
                            $upload_errors[] = "Failed to move uploaded file $fileName.";
                        }
                    } elseif ($error != UPLOAD_ERR_NO_FILE) {
                        // Capture other upload errors
                        $upload_errors[] = "Error uploading file $fileName (Code: $error).";
                        if ($error == UPLOAD_ERR_INI_SIZE || $error == UPLOAD_ERR_FORM_SIZE) {
                             $upload_errors[] = "File is too large for server configuration.";
                        }
                    }
                }
                
                if (!empty($upload_errors)) {
                     // Throw exception to rollback transaction
                     throw new Exception("File upload errors: " . implode(", ", $upload_errors));
                }
            }

            // Create Initial History
            $action_by_type = ($_SESSION['role_id'] == 4) ? 'user' : 'employee';
            $stmtHist = $db->prepare("INSERT INTO complaint_history (complaint_id, action_by_user_id, action_by_type, action_description) VALUES (?, ?, ?, ?)");
            $stmtHist->execute([$complaint_id, $user_id, $action_by_type, "Complaint submitted via web portal."]);

            $db->commit();

            // $db->commit(); // Already committed above
            
            $_SESSION['success'] = "<b>Success!</b> Your submission has been received. Ticket ID: #$complaint_id";
            
            // Redirect based on Role
            if ($_SESSION['role_id'] == 2) {
                header("Location: ../pr/new_complaint.php");
            } else {
                header("Location: ../user/status.php");
            }
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Submission failed: " . $e->getMessage();
            
            if ($_SESSION['role_id'] == 2) {
                 header("Location: ../pr/Complaint_Suggestion.php");
            } else {
                 header("Location: ../user/Complaint_Suggestion.php");
            }
            exit;
        }
    }
    
    // ----------- ACTION: ASSIGN EMPLOYEE (Department Officer) -----------
    elseif (isset($_POST['action']) && $_POST['action'] === 'assign_employee') {
        $complaint_id = $_POST['complaint_id'];
        $assigned_employee_id = $_POST['assigned_employee_id'];
        $user_id = $_SESSION['user_id'];
        $department_id = $_SESSION['department_id']; // Dept Officer's Department

        if (empty($assigned_employee_id)) {
             $_SESSION['error'] = "Please select an employee.";
             header("Location: " . $_SERVER['HTTP_REFERER']);
             exit;
        }

        try {
            $db->beginTransaction();

            // Validate that the employee belongs to the same department
            $stmtCheck = $db->prepare("SELECT id, full_name FROM employees WHERE id = ? AND department_id = ? AND role_id = 5");
            $stmtCheck->execute([$assigned_employee_id, $department_id]);
            $employee = $stmtCheck->fetch();

            if (!$employee) {
                throw new Exception("Invalid employee selection.");
            }

            // Update Complaint
            $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            $note = trim($_POST['note'] ?? '');
            $stmt = $db->prepare("UPDATE complaints SET assigned_employee_id = ?, status = 'Pending', due_date = ?, department_note = ?, updated_at = NOW() WHERE id = ? AND assigned_dept_id = ?");
            $stmt->execute([$assigned_employee_id, $due_date, $note, $complaint_id, $department_id]);

            if ($stmt->rowCount() == 0) {
                 throw new Exception("Complaint not found or not assigned to your department.");
            }

            // Log History
            $note = trim($_POST['note'] ?? '');
            $action_desc = "Assigned to Employee: " . $employee['full_name'];
            if (!empty($note)) {
                $action_desc .= " Note: " . $note;
            }
            if (!empty($due_date)) {
                $action_desc .= " [Due Date: " . $due_date . "]";
            }
            
            $stmtHist = $db->prepare("INSERT INTO complaint_history (complaint_id, action_by_user_id, action_description) VALUES (?, ?, ?)");
            $stmtHist->execute([$complaint_id, $user_id, $action_desc]);

            $db->commit();
            
            $_SESSION['success'] = "Complaint assigned to " . $employee['full_name'];
            header("Location: ../department/new_complaints_details.php?id=" . $complaint_id);
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Assignment failed: " . $e->getMessage();
            header("Location: ../department/new_complaints_details.php?id=" . $complaint_id);
            exit;
        }
    }

    // ----------- ACTION: UPDATE STATUS (Admin) -----------
    elseif (isset($_POST['complaint_id']) && isset($_POST['status'])) {
        $complaint_id = $_POST['complaint_id'];
        $status = $_POST['status'];
        $note = trim($_POST['note']);
        $user_id = $_SESSION['user_id'];
        $assigned_dept_id = isset($_POST['assigned_dept_id']) && !empty($_POST['assigned_dept_id']) ? $_POST['assigned_dept_id'] : null;


        if (empty($status)) {
            $referer = $_SERVER['HTTP_REFERER'] ?? "../index.php";
            if (strpos($referer, '?') !== false) {
                 header("Location: " . $referer . "&error=MissingStatus");
            } else {
                 header("Location: " . $referer . "?error=MissingStatus");
            }
            exit;
        }

        // Determine Redirect URL
        $redirect_url = isset($_POST['redirect']) ? $_POST['redirect'] : ($_SERVER['HTTP_REFERER'] ?? "../index.php");

        try {
            $db->beginTransaction();

            $updates = ["status = ?", "updated_at = NOW()"];
            $params = [$status];

            if ($assigned_dept_id !== null) {
                $updates[] = "assigned_dept_id = ?";
                $params[] = $assigned_dept_id;
            }
            
            // Handle Due Date Update (if provided)
            if (isset($_POST['due_date']) && !empty($_POST['due_date'])) {
                $updates[] = "due_date = ?";
                $params[] = $_POST['due_date'];
            }

            // Save Employee Note to complaints table if role is employee and status is internal review
            if ($_SESSION['role_id'] == 5 && $status === 'Review') {
                $updates[] = "employee_note = ?";
                $params[] = $note;
            }

            // Save Department Note if role is Department Officer
            if ($_SESSION['role_id'] == 3 && !empty($note)) {
                $updates[] = "department_note = ?";
                $params[] = $note;
            }

            $params[] = $complaint_id;
            $sql = "UPDATE complaints SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // Add History Log
            $action_desc = "Status updated to $status.";
            if (!empty($note)) {
                $action_desc .= " Note: $note";
            }
            if ($assigned_dept_id !== null) {
                $action_desc .= " [Department Assigned/Changed]";
            }
            if (isset($_POST['due_date']) && !empty($_POST['due_date'])) {
                 $action_desc .= " [Due Date: " . $_POST['due_date'] . "]";
            }


            $stmtHist = $db->prepare("INSERT INTO complaint_history (complaint_id, action_by_user_id, action_description) VALUES (?, ?, ?)");
            $stmtHist->execute([$complaint_id, $user_id, $action_desc]);

            // Handle File Deletions
            if (isset($_POST['deleted_attachments']) && is_array($_POST['deleted_attachments'])) {
                foreach ($_POST['deleted_attachments'] as $del_id) {
                    $del_id = (int)$del_id;
                    // Verify the attachment belongs to this complaint
                    $stmtCheckAtt = $db->prepare("SELECT file_path FROM attachments WHERE id = ? AND complaint_id = ?");
                    $stmtCheckAtt->execute([$del_id, $complaint_id]);
                    if ($row = $stmtCheckAtt->fetch()) {
                        $filePath = "../" . $row['file_path'];
                        // Delete file from disk
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        // Delete from database
                        $stmtDelAtt = $db->prepare("DELETE FROM attachments WHERE id = ?");
                        $stmtDelAtt->execute([$del_id]);
                    }
                }
            }

            // Handle File Uploads (Optional)
            if (isset($_FILES['attachments'])) {
                // Admin/Employee Uploads -> assets/file/emp/
                $target_dir = "../assets/file/emp/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $total_new_files = 0;
                $total_files = count($_FILES['attachments']['name']);
                for ($i = 0; $i < $total_files; $i++) {
                    if (!empty($_FILES['attachments']['name'][$i])) {
                        $total_new_files++;
                    }
                }

                // Check existing employee files
                $stmtCountFiles = $db->prepare("SELECT COUNT(*) FROM attachments WHERE complaint_id = ? AND file_path LIKE '%assets/file/emp/%'");
                $stmtCountFiles->execute([$complaint_id]);
                $existing_emp_files = (int)$stmtCountFiles->fetchColumn();

                if ($existing_emp_files + $total_new_files > 5) {
                    throw new Exception("You can only upload a maximum of 5 files in total.");
                }

                $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf', 'docx'];
                $upload_errors = [];
                
                for ($i = 0; $i < $total_files; $i++) {
                    $fileName = $_FILES['attachments']['name'][$i];
                    $tmpName = $_FILES['attachments']['tmp_name'][$i];
                    $fileSize = $_FILES['attachments']['size'][$i];
                    $error = $_FILES['attachments']['error'][$i];

                    if ($error === UPLOAD_ERR_OK && $fileName != "") {
                        // Validate size (5MB)
                        if ($fileSize > 5 * 1024 * 1024) {
                            $upload_errors[] = "File $fileName is too large (Max 5MB).";
                            continue;
                        }

                        // Validate Extension
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        if (!in_array($fileExt, $allowed_exts)) {
                             $upload_errors[] = "File $fileName has an invalid extension. Only JPG, JPEG, PNG, PDF are allowed.";
                             continue;
                        }

                        // Generate unique name
                        $newFileName = uniqid('doc_', true) . '.' . $fileExt;
                        $target_path = $target_dir . $newFileName;

                        if (move_uploaded_file($tmpName, $target_path)) {
                            // Save to DB (Store relative path: assets/file/emp/filename.ext)
                            $dbPath = "assets/file/emp/" . $newFileName;
                            $stmtAtt = $db->prepare("INSERT INTO attachments (complaint_id, file_path, file_name, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                            $fileType = $_FILES['attachments']['type'][$i];
                            
                            // Truncate long MIME types to fit DB column (likely VARCHAR(50))
                            if (strlen($fileType) > 50) {
                                $fileType = substr($fileType, 0, 50);
                            }
                            
                            $stmtAtt->execute([$complaint_id, $dbPath, $fileName, $fileType, $fileSize]);
                        } else {
                            $upload_errors[] = "Failed to move uploaded file $fileName.";
                        }
                    }
                }
                
                if (!empty($upload_errors)) {
                     throw new Exception("File upload errors: " . implode(", ", $upload_errors));
                }
            }

            // Trigger Email Notification if status is Completed
            if ($status === 'Completed') {
                require_once '../includes/email_helper.php';
                EmailHelper::sendCompletionEmail($complaint_id);
            }

            $db->commit();
            
            // Redirect to specified URL or Referer
            header("Location: " . $redirect_url);
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            // Redirect with error
            $redirect_fail = $redirect_url;
             if (strpos($redirect_fail, '?') !== false) {
                 $redirect_fail .= "&error=" . urlencode($e->getMessage());
            } else {
                 $redirect_fail .= "?error=" . urlencode($e->getMessage());
            }
             header("Location: " . $redirect_fail);
             exit;
        }
    }
    // ----------- FALLBACK: INVALID REQUEST -----------
    else {
        // If we reached here, it means POST data is missing or doesn't match expected fields.
        // This often happens if file size exceeds 'post_max_size' in php.ini.
        
        $referer = $_SERVER['HTTP_REFERER'] ?? "../index.php";
        $error_msg = "Invalid request. If you uploaded files, they might be too large (Server Limit).";
        
        if (strpos($referer, '?') !== false) {
             header("Location: " . $referer . "&error=" . urlencode($error_msg));
        } else {
             header("Location: " . $referer . "?error=" . urlencode($error_msg));
        }
        exit;
    }
}
?>