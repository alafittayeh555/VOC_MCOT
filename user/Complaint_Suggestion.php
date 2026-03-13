<?php
// user/Complaint_Suggestion.php
$hide_header = false;
require_once '../includes/header_landing.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch Agencies & Options
try {
    $stmtAgencies = $db->query("SELECT * FROM agencies ORDER BY id ASC");
    $agencies_list = $stmtAgencies->fetchAll();
    $stmtOptions = $db->query("SELECT agency_id, name FROM agency_options ORDER BY name ASC");
    $raw_options = $stmtOptions->fetchAll(PDO::FETCH_ASSOC);
    $options_map = [];
    foreach ($raw_options as $opt) {
        $options_map[$opt['agency_id']][] = $opt['name'];
    }
} catch (PDOException $e) {
    $agencies_list = [];
    $options_map = [];
}
?>

<style>
/* ===== Page Layout ===== */
.cs-page {
    min-height: 100vh;
    background: linear-gradient(135deg, #ede9fe 0%, #f5f3ff 50%, #ddd6fe 100%);
    padding: 32px 16px 60px;
    font-family: 'Prompt', sans-serif;
}

/* ===== Hero Header ===== */
.cs-hero {
    max-width: 900px;
    margin: 0 auto 32px;
    text-align: center;
}
.cs-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(99,102,241,0.12);
    border: 1px solid rgba(99,102,241,0.3);
    color: #6366f1;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 6px 18px;
    border-radius: 99px;
    margin-bottom: 16px;
}
.cs-hero h1 {
    font-size: clamp(24px, 4vw, 38px);
    font-weight: 800;
    color: #3730a3;
    margin-bottom: 10px;
    line-height: 1.2;
}
.cs-hero p {
    color: #6d6d89;
    font-size: 15px;
}

/* ===== Step Indicator ===== */
.cs-steps {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0;
    margin-bottom: 32px;
}
.cs-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    position: relative;
}
.cs-step-dot {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    border: 2px solid #c4b5fd;
    color: #8b5cf6;
    background: #fff;
    transition: all 0.3s;
}
.cs-step.active .cs-step-dot {
    background: #6366f1;
    border-color: #6366f1;
    color: #fff;
    box-shadow: 0 0 20px rgba(99,102,241,0.35);
}
.cs-step-label {
    font-size: 11px;
    font-weight: 600;
    color: #8b8ba8;
    white-space: nowrap;
}
.cs-step.active .cs-step-label { color: #6366f1; }
.cs-step-line {
    width: 60px;
    height: 2px;
    background: #c4b5fd;
    margin: 0 4px;
    margin-bottom: 22px;
}
@media (max-width: 480px) { .cs-step-line { width: 28px; } }

/* ===== Card ===== */
.cs-card {
    max-width: 900px;
    margin: 0 auto 24px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 24px;
    padding: 32px;
    box-shadow: 0 4px 24px rgba(99,102,241,0.08);
    transition: box-shadow 0.3s;
}
.cs-card:hover { box-shadow: 0 8px 32px rgba(99,102,241,0.14); }

/* ===== Section Header ===== */
.cs-section-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 24px;
}
.cs-section-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.cs-section-icon.purple { background: #ede9fe; color: #7c3aed; }
.cs-section-icon.blue   { background: #dbeafe; color: #2563eb; }
.cs-section-icon.teal   { background: #ccfbf1; color: #0f766e; }
.cs-section-title {
    font-size: 17px;
    font-weight: 700;
    color: #1e1b4b;
    margin: 0;
}
.cs-section-sub {
    font-size: 12px;
    color: #9ca3af;
    margin: 2px 0 0;
}

/* ===== Info Grid ===== */
.cs-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
@media (max-width: 600px) { .cs-info-grid { grid-template-columns: 1fr; } }

.cs-field-group { display: flex; flex-direction: column; gap: 6px; }
.cs-field-label {
    font-size: 11px;
    font-weight: 700;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.cs-field-value {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 11px 16px;
    font-size: 14px;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 10px;
}
.cs-field-value i { color: #a5b4fc; font-size: 16px; }

/* ===== Anonymous Toggle ===== */
.cs-anon {
    margin-top: 20px;
    background: #f5f3ff;
    border: 1px solid #ddd6fe;
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
.cs-anon-text h4 { font-size: 14px; font-weight: 700; color: #3730a3; margin: 0 0 2px; }
.cs-anon-text p  { font-size: 12px; color: #9ca3af; margin: 0; }

/* Toggle Switch */
.cs-toggle { position: relative; display: inline-flex; align-items: center; cursor: pointer; }
.cs-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.cs-toggle-track {
    width: 48px; height: 26px;
    background: #e5e7eb;
    border-radius: 99px;
    position: relative;
    transition: background 0.3s;
    border: 1px solid #d1d5db;
}
.cs-toggle input:checked ~ .cs-toggle-track { background: #6366f1; border-color: #6366f1; }
.cs-toggle-thumb {
    position: absolute;
    top: 3px; left: 3px;
    width: 18px; height: 18px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.3s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15);
}
.cs-toggle input:checked ~ .cs-toggle-track .cs-toggle-thumb { transform: translateX(22px); }
.cs-toggle-label { margin-left: 10px; font-size: 13px; font-weight: 600; color: #6b7280; }

/* ===== Type Cards ===== */
.cs-type-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}
@media (max-width: 600px) { .cs-type-grid { grid-template-columns: 1fr; } }

.cs-type-label { cursor: pointer; display: block; }
.cs-type-label input { position: absolute; opacity: 0; width: 0; height: 0; }
.cs-type-card {
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    padding: 20px 16px;
    text-align: center;
    transition: all 0.25s;
    background: #fafafa;
    position: relative;
    overflow: hidden;
}
.cs-type-label:hover .cs-type-card { transform: translateY(-3px); border-color: #c4b5fd; background: #f5f3ff; }

/* Orange - Complaint */
.cs-type-label input[value="Complaint"]:checked ~ .cs-type-card {
    border-color: #f97316;
    background: #fff7ed;
    box-shadow: 0 4px 20px rgba(249,115,22,0.12);
}
/* Blue - Suggestion */
.cs-type-label input[value="Suggestion"]:checked ~ .cs-type-card {
    border-color: #3b82f6;
    background: #eff6ff;
    box-shadow: 0 4px 20px rgba(59,130,246,0.12);
}
/* Green - Compliment */
.cs-type-label input[value="Compliment"]:checked ~ .cs-type-card {
    border-color: #22c55e;
    background: #f0fdf4;
    box-shadow: 0 4px 20px rgba(34,197,94,0.12);
}

.cs-type-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    transition: all 0.3s;
}
.cs-type-icon.orange { background: #fed7aa; color: #ea580c; }
.cs-type-icon.blue   { background: #bfdbfe; color: #2563eb; }
.cs-type-icon.green  { background: #bbf7d0; color: #16a34a; }
.cs-type-card h4 { font-size: 14px; font-weight: 700; color: #1f2937; margin: 0 0 4px; }
.cs-type-card p  { font-size: 11px; color: #9ca3af; margin: 0; }

/* ===== Form Inputs ===== */
.cs-input-group { margin-bottom: 18px; }
.cs-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 8px;
}
.cs-label span { color: #6366f1; }
.cs-input, .cs-select, .cs-textarea {
    width: 100%;
    background: #f9fafb;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 14px;
    color: #111827;
    font-family: inherit;
    outline: none;
    transition: all 0.2s;
    appearance: none;
    -webkit-appearance: none;
}
.cs-input::placeholder, .cs-textarea::placeholder { color: #9ca3af; }
.cs-input:focus, .cs-select:focus, .cs-textarea:focus {
    border-color: #6366f1;
    background: #faf5ff;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
.cs-textarea { resize: vertical; min-height: 130px; line-height: 1.6; }

.cs-select-wrap { position: relative; }
.cs-select-wrap .cs-chevron {
    position: absolute;
    right: 14px; top: 50%; transform: translateY(-50%);
    color: #9ca3af;
    pointer-events: none;
    font-size: 18px;
}
.cs-select option { background: #fff; color: #111827; }

/* ===== Sub-select (hidden) ===== */
.cs-sub-field {
    margin-top: 12px;
    display: none;
    animation: fadeIn 0.2s ease;
}
.cs-sub-field.visible { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

/* ===== Upload Area ===== */
/* ===== Upload Area ===== */
.cs-upload-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); /* Slightly wider for better text fit */
    gap: 16px;
    margin-top: 8px;
}
.cs-file-card {
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    background: #fff;
    height: 190px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    transition: all 0.2s;
}
.cs-file-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); }

.cs-file-card.add-card {
    border: 2px dashed #c4b5fd;
    background: #faf5ff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: none;
}
.cs-file-card.add-card:hover { border-color: #6366f1; background: #ede9fe; }

.cs-add-icon {
    width: 48px;
    height: 48px;
    background: #ede9fe;
    color: #7c3aed;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 12px;
}

.cs-file-preview {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #f9fafb;
}
.cs-file-preview img { width: 100%; height: 100%; object-fit: cover; }
.cs-file-footer {
    padding: 8px 12px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    border-top: 1px solid #f3f4f6;
    background: #fff;
}
.cs-del-btn {
    background: #fee2e2;
    border: none;
    border-radius: 8px;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    color: #ef4444;
    transition: all 0.2s;
    font-size: 16px;
}
.cs-del-btn:hover { background: #fecaca; }

/* ===== Flash Messages ===== */
.cs-alert {
    max-width: 900px;
    margin: 0 auto 20px;
    padding: 14px 20px;
    border-radius: 14px;
    border-left: 4px solid;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
}
.cs-alert.success { background: #f0fdf4; border-color: #22c55e; color: #16a34a; }
.cs-alert.error   { background: #fef2f2; border-color: #ef4444; color: #dc2626; }

/* ===== Submit Button ===== */
.cs-submit-wrap { max-width: 900px; margin: 0 auto; display: flex; justify-content: flex-end; gap: 12px; }
.cs-btn-back {
    padding: 14px 28px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    background: #fff;
    border: 1.5px solid #e5e7eb;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s;
    display: flex; align-items: center; gap: 8px;
    font-family: inherit;
    text-decoration: none;
}
.cs-btn-back:hover { background: #f3f4f6; color: #374151; border-color: #d1d5db; }
.cs-btn-submit {
    padding: 14px 36px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    background: linear-gradient(135deg, #7c3aed, #6366f1);
    border: none;
    color: #fff;
    cursor: pointer;
    transition: all 0.25s;
    display: flex; align-items: center; gap: 8px;
    font-family: inherit;
    box-shadow: 0 4px 20px rgba(124,58,237,0.3);
}
.cs-btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(124,58,237,0.45); }
.cs-btn-submit:active { transform: translateY(0); }
</style>


<div class="cs-page">

    <!-- Hero Header -->
    <div class="cs-hero">
        <div class="cs-hero-badge"><i class='bx bx-edit-alt'></i> <?php echo __('submit_hero_badge'); ?></div>
        <h1><?php echo __('submit_title'); ?></h1>
        <p><?php echo __('submit_subtitle', 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง เพื่อให้เราสามารถดำเนินการได้อย่างมีประสิทธิภาพ'); ?></p>
    </div>

    <!-- Step Indicator -->
    <div class="cs-steps">
        <div class="cs-step active">
            <div class="cs-step-dot"><i class='bx bxs-user'></i></div>
            <div class="cs-step-label"><?php echo __('submit_step_1'); ?></div>
        </div>
        <div class="cs-step-line"></div>
        <div class="cs-step active">
            <div class="cs-step-dot"><i class='bx bxs-note'></i></div>
            <div class="cs-step-label"><?php echo __('submit_step_2'); ?></div>
        </div>
        <div class="cs-step-line"></div>
        <div class="cs-step active">
            <div class="cs-step-dot"><i class='bx bx-paperclip'></i></div>
            <div class="cs-step-label"><?php echo __('submit_step_3'); ?></div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="cs-alert success"><i class='bx bx-check-circle' style="font-size:20px;"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="cs-alert error"><i class='bx bx-error-circle' style="font-size:20px;"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form action="../controllers/complaint_action.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="submit_complaint">

        <!-- SECTION 1: Complainant Info -->
        <div class="cs-card">
            <div class="cs-section-header">
                <div class="cs-section-icon purple"><i class='bx bxs-user'></i></div>
                <div>
                    <div class="cs-section-title"><?php echo __('submit_section_1'); ?></div>
                    <div class="cs-section-sub"><?php echo __('submit_section_1_desc'); ?></div>
                </div>
            </div>

            <div class="cs-info-grid">
                <div class="cs-field-group">
                    <div class="cs-field-label"><?php echo __('submit_fullname'); ?></div>
                    <div class="cs-field-value"><i class='bx bxs-user'></i><?php echo htmlspecialchars($user['full_name']); ?></div>
                </div>
                <div class="cs-field-group">
                    <div class="cs-field-label"><?php echo __('profile_occupation'); ?></div>
                    <div class="cs-field-value"><i class='bx bxs-briefcase'></i><?php echo htmlspecialchars($user['occupation'] ?? '-'); ?></div>
                </div>
                <div class="cs-field-group">
                    <div class="cs-field-label"><?php echo __('profile_phone'); ?></div>
                    <div class="cs-field-value"><i class='bx bxs-phone'></i><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></div>
                </div>
                <div class="cs-field-group">
                    <div class="cs-field-label"><?php echo __('submit_email'); ?></div>
                    <div class="cs-field-value"><i class='bx bxs-envelope'></i><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
            </div>

            <!-- Anonymous Toggle -->
            <div class="cs-anon">
                <div class="cs-anon-text">
                    <h4><?php echo __('submit_caller_hide_identity'); ?></h4>
                    <p><?php echo __('submit_caller_hide_identity_hint'); ?></p>
                </div>
                <label class="cs-toggle">
                    <input type="checkbox" name="is_anonymous" value="1">
                    <div class="cs-toggle-track"><div class="cs-toggle-thumb"></div></div>
                    <span class="cs-toggle-label"><?php echo __('submit_caller_anonymous'); ?></span>
                </label>
            </div>
        </div>

        <!-- SECTION 2: Complaint Details -->
        <div class="cs-card">
            <div class="cs-section-header">
                <div class="cs-section-icon blue"><i class='bx bxs-note'></i></div>
                <div>
                    <div class="cs-section-title"><?php echo __('submit_section_2'); ?></div>
                    <div class="cs-section-sub"><?php echo __('submit_section_2_desc'); ?></div>
                </div>
            </div>

            <!-- Type Cards -->
            <div class="cs-label"><?php echo __('submit_type_label'); ?> <span>*</span></div>
            <div class="cs-type-grid">
                <label class="cs-type-label">
                    <input type="radio" name="complaint_type" value="Complaint" checked required>
                    <div class="cs-type-card">
                        <div class="cs-type-icon orange"><i class='bx bxs-megaphone'></i></div>
                        <h4><?php echo __('submit_type_complaint'); ?></h4>
                        <p><?php echo __('submit_type_complaint_desc'); ?></p>
                    </div>
                </label>
                <label class="cs-type-label">
                    <input type="radio" name="complaint_type" value="Suggestion">
                    <div class="cs-type-card">
                        <div class="cs-type-icon blue"><i class='bx bxs-bulb'></i></div>
                        <h4><?php echo __('submit_type_suggestion'); ?></h4>
                        <p><?php echo __('submit_type_suggestion_desc'); ?></p>
                    </div>
                </label>
                <label class="cs-type-label">
                    <input type="radio" name="complaint_type" value="Compliment">
                    <div class="cs-type-card">
                        <div class="cs-type-icon green"><i class='bx bxs-heart'></i></div>
                        <h4><?php echo __('submit_type_compliment'); ?></h4>
                        <p><?php echo __('submit_type_compliment_desc'); ?></p>
                    </div>
                </label>
            </div>

            <!-- Agency Select -->
            <div class="cs-input-group">
                <label class="cs-label"><?php echo __('submit_rel_org_label'); ?> <span>*</span></label>
                <div class="cs-select-wrap">
                    <select name="agencies" id="agenciesSelect" onchange="toggleRadioStations()" required class="cs-select">
                        <option value="General"><?php echo __('submit_select_agency_default'); ?></option>
                        <?php foreach ($agencies_list as $agency): ?>
                            <option value="<?php echo htmlspecialchars($agency['name']); ?>"
                                    data-id="<?php echo $agency['id']; ?>"
                                    data-has-sub="<?php echo $agency['has_sub_options']; ?>"
                                    data-is-other="<?php echo isset($agency['is_other']) ? $agency['is_other'] : 0; ?>">
                                <?php echo htmlspecialchars($agency['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <i class='bx bx-chevron-down cs-chevron'></i>
                </div>

                <!-- Sub-select -->
                <div id="radioStationDiv" class="cs-sub-field">
                    <label class="cs-label"><?php echo __('submit_select_radio_label'); ?></label>
                    <div class="cs-select-wrap">
                        <select name="radio_station" id="radioStationSelect" class="cs-select">
                            <option value=""><?php echo __('submit_select_radio_default'); ?></option>
                        </select>
                        <i class='bx bx-chevron-down cs-chevron'></i>
                    </div>
                </div>

                <!-- Other input -->
                <div id="otherAgencyDiv" class="cs-sub-field">
                    <label class="cs-label"><?php echo __('submit_specify_label'); ?></label>
                    <input type="text" name="agencies_other" id="otherAgencyInput"
                           placeholder="<?php echo __('submit_custom_agency_placeholder'); ?>"
                           class="cs-input">
                </div>
            </div>

            <!-- Subject -->
            <div class="cs-input-group">
                <label class="cs-label"><?php echo __('submit_subject_label'); ?> <span>*</span></label>
                <input type="text" name="subject" required
                       placeholder="<?php echo __('submit_subject_placeholder'); ?>"
                       class="cs-input">
            </div>

            <!-- Description -->
            <div class="cs-input-group" style="margin-bottom:0;">
                <label class="cs-label"><?php echo __('submit_desc_label'); ?> <span>*</span></label>
                <textarea name="description" rows="5" required
                          placeholder="<?php echo __('submit_desc_placeholder'); ?>"
                          class="cs-textarea"></textarea>
            </div>
        </div>

        <!-- SECTION 3: Attachments -->
        <div class="cs-card">
            <div class="cs-section-header">
                <div class="cs-section-icon teal"><i class='bx bx-paperclip'></i></div>
                <div>
                    <div class="cs-section-title"><?php echo __('submit_attachment_title'); ?></div>
                    <div class="cs-section-sub"><?php echo __('submit_attachment_desc'); ?></div>
                </div>
            </div>

            <input type="file" name="attachments[]" id="fileInput" multiple style="display:none;"
                   onchange="handleFileSelect(event)" accept=".jpg,.jpeg,.png,.pdf">

            <div class="cs-upload-grid" id="uploadGrid">
                <!-- Grid will be rendered by JS, including the "Add" card -->
            </div>
        </div>

        <!-- Submit -->
        <div class="cs-submit-wrap">
            <a href="../index.php" class="cs-btn-back"><i class='bx bx-arrow-back'></i> <?php echo __('btn_back_home'); ?></a>
            <button type="submit" class="cs-btn-submit">
                <i class='bx bx-send'></i> <?php echo __('submit_btn_submit'); ?>
            </button>
        </div>

    </form>
</div>

<script>
// Initialize Grid
document.addEventListener('DOMContentLoaded', renderGrid);

// ===== Agency Toggle =====
const agencyOptionsMap = <?php echo json_encode($options_map); ?>;

function toggleRadioStations() {
    const sel = document.getElementById('agenciesSelect');
    const opt = sel.options[sel.selectedIndex];
    const hasSub  = opt.getAttribute('data-has-sub');
    const isOther = opt.getAttribute('data-is-other');
    const agId    = opt.getAttribute('data-id');

    const radioDiv   = document.getElementById('radioStationDiv');
    const subSelect  = document.getElementById('radioStationSelect');
    const otherDiv   = document.getElementById('otherAgencyDiv');
    const otherInput = document.getElementById('otherAgencyInput');

    // Other
    if (isOther == '1') {
        otherDiv.classList.add('visible');
        otherInput.setAttribute('required','required');
    } else {
        otherDiv.classList.remove('visible');
        otherInput.removeAttribute('required');
        otherInput.value = '';
    }

    // Sub-select
    if (hasSub == '1') {
        radioDiv.classList.add('visible');
        subSelect.setAttribute('required','required');
        subSelect.innerHTML = '<option value=""><?php echo __('submit_select_radio_default'); ?></option>';
        if (agencyOptionsMap[agId]) {
            agencyOptionsMap[agId].forEach(o => {
                const el = document.createElement('option');
                el.value = o; el.textContent = o;
                subSelect.appendChild(el);
            });
        }
    } else {
        radioDiv.classList.remove('visible');
        subSelect.removeAttribute('required');
        subSelect.value = '';
    }
}

// ===== File Upload =====
const fileInput  = document.getElementById('fileInput');
const uploadGrid = document.getElementById('uploadGrid');
const dt         = new DataTransfer();
const MAX_FILES  = 5;
const MAX_SIZE   = 5 * 1024 * 1024;
const ALLOWED    = ['image/jpeg','image/png','application/pdf'];

function handleFileSelect(e) {
    const files = e.target.files;
    for (let i = 0; i < files.length; i++) {
        const f = files[i];
        if (dt.items.length >= MAX_FILES) { alert("<?php echo __('submit_alert_max_files'); ?>"); break; }
        if (!ALLOWED.includes(f.type))    { alert("<?php echo __('submit_alert_invalid_type'); ?>" + " " + f.name + "\n" + "<?php echo __('submit_alert_allowed_types'); ?>"); continue; }
        if (f.size > MAX_SIZE)            { alert("<?php echo __('submit_alert_file_large'); ?>" + " " + f.name + "\n" + "<?php echo __('submit_alert_max_size'); ?>"); continue; }
        let dup = false;
        for (let j = 0; j < dt.items.length; j++) {
            const ex = dt.items[j].getAsFile();
            if (ex.name === f.name && ex.size === f.size) { dup = true; break; }
        }
        if (!dup) dt.items.add(f);
    }
    fileInput.files = dt.files;
    renderGrid();
}

function renderGrid() {
    uploadGrid.innerHTML = '';

    // 1. Render existing files
    for (let i = 0; i < dt.files.length; i++) {
        const file = dt.files[i];
        const card = document.createElement('div');
        card.className = 'cs-file-card';

        const preview = document.createElement('div');
        preview.className = 'cs-file-preview';

        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            const reader = new FileReader();
            reader.onload = (function(a){ return function(ev){ a.src = ev.target.result; }; })(img);
            reader.readAsDataURL(file);
            preview.appendChild(img);
        } else {
            const icon = file.type.includes('pdf') ? 'bxs-file-pdf' : 'bxs-file';
            const clr  = file.type.includes('pdf') ? '#ef4444' : '#94a3b8';
            preview.innerHTML = `<div style="text-align:center;padding:10px;">
                <i class='bx ${icon}' style="font-size:38px;color:${clr};"></i>
                <p style="font-size:10px;color:#64748b;margin:6px 0 0;word-break:break-all;font-weight:600;">${file.name}</p>
            </div>`;
        }

        const footer = document.createElement('div');
        footer.className = 'cs-file-footer';
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'cs-del-btn';
        del.innerHTML = "<i class='bx bxs-trash'></i>";
        del.onclick = (function(idx){ return function(){ removeFile(idx); }; })(i);
        footer.appendChild(del);

        card.appendChild(preview);
        card.appendChild(footer);
        uploadGrid.appendChild(card);
    }

    // 2. Render "Add Card" if below limit
    if (dt.items.length < MAX_FILES) {
        const addCard = document.createElement('div');
        addCard.className = 'cs-file-card add-card';
        addCard.onclick = () => fileInput.click();
        addCard.innerHTML = `
            <div class="cs-add-icon">
                <i class='bx bx-plus'></i>
            </div>
            <h5 style="margin: 0; font-weight: 700; color: #7c3aed; font-size: 14px;"><?php echo __('submit_btn_add_document'); ?></h5>
            <p style="margin: 4px 0 0; font-size: 11px; color: #9ca3af; font-weight: 600;">${dt.items.length} / ${MAX_FILES} <?php echo __('submit_uploaded_count'); ?></p>
        `;
        uploadGrid.appendChild(addCard);
    }
}

function removeFile(index) {
    dt.items.remove(index);
    fileInput.files = dt.files;
    renderGrid();
}
</script>

<?php require_once '../includes/footer.php'; ?>