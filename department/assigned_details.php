<?php
// department/assigned_details.php
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
    echo "<div class='text-center py-10'>Complaint not found or not assigned to your department.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Fetch Attachments
$stmtAtt = $db->prepare("SELECT * FROM attachments WHERE complaint_id = ?");
$stmtAtt->execute([$complaint_id]);
$all_attachments = $stmtAtt->fetchAll();

$user_attachments = [];
$emp_attachments = [];

$comp_ts = strtotime($complaint['created_at']);

foreach ($all_attachments as $att) {
    if (strtotime($att['uploaded_at']) > ($comp_ts + 300)) { 
        $emp_attachments[] = $att;
    } else {
        $user_attachments[] = $att;
    }
}

// Fetch History
$stmtHist = $db->prepare("SELECT h.*, u.full_name, r.role_name 
                          FROM complaint_history h 
                          JOIN users u ON h.action_by_user_id = u.id 
                          JOIN roles r ON u.role_id = r.id 
                          WHERE h.complaint_id = ? ORDER BY h.timestamp DESC");
$stmtHist->execute([$complaint_id]);
$history = $stmtHist->fetchAll();

// Fetch Employees in this Department (Role 5) - Needed for re-assignment if necessary
$stmtEmps = $db->prepare("SELECT * FROM employees WHERE department_id = ? AND role_id = 5");
$stmtEmps->execute([$dept_id]);
$employees = $stmtEmps->fetchAll();
?>

<!-- AdminHub UI Structure -->
<div class="head-title">
    <div class="left">
        <h1><?php echo $lang['assigned_title_details']; ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo $lang['breadcrumb_dashboard']; ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a href="assigned.php"><?php echo $lang['assigned_title']; ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo $lang['details_breadcrumb_details']; ?></a></li>
        </ul>
    </div>
    <a href="assigned.php" class="btn-download" style="background: var(--light-blue); color: var(--blue);">
        <i class='bx bx-arrow-back'></i>
        <span class="text"><?php echo $lang['btn_back']; ?></span>
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
                        <span style="color: var(--dark); font-size: 1.5rem; margin-right: 5px; font-weight: 700;"><?php echo $lang['details_subject']; ?> :</span>
                        <?php echo htmlspecialchars($complaint['subject']); ?>
                    </h2>
                </div>
                
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; font-size: 13px; color: var(--dark-grey); background: var(--light); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--grey);">
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <i class='bx bxs-category'></i> 
                        <span style="font-weight: 600;"><?php echo $lang['complaint_form_type']; ?>: </span>
                        <?php 
                        $type_map = [
                            'complaint' => $lang['type_complaint'], 
                            'suggestion' => $lang['type_suggestion_feedback']
                        ];
                        $ctype = strtolower($complaint['complaint_type'] ?? '');
                        echo htmlspecialchars($type_map[$ctype] ?? ucfirst($ctype)); 
                        ?>
                    </span>
                    <span style="color: #cbd5e0;">|</span>
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <i class='bx bxs-business'></i> 
                        <span style="font-weight: 600;"><?php echo $lang['filter_faculty']; ?>: </span>
                        <?php echo htmlspecialchars($complaint['program'] ?? '-'); ?>
                    </span>
                </div>
            </div>
            

        </div>

        <div class="detail-content">
            <!-- Description Card -->
            <div style="background: var(--light); border: 1px solid var(--grey); border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h4 style="font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--dark);">
                    <i class='bx bx-align-left' style="color: var(--blue);"></i> <?php echo $lang['details_description']; ?>
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
             <?php if (count($user_attachments) > 0): ?>
                <div style="margin-top: 30px;">
                    <h4 style="font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--dark);">
                        <i class='bx bx-paperclip' style="color: var(--blue);"></i> <?php echo $lang['details_attachments']; ?> (<?php echo count($user_attachments); ?>)
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                        <?php foreach ($user_attachments as $att): ?>
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
         <!-- Complainant Info -->
         <div style="background: var(--light); border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="head" style="margin-bottom: 15px;">
                <h3><?php echo $lang['details_section_complainant']; ?></h3>
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
                        <span style="color: var(--dark-grey);"><i class='bx bxs-phone-call' style="color: var(--blue);"></i> <?php echo $lang['details_label_name']; ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['name'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo $lang['profile_occupation']; ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php 
                            $occ_key = 'occ_' . ($caller_data['occupation'] ?? '');
                            echo htmlspecialchars($lang[$occ_key] ?? ($caller_data['occupation'] ?? '-')); 
                        ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo $lang['profile_phone']; ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['phone'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo $lang['profile_email']; ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['email'] ?? '-'); ?></span>
                    </div>

                <?php elseif (!empty($complaint['is_anonymous'])): ?>
                    <div style="padding: 15px; background: var(--light); border-radius: 8px; border: 1px dashed var(--dark-grey); text-align: center; color: var(--dark-grey);">
                        <i class='bx bxs-ghost' style="font-size: 24px; margin-bottom: 5px;"></i>
                        <p><?php echo $lang['submit_anonymous_label']; ?></p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo $lang['details_label_name']; ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['complainer'] ?? '-'); ?></span>
                    </div>
                    <?php if (!empty($complaint['occupation'])): ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo $lang['profile_occupation']; ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php 
                            $occ_key = 'occ_' . ($complaint['occupation'] ?? '');
                            echo htmlspecialchars($lang[$occ_key] ?? ($complaint['occupation'] ?? '-')); 
                        ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($complaint['phone'])): ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo $lang['profile_phone']; ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo $lang['profile_email']; ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['email'] ?? '-'); ?></span>
                    </div>
                    <?php if (!empty($complaint['due_date'])): ?>
                    <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--grey); padding-top: 8px; margin-top: 8px;">
                        <span style="color: var(--dark-grey);"><i class='bx bx-calendar' style="color: var(--orange);"></i> <?php echo $lang['assigned_th_due_date']; ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo date('d/m/Y', strtotime($complaint['due_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="head">
            <h3><?php echo $lang['action_center']; ?></h3>
        </div>
        
         <!-- Workflow Action Panel (Assigned View) -->
        <div style="background: var(--light); border-radius: 15px; margin-bottom: 25px;">
            <?php if ($complaint['status'] === 'Resolved' || $complaint['status'] === 'Completed'): ?>
                <div style="text-align: center; padding: 40px 20px; background: var(--light); border-radius: 15px; border: 1px solid var(--grey);">
                    <div style="width: 80px; height: 80px; background: var(--grey); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto;">
                        <i class='bx bx-check' style="font-size: 50px; color: var(--dark);"></i>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 700; color: var(--dark); margin-bottom: 5px;"><?php echo $lang['case_processed']; ?></h3>
                    <p style="color: var(--dark-grey); font-size: 13px;"><?php echo $lang['case_processed_desc']; ?></p>
                </div>
            <?php elseif ($complaint['status'] === 'Review'): ?>
                 <div style="background: var(--light); padding: 20px; border-radius: 15px; border: 1px solid var(--grey); margin-bottom: 25px;">
                    
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
                    }
                    ?>
                    
                    <?php
                    $display_instruction = !empty($complaint['department_note']) ? $complaint['department_note'] : $instruction;
                    $display_source = !empty($complaint['department_note']) ? 'Department Officer' : $instruction_source;
                    ?>
                    
                    <?php if (!empty($display_instruction)): ?>
                        <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 15px; color: var(--dark); display: flex; align-items: center; gap: 8px;">
                            <i class='bx bx-note'></i> <?php echo $lang['instruction_from']; ?> <?php echo htmlspecialchars($display_source); ?>
                        </h4>
                        <div style="margin-bottom: 25px; position: relative;">
                            <textarea id="deptNote" rows="4" readonly style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--grey); background: var(--light); color: var(--dark); font-size: 14px; outline: none; resize: vertical; cursor: default; transition: all 0.3s ease;"><?php echo htmlspecialchars($display_instruction); ?></textarea>
                            
                            <!-- Expand Button -->
                            <button type="button" onclick="toggleFullscreenDeptNote()" id="fsBtnDept" style="position: absolute; bottom: 15px; right: 15px; background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 18px; color: var(--dark-grey); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; transition: all 0.2s; z-index: 10;" onmouseover="this.style.color='var(--blue)'; this.style.borderColor='var(--blue)';" onmouseout="this.style.color='var(--dark-grey)'; this.style.borderColor='var(--grey)';" title="<?php echo $lang['details_hint_expand']; ?>">
                                <i class='bx bx-expand-alt' id="fsIconDept"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 20px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                        <i class='bx bxs-user-check'></i> <?php echo $lang['details_employee_review']; ?>
                    </h4>
                    
                    
                    <?php
                    // Retrieve submission note from employee directly from the complaint record
                    $emp_note = "No note provided.";
                    if (!empty($complaint['employee_note'])) {
                        $emp_note = $complaint['employee_note'];
                    } else {
                        // Fallback to history for older records
                        foreach ($history as $log) {
                            if (strpos($log['action_description'], 'Review') !== false || strpos($log['action_description'], 'Status updated to Review') !== false) {
                                 if (preg_match('/Note:\s*(.*?)(\[|$)/', $log['action_description'], $matches)) {
                                     $emp_note = trim($matches[1]);
                                 }
                                 if ($emp_note !== "No note provided." && !empty($emp_note)) {
                                     break;
                                 }
                            }
                        }
                    }
                    ?>
                    
                    <!-- Quill CSS -->
                    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
                    <div id="noteWrapper" style="position: relative; margin-bottom: 20px; background: var(--light); border-radius: 10px; border: 1px solid var(--grey); transition: all 0.3s ease; display: flex; flex-direction: column;">
                        <!-- Quill Editor Container -->
                        <div id="resolutionNote" style="flex: 1; overflow-y: auto; max-height: 150px; color: var(--dark); border: none; font-family: var(--poppins, sans-serif);">
                            <?php echo $emp_note; // Allow HTML ?>
                        </div>
                        
                        <!-- Expand Button -->
                        <button type="button" onclick="toggleFullscreenNote()" id="fsBtn" style="position: absolute; bottom: 15px; right: 15px; background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 18px; color: var(--dark-grey); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; transition: all 0.2s; z-index: 10;" onmouseover="this.style.color='var(--blue)'; this.style.borderColor='var(--blue)';" onmouseout="this.style.color='var(--dark-grey)'; this.style.borderColor='var(--grey)';" title="<?php echo $lang['details_hint_expand']; ?>">
                            <i class='bx bx-expand-alt' id="fsIcon"></i>
                        </button>
                    </div>

                    <style>
                        /* Quill resets/overrides */
                        .ql-container.ql-snow { border: none !important; font-family: inherit !important; }
                        .ql-editor { padding: 12px 50px 12px 12px !important; font-size: 14px; min-height: 104px; }
                        
                        /* Adjust quill editor resize behavior to not break border radius */
                        #noteWrapper { min-height: 104px; }
                        
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

                        .note-wrapper-fullscreen #resolutionNote {
                            flex: 1;
                            overflow-y: auto;
                            font-size: 16px !important;
                            padding: 30px !important;
                            max-height: none !important;
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
                    </style>

                    <div id="fsBackdrop" class="fs-backdrop" onclick="closeAllFullscreen()"></div>

                    <!-- Quill JS -->
                    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            if (typeof Quill !== 'undefined') {
                                // Register inline style attributors so formatting works everywhere
                                var SizeStyle = Quill.import('attributors/style/size');
                                var AlignStyle = Quill.import('attributors/style/align');
                                var FontStyle = Quill.import('attributors/style/font');
                                Quill.register(SizeStyle, true);
                                Quill.register(AlignStyle, true);
                                Quill.register(FontStyle, true);

                                new Quill('#resolutionNote', {
                                    theme: 'snow',
                                    modules: { toolbar: false },
                                    readOnly: true
                                });
                            }
                        });
                        
                        function closeAllFullscreen() {
                            // Close Quill Fullscreen
                            const wrapper = document.getElementById('noteWrapper');
                            if (wrapper && wrapper.classList.contains('note-wrapper-fullscreen')) {
                                toggleFullscreenNote();
                            }
                            
                            // Close Textarea Fullscreen
                            const ta = document.getElementById('deptNote');
                            if (ta && ta.classList.contains('textarea-fullscreen')) {
                                toggleFullscreenDeptNote();
                            }
                        }

                        function toggleFullscreenDeptNote() {
                            const ta = document.getElementById('deptNote');
                            const btn = document.getElementById('fsBtnDept');
                            const icon = document.getElementById('fsIconDept');
                            const backdrop = document.getElementById('fsBackdrop');

                            if (ta.classList.contains('textarea-fullscreen')) {
                                ta.classList.remove('textarea-fullscreen');
                                btn.classList.remove('btn-fullscreen');
                                
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

                        function toggleFullscreenNote() {
                            const wrapper = document.getElementById('noteWrapper');
                            const btn = document.getElementById('fsBtn');
                            const icon = document.getElementById('fsIcon');
                            const backdrop = document.getElementById('fsBackdrop');

                            if (wrapper.classList.contains('note-wrapper-fullscreen')) {
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
                            }
                        }

                        window.addEventListener('resize', () => {
                            const wrapper = document.getElementById('noteWrapper');
                            if (wrapper && wrapper.classList.contains('note-wrapper-fullscreen')) {
                                const btn = document.getElementById('fsBtn');
                                btn.style.top = '10px';
                                btn.style.right = '15px';
                            }
                            
                            const ta = document.getElementById('deptNote');
                            if (ta && ta.classList.contains('textarea-fullscreen')) {
                                const btn = document.getElementById('fsBtnDept');
                                const maxWidth = 1000;
                                const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
                                const taWidth = Math.min(vw * 0.9, maxWidth);
                                
                                btn.style.top = 'calc(50vh - 40vh + 15px)';
                                btn.style.right = `calc(50vw - ${taWidth/2}px + 15px)`;
                            }
                        });
                    </script>

                    <?php if (count($emp_attachments) > 0): ?>
                        <div class="mb-8" style="background: var(--light); box-sizing: border-box; padding: 20px; border-radius: 12px; border: 1px solid var(--grey); margin-bottom: 20px;">
                            <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 5px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-paperclip'></i> Supporting Documents
                            </h4>
                            
                            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                                <?php foreach ($emp_attachments as $d_att): 
                                    $d_ext = strtolower(pathinfo($d_att['file_path'], PATHINFO_EXTENSION));
                                    
                                    // Determine Path (Old vs New)
                                    $dFilePath = $d_att['file_path'];
                                    if (strpos($dFilePath, 'assets/') === false) {
                                        $dFilePath = 'assets/uploads/complaints-file/' . $dFilePath;
                                    }
                                ?>
                                    <a href="../<?php echo $dFilePath; ?>" target="_blank" style="background: var(--light); border-radius: 8px; padding: 10px; display: flex; align-items: center; border: 1px solid var(--grey); gap: 15px; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.05)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                        <div style="width: 40px; height: 40px; border-radius: 6px; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--grey); border: 1px solid var(--grey);">
                                            <?php if (in_array($d_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                <img src="../<?php echo $dFilePath; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php elseif ($d_ext == 'pdf'): ?>
                                                <i class='bx bxs-file-pdf' style="font-size: 24px; color: var(--red);"></i>
                                            <?php else: ?>
                                                <i class='bx bxs-file-doc' style="font-size: 24px; color: var(--blue);"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div style="flex: 1; overflow: hidden;">
                                            <div style="font-size: 13px; font-weight: 500; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px;"><?php echo htmlspecialchars($d_att['file_name']); ?></div>
                                            <div style="font-size: 11px; font-weight: 500; color: var(--dark-grey);"><?php echo round($d_att['file_size']/1024, 1); ?> KB</div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; gap: 15px; margin-bottom: 25px;">
                        <!-- Reject Button (Triggers Modal) -->
                        <button type="button" onclick="openRejectModal()" style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid var(--red); background: transparent; color: var(--red); font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s;" onmouseover="this.style.background='var(--red)'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='var(--red)';">
                            <i class='bx bx-x'></i> <?php echo $lang['status_rejected']; ?>
                        </button>

                        <!-- Approve Form -->
                        <form action="../controllers/complaint_action.php" method="POST" style="flex: 1;">
                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                            <input type="hidden" name="status" value="Processed">
                            <input type="hidden" name="note" value="Approved by Department Officer">
                            <button type="submit" style="width: 100%; padding: 12px; border-radius: 8px; border: none; background: var(--green); color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class='bx bx-check'></i> <?php echo $lang['btn_approve']; ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Reject Modal Backdrop -->
                <div id="rejectModalBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9998; backdrop-filter: blur(3px);" onclick="closeRejectModal()"></div>
                
                <!-- Reject Modal Form -->
                <div id="rejectModal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 400px; background: var(--light); padding: 25px; border-radius: 15px; border: 1px dashed var(--red); box-shadow: 0 10px 40px rgba(0,0,0,0.2); z-index: 9999;">
                     <form action="../controllers/complaint_action.php" method="POST" enctype="multipart/form-data" id="rejectForm">
                        <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                        <input type="hidden" name="status" value="Rejected">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h5 style="font-size: 16px; font-weight: 700; color: var(--red); display: flex; align-items: center; gap: 5px; margin: 0;">
                                <i class='bx bx-x-circle'></i> <?php echo $lang['details_title_reject']; ?>
                            </h5>
                            <button type="button" onclick="closeRejectModal()" style="background: transparent; border: none; font-size: 20px; color: var(--dark-grey); cursor: pointer;"><i class='bx bx-x'></i></button>
                        </div>
                        
                        <textarea name="note" rows="4" required style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--grey); background: #fff; color: var(--dark); font-size: 14px; outline: none; resize: vertical; margin-bottom: 20px;" placeholder="<?php echo $lang['details_placeholder_reject']; ?>"></textarea>
                        
                        <button type="submit" style="width: 100%; padding: 12px; border-radius: 10px; border: none; background: var(--red); color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.3s;" onmouseover="this.style.background='#c1121f'" onmouseout="this.style.background='var(--red)'">
                            <i class='bx bx-x'></i> <?php echo $lang['status_rejected']; ?>
                        </button>
                     </form>
                </div>
                
                <script>
                    function openRejectModal() {
                        document.getElementById('rejectModal').style.display = 'block';
                        document.getElementById('rejectModalBackdrop').style.display = 'block';
                    }
                    function closeRejectModal() {
                        document.getElementById('rejectModal').style.display = 'none';
                        document.getElementById('rejectModalBackdrop').style.display = 'none';
                    }
                </script>
                </div>

            <?php elseif ($complaint['status'] === 'Processed'): ?>
                 <div style="text-align: center; padding: 40px 20px; background: var(--light); border-radius: 15px; border: 1px solid var(--grey);">
                    <div style="width: 80px; height: 80px; background: var(--grey); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto;">
                        <i class='bx bx-time-five' style="font-size: 50px; color: var(--dark);"></i>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 700; color: var(--dark); margin-bottom: 5px;"><?php echo $lang['wait_for_pr_review']; ?></h3>
                    <p style="color: var(--dark-grey); font-size: 13px;"><?php echo $lang['wait_for_pr_review_desc']; ?></p>
                </div>
            <?php else: ?>
                 <!-- Department Action Form (Assigned Mode) -->
                 <div style="background: var(--light); padding: 20px; border-radius: 15px; border: 1px solid var(--grey); margin-bottom: 25px;">
                    <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 20px; color: var(--dark);"><?php echo $lang['assignment_details']; ?></h4>
                    
                    <?php if ($complaint['assigned_employee_id']): ?>
                        <?php
                            // Find employee name
                            $assigned_emp_name = $lang['unknown'];
                            foreach($employees as $emp) {
                                if ($emp['id'] == $complaint['assigned_employee_id']) {
                                    $assigned_emp_name = $emp['full_name'];
                                    break;
                                }
                            }
                        ?>
                        <div style="background: #e8f0fe; color: #1967d2; padding: 15px; border-radius: 10px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                            <i class='bx bxs-user-check' style="font-size: 24px;"></i> 
                            <div>
                                <p style="font-size: 12px; color: var(--blue); margin-bottom: 2px;"><?php echo $lang['details_currently_assigned']; ?></p>
                                <strong style="font-size: 16px;"><?php echo htmlspecialchars($assigned_emp_name); ?></strong>
                            </div>
                        </div>

                        <!-- Re-assignment (Collapsed by default or small link?) -->
                        <details style="margin-top: 15px; border-top: 1px solid var(--grey); padding-top: 15px;">
                            <summary style="font-size: 13px; color: var(--dark-grey); cursor: pointer; font-weight: 600;"><?php echo $lang['change_assignment']; ?> <i class='bx bx-chevron-down'></i></summary>
                            
                            <form action="../controllers/complaint_action.php" method="POST" style="display: flex; flex-direction: column; gap: 15px; margin-top: 15px;">
                                <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                <input type="hidden" name="action" value="assign_employee">
                                
                                <div>
                                     <select name="assigned_employee_id" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--grey); background: var(--light); font-size: 13px;">
                                        <option value=""><?php echo $lang['details_select_employee_default']; ?></option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>">
                                                <?php echo htmlspecialchars($emp['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>


                                <button type="submit" style="width: 100%; padding: 10px; border-radius: 20px; border: none; background: var(--blue); color: #fff; font-size: 13px; font-weight: 600; cursor: pointer;">
                                    <?php echo $lang['btn_update_assignment']; ?>
                                </button>
                            </form>
                        </details>

                    <?php else: ?>
                        <!-- Fallback if not assigned (Shouldn't happen on this page) -->
                        <div class="alert-box error"><?php echo $lang['error_case_not_assigned']; ?></div>
                    <?php endif; ?>
                 </div>
            <?php endif; ?>
        </div>



    </div>
</div>



<?php require_once '../includes/footer.php'; ?>
