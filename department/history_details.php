<?php
// department/history_details.php
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
        <h1><?php echo $lang['history_details_title']; ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo $lang['details_breadcrumb_dashboard']; ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a href="history.php"><?php echo $lang['history_breadcrumb_active']; ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo $lang['details_breadcrumb_details']; ?></a></li>
        </ul>
    </div>
    <a href="history.php" class="btn-download" style="background: var(--light-blue); color: var(--blue);">
        <i class='bx bx-arrow-back'></i>
        <span class="text"><?php echo $lang['btn_back_to_list']; ?></span>
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
                        <span style="color: var(--dark); font-size: 1.5rem; margin-right: 5px; font-weight: 700;"><?php echo $lang['details_subject_label']; ?> :</span>
                        <?php echo htmlspecialchars($complaint['subject']); ?>
                    </h2>
                </div>
                
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; font-size: 13px; color: var(--dark-grey); background: var(--light); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--grey);">
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <i class='bx bxs-category'></i> 
                        <span style="font-weight: 600;"><?php echo $lang['details_type_label']; ?>: </span>
                        <?php 
                        $ctype = strtolower($complaint['complaint_type'] ?? '');
                        if ($ctype === 'complaint') echo $lang['type_complaint'];
                        elseif ($ctype === 'suggestion') echo $lang['type_suggestion'];
                        else echo ucfirst($ctype);
                        ?>
                    </span>
                    <span style="color: #cbd5e0;">|</span>
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <i class='bx bxs-business'></i> 
                        <span style="font-weight: 600;"><?php echo $lang['details_agency_label']; ?>: </span>
                        <?php echo htmlspecialchars($complaint['program'] ?? '-'); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="detail-content">
            <!-- Description Card -->
            <div style="background: var(--light); border: 1px solid var(--grey); border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h4 style="font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--dark);">
                    <i class='bx bx-align-left' style="color: var(--blue);"></i> <?php echo $lang['history_details_th_desc']; ?>
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
                        <i class='bx bx-paperclip' style="color: var(--blue);"></i> <?php echo $lang['history_details_th_attach']; ?> (<?php echo count($user_attachments); ?>)
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
         <!-- Complainant Info (Moved to Sidebar) -->
         <div style="background: var(--light); border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="head" style="margin-bottom: 15px;">
                <h3><?php echo $lang['nav_profile']; ?></h3>
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
                        <span style="color: var(--dark-grey);"><i class='bx bxs-phone-call' style="color: var(--blue);"></i> <?php echo $lang['profile_name']; ?></span>
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
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--dark-grey);"><?php echo $lang['profile_email']; ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['email'] ?? '-'); ?></span>
                    </div>

                <?php elseif (!empty($complaint['is_anonymous'])): ?>
                    <div style="padding: 15px; background: var(--light); border-radius: 8px; border: 1px dashed var(--dark-grey); text-align: center; color: var(--dark-grey);">
                        <i class='bx bxs-ghost' style="font-size: 24px; margin-bottom: 5px;"></i>
                        <p><?php echo $lang['assigned_anonymous']; ?></p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo $lang['profile_name']; ?></span>
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
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--dark-grey);"><?php echo $lang['profile_email']; ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['email'] ?? '-'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="head">
            <h3><?php echo $lang['action_center_title']; ?></h3>
        </div>
        
            <!-- WorkFlow Action Panel (History Logic) -->
            <?php
            // Extract Final Resolution Note from History
            $emp_note = $lang['history_details_no_note'];
            foreach ($history as $log) {
                // Find the note from the step where the department processed it ('Processed') 
                // or where PR resolved it ('Resolved' / 'Completed')
                if (strpos($log['action_description'], 'Resolved') !== false || 
                    strpos($log['action_description'], 'Completed') !== false ||
                    strpos($log['action_description'], 'Processed') !== false ||
                    strpos($log['action_description'], 'Review') !== false) {
                    if (preg_match('/Note:\s*(.*?)(\[|$)/', $log['action_description'], $matches)) {
                        $emp_note = trim($matches[1]);
                    }
                    if ($emp_note !== $lang['history_details_no_note'] && !empty($emp_note)) {
                        break;
                    }
                }
            }
            ?>
            
            <!-- Process & Reply Card (Readonly) -->
            <div style="background: var(--light); border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                        <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 20px; color: var(--dark);"><?php echo $lang['process_reply_title']; ?></h4>

                        <!-- Quill CSS -->
                        <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
                        <div id="noteWrapper" style="position: relative; margin-bottom: 20px; background: var(--light); border-radius: 10px; border: 1px solid var(--grey); transition: all 0.3s ease; display: flex; flex-direction: column;">
                            <!-- Quill Editor Container -->
                            <div id="resolutionNote" style="flex: 1; overflow-y: auto; max-height: 150px; color: var(--dark); border: none; font-family: var(--poppins, sans-serif);">
                                <?php echo $emp_note; // Allow HTML ?>
                            </div>
                            
                            <!-- Expand Button -->
                            <button type="button" onclick="toggleFullscreenNote()" id="fsBtn" style="position: absolute; bottom: 15px; right: 15px; background: var(--light); border: 1px solid var(--grey); box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 18px; color: var(--dark-grey); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; transition: all 0.2s; z-index: 10;" onmouseover="this.style.color='var(--blue)'; this.style.borderColor='var(--blue)';" onmouseout="this.style.color='var(--dark-grey)'; this.style.borderColor='var(--grey)';" title="<?php echo $lang['details_hint_expand'] ?? 'Expand'; ?>">
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
                        </style>

                        <div id="fsBackdrop" class="fs-backdrop" onclick="toggleFullscreenNote()"></div>

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
                            });
                        </script>
            
                        <?php if (count($emp_attachments) > 0): ?>
                            <div class="mb-8" style="background: var(--light); box-sizing: border-box; padding: 20px; border-radius: 12px; border: 1px solid var(--grey); margin-bottom: 20px;">
                                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 5px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                                    <i class='bx bx-paperclip'></i> <?php echo $lang['history_details_th_attach']; ?>
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
                                        <div style="background: var(--light); border-radius: 8px; padding: 10px; display: flex; align-items: center; border: 1px solid var(--grey); gap: 15px;">
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
                                            <a href="../<?php echo $dFilePath; ?>" target="_blank" style="background: transparent; border: none; cursor: pointer; color: var(--blue); padding: 5px; display: flex; align-items: center; justify-content: center; transition: color 0.2s; text-decoration: none;" title="View File">
                                                <i class='bx bx-show' style='font-size: 20px;'></i>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
            </div>



    </div>
</div>

<?php require_once '../includes/footer.php'; ?>