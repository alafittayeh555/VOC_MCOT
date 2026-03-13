<?php
// admin/settings_index.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();

// 1. Auto-Installation: Check if table exists
try {
    $db->query("SELECT 1 FROM images LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it
    $sql = "CREATE TABLE IF NOT EXISTS images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        subtitle VARCHAR(255) DEFAULT NULL,
        link VARCHAR(255) DEFAULT '#',
        image_path VARCHAR(255) DEFAULT NULL,
        image_path_en VARCHAR(255) DEFAULT NULL,
        type ENUM('banner', 'logo', 'profile', 'process2', 'other') DEFAULT 'banner',
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->exec($sql);
    
    // Seed one example image if empty
    $db->exec("INSERT INTO images (title, subtitle, link, is_active, type) VALUES ('Welcome to VOC System', 'We are here to help', '#complaint', 1, 'banner')");
}

// 1.0.1 Auto-Installation: Check if image_path_en column exists
try {
    $db->query("SELECT image_path_en FROM images LIMIT 1");
} catch (PDOException $e) {
    try {
        $db->exec("ALTER TABLE images ADD COLUMN image_path_en VARCHAR(255) DEFAULT NULL AFTER image_path");
    } catch (PDOException $e2) { }
}

// 1.1 Auto-Installation: Check if page_settings table exists
try {
    $db->query("SELECT 1 FROM page_settings LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it
    $sql = "CREATE TABLE IF NOT EXISTS page_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->exec($sql);
    
    // Seed default values for About System & Contact
    $defaults = [
        'index_about_title' => 'เกี่ยวกับระบบ',
        'index_about_desc' => 'ระบบรับเรื่องร้องเรียนและข้อเสนอแนะ เป็นช่องทางสำหรับประชาชนในการแจ้งเรื่องร้องเรียน ข้อเสนอแนะ หรือความคิดเห็นต่างๆ เกี่ยวกับการดำเนินงานของ MCOT เพื่อให้เราสามารถนำไปปรับปรุงและพัฒนาการให้บริการได้อย่างมีประสิทธิภาพยิ่งขึ้น',
        'index_feature_1' => 'ส่งเรื่องร้องเรียนได้ง่าย รวดเร็ว',
        'index_feature_2' => 'ติดตามสถานะได้แบบ Real-time',
        'index_feature_3' => 'รับการตอบกลับอย่างรวดเร็ว',
        'index_feature_4' => 'ข้อมูลความปลอดภัยสูง',
        
        // Contact Settings (Footer)
        'footer_col1_link_1_text' => 'หน้าแรก', 'footer_col1_link_1_url' => '#',
        'footer_col1_link_2_text' => 'สำนักข่าวไทย', 'footer_col1_link_2_url' => '#',
        'footer_col1_link_3_text' => 'NineEntertain', 'footer_col1_link_3_url' => '#',
        'footer_col1_link_4_text' => '9MCOT HD', 'footer_col1_link_4_url' => '#',
        'footer_col1_link_5_text' => 'BACKBONE', 'footer_col1_link_5_url' => '#',
        
        'footer_col2_link_1_text' => 'Mellow pop', 'footer_col2_link_1_url' => '#',
        'footer_col2_link_2_text' => 'ลูกทุ่งมหานคร', 'footer_col2_link_2_url' => '#',
        'footer_col2_link_3_text' => 'Think', 'footer_col2_link_3_url' => '#',
        'footer_col2_link_4_text' => 'Active99', 'footer_col2_link_4_url' => '#',
        'footer_col2_link_5_text' => '', 'footer_col2_link_5_url' => '',
        
        'footer_col3_link_1_text' => 'FM 105.5 Smooth', 'footer_col3_link_1_url' => '#',
        'footer_col3_link_2_text' => 'MET107', 'footer_col3_link_2_url' => '#',
        'footer_col3_link_3_text' => 'ชมสด', 'footer_col3_link_3_url' => '#',
        'footer_col3_link_4_text' => '', 'footer_col3_link_4_url' => '',
        'footer_col3_link_5_text' => '', 'footer_col3_link_5_url' => '',
        
        'footer_company_name' => 'บริษัท อสมท จำกัด (มหาชน)',
        'footer_address' => '63/1 ถ.พระราม 9 ห้วยขวาง กทม. 10310',
        'footer_contact_email_1' => 'contact@mcot.net',
        'footer_contact_ads' => '02-201-6155',
        'footer_contact_email_2' => 'contact@mcot.net',
        'footer_contact_tel' => '02-201-6155',
        
        'social_facebook' => '#',
        'social_tiktok' => '#',
        'social_instagram' => '#'
    ];
    
    $stmt = $db->prepare("INSERT INTO page_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=setting_value");
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
    }
}

// Keep existing defaults synced during page load if they somehow got missed
$sync_stmt = $db->prepare("INSERT IGNORE INTO page_settings (setting_key, setting_value) VALUES (?, ?)");
$defaults_sync = [
    'footer_col1_link_1_text' => 'หน้าแรก', 'footer_col1_link_1_url' => '#',
    'footer_col1_link_2_text' => 'สำนักข่าวไทย', 'footer_col1_link_2_url' => '#',
    'footer_col1_link_3_text' => 'NineEntertain', 'footer_col1_link_3_url' => '#',
    'footer_col1_link_4_text' => '9MCOT HD', 'footer_col1_link_4_url' => '#',
    'footer_col1_link_5_text' => 'BACKBONE', 'footer_col1_link_5_url' => '#',
    'footer_col2_link_1_text' => 'Mellow pop', 'footer_col2_link_1_url' => '#',
    'footer_col2_link_2_text' => 'ลูกทุ่งมหานคร', 'footer_col2_link_2_url' => '#',
    'footer_col2_link_3_text' => 'Think', 'footer_col2_link_3_url' => '#',
    'footer_col2_link_4_text' => 'Active99', 'footer_col2_link_4_url' => '#',
    'footer_col2_link_5_text' => '', 'footer_col2_link_5_url' => '',
    'footer_col3_link_1_text' => 'FM 105.5 Smooth', 'footer_col3_link_1_url' => '#',
    'footer_col3_link_2_text' => 'MET107', 'footer_col3_link_2_url' => '#',
    'footer_col3_link_3_text' => 'ชมสด', 'footer_col3_link_3_url' => '#',
    'footer_col3_link_4_text' => '', 'footer_col3_link_4_url' => '',
    'footer_col3_link_5_text' => '', 'footer_col3_link_5_url' => '',
    'footer_company_name' => 'บริษัท อสมท จำกัด (มหาชน)',
    'footer_address' => '63/1 ถ.พระราม 9 ห้วยขวาง กทม. 10310',
    'footer_contact_email_1' => 'contact@mcot.net',
    'footer_contact_ads' => '02-201-6155',
    'footer_contact_email_2' => 'contact@mcot.net',
    'footer_contact_tel' => '02-201-6155',
    'social_facebook' => '#',
    'social_tiktok' => '#',
    'social_instagram' => '#'
];
foreach($defaults_sync as $k => $v) {
    $sync_stmt->execute([$k, $v]);
}

// Handle Form Submission (Add/Edit)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            // Default values for fields we removed
            $title = 'Image';
            $subtitle = '';
            $link = '#complaint';
            $order = 0; // Or auto-calc max+1
            $active = 1;
            $type = $_POST['type'] ?? 'banner'; // Get type from form
            
            // Image Upload
            $imagePath = null;
            $imagePathEn = null;
            
            // Determine folder based on type
            $folder = 'banner';
            if ($type === 'logo') $folder = 'logo';
            if ($type === 'profile') $folder = 'profile';
            if ($type === 'process2') $folder = 'process2';
            
            $uploadDir = "../assets/img/$folder/";
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    $message = "Failed to create upload directory.";
                }
            }
            
            if (empty($message)) {
                // Upload TH (main)
                if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                    $fileName = time() . '_th_' . basename($_FILES['banner_image']['name']);
                    $targetFile = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $targetFile)) {
                        $imagePath = "assets/img/$folder/" . $fileName;
                    } else {
                        $message = "Failed to move uploaded TH file. Check permissions.";
                    }
                } else {
                    $message = "Upload error TH code: " . ($_FILES['banner_image']['error'] ?? 'Unknown');
                }
                
                // Upload EN (required)
                if (empty($message)) {
                    if (isset($_FILES['banner_image_en']) && $_FILES['banner_image_en']['error'] === UPLOAD_ERR_OK) {
                        $fileNameEn = time() . '_en_' . basename($_FILES['banner_image_en']['name']);
                        $targetFileEn = $uploadDir . $fileNameEn;
                        if (move_uploaded_file($_FILES['banner_image_en']['tmp_name'], $targetFileEn)) {
                            $imagePathEn = "assets/img/$folder/" . $fileNameEn;
                        } else {
                            $message = "Failed to move uploaded EN file. Check permissions.";
                        }
                    } else {
                        $message = "Upload error EN code: " . ($_FILES['banner_image_en']['error'] ?? 'Unknown');
                    }
                }
            }
            
            if ($imagePath && empty($message)) {
                 $stmt = $db->prepare("INSERT INTO images (title, subtitle, link, image_path, image_path_en, type, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                 if ($stmt->execute([$title, $subtitle, $link, $imagePath, $imagePathEn, $type, $order, $active])) {
                     $message = "Image added successfully.";
                 } else {
                     $message = "Database error adding image.";
                 }
            } else {
                if (empty($message)) $message = "Unknown upload error.";
            }

        } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            
            // 1. Fetch image path
            $stmt = $db->prepare("SELECT image_path, image_path_en FROM images WHERE id = ?");
            $stmt->execute([$id]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 2. Delete file if exists
            if ($image) {
                if (!empty($image['image_path'])) {
                    $filePath = '../' . $image['image_path'];
                    if (file_exists($filePath)) unlink($filePath);
                }
                if (!empty($image['image_path_en'])) {
                    $filePathEn = '../' . $image['image_path_en'];
                    if (file_exists($filePathEn)) unlink($filePathEn);
                }
            }

            // 3. Delete record
            $stmt = $db->prepare("DELETE FROM images WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = "Image deleted.";
            }
        } elseif ($_POST['action'] === 'reorder' && isset($_POST['order'])) {
            $order = $_POST['order']; // Array of IDs
            if (is_array($order)) {
                $stmt = $db->prepare("UPDATE images SET display_order = ? WHERE id = ?");
                foreach ($order as $index => $id) {
                    $stmt->execute([$index + 1, (int)$id]);
                }
                echo json_encode(['status' => 'success']);
                exit; // Stop further execution for AJAX
            }
        } elseif ($_POST['action'] === 'save_process2') {
            $keys = ['process2_bg_color', 'process2_title_color'];
            $stmt = $db->prepare("INSERT INTO page_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $success = true;
            foreach ($keys as $k) {
                if (isset($_POST[$k])) {
                    if (!$stmt->execute([$k, trim($_POST[$k])])) $success = false;
                }
            }
            $message = $success ? "Process 2 settings saved successfully." : "Error saving some settings.";

        } elseif ($_POST['action'] === 'save_procedure') {
            $keys = [
                'procedure_bg_color',
                'procedure_card_bg_color',
                'procedure_title_color',
                'procedure_text_color',
                'procedure_icon_color',
                'procedure_btn_color',
                'procedure_btn_text_color',
            ];
            $stmt = $db->prepare("INSERT INTO page_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $success = true;
            foreach ($keys as $k) {
                if (isset($_POST[$k])) {
                    if (!$stmt->execute([$k, trim($_POST[$k])])) $success = false;
                }
            }
            $message = $success ? "Procedure settings saved successfully." : "Error saving some settings.";

        } elseif ($_POST['action'] === 'save_about_system') {
            // Check if user is saving the About System strings
            $keys = [
                'index_about_title',
                'index_about_title_en',
                'index_about_desc',
                'index_about_desc_en',
                'index_feature_1',
                'index_feature_1_en',
                'index_feature_2',
                'index_feature_2_en',
                'index_feature_3',
                'index_feature_3_en',
                'index_feature_4',
                'index_feature_4_en',
                'about_bg_color',
                'about_text_color'
            ];
            
            $stmt = $db->prepare("INSERT INTO page_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            $success = true;
            foreach ($keys as $k) {
                if (isset($_POST[$k])) {
                    $val = trim($_POST[$k]);
                    if (!$stmt->execute([$k, $val])) {
                        $success = false;
                    }
                }
            }
            
            if ($success) {
                $message = "About System settings saved successfully.";
            } else {
                $message = "Error saving some settings.";
            }
        } elseif ($_POST['action'] === 'save_contact_system') {
            // Check if user is saving the Contact strings
            $keys = [
                'footer_col1_link_1_text', 'footer_col1_link_1_url',
                'footer_col1_link_2_text', 'footer_col1_link_2_url',
                'footer_col1_link_3_text', 'footer_col1_link_3_url',
                'footer_col1_link_4_text', 'footer_col1_link_4_url',
                'footer_col1_link_5_text', 'footer_col1_link_5_url',
                'footer_col2_link_1_text', 'footer_col2_link_1_url',
                'footer_col2_link_2_text', 'footer_col2_link_2_url',
                'footer_col2_link_3_text', 'footer_col2_link_3_url',
                'footer_col2_link_4_text', 'footer_col2_link_4_url',
                'footer_col2_link_5_text', 'footer_col2_link_5_url',
                'footer_col3_link_1_text', 'footer_col3_link_1_url',
                'footer_col3_link_2_text', 'footer_col3_link_2_url',
                'footer_col3_link_3_text', 'footer_col3_link_3_url',
                'footer_col3_link_4_text', 'footer_col3_link_4_url',
                'footer_col3_link_5_text', 'footer_col3_link_5_url',
                'footer_company_name',
                'footer_address',
                'footer_contact_email_1',
                'footer_contact_ads',
                'footer_contact_email_2',
                'footer_contact_tel',
                'social_facebook',
                'social_tiktok',
                'social_instagram',
                'footer_bg_color',
                'footer_text_color',
                'footer_link_color'
            ];
            
            $stmt = $db->prepare("INSERT INTO page_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            $success = true;
            foreach ($keys as $k) {
                if (isset($_POST[$k])) {
                    $val = trim($_POST[$k]);
                    if (!$stmt->execute([$k, $val])) {
                        $success = false;
                    }
                }
            }
            
            if ($success) {
                $message = "Contact settings saved successfully.";
            } else {
                $message = "Error saving contact settings.";
            }
        }
    }
}

// Fetch all page settings into an array
$settings_query = $db->query("SELECT setting_key, setting_value FROM page_settings");
$page_settings = [];
while ($row = $settings_query->fetch(PDO::FETCH_ASSOC)) {
    $page_settings[$row['setting_key']] = $row['setting_value'];
}

// Fetch Banners
$stmt_banners = $db->query("SELECT * FROM images WHERE type = 'banner' ORDER BY display_order ASC, created_at DESC");
$banners = $stmt_banners->fetchAll(PDO::FETCH_ASSOC);

// Fetch Process 2 Images
$stmt_process2 = $db->query("SELECT * FROM images WHERE type = 'process2' ORDER BY display_order ASC, created_at DESC");
$process2_images = $stmt_process2->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="head-title">
    <div class="left">
        <h1><?php echo __('hp_edit_title'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="#"><?php echo __('hp_settings'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('hp_edit_title'); ?></a></li>
        </ul>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-info" style="padding: 15px; background: #e0f2f1; color: #00695c; border-radius: 5px; margin-bottom: 20px;">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<style>
    /* ===== Dark Mode ===== */
    body.dark .table-data > div[style] {
        background: var(--light) !important;
        box-shadow: none !important;
    }
    body.dark .head h3,
    body.dark h4[style],
    body.dark label[style],
    body.dark span[style*="color: #28a745"],
    body.dark span[style*="color: #333"] {
        color: var(--dark) !important;
    }
    body.dark input[type="text"],
    body.dark textarea {
        background: var(--light) !important;
        color: var(--dark) !important;
        border-color: var(--grey) !important;
    }
    body.dark .banner-card {
        background: var(--light) !important;
        border-color: var(--grey) !important;
    }
    body.dark .banner-actions {
        background: var(--light) !important;
    }
    body.dark .banner-card.ignore-sort {
        background: var(--grey) !important;
        border-color: #444 !important;
    }
    body.dark .custom-modal-content {
        background: var(--light) !important;
    }
    body.dark .custom-modal-content h3[style],
    body.dark .custom-modal-content label[style] {
        color: var(--dark) !important;
        border-color: #333 !important;
    }
    body.dark .custom-modal-content p[style] {
        color: #aaa !important;
    }
    body.dark .file-input-wrapper {
        background: var(--light) !important;
        border-color: var(--grey) !important;
    }
    body.dark .file-btn {
        background: var(--grey) !important;
        color: var(--dark) !important;
        border-color: #555 !important;
    }
    body.dark .file-label-text {
        color: #aaa !important;
    }
    body.dark button[style*="background: #fff"] {
        background: var(--light) !important;
        border-color: var(--grey) !important;
        color: var(--dark) !important;
    }
    body.dark button[style*="background: #f4f5f7"] {
        background: var(--grey) !important;
        color: var(--dark) !important;
    }
    /* Color picker section cards */
    body.dark div[style*="background: #fff"],
    body.dark div[style*="background:#fff"] {
        background: var(--light) !important;
    }
    body.dark div[style*="background: #f8fafc"],
    body.dark div[style*="background:#f8fafc"] {
        background: #1a1f2e !important;
        border-color: #2d3748 !important;
    }
    body.dark div[style*="color: #1f2937"],
    body.dark h3[style*="color: #1f2937"] {
        color: var(--dark) !important;
    }
    body.dark div[style*="color: #64748b"] {
        color: #94a3b8 !important;
    }
    body.dark label[style*="color: #334155"] {
        color: #cbd5e1 !important;
    }
    body.dark div[style*="border: 1px solid #e2e8f0"],
    body.dark div[style*="border: 1.5px solid #e2e8f0"] {
        border-color: #2d3748 !important;
        background: #1e2534 !important;
    }
    body.dark input[type="color"] {
        background: var(--grey) !important;
    }
    body.dark p[style*="color: #94a3b8"] {
        color: #64748b !important;
    }
    body.dark span[style*="background: #eef2ff"] {
        background: #1e2347 !important;
        color: #818cf8 !important;
    }
    body.dark div[style*="background: #e0e7ff"] {
        background: #1e2347 !important;
        color: #818cf8 !important;
    }
    body.dark h4[style*="color:#"],
    body.dark div[style*="color: #1f2937"] {
        color: var(--dark) !important;
    }
    body.dark input[type="text"][style*="border: none"] {
        background: transparent !important;
        color: var(--dark) !important;
        border-color: transparent !important;
    }
    body.dark span[id="saveStatus"] {
        color: #34d399 !important;
    }
    body.dark div[style*="border: 1px solid #f0f0f0"],
    body.dark div[style*="border: 1px solid #edf2f7"] {
        border-color: #2d3748 !important;
        background: var(--light) !important;
    }


    /* Tabs */
    .type-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .type-tab {
        padding: 10px 20px;
        background: #fff;
        border-radius: 8px;
        text-decoration: none;
        color: #555;
        font-weight: 500;
        transition: all 0.2s;
        border: 1px solid #eee;
    }
    .type-tab.active {
        background: var(--blue);
        color: #fff;
        border-color: var(--blue);
    }
    .type-tab:hover {
        background: #f8f9fa;
        color: var(--blue);
    }
    .type-tab.active:hover {
        background: var(--blue);
        color: #fff;
    }

    /* Custom Styles for Settings Index */
    .image-grid {
        display: flex;
        overflow-x: auto;
        gap: 20px;
        margin-top: 20px;
        padding-bottom: 15px; /* Space for scrollbar */
        /* hide scrollbar for cleaner look if desired, but keep it functional */
    }
    .image-grid::-webkit-scrollbar {
        height: 6px;
    }
    .image-grid::-webkit-scrollbar-track {
        background: #f1f1f1; 
        border-radius: 4px;
    }
    .image-grid::-webkit-scrollbar-thumb {
        background: #ccc; 
        border-radius: 4px;
    }
    .image-grid::-webkit-scrollbar-thumb:hover {
        background: #aaa; 
    }

    .banner-card {
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        transition: transform 0.2s, box-shadow 0.2s;
        position: relative;
        flex: 0 0 320px; /* Fixed width, won't shrink or grow */
        max-width: 320px;
    }

    .banner-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .banner-img {
        width: 100%;
        height: 150px;
        object-fit: cover;
        background: #f0f0f0;
    }

    .banner-actions {
        padding: 15px;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        background: #fff;
    }

    .banner-status {
        font-size: 12px;
        color: #666;
        background: #f5f5f5;
        padding: 4px 8px;
        border-radius: 4px;
    }

    .btn-delete {
        background: #ffebee;
        color: #c62828;
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-delete:hover {
        background: #ffcdd2;
    }

    /* Upload Area */
    .upload-area {
        border: 2px dashed #cfd8dc;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s;
        position: relative;
    }

    .upload-area:hover {
        border-color: var(--blue);
        background: #f1f8ff;
    }

    .upload-area input[type="file"] {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        opacity: 0;
        cursor: pointer;
    }

    .upload-icon {
        font-size: 48px;
        color: var(--blue);
        margin-bottom: 15px;
    }
    
    .upload-text {
        font-size: 16px;
        font-weight: 500;
        color: var(--dark);
        margin-bottom: 5px;
    }
    
    .upload-hint {
        font-size: 13px;
        color: #888;
    }

    /* Modal Styles */
    .custom-modal {
        display: none;
        position: fixed;
        z-index: 9999 !important;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.6) !important; /* Forces background to be dark */
        backdrop-filter: blur(4px); /* Adds a nice blur effect */
        align-items: center;
        justify-content: center;
    }
    .custom-modal-content {
        background-color: #fff;
        margin: auto;
        padding: 30px;
        border: none;
        width: 90%;
        max-width: 550px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        position: relative;
    }
    .custom-modal-close {
        color: #b0b0b0;
        position: absolute;
        top: 25px;
        right: 25px;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        transition: color 0.2s;
        line-height: 1;
    }
    .custom-modal-close:hover {
        color: #333;
    }
    .custom-modal.active {
        display: flex;
    }
    
    /* Beautiful File Input */
    .file-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 0;
        background: #fff;
        transition: border-color 0.2s;
        overflow: hidden;
    }
    .file-input-wrapper:hover {
        border-color: #b0b0b0;
    }
    .file-btn {
        background: #f5f5f5;
        border: none;
        border-right: 1px solid #e0e0e0;
        color: #333;
        padding: 12px 20px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        margin-right: 0;
        transition: background 0.2s;
        height: 100%;
    }
    .file-btn:hover {
        background: #ebebeb;
    }
    .file-label-text {
        font-size: 15px;
        color: #666;
        flex: 1;
        padding: 0 16px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .file-input-hidden {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
</style>

<div class="table-data" style="flex-direction: column; background: transparent; box-shadow: none; padding: 0;">
    
    <!-- Banner Section -->
    <div style="background: #fff; padding: 24px; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 24px; width: 100%;">
        <div class="head" style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 4px; height: 24px; background: var(--blue); border-radius: 4px;"></div>
                <h3 style="font-size: 20px; color: var(--dark); font-weight: 600;"><?php echo __('hp_banners'); ?></h3>
            </div>
            <button type="button" onclick="openBannerModal()" style="width: 36px; height: 36px; border-radius: 50%; background: #fff; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--dark); transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.05);" onmouseover="this.style.background='#f8f9fa'; this.style.borderColor='#ccc';" onmouseout="this.style.background='#fff'; this.style.borderColor='#ddd';" title="<?php echo htmlspecialchars(__('hp_upload_new_banner')); ?>">
                <i class='bx bx-plus' style="font-size: 22px;"></i>
            </button>
        </div>
        
        <div class="image-grid" id="bannerGrid" style="margin-bottom: 10px;">
            <?php if (!empty($banners)): ?>
                <?php foreach ($banners as $image): ?>
                <div class="banner-card" data-id="<?php echo $image['id']; ?>" style="cursor: move;">
                    <div style="position: relative;">
                        <?php if ($image['image_path']): ?>
                            <img src="../<?php echo htmlspecialchars($image['image_path']); ?>" alt="Banner TH" class="banner-img" style="height: 120px;">
                            <span style="position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.5); color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: bold;">TH</span>
                        <?php else: ?>
                            <div class="banner-img" style="height: 120px; display: flex; align-items: center; justify-content: center; color: #999;">No Image</div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($image['image_path_en'])): ?>
                    <div style="position: relative; border-top: 1px solid #eee;">
                        <img src="../<?php echo htmlspecialchars($image['image_path_en']); ?>" alt="Banner EN" class="banner-img" style="height: 120px;">
                        <span style="position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.5); color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: bold;">EN</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="banner-actions">
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this banner?');" style="margin: 0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $image['id']; ?>">
                            <button type="submit" class="btn-delete" title="Delete">
                                <i class='bx bxs-trash'></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

    <!-- Banner Upload Modal -->
    <div id="bannerUploadModal" class="custom-modal">
        <div class="custom-modal-content">
            <span class="custom-modal-close" onclick="closeBannerModal()"><i class='bx bx-x'></i></span>
            <h3 style="margin-bottom: 25px; font-size: 22px; color: #2a2a2a; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; font-weight: 600;"><?php echo __('hp_upload_new_banner'); ?></h3>
            <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 20px;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="type" value="banner">
                <p style="font-size: 13px; color: #888; margin-top: -15px;"><?php echo __('hp_recommended_size_banner'); ?></p>
                
                <div>
                    <label style="font-size: 15px; font-weight: 700; color: #333; display: block; margin-bottom: 8px;"><?php echo __('hp_thai_banner_req'); ?> <span style="color: #6a5acd;">*</span></label>
                    <div class="file-input-wrapper">
                        <button type="button" class="file-btn"><?php echo __('hp_choose_file'); ?></button>
                        <span class="file-label-text" id="thFileName"><?php echo __('hp_no_file_chosen'); ?></span>
                        <input type="file" name="banner_image" accept="image/*" required class="file-input-hidden" onchange="document.getElementById('thFileName').textContent = this.files[0] ? this.files[0].name : '<?php echo addslashes(__('hp_no_file_chosen')); ?>'">
                    </div>
                </div>
                
                <div>
                    <label style="font-size: 15px; font-weight: 700; color: #333; display: block; margin-bottom: 8px;"><?php echo __('hp_eng_banner_req'); ?> <span style="color: #6a5acd;">*</span></label>
                    <div class="file-input-wrapper">
                        <button type="button" class="file-btn"><?php echo __('hp_choose_file'); ?></button>
                        <span class="file-label-text" id="enFileName"><?php echo __('hp_no_file_chosen'); ?></span>
                        <input type="file" name="banner_image_en" accept="image/*" required class="file-input-hidden" onchange="document.getElementById('enFileName').textContent = this.files[0] ? this.files[0].name : '<?php echo addslashes(__('hp_no_file_chosen')); ?>'">
                    </div>
                    <p style="font-size: 13px; color: #888; margin-top: 8px;"><?php echo __('hp_banner_eng_hint'); ?></p>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 15px;">
                    <button type="button" onclick="closeBannerModal()" style="background: #f4f5f7; color: #333; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; transition: background 0.2s;"><?php echo __('hp_cancel'); ?></button>
                    <button type="submit" style="background: #6a5acd; color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s;" onmouseover="this.style.background='#5a4db8';" onmouseout="this.style.background='#6a5acd';">
                        <i class='bx bx-upload'></i> <?php echo __('hp_upload_banner'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Process 2 Section -->
    <div style="background: #fff; padding: 24px; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); width: 100%;">
        <div class="head" style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h3 style="font-size: 20px; color: var(--dark); font-weight: 600;"><?php echo __('hp_process2'); ?></h3>
            </div>
            <button type="button" onclick="openProcess2Modal()" style="width: 36px; height: 36px; border-radius: 50%; background: #fff; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--dark); transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.05);" onmouseover="this.style.background='#f8f9fa'; this.style.borderColor='#ccc';" onmouseout="this.style.background='#fff'; this.style.borderColor='#ddd';" title="<?php echo htmlspecialchars(__('hp_upload_new_process2')); ?>">
                <i class='bx bx-plus' style="font-size: 22px;"></i>
            </button>
        </div>

        <div class="image-grid" id="process2Grid" style="margin-bottom: 10px;">
            <?php if (!empty($process2_images)): ?>
                <?php foreach ($process2_images as $image): ?>
                <div class="banner-card" data-id="<?php echo $image['id']; ?>" style="cursor: move;">
                    <?php if ($image['image_path']): ?>
                        <img src="../<?php echo htmlspecialchars($image['image_path']); ?>" alt="Process 2" class="banner-img">
                    <?php else: ?>
                        <div class="banner-img" style="display: flex; align-items: center; justify-content: center; color: #999;">No Image</div>
                    <?php endif; ?>
                    
                    <div class="banner-actions">
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this image?');" style="margin: 0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $image['id']; ?>">
                            <button type="submit" class="btn-delete" title="Delete">
                                <i class='bx bxs-trash'></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

    <!-- Process 2 Upload Modal -->
    <div id="process2UploadModal" class="custom-modal">
        <div class="custom-modal-content">
            <span class="custom-modal-close" onclick="closeProcess2Modal()"><i class='bx bx-x'></i></span>
            <h3 style="margin-bottom: 25px; font-size: 22px; color: #2a2a2a; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; font-weight: 600;"><?php echo __('hp_upload_new_process2'); ?></h3>
            <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 20px;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="type" value="process2">
                <p style="font-size: 13px; color: #888; margin-top: -15px;"><?php echo __('hp_recommended_size_process2'); ?></p>
                
                <div>
                    <label style="font-size: 15px; font-weight: 700; color: #333; display: block; margin-bottom: 8px;"><?php echo __('hp_thai_image_req'); ?> <span style="color: #6a5acd;">*</span></label>
                    <div class="file-input-wrapper">
                        <button type="button" class="file-btn"><?php echo __('hp_choose_file'); ?></button>
                        <span class="file-label-text" id="thProcessFileName"><?php echo __('hp_no_file_chosen'); ?></span>
                        <input type="file" name="banner_image" accept="image/*" required class="file-input-hidden" onchange="document.getElementById('thProcessFileName').textContent = this.files[0] ? this.files[0].name : '<?php echo addslashes(__('hp_no_file_chosen')); ?>'">
                    </div>
                </div>
                
                <div>
                    <label style="font-size: 15px; font-weight: 700; color: #333; display: block; margin-bottom: 8px;"><?php echo __('hp_eng_image_req'); ?> <span style="color: #6a5acd;">*</span></label>
                    <div class="file-input-wrapper">
                        <button type="button" class="file-btn"><?php echo __('hp_choose_file'); ?></button>
                        <span class="file-label-text" id="enProcessFileName"><?php echo __('hp_no_file_chosen'); ?></span>
                        <input type="file" name="banner_image_en" accept="image/*" required class="file-input-hidden" onchange="document.getElementById('enProcessFileName').textContent = this.files[0] ? this.files[0].name : '<?php echo addslashes(__('hp_no_file_chosen')); ?>'">
                    </div>
                    <p style="font-size: 13px; color: #888; margin-top: 8px;"><?php echo __('hp_banner_eng_hint'); ?></p>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 15px;">
                    <button type="button" onclick="closeProcess2Modal()" style="background: #f4f5f7; color: #333; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; transition: background 0.2s;"><?php echo __('hp_cancel'); ?></button>
                    <button type="submit" style="background: #28a745; color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s;" onmouseover="this.style.background='#218838';" onmouseout="this.style.background='#28a745';">
                        <i class='bx bx-upload'></i> <?php echo __('hp_upload_image'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Process 2 Title Color Settings -->
    <div style="background: #fff; padding: 28px; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); width: 100%; margin-top: 24px; border: 1px solid #f0f0f0;">
        <div class="head" style="margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
            <div style="width: 4px; height: 26px; background: #1D2B4F; border-radius: 4px;"></div>
            <h3 style="font-size: 20px; color: #1f2937; font-weight: 700;"><?php echo __('hp_process2_colors', 'Process 2 Section Colors'); ?></h3>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save_process2">

            <div style="background: #f8fafc; padding: 20px; border-radius: 16px; margin-bottom: 24px; border: 1px dashed #e2e8f0;">
                <div style="font-size: 14px; font-weight: 700; color: #64748b; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.025em; display: flex; align-items: center; gap: 8px;">
                    <i class='bx bx-palette' style="font-size: 20px;"></i> <?php echo __('hp_section_design_colors'); ?>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

                    <!-- Section Background Color -->
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><i class='bx bx-paint-roll'></i> <?php echo __('hp_section_background'); ?></label>
                        <div style="display: flex; align-items: center; gap: 12px; background: #fff; padding: 8px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <input type="color" id="p2BgColor" name="process2_bg_color" value="<?php echo htmlspecialchars($page_settings['process2_bg_color'] ?? '#ffffff'); ?>" style="width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer;">
                            <input type="text" value="<?php echo htmlspecialchars($page_settings['process2_bg_color'] ?? '#ffffff'); ?>" oninput="document.getElementById('p2BgColor').value=this.value" style="flex: 1; border: none; font-family: monospace; font-size: 14px; outline: none;">
                        </div>
                    </div>

                    <!-- Title Color -->
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><i class='bx bx-heading'></i> <?php echo __('hp_title_color'); ?></label>
                        <div style="display: flex; align-items: center; gap: 12px; background: #fff; padding: 8px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <input type="color" id="p2TitleColor" name="process2_title_color" value="<?php echo htmlspecialchars($page_settings['process2_title_color'] ?? '#1D2B4F'); ?>" style="width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer;">
                            <input type="text" value="<?php echo htmlspecialchars($page_settings['process2_title_color'] ?? '#1D2B4F'); ?>" oninput="document.getElementById('p2TitleColor').value=this.value" style="flex: 1; border: none; font-family: monospace; font-size: 14px; outline: none;">
                        </div>
                    </div>

                </div>

                <script>
                    ['BgColor','TitleColor'].forEach(id => {
                        const picker = document.getElementById('p2' + id);
                        if (picker) {
                            const text = picker.parentElement.querySelector('input[type="text"]');
                            if (text) picker.addEventListener('input', () => text.value = picker.value);
                        }
                    });
                </script>

                <p style="font-size: 12px; color: #94a3b8; margin-top: 16px;">
                    <i class='bx bx-info-circle'></i> <?php echo __('hp_process2_colors_hint'); ?>
                </p>
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" style="background: #1D2B4F; color: #fff; border: none; padding: 14px 32px; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s;" onmouseover="this.style.background='#141e36';" onmouseout="this.style.background='#1D2B4F';">
                    <i class='bx bx-save'></i> <?php echo __('hp_save_process2_colors'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Procedure (Process 1) Color Settings -->
    <div style="background: #fff; padding: 28px; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); width: 100%; margin-top: 24px; border: 1px solid #f0f0f0;">
        <div class="head" style="margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
            <div style="width: 4px; height: 26px; background: #142A51; border-radius: 4px;"></div>
            <h3 style="font-size: 20px; color: #1f2937; font-weight: 700;"><?php echo __('hp_procedure_colors', 'Procedure Section Colors'); ?></h3>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save_procedure">

            <div style="background: #f8fafc; padding: 20px; border-radius: 16px; margin-bottom: 24px; border: 1px dashed #e2e8f0;">
                <div style="font-size: 14px; font-weight: 700; color: #64748b; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.025em; display: flex; align-items: center; gap: 8px;">
                    <i class='bx bx-palette' style="font-size: 20px;"></i> <?php echo __('hp_section_design_colors'); ?>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

                    <!-- Background Color -->
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><i class='bx bx-paint-roll'></i> <?php echo __('hp_section_background'); ?></label>
                        <div style="display: flex; align-items: center; gap: 12px; background: #fff; padding: 8px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <input type="color" id="procBgColor" name="procedure_bg_color" value="<?php echo htmlspecialchars($page_settings['procedure_bg_color'] ?? '#142A51'); ?>" style="width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer;">
                            <input type="text" value="<?php echo htmlspecialchars($page_settings['procedure_bg_color'] ?? '#142A51'); ?>" oninput="document.getElementById('procBgColor').value=this.value" style="flex: 1; border: none; font-family: monospace; font-size: 14px; outline: none;">
                        </div>
                    </div>

                    <!-- Card Background Color -->
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><i class='bx bx-card'></i> <?php echo __('hp_card_background'); ?></label>
                        <div style="display: flex; align-items: center; gap: 12px; background: #fff; padding: 8px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <input type="color" id="procCardBgColor" name="procedure_card_bg_color" value="<?php echo htmlspecialchars($page_settings['procedure_card_bg_color'] ?? '#ffffff'); ?>" style="width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer;">
                            <input type="text" value="<?php echo htmlspecialchars($page_settings['procedure_card_bg_color'] ?? '#ffffff'); ?>" oninput="document.getElementById('procCardBgColor').value=this.value" style="flex: 1; border: none; font-family: monospace; font-size: 14px; outline: none;">
                        </div>
                    </div>

                    <!-- Title Text Color -->
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><i class='bx bx-heading'></i> <?php echo __('hp_section_title_color'); ?></label>
                        <div style="display: flex; align-items: center; gap: 12px; background: #fff; padding: 8px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <input type="color" id="procTitleColor" name="procedure_title_color" value="<?php echo htmlspecialchars($page_settings['procedure_title_color'] ?? '#ffffff'); ?>" style="width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer;">
                            <input type="text" value="<?php echo htmlspecialchars($page_settings['procedure_title_color'] ?? '#ffffff'); ?>" oninput="document.getElementById('procTitleColor').value=this.value" style="flex: 1; border: none; font-family: monospace; font-size: 14px; outline: none;">
                        </div>
                    </div>

                    <!-- Card Text Color -->
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><i class='bx bx-font-color'></i> <?php echo __('hp_card_text_color'); ?></label>
                        <div style="display: flex; align-items: center; gap: 12px; background: #fff; padding: 8px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <input type="color" id="procTextColor" name="procedure_text_color" value="<?php echo htmlspecialchars($page_settings['procedure_text_color'] ?? '#1D2B4F'); ?>" style="width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer;">
                            <input type="text" value="<?php echo htmlspecialchars($page_settings['procedure_text_color'] ?? '#1D2B4F'); ?>" oninput="document.getElementById('procTextColor').value=this.value" style="flex: 1; border: none; font-family: monospace; font-size: 14px; outline: none;">
                        </div>
                    </div>

                    <!-- Icon / Circle Background Color -->
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><i class='bx bx-shape-circle'></i> <?php echo __('hp_icon_btn_color'); ?></label>
                        <div style="display: flex; align-items: center; gap: 12px; background: #fff; padding: 8px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <input type="color" id="procIconColor" name="procedure_icon_color" value="<?php echo htmlspecialchars($page_settings['procedure_icon_color'] ?? '#0056FF'); ?>" style="width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer;">
                            <input type="text" value="<?php echo htmlspecialchars($page_settings['procedure_icon_color'] ?? '#0056FF'); ?>" oninput="document.getElementById('procIconColor').value=this.value" style="flex: 1; border: none; font-family: monospace; font-size: 14px; outline: none;">
                        </div>
                    </div>

                    <!-- Button Text Color -->
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><i class='bx bx-text'></i> <?php echo __('hp_btn_text_color'); ?></label>
                        <div style="display: flex; align-items: center; gap: 12px; background: #fff; padding: 8px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <input type="color" id="procBtnTextColor" name="procedure_btn_text_color" value="<?php echo htmlspecialchars($page_settings['procedure_btn_text_color'] ?? '#0056FF'); ?>" style="width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer;">
                            <input type="text" value="<?php echo htmlspecialchars($page_settings['procedure_btn_text_color'] ?? '#0056FF'); ?>" oninput="document.getElementById('procBtnTextColor').value=this.value" style="flex: 1; border: none; font-family: monospace; font-size: 14px; outline: none;">
                        </div>
                    </div>

                </div>

                <script>
                    // Sync color pickers <-> text inputs for procedure
                    ['BgColor','CardBgColor','TitleColor','TextColor','IconColor','BtnTextColor'].forEach(id => {
                        const picker = document.getElementById('proc' + id);
                        if (picker) {
                            const text = picker.parentElement.querySelector('input[type="text"]');
                            if (text) picker.addEventListener('input', () => text.value = picker.value);
                        }
                    });
                </script>

                <p style="font-size: 12px; color: #94a3b8; margin-top: 16px;">
                    <i class='bx bx-info-circle'></i> <?php echo __('hp_procedure_colors_hint'); ?>
                </p>
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" style="background: #142A51; color: #fff; border: none; padding: 14px 32px; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s;" onmouseover="this.style.background='#0e1e3a';" onmouseout="this.style.background='#142A51';">
                    <i class='bx bx-save'></i> <?php echo __('hp_save_procedure_colors'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- About System Section (Bilingual & Modernized) -->
    <div style="background: #fff; padding: 28px; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); width: 100%; margin-top: 24px; border: 1px solid #f0f0f0;">
        <div class="head" style="margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 4px; height: 26px; background: #6366f1; border-radius: 4px;"></div>
                <h3 style="font-size: 20px; color: #1f2937; font-weight: 700;"><?php echo __('hp_about_system', 'เกี่ยวกับระบบ (About System)'); ?></h3>
            </div>
            <div style="display: flex; gap: 8px;">
                <span style="background: #eef2ff; color: #6366f1; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;"><?php echo __('hp_bilingual_badge', 'Bilingual Supported'); ?></span>
            </div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_about_system">
            
            <!-- Title Section -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 16px; margin-bottom: 24px;">
                <div style="font-size: 14px; font-weight: 700; color: #64748b; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.025em; display: flex; align-items: center; gap: 8px;">
                    <i class='bx bx-heading' style="font-size: 18px;"></i> <?php echo __('hp_section_title_label'); ?>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><?php echo __('hp_thai_title'); ?></label>
                        <input type="text" name="index_about_title" value="<?php echo htmlspecialchars($page_settings['index_about_title'] ?? ''); ?>" required style="width: 100%; padding: 12px 16px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; transition: all 0.2s;" onfocus="this.style.borderColor='#6366f1'; this.style.boxShadow='0 0 0 3px rgba(99,102,241,0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><?php echo __('hp_english_title'); ?></label>
                        <input type="text" name="index_about_title_en" value="<?php echo htmlspecialchars($page_settings['index_about_title_en'] ?? ''); ?>" placeholder="About System" style="width: 100%; padding: 12px 16px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; transition: all 0.2s;" onfocus="this.style.borderColor='#6366f1'; this.style.boxShadow='0 0 0 3px rgba(99,102,241,0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                </div>
            </div>
            
            <!-- Description Section -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 16px; margin-bottom: 24px;">
                <div style="font-size: 14px; font-weight: 700; color: #64748b; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.025em; display: flex; align-items: center; gap: 8px;">
                    <i class='bx bx-align-left' style="font-size: 18px;"></i> <?php echo __('hp_description_content'); ?>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><?php echo __('hp_thai_description'); ?></label>
                        <textarea name="index_about_desc" rows="5" required style="width: 100%; padding: 12px 16px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; resize: vertical; line-height: 1.6; transition: all 0.2s;" onfocus="this.style.borderColor='#6366f1'; this.style.boxShadow='0 0 0 3px rgba(99,102,241,0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';"><?php echo htmlspecialchars($page_settings['index_about_desc'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><?php echo __('hp_english_description'); ?></label>
                        <textarea name="index_about_desc_en" rows="5" placeholder="Description in English..." style="width: 100%; padding: 12px 16px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; resize: vertical; line-height: 1.6; transition: all 0.2s;" onfocus="this.style.borderColor='#6366f1'; this.style.boxShadow='0 0 0 3px rgba(99,102,241,0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';"><?php echo htmlspecialchars($page_settings['index_about_desc_en'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Features Section -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 16px; margin-bottom: 24px;">
                <div style="font-size: 14px; font-weight: 700; color: #64748b; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.025em; display: flex; align-items: center; gap: 8px;">
                    <i class='bx bx-list-check' style="font-size: 20px;"></i> <?php echo __('hp_key_features'); ?>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php for($i=1; $i<=4; $i++): ?>
                    <div style="display: grid; grid-template-columns: auto 1fr 1fr; gap: 16px; align-items: center; background: #fff; padding: 12px; border-radius: 12px; border: 1px solid #edf2f7;">
                        <div style="width: 32px; height: 32px; background: #e0e7ff; color: #6366f1; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                            <?php echo $i; ?>
                        </div>
                        <div>
                            <input type="text" name="index_feature_<?php echo $i; ?>" value="<?php echo htmlspecialchars($page_settings['index_feature_'.$i] ?? ''); ?>" placeholder="Thai feature text..." style="width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div>
                            <input type="text" name="index_feature_<?php echo $i; ?>_en" value="<?php echo htmlspecialchars($page_settings['index_feature_'.$i.'_en'] ?? ''); ?>" placeholder="English translation..." style="width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Design & Colors Section -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 16px; margin-bottom: 24px; border: 1px dashed #e2e8f0;">
                <div style="font-size: 14px; font-weight: 700; color: #64748b; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.025em; display: flex; align-items: center; gap: 8px;">
                    <i class='bx bx-palette' style="font-size: 20px;"></i> <?php echo __('hp_section_design_colors'); ?>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><?php echo __('hp_background_color'); ?></label>
                        <div style="display: flex; align-items: center; gap: 12px; background: #fff; padding: 8px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <input type="color" id="aboutBgColor" name="about_bg_color" value="<?php echo htmlspecialchars($page_settings['about_bg_color'] ?? '#f9fafb'); ?>" style="width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer; background: none;">
                            <input type="text" value="<?php echo htmlspecialchars($page_settings['about_bg_color'] ?? '#f9fafb'); ?>" oninput="document.getElementById('aboutBgColor').value=this.value" style="flex: 1; border: none; font-family: monospace; font-size: 14px; outline: none;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 13px;"><?php echo __('hp_text_icon_color'); ?></label>
                        <div style="display: flex; align-items: center; gap: 12px; background: #fff; padding: 8px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <input type="color" id="aboutTextColor" name="about_text_color" value="<?php echo htmlspecialchars($page_settings['about_text_color'] ?? '#1f2937'); ?>" style="width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer; background: none;">
                            <input type="text" value="<?php echo htmlspecialchars($page_settings['about_text_color'] ?? '#1f2937'); ?>" oninput="document.getElementById('aboutTextColor').value=this.value" style="flex: 1; border: none; font-family: monospace; font-size: 14px; outline: none;">
                        </div>
                    </div>
                </div>
                <p style="font-size: 12px; color: #94a3b8; margin-top: 12px;">
                    <i class='bx bx-info-circle'></i> <?php echo __('hp_about_colors_hint'); ?>
                </p>
            </div>
            
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 16px;">
                <span id="saveStatus" style="font-size: 14px; color: #10b981; font-weight: 600; display: none;">
                    <i class='bx bx-check-double'></i> <?php echo __('hp_settings_updated'); ?>
                </span>
                <button type="submit" style="background: #6366f1; color: #fff; border: none; padding: 14px 32px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(99,102,241, 0.2); display: flex; align-items: center; gap: 8px;" onmouseover="this.style.background='#4f46e5'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#6366f1'; this.style.transform='translateY(0)';">
                    <i class='bx bx-save'></i> <?php echo __('hp_save_changes'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Contact System Section -->
    <div style="background: var(--light); padding: 28px; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); width: 100%; margin-top: 24px;">
        <div class="head" style="margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
            <div style="width: 4px; height: 24px; background: #fd7e14; border-radius: 4px;"></div>
            <h3 style="font-size: 20px; color: var(--dark); font-weight: 600;"><?php echo __('hp_contact_details'); ?></h3>
        </div>

        <style>
            .contact-input-group {
                position: relative;
                display: flex;
                align-items: center;
            }
            .contact-input-group .input-icon {
                position: absolute;
                left: 14px;
                font-size: 18px;
                color: #9ca3af;
                pointer-events: none;
                z-index: 1;
            }
            .contact-input-group input {
                width: 100%;
                padding: 11px 14px 11px 42px;
                border: 1.5px solid var(--grey);
                border-radius: 10px;
                font-size: 14px;
                background: var(--light);
                color: var(--dark);
                transition: border-color 0.2s, box-shadow 0.2s;
                outline: none;
            }
            .contact-input-group input:focus {
                border-color: var(--blue);
                box-shadow: 0 0 0 3px rgba(85,82,249,0.1);
            }
            .contact-section-card {
                background: var(--grey);
                border-radius: 14px;
                padding: 20px;
                margin-bottom: 20px;
            }
            .contact-section-title {
                font-size: 13px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.07em;
                color: #9ca3af;
                margin-bottom: 16px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .footer-link-row {
                display: grid;
                grid-template-columns: 1fr 1.4fr;
                gap: 8px;
                margin-bottom: 8px;
                align-items: center;
            }
            .footer-link-row input {
                width: 100%;
                padding: 8px 12px;
                border: 1.5px solid var(--grey);
                border-radius: 8px;
                font-size: 13px;
                background: var(--light);
                color: var(--dark);
                outline: none;
                transition: border-color 0.2s;
            }
            .footer-link-row input:focus { border-color: var(--blue); }
            .footer-link-row .link-num {
                font-size: 11px;
                font-weight: 700;
                color: #9ca3af;
                width: 20px;
            }
            .col-links-grid {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 16px;
            }
        </style>

        <form method="POST">
            <input type="hidden" name="action" value="save_contact_system">

            <!-- Organisation Info -->
            <div class="contact-section-card">
                <div class="contact-section-title">
                    <i class='bx bxs-buildings'></i> <?php echo __('hp_mcot_text_box', 'ข้อมูลองค์กร'); ?>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><?php echo __('hp_company_name'); ?></label>
                        <div class="contact-input-group">
                            <i class='bx bxs-building-house input-icon'></i>
                            <input type="text" name="footer_company_name" value="<?php echo htmlspecialchars($page_settings['footer_company_name'] ?? ''); ?>" placeholder="Company name...">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><?php echo __('hp_address'); ?></label>
                        <div class="contact-input-group">
                            <i class='bx bxs-map input-icon'></i>
                            <input type="text" name="footer_address" value="<?php echo htmlspecialchars($page_settings['footer_address'] ?? ''); ?>" placeholder="Address...">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="contact-section-card">
                <div class="contact-section-title">
                    <i class='bx bxs-phone-call'></i> <?php echo __('hp_contact_info', 'ข้อมูลติดต่อ'); ?>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><?php echo __('hp_first_email'); ?></label>
                        <div class="contact-input-group">
                            <i class='bx bxs-envelope input-icon'></i>
                            <input type="text" name="footer_contact_email_1" value="<?php echo htmlspecialchars($page_settings['footer_contact_email_1'] ?? ''); ?>" placeholder="email@example.com">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><?php echo __('hp_ads_tel'); ?></label>
                        <div class="contact-input-group">
                            <i class='bx bxs-phone input-icon'></i>
                            <input type="text" name="footer_contact_ads" value="<?php echo htmlspecialchars($page_settings['footer_contact_ads'] ?? ''); ?>" placeholder="0x-xxx-xxxx">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><?php echo __('hp_second_email'); ?></label>
                        <div class="contact-input-group">
                            <i class='bx bxs-envelope input-icon'></i>
                            <input type="text" name="footer_contact_email_2" value="<?php echo htmlspecialchars($page_settings['footer_contact_email_2'] ?? ''); ?>" placeholder="email2@example.com">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><?php echo __('hp_main_tel'); ?></label>
                        <div class="contact-input-group">
                            <i class='bx bxs-phone input-icon'></i>
                            <input type="text" name="footer_contact_tel" value="<?php echo htmlspecialchars($page_settings['footer_contact_tel'] ?? ''); ?>" placeholder="0x-xxx-xxxx">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Media -->
            <div class="contact-section-card">
                <div class="contact-section-title">
                    <i class='bx bxs-share-alt'></i> <?php echo __('hp_social_media', 'Social Media'); ?>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><i class='bx bxl-facebook-circle' style="color:#1877F2;"></i> <?php echo __('hp_facebook_url'); ?></label>
                        <div class="contact-input-group">
                            <i class='bx bxl-facebook-circle input-icon' style="color:#1877F2;"></i>
                            <input type="text" name="social_facebook" value="<?php echo htmlspecialchars($page_settings['social_facebook'] ?? ''); ?>" placeholder="https://facebook.com/...">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><i class='bx bxl-tiktok'></i> <?php echo __('hp_tiktok_url'); ?></label>
                        <div class="contact-input-group">
                            <i class='bx bxl-tiktok input-icon'></i>
                            <input type="text" name="social_tiktok" value="<?php echo htmlspecialchars($page_settings['social_tiktok'] ?? ''); ?>" placeholder="https://tiktok.com/@...">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><i class='bx bxl-instagram' style="color:#E1306C;"></i> <?php echo __('hp_instagram_url'); ?></label>
                        <div class="contact-input-group">
                            <i class='bx bxl-instagram input-icon' style="color:#E1306C;"></i>
                            <input type="text" name="social_instagram" value="<?php echo htmlspecialchars($page_settings['social_instagram'] ?? ''); ?>" placeholder="https://instagram.com/...">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Colors -->
            <div class="contact-section-card">
                <div class="contact-section-title">
                    <i class='bx bxs-palette'></i> <?php echo __('hp_footer_colors', 'สีท้ายเว็บ (Footer Colors)'); ?>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; align-items: center;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><i class='bx bx-paint-roll'></i> <?php echo __('hp_footer_bg_color', 'สีพื้นหลัง Footer'); ?></label>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <input type="color" id="footerBgColor" name="footer_bg_color"
                                value="<?php echo htmlspecialchars($page_settings['footer_bg_color'] ?? '#04041B'); ?>"
                                style="width:48px; height:48px; border:none; border-radius:10px; cursor:pointer; padding:3px; background:var(--grey);">
                            <input type="text" id="footerBgColorText"
                                value="<?php echo htmlspecialchars($page_settings['footer_bg_color'] ?? '#04041B'); ?>"
                                oninput="document.getElementById('footerBgColor').value=this.value"
                                style="width:100%; padding:10px 14px; border:1.5px solid var(--grey); border-radius:10px; font-size:13px; background:var(--light); color:var(--dark); font-family:monospace;">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><i class='bx bx-font-color'></i> <?php echo __('hp_footer_text_color', 'สีตัวอักษร'); ?></label>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <input type="color" id="footerTextColor" name="footer_text_color"
                                value="<?php echo htmlspecialchars($page_settings['footer_text_color'] ?? '#ffffff'); ?>"
                                style="width:48px; height:48px; border:none; border-radius:10px; cursor:pointer; padding:3px; background:var(--grey);">
                            <input type="text" id="footerTextColorText"
                                value="<?php echo htmlspecialchars($page_settings['footer_text_color'] ?? '#ffffff'); ?>"
                                oninput="document.getElementById('footerTextColor').value=this.value"
                                style="width:100%; padding:10px 14px; border:1.5px solid var(--grey); border-radius:10px; font-size:13px; background:var(--light); color:var(--dark); font-family:monospace;">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:var(--dark); margin-bottom:8px;"><i class='bx bx-link'></i> <?php echo __('hp_footer_link_color', 'สีลิงก์ / ไอคอน'); ?></label>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <input type="color" id="footerLinkColor" name="footer_link_color"
                                value="<?php echo htmlspecialchars($page_settings['footer_link_color'] ?? '#ffffff'); ?>"
                                style="width:48px; height:48px; border:none; border-radius:10px; cursor:pointer; padding:3px; background:var(--grey);">
                            <input type="text" id="footerLinkColorText"
                                value="<?php echo htmlspecialchars($page_settings['footer_link_color'] ?? '#ffffff'); ?>"
                                oninput="document.getElementById('footerLinkColor').value=this.value"
                                style="width:100%; padding:10px 14px; border:1.5px solid var(--grey); border-radius:10px; font-size:13px; background:var(--light); color:var(--dark); font-family:monospace;">
                        </div>
                    </div>
                </div>
                <script>
                    // Sync color pickers <-> text inputs
                    ['Bg','Text','Link'].forEach(name => {
                        const picker = document.getElementById('footer'+name+'Color');
                        const text   = document.getElementById('footer'+name+'ColorText');
                        if (picker && text) {
                            picker.addEventListener('input', () => text.value = picker.value);
                        }
                    });
                </script>
            </div>

            <!-- Footer Links -->
            <div class="contact-section-card">
                <div class="contact-section-title">
                    <i class='bx bx-link'></i> <?php echo __('hp_footer_links', 'Footer Links'); ?>
                </div>
                <div class="col-links-grid">
                    <?php foreach ([1 => __('hp_row_1','คอลัมน์ 1'), 2 => __('hp_row_2','คอลัมน์ 2'), 3 => __('hp_row_3','คอลัมน์ 3')] as $col => $colLabel): ?>
                    <div>
                        <div style="font-size:12px; font-weight:700; color:var(--dark); margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid var(--grey);"><?php echo $colLabel; ?></div>
                        <?php for($i=1; $i<=5; $i++): ?>
                        <div class="footer-link-row">
                            <input type="text" name="footer_col<?php echo $col; ?>_link_<?php echo $i; ?>_text"
                                value="<?php echo htmlspecialchars($page_settings['footer_col'.$col.'_link_'.$i.'_text'] ?? ''); ?>"
                                placeholder="Link <?php echo $i; ?> Text">
                            <input type="text" name="footer_col<?php echo $col; ?>_link_<?php echo $i; ?>_url"
                                value="<?php echo htmlspecialchars($page_settings['footer_col'.$col.'_link_'.$i.'_url'] ?? ''); ?>"
                                placeholder="URL">
                        </div>
                        <?php endfor; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; margin-top:8px;">
                <button type="submit" style="background: var(--blue); color: #fff; border: none; padding: 12px 28px; border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer; display:flex; align-items:center; gap:8px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.88'" onmouseout="this.style.opacity='1'">
                    <i class='bx bx-save'></i> <?php echo __('hp_save_changes', 'บันทึกข้อมูล'); ?>
                </button>
            </div>
        </form>
    </div>

</div>

<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to handle reordering logic
        const handleReorder = (evt, gridId) => {
            var order = [];
            // Get all image IDs in current order for the specific grid
            document.querySelectorAll(`#${gridId} .banner-card[data-id]`).forEach(function(card) {
                order.push(card.getAttribute('data-id'));
            });
            
            // Send new order to server
            if(order.length > 0) {
                var formData = new FormData();
                formData.append('action', 'reorder');
                order.forEach(function(id) {
                    formData.append('order[]', id);
                });
                
                fetch('edit_homepage.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.status !== 'success') {
                        console.error('Reorder failed');
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        };

        // Initialize Sortable for Banners Grid
        var bannerGrid = document.getElementById('bannerGrid');
        if (bannerGrid) {
            Sortable.create(bannerGrid, {
                animation: 150,
                filter: '.ignore-sort',
                draggable: ".banner-card:not(.ignore-sort)",
                onEnd: function (evt) { handleReorder(evt, 'bannerGrid'); }
            });
        }

        // Initialize Sortable for Process 2 Grid
        var process2Grid = document.getElementById('process2Grid');
        if (process2Grid) {
            Sortable.create(process2Grid, {
                animation: 150,
                filter: '.ignore-sort',
                draggable: ".banner-card:not(.ignore-sort)",
                onEnd: function (evt) { handleReorder(evt, 'process2Grid'); }
            });
        }
        
        // Modal functions
        window.openBannerModal = function() {
            document.getElementById('bannerUploadModal').classList.add('active');
        };
        window.closeBannerModal = function() {
            document.getElementById('bannerUploadModal').classList.remove('active');
        };
        
        // Close modal when clicking outside
        var bannerModal = document.getElementById('bannerUploadModal');
        if(bannerModal) {
            bannerModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeBannerModal();
                }
            });
        }
        
        // Process 2 Modal functions
        window.openProcess2Modal = function() {
            document.getElementById('process2UploadModal').classList.add('active');
        };
        window.closeProcess2Modal = function() {
            document.getElementById('process2UploadModal').classList.remove('active');
        };
        
        // Close Process 2 modal when clicking outside
        var process2Modal = document.getElementById('process2UploadModal');
        if(process2Modal) {
            process2Modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeProcess2Modal();
                }
            });
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>
