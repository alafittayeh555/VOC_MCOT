<?php
require_once 'config/database.php';

try {
    $db = Database::connect();
    echo "Connected to database.\n";

    // Add columns if they don't exist
    // MySQL doesn't have "IF NOT EXISTS" for ADD COLUMN in standard syntax easily for one-liners without procedure, 
    // but we'll try catching the error or checking first.
    
    // file_name
    try {
        $db->exec("ALTER TABLE attachments ADD COLUMN file_name VARCHAR(255) DEFAULT NULL AFTER file_path");
        echo "Added file_name column.\n";
    } catch (PDOException $e) {
        echo "file_name column might already exist or error: " . $e->getMessage() . "\n";
    }

    // file_type
    try {
        $db->exec("ALTER TABLE attachments ADD COLUMN file_type VARCHAR(50) DEFAULT NULL AFTER file_name");
        echo "Added file_type column.\n";
    } catch (PDOException $e) {
        echo "file_type column might already exist or error: " . $e->getMessage() . "\n";
    }

    // file_size
    try {
        $db->exec("ALTER TABLE attachments ADD COLUMN file_size INT(11) DEFAULT 0 AFTER file_type");
        echo "Added file_size column.\n";
    } catch (PDOException $e) {
        echo "file_size column might already exist or error: " . $e->getMessage() . "\n";
    }

    echo "Migration completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
