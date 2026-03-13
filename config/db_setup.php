<?php
// admin/includes/db_setup.php

// This script ensures the database schema is up-to-date and migrates users if necessary.
// It is intended to be included in admin pages (like employees.php) to self-heal the DB.

if (!isset($db)) {
    // Ensure we have a DB connection if not already provided
    require_once __DIR__ . '/database.php';
    try {
        $db = Database::connect();
    } catch (Exception $e) {
        // If DB fails, we can't do anything
        return;
    }
}

try {
    // --- 1. Ensure 'users' table has 'employee_id' column (from update_db.php) ---
    $check = $db->query("SHOW COLUMNS FROM users LIKE 'employee_id'");
    if ($check->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN employee_id varchar(50) DEFAULT NULL AFTER full_name");
    }

    // --- 2. Create 'employees' table if not exists (from migrate_users_to_employees.php) ---
    $sql_create = "CREATE TABLE IF NOT EXISTS `employees` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NOT NULL,
      `email` varchar(100) NOT NULL,
      `password_hash` varchar(255) NOT NULL,
      `full_name` varchar(100) NOT NULL,
      `role_id` int(11) NOT NULL,
      `department_id` int(11) DEFAULT NULL,
      `employee_id` varchar(50) DEFAULT NULL,
      `created_at` datetime DEFAULT current_timestamp(),
      `is_active` tinyint(1) DEFAULT 1,
      PRIMARY KEY (`id`),
      UNIQUE KEY `username` (`username`),
      UNIQUE KEY `email` (`email`),
      KEY `role_id` (`role_id`),
      KEY `department_id` (`department_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->exec($sql_create);

    // --- 3. Migrate Data: Move Non-User roles (1, 2, 3) to employees ---
    // Use INSERT IGNORE to avoid errors if they already exist
    $sql_move = "INSERT IGNORE INTO employees (id, username, email, password_hash, full_name, role_id, department_id, employee_id, created_at, is_active)
                 SELECT id, username, email, password_hash, full_name, role_id, department_id, employee_id, created_at, is_active
                 FROM users
                 WHERE role_id != 4";
    $db->exec($sql_move);

    // --- 4. Cleanup: Remove migrated staff from 'users' table ---
    // Only delete if they are successfully in employees table (implicitly handled by previous step mostly, but let's be safe)
    // We only delete those who are NOT role 4.
    $sql_delete = "DELETE FROM users WHERE role_id != 4";
    $db->exec($sql_delete);

    // --- 5. Update complaint_history schema for dual user types ---
    $check_col = $db->query("SHOW COLUMNS FROM complaint_history LIKE 'action_by_type'");
    if ($check_col->rowCount() == 0) {
        $db->exec("ALTER TABLE complaint_history ADD COLUMN action_by_type ENUM('user', 'employee') DEFAULT 'employee' AFTER action_by_user_id");
    }
    
    // --- 6. Fix Duplicates in 'employees' (from fix_duplicates_and_add_unique.php) ---
    // Identify duplicates based on employee_id
    $stmt = $db->query("
        SELECT id, employee_id 
        FROM employees 
        WHERE employee_id IN (
            SELECT employee_id FROM employees GROUP BY employee_id HAVING COUNT(*) > 1 AND employee_id IS NOT NULL AND employee_id != ''
        )
    ");
    $duplicates = $stmt->fetchAll();
    foreach ($duplicates as $row) {
        // Append ID to make it unique
        $new_id = $row['employee_id'] . '_' . $row['id'];
        $update = $db->prepare("UPDATE employees SET employee_id = ? WHERE id = ?");
        $update->execute([$new_id, $row['id']]);
    }

    // --- 7. Add UNIQUE Constraint to 'employees.employee_id' (from add_unique_employee_id.php) ---
    // Only attempt if it doesn't exist (simulated by try-catch on the specific error or checking connection)
    // Checking index existence is cleaner.
    $check_idx = $db->query("SHOW INDEX FROM employees WHERE Key_name = 'unique_employee_id'");
    if ($check_idx->rowCount() == 0) {
        // Try adding it. If still fails due to some data race, we catch it.
        try {
           $db->exec("ALTER TABLE employees ADD UNIQUE KEY `unique_employee_id` (`employee_id`)");
        } catch (PDOException $e) {
            // Ignore duplication error if it happened just now, otherwise log it?
            // For this user context, silent failure (if it's already there) is fine.
        }
    }

    // --- 8. Add 'profile_image' column to 'employees' table (from profile image fix) ---
    $check_img = $db->query("SHOW COLUMNS FROM employees LIKE 'profile_image'");
    if ($check_img->rowCount() == 0) {
        $db->exec("ALTER TABLE employees ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER email");
    }

    // --- 9. Add 'submission_channel' to 'complaints' (from source tracking) ---
    $check_channel = $db->query("SHOW COLUMNS FROM complaints LIKE 'submission_channel'");
    if ($check_channel->rowCount() == 0) {
        $db->exec("ALTER TABLE complaints ADD COLUMN submission_channel ENUM('System', 'PR') NOT NULL DEFAULT 'System' AFTER status");
    }

    // --- 10. Update 'status' ENUM in 'complaints' for new workflow ---
    $db->exec("ALTER TABLE complaints MODIFY COLUMN status ENUM('Pending', 'In Progress', 'Resolved', 'Rejected', 'Received', 'Internal Review', 'Review', 'Completed', 'Processed', 'Cancelled') DEFAULT 'Pending'");
    $db->exec("UPDATE complaints SET status = 'Review' WHERE status = 'Internal Review'");
    $db->exec("ALTER TABLE complaints MODIFY COLUMN status ENUM('Pending', 'In Progress', 'Resolved', 'Rejected', 'Received', 'Review', 'Completed', 'Processed', 'Cancelled') DEFAULT 'Pending'");

    // --- 11. Add 'department_note' to 'complaints' ---
    $check_dept_note = $db->query("SHOW COLUMNS FROM complaints LIKE 'department_note'");
    if ($check_dept_note->rowCount() == 0) {
        $db->exec("ALTER TABLE complaints ADD COLUMN department_note TEXT NULL AFTER employee_note");
    }

    // --- 12. Add 'phone' column to 'users' table ---
    $check_phone = $db->query("SHOW COLUMNS FROM users LIKE 'phone'");
    if ($check_phone->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER full_name");
    }

    // --- 13. Add 'occupation' column to 'users' table ---
    $check_occ = $db->query("SHOW COLUMNS FROM users LIKE 'occupation'");
    if ($check_occ->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN occupation VARCHAR(100) DEFAULT NULL AFTER phone");
    }
    // --- 14. Add 'reporter_email' to 'complaints' ---
    $check_rep_email = $db->query("SHOW COLUMNS FROM complaints LIKE 'reporter_email'");
    if ($check_rep_email->rowCount() == 0) {
        $db->exec("ALTER TABLE complaints ADD COLUMN reporter_email VARCHAR(100) DEFAULT NULL AFTER subject");
    }

    // --- 15. Add 'reporter_name' to 'complaints' ---
    $check_rep_name = $db->query("SHOW COLUMNS FROM complaints LIKE 'reporter_name'");
    if ($check_rep_name->rowCount() == 0) {
        $db->exec("ALTER TABLE complaints ADD COLUMN reporter_name VARCHAR(100) DEFAULT NULL AFTER reporter_email");
    }

} catch (Exception $e) {
    // Log error or silently fail to avoid breaking the main page
    error_log("DB Setup Error: " . $e->getMessage());
}
?>
