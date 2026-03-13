<?php
// pr/Complaint_Suggestion.php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit;
}

$db = Database::connect();
// Fetch user details for display (PR Officer's details)
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Occupation Options (mapped to translation keys to preserve DB consistency)
$occupation_options = [
    'นักเรียน (Student)' => __('occ_student', 'นักเรียน (Student)'),
    'นักศึกษา (University Student)' => __('occ_university_student', 'นักศึกษา (University Student)'),
    'ข้าราชการ (Government Officer)' => __('occ_government_officer', 'ข้าราชการ (Government Officer)'),
    'พนักงานบริษัท (Employee)' => __('occ_employee', 'พนักงานบริษัท (Employee)'),
    'พนักงานรัฐวิสาหกิจ (State Enterprise Employee)' => __('occ_state_enterprise', 'พนักงานรัฐวิสาหกิจ (State Enterprise Employee)'),
    'ธุรกิจส่วนตัว (Business Owner)' => __('occ_business_owner', 'ธุรกิจส่วนตัว (Business Owner)'),
    'ฟรีแลนซ์ (Freelancer)' => __('occ_freelancer', 'ฟรีแลนซ์ (Freelancer)'),
    'ค้าขาย (Merchant)' => __('occ_merchant', 'ค้าขาย (Merchant)'),
    'เกษตรกร (Farmer)' => __('occ_farmer', 'เกษตรกร (Farmer)'),
    'วิศวกร (Engineer)' => __('occ_engineer', 'วิศวกร (Engineer)'),
    'แพทย์ (Doctor)' => __('occ_doctor', 'แพทย์ (Doctor)'),
    'พยาบาล (Nurse)' => __('occ_nurse', 'พยาบาล (Nurse)'),
    'ครู / อาจารย์ (Teacher / Lecturer)' => __('occ_teacher', 'ครู / อาจารย์ (Teacher / Lecturer)'),
    'โปรแกรมเมอร์ / นักพัฒนา (Programmer / Developer)' => __('occ_programmer', 'โปรแกรมเมอร์ / นักพัฒนา (Programmer / Developer)'),
    'นักออกแบบ (Designer)' => __('occ_designer', 'นักออกแบบ (Designer)'),
    'ช่างเทคนิค (Technician)' => __('occ_technician', 'ช่างเทคนิค (Technician)'),
    'แม่บ้าน / พ่อบ้าน (Homemaker)' => __('occ_homemaker', 'แม่บ้าน / พ่อบ้าน (Homemaker)'),
    'ว่างงาน (Unemployed)' => __('occ_unemployed', 'ว่างงาน (Unemployed)'),
    'เกษียณอายุ (Retired)' => __('occ_retired', 'เกษียณอายุ (Retired)'),
    'อื่น ๆ (Other)' => __('occ_other', 'อื่น ๆ (Other)')
];
?>

<!-- Main Content -->
<div class="head-title">
    <div class="left">
        <h1><?php echo __('submit_title'); ?></h1>
        <ul class="breadcrumb">
            <li><a href="dashboard.php"><?php echo __('menu_dashboard'); ?></a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="#"><?php echo __('submit_breadcrumb_active'); ?></a></li>
        </ul>
    </div>
</div>

<div class="table-data">
    <div class="order">
        <div class="head">
            <h3><?php echo __('submit_form_title'); ?></h3>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-sm relative" role="alert">
                <p class="font-bold"><?php echo __('submit_alert_success', 'Success'); ?></p>
                <p><?php echo $_SESSION['success']; ?></p>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm relative" role="alert">
                <p class="font-bold"><?php echo __('submit_alert_error', 'Error'); ?></p>
                <p><?php echo $_SESSION['error']; ?></p>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form action="../controllers/complaint_action.php" method="POST" enctype="multipart/form-data" style="width: 100%;">
            <input type="hidden" name="action" value="submit_complaint">

            <!-- SECTION 1: Caller Information -->
            <div style="margin-bottom: 30px; background: var(--light); padding: 20px; border-radius: 12px; border: 1px solid var(--grey);">
                <h4 style="font-weight: 600; margin-bottom: 20px; color: var(--blue); display: flex; align-items: center; gap: 10px;">
                    <i class='bx bxs-user-voice'></i> <?php echo __('submit_section_caller'); ?>
                </h4>

                <div id="callerInfoFields" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; transition: opacity 0.3s; pointer-events: auto;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">
                            <?php echo __('submit_fullname'); ?> <span class="req-star" style="color: var(--red);">*</span>
                        </label>
                        <input type="text" name="guest_name" placeholder="<?php echo __('submit_caller_name_placeholder'); ?>" required
                            style="width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--dark-grey); outline: none; transition: all 0.3s; background: var(--light); color: var(--dark);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">
                            <?php echo __('submit_caller_occupation'); ?> <span class="req-star" style="color: var(--red);">*</span>
                        </label>
                        <div style="position: relative;">
                            <select name="guest_occupation" id="guestOccupationSelect" onchange="toggleGuestOccupation()" required
                                style="width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--dark-grey); outline: none; transition: all 0.3s; background: var(--light); color: var(--dark); appearance: none;">
                                <option value=""><?php echo __('submit_select_occupation_default'); ?></option>
                                <?php foreach ($occupation_options as $val => $label): ?>
                                    <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class='bx bx-chevron-down' style="position: absolute; right: 15px; top: 15px; pointer-events: none; color: var(--dark);"></i>
                            
                            <div id="guestOtherOccupationDiv" class="hidden" style="margin-top: 10px;">
                                <input type="text" name="guest_occupation_other" placeholder="<?php echo __('submit_occupation_other_placeholder'); ?>"
                                    style="width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--dark-grey); outline: none; transition: all 0.3s; background: var(--light); color: var(--dark);">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">
                            <?php echo __('submit_phone'); ?> <span class="req-star" style="color: var(--red);">*</span>
                        </label>
                        <input type="text" name="guest_phone" placeholder="<?php echo __('submit_caller_phone_placeholder'); ?>" required
                             style="width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--dark-grey); outline: none; transition: all 0.3s; background: var(--light); color: var(--dark);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">
                            <?php echo __('submit_email'); ?> <span class="req-star" style="color: var(--red);">*</span>
                        </label>
                        <input type="email" name="guest_email" placeholder="<?php echo __('submit_caller_email_placeholder'); ?>" required
                             style="width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--dark-grey); outline: none; transition: all 0.3s; background: var(--light); color: var(--dark);">
                    </div>
                </div>
                
                 <div class="mt-4 flex items-center justify-between p-4 rounded-md" style="margin-top: 25px; background: rgba(227, 242, 253, 0.3); border: 1px solid var(--blue); color: var(--dark); border-radius: 8px;">
                    <div>
                        <span class="block text-sm font-bold" style="color: var(--blue);"><?php echo __('submit_caller_hide_identity'); ?></span>
                        <span class="block text-xs" style="color: var(--dark-grey);"><?php echo __('submit_caller_hide_identity_hint'); ?></span>
                    </div>

                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_anonymous" id="isAnonymousCheckbox" value="1" class="sr-only peer" onchange="toggleAnonymous()">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium" style="margin-left: 10px; color: var(--dark); font-weight: 700;"><?php echo __('submit_caller_anonymous'); ?></span>
                    </label>
                </div>
            </div>

            <!-- SECTION 2: Complaint Details -->
            <div style="margin-bottom: 30px; background: var(--light); padding: 20px; border-radius: 12px; border: 1px solid var(--grey);">
                <h4 style="font-weight: 600; margin-bottom: 20px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                    <i class='bx bxs-detail'></i> <?php echo __('submit_section_2'); ?>
                </h4>

                <!-- Type Selection -->
                <div class="mb-8" style="margin-bottom: 25px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 10px;">
                        <?php echo __('submit_type_label'); ?> <span style="color: var(--red);">*</span>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <label class="cursor-pointer">
                            <input type="radio" name="complaint_type" value="Complaint" checked class="peer sr-only">
                            <div class="p-4 border-2 border-gray-200 rounded-xl hover:border-orange-400 peer-checked:border-orange-600 peer-checked:bg-orange-50 transition-all group" style="border: 2px solid var(--grey); border-radius: 12px; padding: 15px; transition: all 0.2s;">
                                <div class="flex items-center gap-3" style="display: flex; align-items: center; gap: 15px;">
                                    <div class="p-2 bg-orange-100 text-orange-600 rounded-lg" style="background: rgba(255, 243, 224, 0.5); color: var(--orange); padding: 10px; border-radius: 8px;">
                                        <i class='bx bxs-megaphone text-xl' style="font-size: 24px;"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold" style="font-weight: 700; color: var(--dark); margin: 0;"><?php echo __('submit_type_complaint'); ?></h4>
                                        <p class="text-xs" style="font-size: 12px; color: var(--dark-grey); margin: 0;"><?php echo __('submit_type_complaint_desc'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </label>

                        <label class="cursor-pointer">
                            <input type="radio" name="complaint_type" value="Suggestion" class="peer sr-only">
                            <div class="p-4 border-2 border-gray-200 rounded-xl hover:border-blue-400 peer-checked:border-blue-600 peer-checked:bg-blue-50 transition-all group" style="border: 2px solid var(--grey); border-radius: 12px; padding: 15px; transition: all 0.2s;">
                                <div class="flex items-center gap-3" style="display: flex; align-items: center; gap: 15px;">
                                    <div class="p-2 bg-blue-100 text-blue-600 rounded-lg" style="background: rgba(227, 242, 253, 0.5); color: var(--blue); padding: 10px; border-radius: 8px;">
                                        <i class='bx bxs-bulb text-xl' style="font-size: 24px;"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold" style="font-weight: 700; color: var(--dark); margin: 0;"><?php echo __('submit_type_suggestion'); ?></h4>
                                        <p class="text-xs" style="font-size: 12px; color: var(--dark-grey); margin: 0;"><?php echo __('submit_type_suggestion_desc'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </label>

                        <label class="cursor-pointer">
                            <input type="radio" name="complaint_type" value="Compliment" class="peer sr-only">
                            <div class="p-4 border-2 border-gray-200 rounded-xl hover:border-green-400 peer-checked:border-green-600 peer-checked:bg-green-50 transition-all group" style="border: 2px solid var(--grey); border-radius: 12px; padding: 15px; transition: all 0.2s;">
                                <div class="flex items-center gap-3" style="display: flex; align-items: center; gap: 15px;">
                                    <div class="p-2 bg-green-100 text-green-600 rounded-lg" style="background: rgba(232, 245, 233, 0.5); color: var(--green); padding: 10px; border-radius: 8px;">
                                        <i class='bx bxs-heart text-xl' style="font-size: 24px;"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold" style="font-weight: 700; color: var(--dark); margin: 0;"><?php echo __('submit_type_compliment'); ?></h4>
                                        <p class="text-xs" style="font-size: 12px; color: var(--dark-grey); margin: 0;"><?php echo __('submit_type_compliment_desc'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">
                        <?php echo __('submit_rel_org_label'); ?> <span style="color: var(--red);">*</span>
                    </label>
                    
                    <!-- Main Agencies Select -->
                    <div style="position: relative; margin-bottom: 15px;">
                        <select name="agencies" id="agenciesSelect" onchange="toggleRadioStations()" required
                                style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--dark-grey); outline: none; cursor: pointer; -webkit-appearance: none; background: var(--light); color: var(--dark);">
                            <option value=""><?php echo __('submit_select_agency_default'); ?></option>
                            <?php
                            // Fetch Agencies from DB
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

                            foreach ($agencies_list as $agency):
                            ?>
                                <option value="<?php echo htmlspecialchars($agency['name']); ?>" 
                                        data-id="<?php echo $agency['id']; ?>"
                                        data-has-sub="<?php echo $agency['has_sub_options']; ?>"
                                        data-is-other="<?php echo isset($agency['is_other']) ? $agency['is_other'] : 0; ?>">
                                    <?php echo htmlspecialchars($agency['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class='bx bx-chevron-down' style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--dark);"></i>
                    </div>

                    <!-- Radio Station Sub-Select -->
                    <div id="radioStationDiv" class="hidden" style="position: relative;">
                        <label style="display: block; font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">
                            <?php echo __('submit_select_radio_label'); ?> <span style="color: var(--red);">*</span>
                        </label>
                        <select name="radio_station" id="radioStationSelect" 
                                style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--dark-grey); outline: none; background: var(--light); color: var(--dark); cursor: pointer;">
                            <option value=""><?php echo __('submit_select_radio_default'); ?></option>
                        </select>
                    </div>

                    <!-- Other Agency Input (Hidden by default) -->
                    <div id="otherAgencyDiv" class="hidden" style="position: relative; margin-top: 15px;">
                        <label style="display: block; font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">
                            <?php echo __('submit_specify_label', 'Please Specify'); ?> <span style="color: var(--red);">*</span>
                        </label>
                        <input type="text" name="agencies_other" id="otherAgencyInput" placeholder="<?php echo __('submit_custom_agency_placeholder', 'Enter custom agency/organization...'); ?>"
                            style="width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--dark-grey); outline: none; transition: all 0.3s; background: var(--light); color: var(--dark);">
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">
                        <?php echo __('submit_subject_label'); ?> <span style="color: var(--red);">*</span>
                    </label>
                    <input type="text" name="subject" required
                        placeholder="<?php echo __('submit_subject_placeholder'); ?>"
                        style="width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--dark-grey); outline: none; transition: all 0.3s; background: var(--light); color: var(--dark);">
                </div>

                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">
                        <?php echo __('submit_desc_label'); ?> <span style="color: var(--red);">*</span>
                    </label>
                    <textarea name="description" rows="5" required
                        placeholder="<?php echo __('submit_desc_placeholder'); ?>"
                        style="width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--dark-grey); outline: none; resize: vertical; transition: all 0.3s; background: var(--light); color: var(--dark);"></textarea>
                </div>
            </div>

            <!-- SECTION 3: Attachments -->
            <div class="mb-8" style="background: var(--light); padding: 20px; border-radius: 12px; border: 1px solid var(--grey); margin-bottom: 30px;">
                 <h4 style="font-weight: 600; margin-bottom: 20px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                    <i class='bx bx-paperclip'></i> <?php echo __('submit_section_attachments'); ?>
                </h4>
                
                <p style="font-size: 13px; color: var(--dark-grey); margin-bottom: 15px;">
                    <?php echo __('submit_attachments_hint'); ?>
                </p>

                <div class="upload-grid" id="uploadGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                    <!-- "Add New" Card -->
                    <div class="upload-card add-card" onclick="document.getElementById('fileInput').click()" 
                         style="border: 2px dashed var(--blue); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 180px; cursor: pointer; transition: all 0.3s; background: rgba(227, 242, 253, 0.1);">
                        <div style="background: rgba(227, 242, 253, 0.8); padding: 12px; border-radius: 50%; color: var(--blue); margin-bottom: 10px;">
                            <i class='bx bx-cloud-upload' style="font-size: 30px;"></i>
                        </div>
                        <h5 style="margin: 0; font-weight: 600; color: var(--blue); font-size: 14px;"><?php echo __('submit_btn_add_document'); ?></h5>
                        <p style="margin: 5px 0 0; font-size: 11px; color: var(--dark-grey);"><?php echo __('submit_click_to_upload'); ?></p>
                    </div>
                </div>
                
                <input type="file" name="attachments[]" id="fileInput" class="hidden" multiple accept=".jpg,.jpeg,.png,.pdf" onchange="handleFileSelect(event)" style="display: none;">
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: flex-end; padding-top: 20px;">
                 <button type="submit" 
                        style="background: var(--blue); color: var(--light); padding: 12px 40px; border-radius: 30px; border: none; font-size: 16px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 10px rgba(60, 145, 230, 0.3); transition: all 0.3s;">
                    <?php echo __('submit_btn_submit'); ?>  <i class='bx bx-send' style="margin-left: 5px;"></i>
                </button>
            </div>

        </form>
    </div>
</div>

<script>
    function toggleAnonymous() {
        const isAnon = document.getElementById('isAnonymousCheckbox').checked;
        const callerInfoFields = document.getElementById('callerInfoFields');
        const guestNameInput = document.getElementsByName('guest_name')[0];
        const guestPhoneInput = document.getElementsByName('guest_phone')[0];
        const guestEmailInput = document.getElementsByName('guest_email')[0];
        const guestOccupationSelect = document.getElementById('guestOccupationSelect');
        const guestOccupationOther = document.getElementsByName('guest_occupation_other')[0];
        const otherDiv = document.getElementById('guestOtherOccupationDiv');
        const stars = callerInfoFields.querySelectorAll('.req-star');

        if (isAnon) {
            callerInfoFields.style.opacity = '0.5';
            callerInfoFields.style.pointerEvents = 'none';
            
            guestNameInput.required = false;
            guestPhoneInput.required = false;
            guestOccupationSelect.required = false;
            guestEmailInput.required = false;

            // Optional: Hide required stars
            stars.forEach(star => star.style.display = 'none');

            // Clear values
            guestNameInput.value = '';
            guestPhoneInput.value = '';
            guestEmailInput.value = '';
            guestOccupationSelect.value = '';
            guestOccupationOther.value = '';
            otherDiv.classList.add('hidden');
        } else {
            callerInfoFields.style.opacity = '1';
            callerInfoFields.style.pointerEvents = 'auto';
            
            guestNameInput.required = true;
            guestPhoneInput.required = true;
            guestOccupationSelect.required = true;
            guestEmailInput.required = true;

            stars.forEach(star => star.style.display = 'inline');
        }
    }

    const agencyOptionsMap = <?php echo json_encode($options_map); ?>;

    function toggleGuestOccupation() {
        const select = document.getElementById('guestOccupationSelect');
        const otherDiv = document.getElementById('guestOtherOccupationDiv');
        
        if (select.value === 'อื่น ๆ (Other)') {
            otherDiv.classList.remove('hidden');
            otherDiv.querySelector('input').focus();
        } else {
            otherDiv.classList.add('hidden');
            otherDiv.querySelector('input').value = ''; // Clear value
        }
    }

    function toggleRadioStations() {
        const select = document.getElementById('agenciesSelect');
        const selectedOption = select.options[select.selectedIndex];
        const hasSub = selectedOption.getAttribute('data-has-sub');
        const isOther = selectedOption.getAttribute('data-is-other');
        const agencyId = selectedOption.getAttribute('data-id');
        
        const radioDiv = document.getElementById('radioStationDiv');
        const subSelect = document.getElementById('radioStationSelect');

        const otherDiv = document.getElementById('otherAgencyDiv');
        const otherInput = document.getElementById('otherAgencyInput');

        if(isOther == '1') {
            otherDiv.classList.remove('hidden');
            otherInput.required = true;
        } else {
            otherDiv.classList.add('hidden');
            otherInput.required = false;
            otherInput.value = '';
        }
        
        if(hasSub == '1') {
            radioDiv.classList.remove('hidden');
            subSelect.required = true; // Make required when visible
            subSelect.innerHTML = '<option value=""><?php echo __('submit_select_radio_default'); ?></option>';
            if (agencyOptionsMap[agencyId]) {
                agencyOptionsMap[agencyId].forEach(opt => {
                    const optionFn = document.createElement('option');
                    optionFn.value = opt;
                    optionFn.textContent = opt;
                    subSelect.appendChild(optionFn);
                });
            }
        } else {
            radioDiv.classList.add('hidden');
            subSelect.required = false; // valid not to select if hidden
            subSelect.value = ''; 
        }
    }

    // File Upload Logic
    const fileInput = document.getElementById('fileInput');
    const uploadGrid = document.getElementById('uploadGrid');
    const dt = new DataTransfer();
    const MAX_FILES = 5;
    const ALLOWED_TYPES = [
        'image/jpeg', 
        'image/png', 
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // docx
    ];
    const MAX_SIZE = 5 * 1024 * 1024; // 5MB

    function handleFileSelect(e) {
        const files = e.target.files;
        let count = dt.items.length;
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // Check Limit
            if (count >= MAX_FILES) {
                alert("<?php echo __('submit_alert_max_files', 'You can only upload a maximum of 5 files.'); ?>");
                break;
            }

            // Check Type (Security)
            if (!ALLOWED_TYPES.includes(file.type)) {
                alert("<?php echo __('submit_alert_invalid_type', 'Invalid file type:'); ?> " + file.name + ". <?php echo __('submit_alert_allowed_types', 'Only JPG, PNG, PDF are allowed.'); ?>");
                continue;
            }

            // Check Size
             if (file.size > MAX_SIZE) {
                alert("<?php echo __('submit_alert_file_large', 'File too large:'); ?> " + file.name + ". <?php echo __('submit_alert_max_size', 'Max size is 5MB.'); ?>");
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
        renderGrid();
    }

    function renderGrid() {
        uploadGrid.innerHTML = '';
        
        for (let i = 0; i < dt.files.length; i++) {
            const file = dt.files[i];
            const card = document.createElement('div');
            card.className = 'upload-card';
            card.style.cssText = 'background: white; border-radius: 12px; height: 180px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; position: relative; display: flex; flex-direction: column; border: 1px solid var(--grey);';
            
            // Content Area (Image or Icon)
            const contentDiv = document.createElement('div');
            contentDiv.style.cssText = 'flex: 1; display: flex; align-items: center; justify-content: center; background: #f9fafb; overflow: hidden;';
            
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.file = file;
                img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
                
                const reader = new FileReader();
                reader.onload = (function(aImg) { return function(e) { aImg.src = e.target.result; }; })(img);
                reader.readAsDataURL(file);
                
                contentDiv.appendChild(img);
            } else {
                // PDF
                let iconClass = 'bx bxs-file';
                let iconColor = 'var(--dark-grey)';
                
                if (file.type.includes('pdf')) {
                    iconClass = 'bx bxs-file-pdf';
                    iconColor = 'var(--red)';
                }
                
                contentDiv.innerHTML = `<div style="text-align: center;"><i class='${iconClass}' style="font-size: 40px; color: ${iconColor};"></i><p style="margin: 5px 0 0; font-size: 11px; color: var(--dark-grey); padding: 0 10px; word-break: break-all;">${file.name}</p></div>`;
            }
            
            // Footer Area (Delete Button)
            const footerDiv = document.createElement('div');
            footerDiv.style.cssText = 'height: 40px; background: white; display: flex; align-items: center; justify-content: flex-end; padding: 0 10px; border-top: 1px solid var(--grey);';
            
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.onclick = function() { removeFile(i); };
            delBtn.style.cssText = 'background: #fee2e2; border: none; border-radius: 4px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #ef4444;';
            delBtn.innerHTML = "<i class='bx bxs-trash'></i>";
            
            footerDiv.appendChild(delBtn);
            
            card.appendChild(contentDiv);
            card.appendChild(footerDiv);
            
            uploadGrid.appendChild(card);
        }
        
        // Show "Add New" button only if less than limit
        if (dt.items.length < MAX_FILES) {
            const addCard = document.createElement('div');
            addCard.className = 'upload-card add-card';
            addCard.onclick = function() { document.getElementById('fileInput').click(); };
            addCard.style.cssText = 'border: 2px dashed var(--blue); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 180px; cursor: pointer; transition: all 0.3s; background: rgba(227, 242, 253, 0.1);';
            addCard.innerHTML = `
                <div style="background: rgba(227, 242, 253, 0.8); padding: 12px; border-radius: 50%; color: var(--blue); margin-bottom: 10px;">
                    <i class='bx bx-cloud-upload' style="font-size: 30px;"></i>
                </div>
                <h5 style="margin: 0; font-weight: 600; color: var(--blue); font-size: 14px;"><?php echo __('submit_btn_add_document'); ?></h5>
                <p style="margin: 5px 0 0; font-size: 11px; color: var(--dark-grey);">${dt.items.length}/${MAX_FILES} <?php echo __('submit_uploaded_count'); ?></p>
            `;
            
            addCard.onmouseover = function() { this.style.background = 'rgba(227, 242, 253, 0.3)'; };
            addCard.onmouseout = function() { this.style.background = 'rgba(227, 242, 253, 0.1)'; };
            
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
