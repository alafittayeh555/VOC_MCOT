<?php
require_once 'config/database.php';
try {
    $db = Database::connect();
    // Check if column exists strictly to avoid error on rerun, or just try-catch ALTER
    $db->exec("ALTER TABLE agencies ADD COLUMN has_sub_options TINYINT(1) DEFAULT 0");
    echo "Column 'has_sub_options' added successfully.";
} catch (PDOException $e) {
    // 42S21 is Column already exists in some drivers, or generic error
    echo "Note/Error: " . $e->getMessage(); 
}
?>
