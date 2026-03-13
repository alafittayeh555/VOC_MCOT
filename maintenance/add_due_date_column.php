<?php
// add_due_date_column.php
require_once '../config/database.php';

try {
    $db = Database::connect();
    
    // Check if column exists
    $check = $db->query("SHOW COLUMNS FROM complaints LIKE 'due_date'");
    if ($check->rowCount() == 0) {
        // Add column
        $sql = "ALTER TABLE complaints ADD COLUMN due_date DATE DEFAULT NULL AFTER status";
        $db->exec($sql);
        echo "Successfully added 'due_date' column to 'complaints' table.";
    } else {
        echo "'due_date' column already exists.";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
