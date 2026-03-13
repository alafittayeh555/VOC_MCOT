<?php
// department/case_details.php
require_once '../includes/header.php';
require_once '../config/database.php';

// Check for Employee role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 5) {
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
?>
<!-- AdminHub UI Structure -->
<!-- Quill CSS -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<div class="head-title">
    <div class="left">
        <h1><?php echo __('employee_details_title'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('breadcrumb_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a href="assigned_case.php"><?php echo __('assigned_title'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('details_breadcrumb_details'); ?></a></li>
        </ul>
    </div>
    <a href="assigned_case.php" class="btn-download" style="background: var(--light-blue); color: var(--blue);">
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
                        <span style="color: var(--dark); font-size: 1.5rem; margin-right: 5px; font-weight: 700;"><?php echo __('assigned_th_subject'); ?> :</span>
                        <?php echo htmlspecialchars($complaint['subject']); ?>
                    </h2>
                </div>
                
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; font-size: 13px; color: var(--dark-grey); background: var(--light); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--grey);">
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <i class='bx bxs-category'></i> 
                        <span style="font-weight: 600;"><?php echo __('complaint_form_type'); ?>: </span>
                        <?php 
                        $type_map = [
                            'complaint'  => __('type_complaint'),
                            'suggestion' => __('type_suggestion'),
                            'compliment' => __('type_compliment'),
                        ];
                        $ctype = strtolower($complaint['complaint_type'] ?? '');
                        echo htmlspecialchars($type_map[$ctype] ?? ucfirst($ctype)); 
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
                    <i class='bx bx-align-left' style="color: var(--blue);"></i> <?php echo __('history_details_th_desc'); ?>
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
             <?php 
             $original_attachments = array_filter($attachments, function($att) {
                 return strpos($att['file_path'], 'assets/file/emp/') === false;
             });
             ?>
             <?php if (count($original_attachments) > 0): ?>
                <div style="margin-top: 30px;">
                    <h4 style="font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--dark);">
                        <i class='bx bx-paperclip' style="color: var(--blue);"></i> <?php echo __('details_attachments'); ?> (<?php echo count($original_attachments); ?>)
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                        <?php foreach ($original_attachments as $att): ?>
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
                                        <?php echo htmlspecialchars($att['file_name'] ?? __('details_attachments')); ?>
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
                <h3><?php echo __('nav_profile'); ?></h3>
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
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo translate_occupation($caller_data['occupation'] ?? '-'); ?></span>
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
                        <p><?php echo __('assigned_anonymous'); ?></p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_name'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['complainer'] ?? '-'); ?></span>
                    </div>
                    <?php if (!empty($complaint['occupation'])): ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_occupation'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo translate_occupation($complaint['occupation']); ?></span>
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
                <?php endif; ?>
            </div>
        </div>

        <div class="head">
            <h3><?php echo __('action_center', 'Action Center'); ?></h3>
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
                 <div style="background: var(--light); padding: 20px; border-radius: 15px; border: 1px solid var(--grey);">
                    
                    <!-- Instruction Display -->
                    <?php
                    $instruction = '';
                    $instruction_source = '';

                    foreach ($history as $log) {
                        // Check for Department Officer Assignment Note
                        if (strpos($log['action_description'], 'Assigned to Employee') !== false) {
                             if (preg_match('/Note:\s*(.*?)(\[|$)/', $log['action_description'], $matches)) {
                                 $instruction = trim($matches[1]);
                                 $instruction_source = 'Department Officer';
                             }
                             break;
                        }
                        // Check for Rejection Note
                        elseif (strpos($log['action_description'], 'Status updated to Rejected') !== false) {
                             if (preg_match('/Note:\s*(.*?)(\[|$)/', $log['action_description'], $matches)) {
                                 $instruction = trim($matches[1]);
                                 $instruction_source = 'Department Officer (Rejected)';
                             }
                             if ($instruction) break;
                        }
                        // Fallback: Check for PR Note (if applicable, though typically Dept filters this)
                        elseif (strpos($log['action_description'], 'Status updated to In Progress') !== false) {
                             if (preg_match('/Note:\s*(.*?)(\[|$)/', $log['action_description'], $matches)) {
                                 $instruction = trim($matches[1]);
                                 $instruction_source = 'PR / Department';
                             }
                             // Keep searching if we want the *assignment* note specifically, but break if this is the relevant one
                             if ($instruction) break;
                        }
                    }
                    ?>
                    
                    <?php
                    $display_instruction = !empty($complaint['department_note']) ? $complaint['department_note'] : $instruction;
                    $display_source = !empty($complaint['department_note']) ? 'Department Officer' : $instruction_source;
                    ?>
                    
                    <?php if (!empty($display_instruction)): ?>
                        <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 15px; color: var(--dark); display: flex; align-items: center; gap: 8px;">
                            <i class='bx bx-note'></i> <?php echo __('notes_department'); ?>
                        </h4>
                        <div style="margin-bottom: 25px; position: relative;">
                            <textarea id="deptNote" rows="4" readonly style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--grey); background: var(--light); color: var(--dark); font-size: 14px; outline: none; resize: vertical; cursor: default; transition: all 0.3s ease;"><?php echo htmlspecialchars($display_instruction); ?></textarea>
                            
                            <!-- Expand Button -->
                            <button type="button" onclick="toggleFullscreenDeptNote()" id="fsBtnDept" style="position: absolute; bottom: 15px; right: 15px; background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 18px; color: var(--dark-grey); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; transition: all 0.2s; z-index: 10;" onmouseover="this.style.color='var(--blue)'; this.style.borderColor='var(--blue)';" onmouseout="this.style.color='var(--dark-grey)'; this.style.borderColor='var(--grey)';" title="Expand">
                                <i class='bx bx-expand-alt' id="fsIconDept"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 20px; color: var(--dark);"><?php echo __('process_reply'); ?></h4>

                    <form action="../controllers/complaint_action.php" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 20px;">
                        <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                        <input type="hidden" name="status" value="Review">
                        <input type="hidden" name="redirect" value="../employee/assigned_case.php">
                        
                        <div id="noteWrapper" style="position: relative; background: var(--light); border-radius: 10px; border: 1px solid var(--grey); transition: all 0.3s ease; display: flex; flex-direction: column;">
                            <!-- Quill Toolbar (Hidden natively, shown in fullscreen via CSS) -->
                            <div id="quill-toolbar" style="display: none; border: none; border-bottom: 1px solid var(--grey); background: #f8f9fa; border-radius: 10px 10px 0 0; padding: 10px;">
                                <span class="ql-formats">
                                    <select class="ql-font"></select>
                                    <select class="ql-size"></select>
                                </span>
                                <span class="ql-formats">
                                    <button class="ql-bold"></button>
                                    <button class="ql-italic"></button>
                                    <button class="ql-underline"></button>
                                </span>
                                <span class="ql-formats">
                                    <select class="ql-color"></select>
                                    <select class="ql-background"></select>
                                </span>
                                <span class="ql-formats">
                                    <button class="ql-list" value="ordered"></button>
                                    <button class="ql-list" value="bullet"></button>
                                    <select class="ql-align"></select>
                                </span>
                                <span class="ql-formats">
                                    <button class="ql-clean"></button>
                                </span>
                            </div>

                            <!-- Quill Editor Container -->
                            <div id="editor-container" style="flex: 1; overflow-y: auto; max-height: 150px; color: var(--dark); border: none; font-family: var(--poppins, sans-serif);">
                                <?php echo $complaint['employee_note'] ?? ''; ?>
                            </div>
                            
                            <!-- Hidden input for form submission -->
                            <input type="hidden" name="note" id="hiddenNote">
                            
                            <!-- Expand Button -->
                            <button type="button" onclick="toggleFullscreenNote()" id="fsBtn" style="position: absolute; bottom: 15px; right: 15px; background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 18px; color: var(--dark-grey); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; transition: all 0.2s; z-index: 10;" onmouseover="this.style.color='var(--blue)'; this.style.borderColor='var(--blue)';" onmouseout="this.style.color='var(--dark-grey)'; this.style.borderColor='var(--grey)';" title="Expand">
                                <i class='bx bx-expand-alt' id="fsIcon"></i>
                            </button>
                        </div>

                        <style>
                            /* Quill resets/overrides */
                            .ql-container.ql-snow { border: none !important; font-family: inherit !important; }
                            .ql-editor { padding: 12px 50px 12px 12px !important; font-size: 14px; min-height: 104px; }
                            .ql-editor.ql-blank::before { font-style: normal; color: #a9a9a9; left: 12px; }
                            
                            /* Adjust quill editor resize behavior to not break border radius */
                            #noteWrapper { resize: vertical; overflow: hidden; min-height: 104px; }
                            .ql-editor::-webkit-resizer { display: none; }
                            
                            .note-wrapper-fullscreen {
                                position: fixed !important;
                                top: 50% !important;
                                left: 50% !important;
                                transform: translate(-50%, -50%) !important;
                                width: 90% !important;
                                max-width: 1000px !important;
                                height: 80vh !important;
                                z-index: 9999 !important;
                                box-shadow: 0 10px 40px rgba(0,0,0,0.3) !important;
                                background: var(--light) !important;
                                overflow: hidden;
                            }
                            
                            .note-wrapper-fullscreen #quill-toolbar {
                                display: block !important;
                            }
                            
                            .note-wrapper-fullscreen #editor-container {
                                flex: 1;
                                overflow-y: auto;
                                font-size: 16px !important;
                                padding: 20px !important;
                                max-height: none !important;
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
                                resize: none !important;
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
                            .btn-fullscreen {
                                position: fixed !important;
                                z-index: 10000 !important;
                            }
                        </style>

                        <div id="fsBackdrop" class="fs-backdrop" onclick="toggleFullscreenNote()"></div>

                        <script>
                            function toggleFullscreenDeptNote() {
                                const ta = document.getElementById('deptNote');
                                const btn = document.getElementById('fsBtnDept');
                                const icon = document.getElementById('fsIconDept');
                                const backdrop = document.getElementById('fsBackdrop');

                                if (ta.classList.contains('textarea-fullscreen')) {
                                    // Close Fullscreen
                                    ta.classList.remove('textarea-fullscreen');
                                    
                                    btn.classList.remove('btn-fullscreen');
                                    btn.style.position = 'absolute';
                                    btn.style.bottom = '15px';
                                    btn.style.right = '15px';
                                    btn.style.top = 'auto';
                                    btn.style.left = 'auto';
                                    
                                    icon.className = 'bx bx-expand-alt';
                                    backdrop.classList.remove('active');
                                    
                                    // Restore original backdrop onclick function
                                    backdrop.onclick = toggleFullscreenNote;
                                } else {
                                    // Open Fullscreen
                                    ta.classList.add('textarea-fullscreen');
                                    
                                    btn.classList.add('btn-fullscreen');
                                    
                                    // Position close button inside the modal at top-right
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
                                    
                                    // Make backdrop click close this specific note
                                    backdrop.onclick = toggleFullscreenDeptNote;
                                }
                            }

                            function toggleFullscreenNote() {
                                const wrapper = document.getElementById('noteWrapper');
                                const btn = document.getElementById('fsBtn');
                                const icon = document.getElementById('fsIcon');
                                const backdrop = document.getElementById('fsBackdrop');

                                if (wrapper.classList.contains('note-wrapper-fullscreen')) {
                                    // Close Fullscreen
                                    wrapper.classList.remove('note-wrapper-fullscreen');
                                    
                                    btn.classList.remove('btn-fullscreen');
                                    btn.style.position = 'absolute';
                                    btn.style.bottom = '15px';
                                    btn.style.right = '15px';
                                    btn.style.top = 'auto';
                                    btn.style.left = 'auto';
                                    
                                    icon.className = 'bx bx-expand-alt';
                                    backdrop.classList.remove('active');
                                    
                                } else {
                                    // Open Fullscreen
                                    wrapper.classList.add('note-wrapper-fullscreen');
                                    
                                    btn.classList.add('btn-fullscreen');
                                    
                                    // Position close button absolutely inside the transformed wrapper
                                    btn.style.position = 'absolute';
                                    btn.style.top = '10px';
                                    btn.style.right = '15px';
                                    btn.style.bottom = 'auto';
                                    btn.style.left = 'auto';
                                    
                                    icon.className = 'bx bx-collapse-alt';
                                    backdrop.classList.add('active');
                                    
                                    // Make backdrop click close this specific note
                                    backdrop.onclick = toggleFullscreenNote;
                                }
                            }

                            // Re-calculate button position when window is resized if in fullscreen mode
                            window.addEventListener('resize', () => {
                                // For Quill editor noteWrapper (absolute position now handles resize automatically)
                                const wrapper = document.getElementById('noteWrapper');
                                if (wrapper && wrapper.classList.contains('note-wrapper-fullscreen')) {
                                    const btn = document.getElementById('fsBtn');
                                    btn.style.top = '10px';
                                    btn.style.right = '15px';
                                }

                                const taDept = document.getElementById('deptNote');
                                if (taDept && taDept.classList.contains('textarea-fullscreen')) {
                                    const btnDept = document.getElementById('fsBtnDept');
                                    const maxWidth = 1000;
                                    const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
                                    const taWidth = Math.min(vw * 0.9, maxWidth);
                                    
                                    btnDept.style.top = 'calc(50vh - 40vh + 15px)';
                                    btnDept.style.right = `calc(50vw - ${taWidth/2}px + 15px)`;
                                }
                            });
                        </script>

                        <div>
                            <!-- Styled File Input (Vertical List Layout) -->
                            <div class="mb-8" style="background: var(--light); padding: 20px; border-radius: 12px; border: 1px solid var(--grey); margin-bottom: 20px;">
                                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 5px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                                    <i class='bx bx-paperclip'></i> <?php echo __('supporting_documents'); ?>
                                </h4>
                                
                                <p style="font-size: 12px; color: var(--dark-grey); margin-bottom: 15px;">
                                   <?php echo __('supported_files_desc'); ?>
                                </p>

                                <div class="upload-list" id="uploadList" style="display: flex; flex-direction: column; gap: 10px;">
                                    <?php 
                                    $existing_count = 0;
                                    foreach ($attachments as $att): 
                                        if (strpos($att['file_path'], 'assets/file/emp/') !== false):
                                            $existing_count++;
                                            $fileExt = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                                            $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']);
                                            $filePath = '../' . $att['file_path'];
                                    ?>
                                        <div class="upload-row" id="existing_file_<?php echo $att['id']; ?>" style="background: var(--light); border-radius: 8px; padding: 10px; display: flex; align-items: center; border: 1px solid var(--grey); gap: 15px; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.05)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                            <a href="<?php echo $filePath; ?>" target="_blank" style="display: flex; align-items: center; gap: 15px; flex: 1; text-decoration: none; overflow: hidden; color: inherit;">
                                                <div style="width: 40px; height: 40px; border-radius: 6px; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--grey); border: 1px solid var(--grey);">
                                                    <?php if ($isImage): ?>
                                                        <img src="<?php echo $filePath; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                    <?php else: ?>
                                                        <i class='bx bxs-file-<?php echo $fileExt == 'pdf' ? 'pdf' : 'doc'; ?>' style="font-size: 24px; color: <?php echo $fileExt == 'pdf' ? '#ef4444' : '#2b579a'; ?>;"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="flex: 1; overflow: hidden;">
                                                    <p style="margin: 0; font-size: 13px; font-weight: 500; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($att['file_name']); ?></p>
                                                    <p style="margin: 0; font-size: 11px; color: var(--dark-grey);"><?php echo ($att['file_size'] > 0) ? round($att['file_size']/1024, 1).' KB' : 'Existing File'; ?></p>
                                                </div>
                                            </a>
                                            <?php if ($complaint['status'] !== 'Review'): ?>
                                            <button type="button" onclick="removeExistingFile(<?php echo $att['id']; ?>)" style="background: transparent; border: none; cursor: pointer; color: #ef4444; padding: 5px; display: flex; align-items: center; justify-content: center; transition: color 0.2s; position: relative; z-index: 2;">
                                                <i class='bx bx-trash' style="font-size: 20px;"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php 
                                        endif; 
                                    endforeach; 
                                    ?>
                                    
                                    <?php if ($complaint['status'] !== 'Review'): ?>
                                    <!-- "Add New" Button (Full Width) -->
                                    <div class="upload-card add-card" onclick="document.getElementById('fileInput').click()" 
                                         style="border: 2px dashed #3b82f6; border-radius: 10px; display: flex; align-items: center; justify-content: center; height: 60px; cursor: pointer; transition: all 0.3s; background: rgba(59, 130, 246, 0.05); gap: 10px;">
                                        <div style="background: rgba(59, 130, 246, 0.1); padding: 5px; border-radius: 50%; color: #2563eb;">
                                            <i class='bx bx-plus' style="font-size: 20px;"></i>
                                        </div>
                                        <h5 style="margin: 0; font-weight: 600; color: #2563eb; font-size: 13px;"><?php echo __('btn_add_file'); ?></h5>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Hidden Input -->
                                <input type="file" name="attachments[]" id="fileInput" multiple style="display: none;" onchange="handleFileSelect(event)" accept=".jpg,.jpeg,.png,.pdf" <?php echo ($complaint['status'] === 'Review') ? 'disabled' : ''; ?>>
                            </div>

                            <script>
                            // File Upload Logic
                            const fileInput = document.getElementById('fileInput');
                            const uploadList = document.getElementById('uploadList');
                            const dt = new DataTransfer();
                            const MAX_FILES = 5;
                            let existingFileCount = <?php echo isset($existing_count) ? $existing_count : 0; ?>;
                            const ALLOWED_TYPES = [
                                'image/jpeg', 
                                'image/png', 
                                'application/pdf',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // docx
                            ];
                            const MAX_SIZE = 5 * 1024 * 1024; // 5MB

                            function handleFileSelect(e) {
                                const files = e.target.files;
                                let count = dt.items.length + existingFileCount;
                                
                                <?php if ($complaint['status'] === 'Review'): ?>
                                    return; // Disable adding files via JS as well
                                <?php endif; ?>
                                
                                for (let i = 0; i < files.length; i++) {
                                    const file = files[i];
                                    
                                    // Check Limit
                                    if (count >= MAX_FILES) {
                                        alert("Maximum " + MAX_FILES + " files allowed.");
                                        break;
                                    }

                                    // Check Type
                                    if (!ALLOWED_TYPES.includes(file.type)) {
                                        alert("Invalid file: " + file.name);
                                        continue;
                                    }

                                    // Check Size
                                    if (file.size > MAX_SIZE) {
                                        alert("File too large: " + file.name);
                                        continue;
                                    }

                                     // Check duplicates
                                     let isDuplicate = false;
                                     for (let j = 0; j < dt.items.length; j++) {
                                         if (dt.items[j].getAsFile().name === file.name && dt.items[j].getAsFile().size === file.size) {
                                             isDuplicate = true;
                                             break;
                                         }
                                     }
                                     
                                     if(!isDuplicate) {
                                         dt.items.add(file);
                                         count++;
                                     }
                                }
                                fileInput.files = dt.files;
                                renderList();
                            }

                            function renderList() {
                                // Remove previously dynamically added rows and buttons, keep existing_file_ rows
                                const dynamicRows = uploadList.querySelectorAll('.upload-row:not([id^="existing_file_"])');
                                dynamicRows.forEach(row => row.remove());
                                
                                const addCards = uploadList.querySelectorAll('.add-card');
                                addCards.forEach(card => card.remove());
                                
                                // Render Files (Newly added via JS)
                                for (let i = 0; i < dt.files.length; i++) {
                                    const file = dt.files[i];
                                    const row = document.createElement('div');
                                    row.className = 'upload-row';
                                    row.style.cssText = 'background: var(--light); border-radius: 8px; padding: 10px; display: flex; align-items: center; border: 1px solid var(--grey); gap: 15px; transition: transform 0.2s, box-shadow 0.2s;';
                                    row.onmouseover = function() { this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.05)'; };
                                    row.onmouseout = function() { this.style.transform='translateY(0)'; this.style.boxShadow='none'; };
                                    
                                    // Icon/Preview Area
                                    const iconDiv = document.createElement('div');
                                    iconDiv.style.cssText = 'width: 40px; height: 40px; border-radius: 6px; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--grey); border: 1px solid var(--grey);';
                                    
                                    if (file.type.startsWith('image/')) {
                                        const img = document.createElement('img');
                                        img.file = file;
                                        img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
                                        
                                        const reader = new FileReader();
                                        reader.onload = (function(aImg) { return function(e) { aImg.src = e.target.result; }; })(img);
                                        reader.readAsDataURL(file);
                                        
                                        iconDiv.appendChild(img);
                                    } else {
                                        // PDF
                                        let iconClass = 'bx bxs-file';
                                        let iconColor = '#6b7280';
                                        
                                        if (file.type.includes('pdf')) {
                                            iconClass = 'bx bxs-file-pdf';
                                            iconColor = '#ef4444';
                                        }
                                        
                                        iconDiv.innerHTML = `<i class='${iconClass}' style="font-size: 24px; color: ${iconColor};"></i>`;
                                    }
                                    
                                    // Info Area
                                    const infoDiv = document.createElement('div');
                                    infoDiv.style.cssText = 'flex: 1; overflow: hidden;';
                                    infoDiv.innerHTML = `
                                        <p style="margin: 0; font-size: 13px; font-weight: 500; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${file.name}</p>
                                        <p style="margin: 0; font-size: 11px; color: var(--dark-grey);">${(file.size/1024).toFixed(1)} KB</p>
                                    `;
                                    
                                    // Remove Button
                                    const delBtn = document.createElement('button');
                                    delBtn.type = 'button';
                                    delBtn.onclick = function() { removeFile(i); };
                                    delBtn.style.cssText = 'background: transparent; border: none; cursor: pointer; color: #ef4444; padding: 5px; display: flex; align-items: center; justify-content: center; transition: color 0.2s;';
                                    delBtn.innerHTML = "<i class='bx bx-trash' style='font-size: 20px;'></i>";
                                    delBtn.onmouseover = function() { this.style.color = '#dc2626'; };
                                    delBtn.onmouseout = function() { this.style.color = '#ef4444'; };
                                    
                                    // Create Anchor wrapper for preview
                                    const aWrapper = document.createElement('a');
                                    aWrapper.href = URL.createObjectURL(file);
                                    aWrapper.target = '_blank';
                                    aWrapper.style.cssText = 'display: flex; align-items: center; gap: 15px; flex: 1; text-decoration: none; overflow: hidden; color: inherit;';
                                    aWrapper.appendChild(iconDiv);
                                    aWrapper.appendChild(infoDiv);
                                    
                                    row.appendChild(aWrapper);
                                    row.appendChild(delBtn);
                                    
                                    uploadList.appendChild(row);
                                }
                                
                                // "Add New" Button
                                <?php if ($complaint['status'] !== 'Review'): ?>
                                if ((dt.items.length + existingFileCount) < MAX_FILES) {
                                    const addBtn = document.createElement('div');
                                    addBtn.className = 'upload-card add-card';
                                    addBtn.onclick = function() { document.getElementById('fileInput').click(); };
                                    addBtn.style.cssText = 'border: 2px dashed #3b82f6; border-radius: 10px; display: flex; align-items: center; justify-content: center; height: 50px; cursor: pointer; transition: all 0.3s; background: rgba(59, 130, 246, 0.05); gap: 8px; color: #2563eb; font-weight: 600; font-size: 13px;';
                                    addBtn.innerHTML = `
                                        <i class='bx bx-plus' style="font-size: 18px;"></i> <?php echo __('btn_add_file'); ?>
                                    `;
                                    
                                    addBtn.onmouseover = function() { this.style.background = 'rgba(59, 130, 246, 0.1)'; };
                                    addBtn.onmouseout = function() { this.style.background = 'rgba(59, 130, 246, 0.05)'; };
                                    
                                    uploadList.appendChild(addBtn);
                                }
                                <?php endif; ?>
                            }

                            function removeFile(index) {
                                dt.items.remove(index);
                                fileInput.files = dt.files;
                                renderList();
                            }

                            function removeExistingFile(attachmentId) {
                                // Remove the UI element
                                const el = document.getElementById('existing_file_' + attachmentId);
                                if (el) el.remove();
                                
                                // Create a hidden input to send to the server
                                const hiddenInput = document.createElement('input');
                                hiddenInput.type = 'hidden';
                                hiddenInput.name = 'deleted_attachments[]';
                                hiddenInput.value = attachmentId;
                                
                                document.querySelector('form[action="../controllers/complaint_action.php"]').appendChild(hiddenInput);
                                
                                existingFileCount--;
                                renderList();
                            }
                            </script>
                        </div>

                        <?php if ($complaint['status'] !== 'Review'): ?>
                        <button type="submit" style="width: 100%; padding: 14px; border-radius: 30px; border: none; background: var(--blue); color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.3s; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);">
                            <i class='bx bx-send'></i> <?php echo __('btn_submit_review'); ?>
                        </button>
                        <?php endif; ?>
                    </form>
                 </div>
            <?php endif; ?>
        </div>




    </div>
</div>

<!-- Quill JS Setup -->
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Register inline style attributors so formatting works everywhere without Quill CSS
        var SizeStyle = Quill.import('attributors/style/size');
        var AlignStyle = Quill.import('attributors/style/align');
        var FontStyle = Quill.import('attributors/style/font');
        Quill.register(SizeStyle, true);
        Quill.register(AlignStyle, true);
        Quill.register(FontStyle, true);

        var isReadOnly = <?php echo ($complaint['status'] === 'Review') ? 'true' : 'false'; ?>;
        var quill = new Quill('#editor-container', {
            modules: {
                toolbar: isReadOnly ? false : '#quill-toolbar'
            },
            placeholder: '<?php echo __('resolution_note_placeholder'); ?>',
            theme: 'snow',
            readOnly: isReadOnly
        });

        // On form submit, copy Quill contents to hidden input
        var forms = document.querySelectorAll('form[action="../controllers/complaint_action.php"]');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var html = quill.root.innerHTML;
                var text = quill.getText().trim();
                
                // If it's empty visually, block submission
                if (text === '' && html.indexOf('<img') === -1) {
                    e.preventDefault();
                    // Optional: show a small alert to replace the HTML5 required bubble
                    alert('<?php echo __('error_provide_resolution'); ?>');
                    return false;
                }
                
                if (html === '<p><br></p>') html = '';
                document.getElementById('hiddenNote').value = html;
            });
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>