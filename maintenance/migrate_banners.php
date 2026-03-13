<?php
// migrate_banners.php
require_once '../config/database.php';

$db = Database::connect();

try {
    // Check if 'banners' table exists
    $bannersExists = false;
    try {
        $db->query("SELECT 1 FROM banners LIMIT 1");
        $bannersExists = true;
    } catch (PDOException $e) {
        $bannersExists = false;
    }

    // Check if 'images' table exists
    $imagesExists = false;
    try {
        $db->query("SELECT 1 FROM images LIMIT 1");
        $imagesExists = true;
    } catch (PDOException $e) {
        $imagesExists = false;
    }

    if ($bannersExists && !$imagesExists) {
        // Rename table
        $db->exec("RENAME TABLE banners TO images");
        echo "Successfully renamed table 'banners' to 'images'.\n";
    } elseif ($imagesExists) {
        echo "Table 'images' already exists.\n";
    } else {
        echo "Table 'banners' does not exist.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
