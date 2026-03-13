<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default language
$default_lang = 'th';

// Check for language switch via query parameter
if (isset($_GET['lang'])) {
    $lang_code = $_GET['lang'];
    if ($lang_code == 'en' || $lang_code == 'th') {
        $_SESSION['lang'] = $lang_code;
        // Set cookie for 30 days
        setcookie('lang', $lang_code, time() + (86400 * 30), "/");
    }
}

// Determine current language
if (isset($_SESSION['lang'])) {
    $curr_lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['lang'])) {
    $curr_lang = $_COOKIE['lang'];
    $_SESSION['lang'] = $curr_lang;
} else {
    $curr_lang = $default_lang;
}

// Load language file
// Make sure $lang is global because this file might be included inside a function/method
global $lang;
$lang_file = __DIR__ . '/lang/' . $curr_lang . '.php';

if (file_exists($lang_file)) {
    include($lang_file);
} else {
    // Fallback to English if file missing
    include(__DIR__ . '/lang/en.php');
}

// Helper function to echo text safely
function __($key, $default = null)
{
    global $lang;
    if (isset($lang[$key])) {
        return $lang[$key];
    }
    return $default !== null ? $default : $key;
}

// Helper to translate occupation values stored in DB
function translate_occupation($occ)
{
    if (empty($occ)) return '-';
    $occ = trim($occ);
    
    // Map known DB values to language keys
    $map = [
        'นักเรียน (Student)' => 'occ_student',
        'นักศึกษา (University Student)' => 'occ_university_student',
        'ข้าราชการ (Government Officer)' => 'occ_government_officer',
        'พนักงานบริษัท (Employee)' => 'occ_employee',
        'พนักงานรัฐวิสาหกิจ (State Enterprise Employee)' => 'occ_state_enterprise',
        'ธุรกิจส่วนตัว (Business Owner)' => 'occ_business_owner',
        'ฟรีแลนซ์ (Freelancer)' => 'occ_freelancer',
        'ค้าขาย (Merchant)' => 'occ_merchant',
        'เกษตรกร (Farmer)' => 'occ_farmer',
        'วิศวกร (Engineer)' => 'occ_engineer',
        'แพทย์ (Doctor)' => 'occ_doctor',
        'พยาบาล (Nurse)' => 'occ_nurse',
        'ครู / อาจารย์ (Teacher / Lecturer)' => 'occ_teacher',
        'โปรแกรมเมอร์ / นักพัฒนา (Programmer / Developer)' => 'occ_programmer',
        'นักออกแบบ (Designer)' => 'occ_designer',
        'ช่างเทคนิค (Technician)' => 'occ_technician',
        'แม่บ้าน / พ่อบ้าน (Homemaker)' => 'occ_homemaker',
        'ว่างงาน (Unemployed)' => 'occ_unemployed',
        'เกษียณอายุ (Retired)' => 'occ_retired',
        'อื่น ๆ (Other)' => 'occ_other'
    ];
    
    // If the DB value is in our map, translate it. Otherwise, return the original.
    if (isset($map[$occ])) {
        return __($map[$occ]);
    }
    
    return htmlspecialchars($occ);
}
?>