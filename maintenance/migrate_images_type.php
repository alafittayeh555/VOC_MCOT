<?php
// migrate_images_type.php
require_once '../config/database.php';

$db = Database::connect();

try {
    // Check if 'type' column exists
    $stmt = $db->query("SHOW COLUMNS FROM images LIKE 'type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        // Add 'type' column
        $sql = "ALTER TABLE images ADD COLUMN type ENUM('banner', 'logo', 'profile', 'other') DEFAULT 'banner' AFTER image_path";
        $db->exec($sql);
        echo "Successfully added column 'type' to 'images' table.\n";
    } else {
        echo "Column 'type' already exists in 'images' table.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
