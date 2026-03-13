<?php
// department/new_complaints_details.php
require_once '../includes/header.php';
require_once '../config/database.php';

// Check for Dept Officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../login.php");
    exit;
}
$dept_id = $_SESSION['department_id'];
$complaint_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$db = Database::connect();

// Fetch Complaint (Security: Must be assigned to this dept)
$sql = "SELECT c.*, u.full_name as complainer, u.email, u.phone, u.occupation, d.name as assigned_dept 
        FROM complaints c 
        LEFT JOIN users u ON c.user_id = u.id 
        LEFT JOIN departments d ON c.assigned_dept_id = d.id 
        WHERE c.id = ? AND c.assigned_dept_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$complaint_id, $dept_id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    echo "<div class='text-center py-10'>" . __('error_complaint_not_found_dept') . "</div>";

    require_once '../includes/footer.php';
    exit;
}

// Fetch Attachments
$stmtAtt = $db->prepare("SELECT * FROM attachments WHERE complaint_id = ?");
$stmtAtt->execute([$complaint_id]);
$attachments = $stmtAtt->fetchAll();

// Fetch History
$stmtHist = $db->prepare("SELECT h.*, u.full_name, r.role_name 
                          FROM complaint_history h 
                          JOIN users u ON h.action_by_user_id = u.id 
                          JOIN roles r ON u.role_id = r.id 
                          WHERE h.complaint_id = ? ORDER BY h.timestamp DESC");
$stmtHist->execute([$complaint_id]);
$history = $stmtHist->fetchAll();

// Fetch Departments for Transfer (Optional usage, keeping standard for now)
$stmtDepts = $db->query("SELECT * FROM departments");
$departments = $stmtDepts->fetchAll();

// Fetch Employees in this Department (Role 5)
$stmtEmps = $db->prepare("SELECT * FROM employees WHERE department_id = ? AND role_id = 5");
$stmtEmps->execute([$dept_id]);
$employees = $stmtEmps->fetchAll();
?>

<!-- AdminHub UI Structure -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('assigned_title_details'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('menu_dashboard'); ?></a></li>

            <li><i class='bx bx-chevron-right'></i></li>
            <li><a href="assigned.php" onclick="if(document.referrer){history.back(); return false;}"><?php echo __('menu_assigned'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('details_breadcrumb_details'); ?></a></li>

        </ul>
    </div>
    <a href="assigned.php" onclick="if(document.referrer) { history.back(); return false; }" class="btn-download" style="background: var(--light-blue); color: var(--blue);">
        <i class='bx bx-arrow-back'></i>
        <span class="text"><?php echo __('btn_back'); ?></span>

    </a>
</div>

<!-- Alert Messages -->
<?php if (isset($_GET['error'])): ?>
    <div class="alert-box error" style="background: var(--light); color: var(--dark); padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border: 1px solid var(--dark-grey);">
        <i class='bx bxs-error-circle' style="font-size: 24px;"></i>
        <span><?php echo htmlspecialchars($_GET['error']); ?></span>
    </div>
<?php endif; ?>
<?php if (isset($_GET['success'])): ?>
    <div class="alert-box success" style="background: var(--light); color: var(--dark); padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border: 1px solid var(--grey);">
        <i class='bx bxs-check-circle' style="font-size: 24px;"></i>
        <span><?php echo htmlspecialchars($_GET['success']); ?></span>
    </div>
<?php endif; ?>

<div class="table-data">
    <!-- LEFT COLUMN: Main Details -->
    <div class="order" style="flex: 2;">
         <div class="head" style="margin-bottom: 30px; align-items: flex-start;">
            <div style="flex: 1;">
                <div style="margin-bottom: 15px;">
                     <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--dark); line-height: 1.3; margin-bottom: 8px;">
                        <span style="color: var(--dark); font-size: 1.5rem; margin-right: 5px; font-weight: 700;"><?php echo __('details_subject'); ?> :</span>

                        <?php echo htmlspecialchars($complaint['subject']); ?>
                    </h2>
                </div>
                
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; font-size: 13px; color: var(--dark-grey); background: var(--light); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--grey);">
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <i class='bx bxs-category'></i> 
                        <span style="font-weight: 600;"><?php echo __('complaint_form_type'); ?>: </span>

                        <?php 
                        $ctype = strtolower($complaint['complaint_type'] ?? '');
                        echo __('type_' . $ctype, ucfirst($ctype)); 
                        ?>

                    </span>
                    <span style="color: #cbd5e0;">|</span>
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <i class='bx bxs-business'></i> 
                        <span style="font-weight: 600;"><?php echo __('filter_faculty'); ?>: </span>

                        <?php echo htmlspecialchars($complaint['program'] ?? '-'); ?>
                    </span>
                </div>
            </div>
            
            
        </div>

        <div class="detail-content">
            <!-- Description Card -->
            <div style="background: var(--light); border: 1px solid var(--grey); border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h4 style="font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--dark);">
                    <i class='bx bx-align-left' style="color: var(--blue);"></i> <?php echo __('details_description'); ?>

                </h4>
                <div class="whitespace-pre-wrap" style="color: var(--dark); font-size: 15px; line-height: 1.7;">
                    <?php
                    // Clean description by removing Caller Information block
                    $clean_description = preg_replace('/\[Caller Information\].*?----------------------------------------\s*/s', '', $complaint['description']);
                    echo htmlspecialchars(trim($clean_description));
                    ?>
                </div>
            </div>

             <!-- Attachments -->
             <?php if (count($attachments) > 0): ?>
                <div style="margin-top: 30px;">
                    <h4 style="font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--dark);">
                        <i class='bx bx-paperclip' style="color: var(--blue);"></i> <?php echo __('details_attachments'); ?> (<?php echo count($attachments); ?>)

                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                        <?php foreach ($attachments as $att): ?>
                            <?php 
                            $fileExt = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                            $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']);
                            
                            // Determine Path (Old vs New)
                            $filePath = $att['file_path'];
                            if (strpos($filePath, 'assets/') === false) {
                                $filePath = 'assets/uploads/complaints-file/' . $filePath;
                            }
                            ?>
                            <a href="../<?php echo $filePath; ?>" target="_blank" 
                               style="display: block; background: var(--light); border: 1px solid var(--grey); border-radius: 10px; overflow: hidden; transition: transform 0.2s; position: relative; text-decoration: none;"
                               onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 5px 15px rgba(0,0,0,0.1)'"
                               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                                
                                <div style="height: 100px; background: var(--light); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                    <?php if ($isImage): ?>
                                        <img src="../<?php echo $filePath; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class='bx bxs-file-pdf' style="font-size: 40px; color: var(--red);"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="padding: 10px;">
                                    <p style="font-size: 12px; font-weight: 600; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;">
                                        <?php echo htmlspecialchars($att['file_name'] ?? 'File'); ?>
                                    </p>
                                    <p style="font-size: 10px; color: var(--dark-grey);">
                                        <?php echo strtoupper($fileExt); ?> • <?php echo ($att['file_size'] > 0) ? round($att['file_size']/1024, 1).' KB' : '-'; ?>
                                    </p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
    
    <!-- RIGHT COLUMN: Actions & History -->
    <div class="todo" style="flex: 1;">
         <!-- Complainant Info (Moved to Sidebar) -->
         <div style="background: var(--light); border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="head" style="margin-bottom: 15px;">
                <h3><?php echo __('details_section_complainant'); ?></h3>
                <i class='bx bxs-user-detail' style="font-size: 20px;"></i>

            </div>
            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 14px;">
                <?php
                // PARSE Caller Information from Description
                $has_caller_info = strpos($complaint['description'], '[Caller Information]') !== false;
                $caller_data = [];
                
                if ($has_caller_info) {
                    if (preg_match('/Name:\s*(.+)/', $complaint['description'], $matches)) $caller_data['name'] = trim($matches[1]);
                    if (preg_match('/Phone:\s*(.+)/', $complaint['description'], $matches)) $caller_data['phone'] = trim($matches[1]);
                    if (preg_match('/Occupation:\s*(.+)/', $complaint['description'], $matches)) $caller_data['occupation'] = trim($matches[1]);
                    if (preg_match('/Email:\s*(.+)/', $complaint['description'], $matches)) $caller_data['email'] = trim($matches[1]);
                }
                ?>

                <?php if ($has_caller_info): ?>
                     <!-- Display Caller Info -->
                     <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><i class='bx bxs-phone-call' style="color: var(--blue);"></i> <?php echo __('profile_name'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['name'] ?? '-'); ?></span>

                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_occupation'); ?></span>

                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['occupation'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_phone'); ?></span>

                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['phone'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_email'); ?></span>

                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['email'] ?? '-'); ?></span>
                    </div>

                <?php elseif (!empty($complaint['is_anonymous'])): ?>
                    <div style="padding: 15px; background: var(--light); border-radius: 8px; border: 1px dashed var(--dark-grey); text-align: center; color: var(--dark-grey);">
                        <i class='bx bxs-ghost' style="font-size: 24px; margin-bottom: 5px;"></i>
                        <p><?php echo __('submit_caller_anonymous'); ?></p>

                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_name'); ?></span>

                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['complainer'] ?? '-'); ?></span>
                    </div>
                    <?php if (!empty($complaint['occupation'])): ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_occupation'); ?></span>

                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['occupation']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($complaint['phone'])): ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_phone'); ?></span>

                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_email'); ?></span>

                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['email'] ?? '-'); ?></span>
                    </div>
                    <?php if (!empty($complaint['due_date'])): ?>
                    <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--grey); padding-top: 8px; margin-top: 8px;">
                        <span style="color: var(--dark-grey);"><i class='bx bx-calendar' style="color: var(--orange);"></i> <?php echo __('assigned_th_due_date'); ?></span>

                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo date('d/m/Y', strtotime($complaint['due_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="head">
            <h3><?php echo __('action_center'); ?></h3>

        </div>
        
         <!-- Workflow Action Panel (Department Logic) -->
        <div style="background: var(--light); border-radius: 15px; margin-bottom: 25px;">
            <?php if ($complaint['status'] === 'Resolved' || $complaint['status'] === 'Completed'): ?>
                <div style="text-align: center; padding: 40px 20px; background: var(--light); border-radius: 15px; border: 1px solid var(--grey);">
                    <div style="width: 80px; height: 80px; background: var(--grey); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto;">
                        <i class='bx bx-check' style="font-size: 50px; color: var(--dark);"></i>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 700; color: var(--dark); margin-bottom: 5px;"><?php echo __('case_processed'); ?></h3>
                    <p style="color: var(--dark-grey); font-size: 13px;"><?php echo __('case_processed_desc'); ?></p>

                </div>
            <?php elseif ($complaint['status'] === 'Review'): ?>
                 <div style="background: var(--light); padding: 20px; border-radius: 15px; border: 1px solid var(--grey); margin-bottom: 25px;">
                    <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 20px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                        <i class='bx bxs-user-check'></i> <?php echo __('details_employee_review'); ?>

                    </h4>
                    
                    <?php
                    // Find latest submission note from employee
                    $emp_note = "No note provided.";
                    foreach ($history as $log) {
                        if ($log['action_by_user_id'] == $complaint['assigned_employee_id'] && (strpos($log['action_description'], 'Review') !== false || strpos($log['action_description'], 'Status updated to Review') !== false)) {
                             if (preg_match('/Note:\s*(.*?)(\[|$)/', $log['action_description'], $matches)) {
                                 $emp_note = trim($matches[1]);
                             }
                             break;
                        }
                    }
                    ?>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; border-left: 4px solid var(--blue); margin-bottom: 20px;">
                        <p style="font-size: 12px; color: var(--dark-grey); margin-bottom: 5px; font-weight: 600;"><?php echo __('details_employee_note'); ?></p>

                        <div style="font-size: 14px; color: var(--dark); line-height: 1.5; font-family: var(--poppins, sans-serif);">
                            <?php echo $emp_note; // Allow HTML ?>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; flex-direction: column;">
                         <!-- Approve Form -->
                         <form action="../controllers/complaint_action.php" method="POST">
                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                            <input type="hidden" name="status" value="Completed">
                            <input type="hidden" name="note" value="Approved by Department Officer">
                            <button type="submit" style="width: 100%; padding: 12px; border-radius: 8px; border: none; background: var(--green); color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class='bx bx-check'></i> <?php echo __('details_btn_approve_close'); ?>

                            </button>
                         </form>

                         <!-- Reject Form -->
                         <form action="../controllers/complaint_action.php" method="POST" enctype="multipart/form-data" id="rejectForm" style="background: var(--light); padding: 15px; border-radius: 10px; border: 1px dashed var(--red);">
                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                            <input type="hidden" name="status" value="Pending">
                            
                            <h5 style="font-size: 13px; font-weight: 600; color: var(--red); margin-bottom: 10px; display: flex; align-items: center; gap: 5px;">
                                <i class='bx bx-x-circle'></i> <?php echo __('details_title_reject'); ?>

                            </h5>
                            
                            <textarea name="note" rows="3" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--grey); background: #fff; color: var(--dark); font-size: 13px; outline: none; resize: vertical; margin-bottom: 15px;" placeholder="<?php echo __('details_placeholder_reject'); ?>"></textarea>

                            
                            <div style="margin-bottom: 15px;">
                                <label style="font-size: 12px; font-weight: 600; color: var(--dark-grey); margin-bottom: 5px; display: block;"><?php echo __('details_label_supporting_docs'); ?></label>

                                <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf" style="width: 100%; padding: 8px; border-radius: 8px; border: 1px solid var(--grey); background: #fff; font-size: 13px; color: var(--dark);">
                                <small style="display: block; margin-top: 5px; color: var(--dark-grey); font-size: 11px;"><?php echo __('details_hint_attachments_dept'); ?></small>

                            </div>
                            
                            <button type="submit" style="width: 100%; padding: 10px; border-radius: 8px; border: none; background: var(--red); color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.3s;" onmouseover="this.style.background='#c1121f'" onmouseout="this.style.background='var(--red)'">
                                <i class='bx bx-x'></i> <?php echo __('details_btn_reject_return'); ?>

                            </button>
                         </form>
                </div>

            <?php elseif ($complaint['status'] === 'Processed'): ?>
                 <div style="text-align: center; padding: 40px 20px; background: var(--light); border-radius: 15px; border: 1px solid var(--grey);">
                    <div style="width: 80px; height: 80px; background: var(--grey); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto;">
                        <i class='bx bx-time-five' style="font-size: 50px; color: var(--dark);"></i>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 700; color: var(--dark); margin-bottom: 5px;"><?php echo __('wait_for_pr_review'); ?></h3>
                    <p style="color: var(--dark-grey); font-size: 13px;"><?php echo __('wait_for_pr_review_desc'); ?></p>

                </div>
            <?php else: ?>
                 <!-- Department Action Form -->
                 <div style="background: var(--light); padding: 20px; border-radius: 15px; border: 1px solid var(--grey); margin-bottom: 25px;">
                    <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 20px; color: var(--dark);"><?php echo __('details_title_assign_emp'); ?></h4>

                    
                    <?php
                    $pr_instruction = '';
                    foreach ($history as $log) {
                        // Find the latest assignment note (usually when status changed to 'In Progress')
                        if (strpos($log['action_description'], 'Status updated to In Progress') !== false) {
                             if (preg_match('/Note:\s*(.*?)(\[|$)/', $log['action_description'], $matches)) {
                                 $pr_instruction = trim($matches[1]);
                             }
                             break;
                        }
                    }
                    ?>
                    
                    <?php if (!empty($pr_instruction)): ?>
                        <div style="background: var(--grey); padding: 15px; border-radius: 10px; border: 1px dashed var(--dark-grey); margin-bottom: 20px;">
                            <h5 style="font-size: 13px; font-weight: 700; color: var(--dark); margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">
                                <i class='bx bx-note'></i> <?php echo __('details_instruction_pr'); ?>

                            </h5>
                            <p style="font-size: 14px; color: var(--dark); font-style: italic;">
                                "<?php echo htmlspecialchars($pr_instruction); ?>"
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($complaint['assigned_employee_id']): ?>
                        <?php
                            // Find employee name
                            $assigned_emp_name = "Unknown";
                            foreach($employees as $emp) {
                                if ($emp['id'] == $complaint['assigned_employee_id']) {
                                    $assigned_emp_name = $emp['full_name'];
                                    break;
                                }
                            }
                        ?>
                        <div style="background: #e8f0fe; color: #1967d2; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 14px;">
                            <i class='bx bxs-user-check'></i> <?php echo __('details_currently_assigned'); ?> <strong><?php echo htmlspecialchars($assigned_emp_name); ?></strong>

                        </div>
                    <?php endif; ?>

                    <form action="../controllers/complaint_action.php" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
                        <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                        <input type="hidden" name="action" value="assign_employee">
                        
                        <div>
                             <label style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--dark-grey); margin-bottom: 8px; display: block;"><?php echo __('details_label_due_date_optional'); ?></label>

                             <div style="position: relative;">
                                <i class='bx bx-calendar' style="position: absolute; top: 50%; transform: translateY(-50%); left: 12px; color: var(--blue); z-index: 10;"></i>
                                <input type="text" name="due_date" id="dueDate" class="input-theme" placeholder="<?php echo __('details_label_due_date_optional'); ?>">
                             </div>
                        </div>

                        <div>
                             <label style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--dark-grey); margin-bottom: 8px; display: block;"><?php echo __('details_label_assign_employee'); ?></label>

                             <div style="position: relative;">
                                <i class='bx bxs-user-badge' style="position: absolute; top: 50%; transform: translateY(-50%); left: 12px; color: var(--blue);"></i>
                                <select name="assigned_employee_id" required style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 10px; border: 1px solid var(--grey); background: var(--light); color: var(--dark); font-size: 14px; outline: none; cursor: pointer; -webkit-appearance: none;">
                                    <option value=""><?php echo __('details_select_employee_default'); ?></option>

                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>" <?php echo ($complaint['assigned_employee_id'] == $emp['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class='bx bx-chevron-down' style="position: absolute; top: 50%; transform: translateY(-50%); right: 12px; color: var(--dark-grey); pointer-events: none;"></i>
                             </div>
                        </div>

                        <div>
                            <label style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--dark-grey); margin-bottom: 8px; display: block;"><?php echo __('details_label_instruction_note'); ?></label>

                            <div style="position: relative;">
                                <textarea name="note" id="instructionNote" rows="3" style="width: 100%; padding: 12px 50px 12px 12px; border-radius: 10px; border: 1px solid var(--grey); background: var(--light); color: var(--dark); font-size: 14px; outline: none; resize: vertical;" placeholder="<?php echo __('details_placeholder_instruction'); ?>"></textarea>

                                <!-- Expand Button -->
                                <button type="button" onclick="toggleFullscreenInstruction()" id="fsBtnInstruction" style="position: absolute; bottom: 15px; right: 15px; background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 18px; color: var(--dark-grey); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; transition: all 0.2s; z-index: 10;" onmouseover="this.style.color='var(--blue)'; this.style.borderColor='var(--blue)';" onmouseout="this.style.color='var(--dark-grey)'; this.style.borderColor='var(--grey)';" title="<?php echo __('details_hint_expand'); ?>">

                                    <i class='bx bx-expand-alt' id="fsIconInstruction"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" style="width: 100%; padding: 14px; border-radius: 30px; border: none; background: var(--blue); color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.3s; box-shadow: 0 4px 10px rgba(60, 145, 230, 0.3);">
                            <i class='bx bx-send'></i> <?php echo __('details_btn_assign_send'); ?>
                        </button>

                    </form>
                 </div>
            <?php endif; ?>
        </div>

         <style>
             /* Fix flatpickr styling in dark mode */
             body.dark .flatpickr-calendar {
                 background: var(--light);
                 box-shadow: 0 0 10px rgba(0,0,0,0.5);
             }
             body.dark .flatpickr-day {
                 color: var(--dark);
             }
             body.dark .flatpickr-day.selected {
                 color: #fff;
             }
             body.dark .flatpickr-month {
                 color: var(--dark);
                 fill: var(--dark);
             }
             body.dark span.flatpickr-weekday {
                 color: var(--dark);
             }
             .input-theme {
                 background-color: transparent !important;
                 color: var(--dark) !important;
                 border: 1px solid var(--grey) !important;
                 width: 100%;
                 padding: 12px 12px 12px 40px !important;
                 border-radius: 10px !important;
                 font-size: 14px !important;
             }
             .input-theme::placeholder {
                 color: var(--dark-grey);
             }

             .textarea-fullscreen {
                 position: fixed !important;
                 top: 50% !important;
                 left: 50% !important;
                 transform: translate(-50%, -50%) !important;
                 width: 90% !important;
                 max-width: 1000px !important;
                 height: 80vh !important;
                 z-index: 9999 !important;
                 box-shadow: 0 10px 40px rgba(0,0,0,0.3) !important;
                 font-size: 16px !important;
                 padding: 30px !important;
             }
             .btn-fullscreen {
                 position: fixed !important;
                 z-index: 10000 !important;
             }
             .fs-backdrop {
                 display: none;
                 position: fixed;
                 top: 0; left: 0; right: 0; bottom: 0;
                 background: rgba(0,0,0,0.5);
                 z-index: 9998;
                 backdrop-filter: blur(3px);
             }
             .fs-backdrop.active {
                 display: block;
             }
         </style>
         
         <div id="fsBackdrop" class="fs-backdrop" onclick="toggleFullscreenInstruction()"></div>

         <script>
             function toggleFullscreenInstruction() {
                 const ta = document.getElementById('instructionNote');
                 const btn = document.getElementById('fsBtnInstruction');
                 const icon = document.getElementById('fsIconInstruction');
                 const backdrop = document.getElementById('fsBackdrop');

                 if (ta.classList.contains('textarea-fullscreen')) {
                     ta.classList.remove('textarea-fullscreen');
                     btn.classList.remove('btn-fullscreen');
                     
                     // Reset button position strictly inside the relative wrapper
                     btn.style.position = 'absolute';
                     btn.style.bottom = '15px';
                     btn.style.right = '15px';
                     btn.style.top = 'auto';
                     btn.style.left = 'auto';
                     
                     icon.className = 'bx bx-expand-alt';
                     backdrop.classList.remove('active');
                 } else {
                     ta.classList.add('textarea-fullscreen');
                     btn.classList.add('btn-fullscreen');
                     
                     // Center the button relative to the fullscreen textarea
                     const maxWidth = 1000;
                     const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
                     const taWidth = Math.min(vw * 0.9, maxWidth);
                     
                     btn.style.position = 'fixed';
                     btn.style.top = 'calc(50vh - 40vh + 15px)';
                     btn.style.right = `calc(50vw - ${taWidth/2}px + 15px)`;
                     btn.style.bottom = 'auto';
                     btn.style.left = 'auto';
                     
                     icon.className = 'bx bx-collapse-alt';
                     backdrop.classList.add('active');
                 }
             }

             // Adjust absolute position if window resizes while expanded
             window.addEventListener('resize', () => {
                 const ta = document.getElementById('instructionNote');
                 if (ta && ta.classList.contains('textarea-fullscreen')) {
                     const btn = document.getElementById('fsBtnInstruction');
                     const maxWidth = 1000;
                     const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
                     const taWidth = Math.min(vw * 0.9, maxWidth);
                     
                     btn.style.top = 'calc(50vh - 40vh + 15px)';
                     btn.style.right = `calc(50vw - ${taWidth/2}px + 15px)`;
                 }
             });
         </script>

         <!-- History Timeline -->
         <div style="background: var(--light); border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="head" style="margin-bottom: 20px; border-bottom: 1px solid var(--grey); padding-bottom: 10px;">
                <h3 style="font-size: 16px; font-weight: 700; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                    <i class='bx bx-history'></i> <?php echo __('details_history_activity'); ?>

                </h3>
            </div>
            
            <div class="history-timeline" style="display: flex; flex-direction: column; gap: 15px;">
                <?php foreach ($history as $log): ?>
                    <div style="display: flex; gap: 15px; position: relative;">
                        <!-- Timeline Line (Visual) -->
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <div style="width: 12px; height: 12px; border-radius: 50%; background: var(--blue); z-index: 1;"></div>
                            <div style="width: 2px; flex: 1; background: var(--grey); margin-top: -2px; margin-bottom: -15px;"></div>
                        </div>
                        
                        <div style="flex: 1; padding-bottom: 5px;">
                            <p style="font-size: 13px; color: var(--dark); font-weight: 600; margin-bottom: 4px;">
                                <?php echo htmlspecialchars(strip_tags($log['action_description'])); ?>
                            </p>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: var(--dark-grey);">
                                <span>
                                    <i class='bx bxs-user-circle' style="vertical-align: middle;"></i> 
                                    <?php echo htmlspecialchars($log['full_name']); ?> 
                                    <span style="color: var(--blue);"> (<?php echo htmlspecialchars($log['role_name']); ?>)</span>
                                </span>
                                <span>
                                    <i class='bx bx-time' style="vertical-align: middle;"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($log['timestamp'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>


    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr("#dueDate", {
            locale: "th",
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "j F Y",
            allowInput: true,
            disableMobile: "true",
            minDate: "today"
        });
    });
</script>
