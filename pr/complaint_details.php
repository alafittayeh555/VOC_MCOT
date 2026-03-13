<?php
// pr/complaint_details.php
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
    echo "<div class='text-center py-10'>" . __('details_not_found') . "</div>";
    require_once '../includes/footer.php';
    exit;
}

// Fetch Attachments
$stmtAtt = $db->prepare("SELECT * FROM attachments WHERE complaint_id = ?");
$stmtAtt->execute([$complaint_id]);
$attachments = $stmtAtt->fetchAll();


// Fetch Departments for Assignment
$stmtDepts = $db->query("SELECT * FROM departments");
$departments = $stmtDepts->fetchAll();
?>

<!-- AdminHub UI Structure -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('details_title'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('menu_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a href="new_complaint.php" onclick="if(document.referrer){history.back(); return false;}"><?php echo __('menu_new_complaints'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('details_breadcrumb_details'); ?></a></li>
        </ul>
    </div>
    <a href="new_complaint.php" onclick="if(document.referrer) { history.back(); return false; }" class="btn-download" style="background: var(--light-blue); color: var(--blue);">
        <i class='bx bx-arrow-back'></i>
        <span class="text"><?php echo __('details_back'); ?></span>
    </a>
</div>

<div class="table-data">
    <!-- LEFT COLUMN: Main Details -->
    <div class="order" style="flex: 2;">
         <div class="head" style="margin-bottom: 30px; align-items: flex-start;">
            <div style="flex: 1;">
                <div style="margin-bottom: 15px;">
                      <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--dark); line-height: 1.3; margin-bottom: 8px;">
                        <span style="color: var(--dark); font-size: 1.5rem; margin-right: 5px; font-weight: 700;"><?php echo __('details_subject_label'); ?></span>
                        <?php echo htmlspecialchars($complaint['subject']); ?>
                    </h2>
                </div>
                
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; font-size: 13px; color: var(--dark-grey); background: var(--light); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--grey);">

                    <span style="display: flex; align-items: center; gap: 5px;">
                        <i class='bx bxs-category'></i> 
                        <span style="font-weight: 600;"><?php echo __('details_type_label'); ?> </span>
                        <?php 
                        $type_map = [
                            'complaint'  => __('nc_type_complaint'), 
                            'suggestion' => __('nc_type_suggestion'),
                            'compliment' => __('nc_type_compliment')
                        ];
                        $ctype = strtolower($complaint['complaint_type'] ?? '');
                        echo htmlspecialchars($type_map[$ctype] ?? ucfirst($ctype)); 
                        ?>
                    </span>
                    <span style="color: #cbd5e0;">|</span>
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <i class='bx bxs-business'></i> 
                        <span style="font-weight: 600;"><?php echo __('details_agency_label'); ?> </span>
                        <?php echo htmlspecialchars($complaint['program'] ?? '-'); ?>
                    </span>
                </div>
            </div>
            
        </div>

        <div class="detail-content">
            <!-- Subject removed from here as it is now in header -->

            <!-- Description Card -->
            <div style="background: var(--light); border: 1px solid var(--grey); border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h4 style="font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--dark);">
                    <i class='bx bx-align-left' style="color: var(--blue);"></i> <?php echo __('details_description_label'); ?>
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
                        <i class='bx bx-paperclip' style="color: var(--blue);"></i> <?php echo __('details_attachments_count'); ?> (<?php echo count($attachments); ?>)
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                        <?php foreach ($attachments as $att): ?>
                            <?php 
                            $fileExt = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                            $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']);
                            
                            // Determine Path (Old vs New)
                            // If file_path contains "assets/", assume it's a full relative path.
                            // Otherwise, assume it's a legacy file in "assets/uploads/complaints-file/"
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
         <!-- Complainant Info (Moved to Sidebar) -->
         <div style="background: var(--light); border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="head" style="margin-bottom: 15px;">
                <h3><?php echo __('details_complainant_title'); ?></h3>
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
                        <span style="color: var(--dark-grey);"><i class='bx bxs-phone-call' style="color: var(--blue);"></i> <?php echo __('details_complainant_name'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['name'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('details_complainant_occ'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['occupation'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('details_complainant_phone'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['phone'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--dark-grey);"><?php echo __('details_complainant_email'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($caller_data['email'] ?? '-'); ?></span>
                    </div>

                <?php elseif (!empty($complaint['is_anonymous'])): ?>
                    <div style="padding: 15px; background: var(--light); border-radius: 8px; border: 1px dashed var(--dark-grey); text-align: center; color: var(--dark-grey);">
                        <i class='bx bxs-ghost' style="font-size: 24px; margin-bottom: 5px;"></i>
                        <p><?php echo __('details_anonymous_box'); ?></p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('details_complainant_name'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['complainer'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('details_complainant_occ'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['occupation'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--grey); padding-bottom: 8px;">
                        <span style="color: var(--dark-grey);"><?php echo __('details_complainant_phone'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['phone'] ?? '-'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--dark-grey);"><?php echo __('details_complainant_email'); ?></span>
                        <span style="font-weight: 600; color: var(--dark); text-align: right;"><?php echo htmlspecialchars($complaint['email'] ?? '-'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="head">
            <h3><?php echo __('details_action_center'); ?></h3>
        </div>
        
         <!-- Workflow Action Panel -->
        <div style="background: #fff; border-radius: 15px; margin-bottom: 25px;">
             <?php if ($complaint['status'] === 'Processed'): ?>
                 <div style="background: var(--light); padding: 20px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px dashed var(--dark-grey);">
                    <h4 style="margin-bottom: 10px; color: var(--dark); display: flex; align-items: center; gap: 8px;">
                        <i class='bx bx-check-shield'></i> <?php echo __('details_ready_review'); ?>
                    </h4>
                    <p style="font-size: 0.9rem; margin-bottom: 15px; color: var(--dark-grey);">
                        <?php echo __('details_ready_review_desc'); ?>
                    </p>
                    <form action="../controllers/complaint_action.php" method="POST">
                        <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                        <input type="hidden" name="status" value="Completed">
                        <textarea name="note" rows="2" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--grey); margin-bottom: 12px; font-size: 13px; background: var(--light); color: var(--dark);" placeholder="<?php echo __('details_verify_note_placeholder'); ?>"></textarea>
                        <button type="submit" class="btn-download" style="width: 100%; justify-content: center; background: var(--dark); color: #fff; border: none; cursor: pointer; font-weight: 700; height: 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                            <?php echo __('details_btn_verify_complete'); ?>
                        </button>
                    </form>
                </div>

            <?php elseif ($complaint['status'] === 'Completed'): ?>
                <div style="text-align: center; padding: 40px 20px; background: var(--light); border-radius: 15px; border: 1px solid var(--grey);">
                    <div style="width: 80px; height: 80px; background: var(--grey); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto;">
                        <i class='bx bx-check' style="font-size: 50px; color: var(--dark);"></i>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 700; color: var(--dark); margin-bottom: 5px;"><?php echo __('status_closed'); ?></h3>
                    <p style="color: var(--dark-grey); font-size: 13px;"><?php echo __('status_closed_subtitle'); ?></p>
                </div>

            <?php else: ?>
                 <!-- Assignment Form -->
                 <form action="../controllers/complaint_action.php" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
                    <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                    <input type="hidden" name="redirect" value="../pr/new_complaint.php">
                    <input type="hidden" name="status" value="Received">

                     <div>
                        <label style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--dark-grey); margin-bottom: 8px; display: block;"><?php echo __('details_assign_dept_label'); ?></label>
                        <div style="position: relative;">
                            <i class='bx bxs-briefcase-alt-2' style="position: absolute; top: 50%; transform: translateY(-50%); left: 12px; color: var(--blue);"></i>
                            <select name="assigned_dept_id" required style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 10px; border: 1px solid var(--grey); background: var(--light); color: var(--dark); font-size: 14px; outline: none; cursor: pointer; -webkit-appearance: none;">
                                <option value=""><?php echo __('details_assign_dept_placeholder'); ?></option>
                                 <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $complaint['assigned_dept_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class='bx bx-chevron-down' style="position: absolute; top: 50%; transform: translateY(-50%); right: 12px; color: var(--dark-grey); pointer-events: none;"></i>
                        </div>
                    </div>


                    <button type="submit" style="width: 100%; padding: 14px; border-radius: 30px; border: none; background: var(--blue); color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.3s; box-shadow: 0 4px 10px rgba(60, 145, 230, 0.3);">
                        <i class='bx bx-send'></i> <?php echo __('details_btn_send'); ?>
                    </button>
                    
                    <p style="font-size: 11px; text-align: center; color: var(--dark-grey);">
                        <i class='bx bx-info-circle'></i> <?php echo __('details_assign_info'); ?>
                    </p>
                </form>
            <?php endif; ?>
        </div>


    </div>
</div>

<?php require_once '../includes/footer.php'; ?>