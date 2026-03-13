<?php
// fix_db_column.php
require_once '../config/database.php';

try {
    $db = Database::connect();
    
    // Check if column exists in employees
    $check = $db->query("SHOW COLUMNS FROM employees LIKE 'profile_image'");
    if ($check->rowCount() == 0) {
        // Add the column
        $sql = "ALTER TABLE employees ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER email";
        $db->exec($sql);
        echo "Successfully added 'profile_image' column to 'employees' table.\n";
    } else {
        echo "'profile_image' column already exists in 'employees' table.\n";
    }

    // Check if column exists in users (just in case)
    $check_users = $db->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    if ($check_users->rowCount() == 0) {
        $sql = "ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER email";
        $db->exec($sql);
        echo "Successfully added 'profile_image' column to 'users' table.\n";
    } else {
        echo "'profile_image' column already exists in 'users' table.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
