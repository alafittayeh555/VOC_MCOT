<?php
// pr/assigned_complaint_details.php
require_once '../includes/header.php';
require_once '../config/database.php';

// Check for PR Officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit;
}

$complaint_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$db = Database::connect();

// Fetch Complaint Details
$sql = "SELECT c.*, u.full_name as complainer, u.email, u.phone, u.occupation, d.name as assigned_dept 
        FROM complaints c 
        LEFT JOIN users u ON c.user_id = u.id 
        LEFT JOIN departments d ON c.assigned_dept_id = d.id 
        WHERE c.id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$complaint_id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    echo "<div class='text-center py-10'>Complaint not found.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Fetch Attachments
$stmtAtt = $db->prepare("SELECT * FROM attachments WHERE complaint_id = ?");
$stmtAtt->execute([$complaint_id]);
$all_attachments = $stmtAtt->fetchAll();

$attachments = []; // Original attachments (User)
$dept_attachments = []; // Department attachments

$comp_ts = strtotime($complaint['created_at']);

foreach ($all_attachments as $att) {
    // If uploaded > 5 minutes after creation, assume department/later upload
    // (User submission happens instantly with creation)
    $att_ts = strtotime($att['uploaded_at']);
    if ($att_ts > ($comp_ts + 300)) { 
        $dept_attachments[] = $att;
    } else {
        $attachments[] = $att;
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

// Fetch Departments for Assignment
$stmtDepts = $db->query("SELECT * FROM departments");
$departments = $stmtDepts->fetchAll();

// Fetch Employee Name if assigned
$assigned_emp_name = null;
if (!empty($complaint['assigned_employee_id'])) {
    $stmtEmp = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmtEmp->execute([$complaint['assigned_employee_id']]);
    $emp_res = $stmtEmp->fetch();
    if ($emp_res) {
        $assigned_emp_name = $emp_res['full_name'];
    }
}
?>

<!-- AdminHub UI Structure -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('pr_assigned_details_title', 'Assigned Details'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('menu_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a href="assigned.php"><?php echo __('menu_assigned_complaints'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('table_link_details'); ?></a></li>
        </ul>
    </div>
    <a href="assigned.php" class="btn-download" style="background: var(--light-blue); color: var(--blue);">
        <i class='bx bx-arrow-back'></i>
        <span class="text"><?php echo __('btn_back', 'Back'); ?></span>
    </a>
</div>

<div class="table-data">
    <!-- LEFT COLUMN: Main Details -->
    <div class="order" style="flex: 2;">
         <div class="head" style="margin-bottom: 30px; align-items: flex-start;">
            <div style="flex: 1;">
                <div style="margin-bottom: 15px;">
                     <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--dark); line-height: 1.3; margin-bottom: 8px;">
                        <span style="color: var(--dark); font-size: 1.5rem; margin-right: 5px; font-weight: 700;"><?php echo __('details_subject_label', 'เรื่อง :'); ?></span>
                        <?php echo htmlspecialchars($complaint['subject']); ?>
                    </h2>
                </div>
                
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; font-size: 13px; color: var(--dark-grey); background: var(--light); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--grey);">

                    <span style="display: flex; align-items: center; gap: 5px;">
                        <i class='bx bxs-category'></i> 
                        <span style="font-weight: 600;"><?php echo __('details_type_label', 'ประเภท:'); ?> </span>
                        <?php 
                        $ctype = strtolower($complaint['complaint_type'] ?? '');
                        if ($ctype === 'complaint') echo __('type_complaint', 'ข้อร้องเรียน');
                        elseif ($ctype === 'suggestion') echo __('type_suggestion', 'ข้อเสนอแนะ / ติชม');
                        else echo ucfirst($ctype);
                        ?>
                    </span>
                    <span style="color: #cbd5e0;">|</span>
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <i class='bx bxs-business'></i> 
                        <span style="font-weight: 600;"><?php echo __('details_agency_label', 'หน่วยงาน:'); ?> </span>
                        <?php echo htmlspecialchars($complaint['program'] ?? '-'); ?>
                    </span>
                </div>
            </div>
            
            <div style="margin-left: 20px;">
                <span class="status <?php
                    echo ($complaint['status'] == 'Resolved' || $complaint['status'] == 'Completed') ? 'completed' :
                        (($complaint['status'] == 'Processed') ? 'processed' :
                        (($complaint['status'] == 'Received') ? 'inprogress' :
                        (($complaint['status'] == 'In Progress') ? 'inprogress' :
                        (($complaint['status'] == 'Review') ? 'internalreview' : 
                            (($complaint['status'] == 'Pending') ? 'pending' : 'pending'))))); 
                    ?>" style="font-size: 12px; padding: 8px 16px; border-radius: 30px;">
                    <?php 
                        $status_key = 'status_' . strtolower(str_replace(' ', '_', $complaint['status']));
                        echo __($status_key, $complaint['status']); 
                    ?>
                </span>
            </div>
        </div>

        <div class="detail-content">
            <!-- Description Card -->
            <div style="background: var(--light); border: 1px solid var(--grey); border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h4 style="font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--dark);">
                    <i class='bx bx-align-left' style="color: var(--blue);"></i> <?php echo __('history_details_th_desc', 'รายละเอียด (Description)'); ?>
                </h4>
                <div class="whitespace-pre-wrap" style="color: var(--dark); font-size: 15px; line-height: 1.7;">
                    <?php 
                    // Remove Caller Information block from display as it's shown in sidebar
                    $clean_description = preg_replace('/\[Caller Information\].*?----------------------------------------\s*/s', '', $complaint['description']);
                    echo htmlspecialchars(trim($clean_description)); 
                    ?>
                </div>
            </div>

             <!-- Attachments -->
             <?php if (count($attachments) > 0): ?>
                <div style="margin-top: 30px;">
                    <h4 style="font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--dark);">
                        <i class='bx bx-paperclip' style="color: var(--blue);"></i> <?php echo __('history_details_th_attach', 'Attachments'); ?> (<?php echo count($attachments); ?>)
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
                <h3><?php echo __('nav_profile', 'Complainant'); ?></h3>
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
                        <span style="color: var(--dark-grey);"><i class='bx bxs-phone-call' style="color: var(--blue);"></i> <?php echo __('profile_name', 'Name'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['name'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_occupation', 'Occupation'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php 
                            $occ_key = 'occ_' . ($caller_data['occupation'] ?? '');
                            echo __($occ_key, $caller_data['occupation'] ?? '-'); 
                        ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_phone', 'Phone'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['phone'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_email', 'Email'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['email'] ?? '-'); ?></span>
                    </div>

                <?php elseif (!empty($complaint['is_anonymous'])): ?>
                    <div style="padding: 15px; background: var(--light); border-radius: 8px; border: 1px dashed var(--dark-grey); text-align: center; color: var(--dark-grey);">
                        <i class='bx bxs-ghost' style="font-size: 24px; margin-bottom: 5px;"></i>
                        <p><?php echo __('assigned_anonymous', 'Anonymous Submission'); ?></p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_name', 'Name'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['complainer'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_occupation', 'Occupation'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php 
                            $occ_key = 'occ_' . ($complaint['occupation'] ?? '');
                            echo __($occ_key, $complaint['occupation'] ?? '-'); 
                        ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_phone', 'Phone'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['phone'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--dark-grey);"><?php echo __('profile_email', 'Email'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['email'] ?? '-'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="head">
            <h3><?php echo __('action_center_title', 'Action Center'); ?></h3>
        </div>
        
         <!-- Workflow Action Panel -->
        <div style="background: var(--light); border-radius: 15px; margin-bottom: 25px;">
            <div style="padding: 20px;">
                <?php
                // Extract Department Note from History
                $dept_note = '';
                foreach ($history as $log) {
                    if (strpos($log['action_description'], 'Status updated to Processed') !== false) {
                        if (preg_match('/Note:\s*(.*?)(\[|$)/', $log['action_description'], $matches)) {
                            $dept_note = trim($matches[1]);
                        }
                        break;
                    }
                }
                ?>
                
                <?php if (!empty($dept_note)): ?>
                    <h5 style="font-size: 15px; font-weight: 700; color: var(--dark); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class='bx bx-note'></i> <?php echo __('pr_dept_note', 'Department Resolution Note'); ?>
                    </h5>
                    <div style="margin-bottom: 25px; position: relative;">
                        <textarea id="deptNote" rows="4" readonly style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--grey); background: var(--light); color: var(--dark); font-size: 14px; outline: none; resize: vertical; cursor: default; transition: all 0.3s ease;"><?php echo htmlspecialchars($dept_note); ?></textarea>
                        
                        <!-- Expand Button -->
                        <button type="button" onclick="toggleFullscreenDeptNote()" id="fsBtnDept" style="position: absolute; bottom: 15px; right: 15px; background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 18px; color: var(--dark-grey); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; transition: all 0.2s; z-index: 10;" onmouseover="this.style.color='var(--blue)'; this.style.borderColor='var(--blue)';" onmouseout="this.style.color='var(--dark-grey)'; this.style.borderColor='var(--grey)';" title="Expand">
                            <i class='bx bx-expand-alt' id="fsIconDept"></i>
                        </button>
                    </div>

                    <style>
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

                    <div id="fsBackdrop" class="fs-backdrop" onclick="toggleFullscreenDeptNote()"></div>

                    <script>
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

                        window.addEventListener('resize', () => {
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
                <?php endif; ?>


                <?php if ($complaint['status'] === 'Processed' || $complaint['status'] === 'Completed'): ?>


                    <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 20px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                        <i class='bx bxs-user-check'></i> <?php echo __('details_employee_review', 'Employee Review Details'); ?>
                    </h4>
                    
                    <?php
                    // Retrieve submission note from employee directly from the complaint record
                    $emp_note = "No note provided.";
                    if (!empty($complaint['employee_note'])) {
                        $emp_note = $complaint['employee_note'];
                    } else {
                        // Fallback to history for older records
                        foreach ($history as $log) {
                            if (strpos($log['action_description'], 'Review') !== false || strpos($log['action_description'], 'Status updated to Review') !== false || strpos($log['action_description'], 'Processed') !== false) {
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
                    
                    <!-- Employee Note (Quill CSS included at top or in layout) -->
                    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
                    <div id="noteWrapper" style="position: relative; margin-bottom: 20px; background: var(--light); border-radius: 10px; border: 1px solid var(--grey); transition: all 0.3s ease; display: flex; flex-direction: column;">
                        <!-- Quill Editor Container -->
                        <div id="resolutionNote" style="flex: 1; overflow-y: auto; max-height: 150px; color: var(--dark); border: none; font-family: var(--poppins, sans-serif);">
                            <?php echo $emp_note; // Allow HTML ?>
                        </div>
                        
                        <!-- Expand Button -->
                        <button type="button" onclick="toggleFullscreenNote()" id="fsBtn" style="position: absolute; bottom: 15px; right: 15px; background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 18px; color: var(--dark-grey); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; transition: all 0.2s; z-index: 10;" onmouseover="this.style.color='var(--blue)'; this.style.borderColor='var(--blue)';" onmouseout="this.style.color='var(--dark-grey)'; this.style.borderColor='var(--grey)';" title="Expand">
                            <i class='bx bx-expand-alt' id="fsIcon"></i>
                        </button>
                    </div>

                    <style>
                        /* Quill resets/overrides */
                        .ql-container.ql-snow { border: none !important; font-family: inherit !important; }
                        .ql-editor { padding: 12px 50px 12px 12px !important; font-size: 14px; min-height: 104px; }
                        
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
                    </style>

                    <!-- Add click listener to backdrop directly from dept note logic -->
                    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            if (typeof Quill !== 'undefined') {
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
                            
                            // Bind close to backdrop
                            document.getElementById('fsBackdrop').addEventListener('click', closeAllFullscreen);
                        });

                        function closeAllFullscreen() {
                            const noteWrapper = document.getElementById('noteWrapper');
                            if (noteWrapper && noteWrapper.classList.contains('note-wrapper-fullscreen')) {
                                toggleFullscreenNote();
                            }
                            
                            const ta = document.getElementById('deptNote');
                            if (ta && ta.classList.contains('textarea-fullscreen')) {
                                toggleFullscreenDeptNote();
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
                        });
                    </script>
                    <?php if (count($dept_attachments) > 0): ?>
                        <div class="mb-8" style="background: var(--light); box-sizing: border-box; padding: 20px; border-radius: 12px; border: 1px solid var(--grey); margin-bottom: 20px;">
                            <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 5px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-paperclip'></i> <?php echo __('pr_supporting_docs', 'Supporting Documents'); ?>
                            </h4>
                            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                                <?php foreach ($dept_attachments as $d_att): 
                                    $d_ext = strtolower(pathinfo($d_att['file_path'], PATHINFO_EXTENSION));
                                    
                                    // Determine Path (Old vs New) for Department Attachments
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

                <?php endif; ?>

                <?php if ($complaint['status'] === 'Processed'): ?>
                     <div style="background: var(--light); padding: 15px; border-radius: 16px; border: 1px solid var(--grey); margin-top: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
                        <form action="../controllers/complaint_action.php" method="POST">
                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                            <input type="hidden" name="status" value="Completed">
                            <input type="hidden" name="redirect" value="../pr/assigned.php">
                            <button type="submit" style="width: 100%; height: 50px; display: flex; align-items: center; justify-content: center; gap: 12px; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #ffffff; border: none; border-radius: 12px; font-weight: 600; font-size: 16px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 10px 20px rgba(15, 23, 42, 0.15); outline: none;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 30px rgba(15, 23, 42, 0.25)'; this.style.filter='brightness(1.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 20px rgba(15, 23, 42, 0.15)'; this.style.filter='brightness(1)';" onmousedown="this.style.transform='translateY(0) scale(0.98)';" onmouseup="this.style.transform='translateY(-2px) scale(1)';">
                                <i class='bx bxs-badge-check' style="font-size: 22px; color: #38bdf8;"></i>
                                <span style="letter-spacing: 0.3px;"><?php echo __('pr_btn_verify_complete', 'Verify & Complete'); ?></span>
                            </button>
                        </form>
                    </div>

                <?php elseif ($complaint['status'] === 'Completed'): ?>
                    <div style="text-align: center; padding: 20px 20px; background: var(--light); border-radius: 15px; border: 1px solid var(--grey);">
                        <div style="width: 60px; height: 60px; background: var(--grey); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px auto;">
                            <i class='bx bx-check' style="font-size: 40px; color: var(--dark);"></i>
                        </div>
                        <h3 style="font-size: 16px; font-weight: 700; color: var(--dark); margin-bottom: 5px;"><?php echo __('pr_case_closed', 'Case Closed'); ?></h3>
                        <p style="color: var(--dark-grey); font-size: 12px;"><?php echo __('pr_case_resolved', 'This complaint has been successfully resolved.'); ?></p>
                    </div>

                <?php else: ?>
                    <?php if ($complaint['assigned_dept'] || $assigned_emp_name): ?>
                        <div style="background: #e8f0fe; color: #1967d2; padding: 20px; border-radius: 16px; margin-bottom: 25px; display: flex; flex-direction: column; gap: 15px; border: 1px solid rgba(25, 103, 210, 0.1);">
                            <?php if ($complaint['assigned_dept']): ?>
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div style="width: 36px; height: 36px; background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #1967d2; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                                        <i class='bx bxs-briefcase-alt-2' style="font-size: 20px;"></i>
                                    </div>
                                    <div>
                                        <p style="font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; color: #1967d2; margin-bottom: 2px; opacity: 0.7;"><?php echo __('assigned_th_dept', 'Responsible Department'); ?></p>
                                        <strong style="font-size: 15px; display: block;"><?php echo htmlspecialchars($complaint['assigned_dept']); ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($assigned_emp_name): ?>
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div style="width: 36px; height: 36px; background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #1967d2; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                                        <i class='bx bxs-user-detail' style="font-size: 22px;"></i>
                                    </div>
                                    <div>
                                        <p style="font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; color: #1967d2; margin-bottom: 2px; opacity: 0.7;"><?php echo __('table_th_assigned_to', 'Person in Charge'); ?></p>
                                        <strong style="font-size: 15px; display: block;"><?php echo htmlspecialchars($assigned_emp_name); ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                     <!-- Assignment Form -->
                     <?php if ($complaint['status'] !== 'Pending'): ?>
                         <form action="../controllers/complaint_action.php" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                            <input type="hidden" name="redirect" value="../pr/assigned.php">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($complaint['status']); ?>">
    
                            <div>
                                <label style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--dark-grey); margin-bottom: 8px; display: block;"><?php echo __('pr_assign_dept', 'Assign Department'); ?></label>
                                <div style="position: relative;">
                                    <i class='bx bxs-briefcase-alt-2' style="position: absolute; top: 50%; transform: translateY(-50%); left: 12px; color: var(--blue);"></i>
                                    <select name="assigned_dept_id" required style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 10px; border: 1px solid var(--grey); background: var(--light); color: var(--dark); font-size: 14px; outline: none; cursor: pointer; -webkit-appearance: none;">
                                        <option value=""><?php echo __('pr_select_dept', 'Select Department...'); ?></option>
                                         <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>" <?php echo $complaint['assigned_dept_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class='bx bx-chevron-down' style="position: absolute; top: 50%; transform: translateY(-50%); right: 12px; color: var(--dark-grey); pointer-events: none;"></i>
                                </div>
                            </div>
    
                            <button type="submit" class="btn-download" style="width: 100%; justify-content: center; background: var(--blue); color: #fff; border: none; cursor: pointer; height: 45px; margin-top: 10px;">
                                <i class='bx bx-save' style="margin-right: 5px;"></i> <?php echo __('pr_btn_update_assign', 'Update & Assign'); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
