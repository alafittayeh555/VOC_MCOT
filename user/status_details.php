<?php
// user/status_details.php
$hide_header = false;
require_once '../includes/header_landing.php';
require_once '../config/database.php';

// Check User Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$complaint_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$db = Database::connect();

// Fetch Complaint Details (SECURE: Ensure user_id matches)
$sql = "SELECT c.*, u.full_name as complainer, u.email, u.phone, u.occupation 
        FROM complaints c 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE c.id = ? AND c.user_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$complaint_id, $user_id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    echo "<div class='text-center py-10'>" . __('details_not_found', 'Complaint not found or access denied.') . "</div>";
    exit; // No footer needed if just basic error
}

// Fetch Attachments
$stmtAtt = $db->prepare("SELECT * FROM attachments WHERE complaint_id = ?");
$stmtAtt->execute([$complaint_id]);
$all_attachments = $stmtAtt->fetchAll();
$attachments = $all_attachments; // User sees all attachments they uploaded

// Fetch History (Timeline)
$stmtHist = $db->prepare("SELECT h.*, u.full_name, r.role_name 
                          FROM complaint_history h 
                          JOIN users u ON h.action_by_user_id = u.id 
                          JOIN roles r ON u.role_id = r.id 
                          WHERE h.complaint_id = ? ORDER BY h.timestamp DESC");
$stmtHist->execute([$complaint_id]);
$history = $stmtHist->fetchAll();
?>

<!-- Styles from Admin/PR Theme (Inline for isolation) -->
<style>
    :root {
        --poppins: 'Prompt', sans-serif;
        --light: #F9F9F9;
        --blue: #6C5CE7;
        --light-blue: #E0D9FC;
        --grey: #eee;
        --dark-grey: #AAAAAA;
        --dark: #342E37;
        --red: #DB504A;
        --yellow: #FFCE26;
        --light-yellow: #FFF2C6;
        --orange: #FD7238;
        --light-orange: #FFE0D3;
        --green: #2ECC71;
        --light-green: #D5F5E3;
    }

    body {
        font-family: var(--poppins);
        background: #f3f4f6;
    }

    a { text-decoration: none; }
    
    .history-container {
        padding: 36px 24px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .head-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }
    .head-title .left h1 {
        font-size: 36px;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--dark);
    }
    .head-title .left .breadcrumb {
        display: flex;
        align-items: center;
        grid-gap: 16px;
        list-style: none;
        padding: 0;
    }
    .head-title .left .breadcrumb li { color: var(--dark); }
    .head-title .left .breadcrumb li a { color: var(--dark-grey); pointer-events: none; }
    .head-title .left .breadcrumb li a.active { color: var(--blue); pointer-events: unset; }

    /* Buttons */
    .btn-download {
        height: 36px;
        padding: 0 16px;
        border-radius: 36px;
        background: var(--light-blue); /* Default for back button logic */
        color: var(--blue);
        display: flex;
        justify-content: center;
        align-items: center;
        grid-gap: 10px;
        font-weight: 500;
        border: none;
        cursor: pointer;
    }

    /* Layout Columns */
    .table-data {
        display: flex;
        flex-wrap: wrap;
        grid-gap: 24px;
        margin-top: 24px;
        width: 100%;
    }
    
    .order {
        flex-grow: 1;
        flex-basis: 500px;
        background: #fff;
        padding: 24px;
        border-radius: 20px;
    }
    
    .todo {
        flex-grow: 1;
        flex-basis: 300px;
    }

    /* Status Pills */
    .status {
        font-size: 11px;
        padding: 6px 12px;
        color: #fff;
        border-radius: 30px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status.completed, .status.resolved { background: #2196F3; }
    .status.processed, .status.inprogress { background: #FFC107; color: #342E37; }
    .status.pending { background: #AB47BC; }
    .status.rejected { background: #DB504A; }

    /* Timeline */
    .todo .todo-list {
        background: #fff;
        border-radius: 20px;
        padding: 24px;
    }
    .todo .head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }
    .todo .head h3 { font-size: 24px; font-weight: 600; color: var(--dark); }

    .timeline-item {
        position: relative;
        padding-left: 30px;
        margin-bottom: 20px;
        border-left: 2px solid var(--grey);
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--blue);
    }
    .timeline-date { font-size: 12px; color: var(--dark-grey); margin-bottom: 5px; }
    .timeline-content { font-size: 14px; color: var(--dark); }
    .timeline-author { font-size: 12px; font-weight: 600; color: var(--dark); margin-top: 5px; }

</style>

<div class="history-container">
    <div class="head-title">
        <div class="left">
            <h1><?php echo __('table_link_details'); ?></h1>
        </div>
        <a href="status.php" class="btn-download">
            <i class='bx bx-arrow-back'></i>
            <span class="text"><?php echo __('btn_back', 'Back'); ?></span>
        </a>
    </div>

    <div class="table-data">
        <!-- Main Details (Full Width) -->
        <div class="order" style="width: 100%;">
            <div class="head" style="margin-bottom: 30px; display: flex; align-items: flex-start; justify-content: space-between;">
                <div style="flex: 1;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--dark); margin-bottom: 10px; line-height: 1.3;">
                         <span style="color: var(--dark); font-size: 1.5rem; margin-right: 5px; font-weight: 700;">เรื่อง :</span>
                         <?php echo htmlspecialchars($complaint['subject']); ?>
                    </h2>
                    
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; font-size: 13px; color: var(--dark-grey); background: var(--light); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--grey);">
                        <span style="display: flex; align-items: center; gap: 5px;">
                            <i class='bx bxs-category'></i> 
                            <span style="font-weight: 600;"><?php echo __('nc_th_type', 'Type'); ?>: </span>
                            <?php echo ucfirst($complaint['complaint_type']); ?>
                        </span>
                        <span style="color: #cbd5e0;">|</span>
                        <span style="display: flex; align-items: center; gap: 5px;">
                            <i class='bx bxs-business'></i> 
                            <span style="font-weight: 600;"><?php echo __('nc_th_agency', 'Agency'); ?>: </span>
                            <?php 
                                $parts = explode(' - ', $complaint['program'] ?? '-');
                                echo htmlspecialchars($parts[0]); 
                            ?>
                        </span>
                    </div>
                </div>
                
            </div>

            <!-- Description -->
            <div style="background: var(--light); border: 1px solid var(--grey); border-radius: 12px; padding: 25px; margin-bottom: 30px;">
                <h4 style="font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--dark);">
                    <i class='bx bx-align-left' style="color: var(--blue);"></i> <?php echo __('submit_desc_label'); ?>
                </h4>
                <div class="whitespace-pre-wrap" style="color: var(--dark); font-size: 15px; line-height: 1.7;">
                    <?php 
                    // Remove Caller Information block from display if present
                    $clean_description = preg_replace('/\[Caller Information\].*?----------------------------------------\s*/s', '', $complaint['description']);
                    echo htmlspecialchars(trim($clean_description)); 
                    ?>
                </div>
            </div>

             <!-- Attachments -->
             <?php if (count($attachments) > 0): ?>
                <div style="margin-top: 30px;">
                    <h4 style="font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--dark);">
                        <i class='bx bx-paperclip' style="color: var(--blue);"></i> <?php echo __('details_attachments', 'Attachments'); ?> (<?php echo count($attachments); ?>)
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
                               style="display: block; background: var(--light); border: 1px solid var(--grey); border-radius: 10px; overflow: hidden; text-decoration: none;">
                                
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
                                        <?php echo strtoupper($fileExt); ?>
                                    </p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>


    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
