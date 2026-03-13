<?php
// verify_images_type.php
require_once '../config/database.php';

$db = Database::connect();

try {
    // 1. Check 'type' column
    $stmt = $db->query("SHOW COLUMNS FROM images LIKE 'type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column) {
        echo "Column 'type' exists in 'images' table. Type: " . $column['Type'] . "\n";
    } else {
        echo "Column 'type' DOES NOT exist in 'images' table.\n";
    }

    // 2. Check if default value works (insert without type)
    $db->exec("INSERT INTO images (title, is_active) VALUES ('Test Banner Default', 0)");
    $id = $db->lastInsertId();
    $stmt = $db->prepare("SELECT type FROM images WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Inserted record without type has type: " . $row['type'] . "\n";
    
    // Clean up
    $db->exec("DELETE FROM images WHERE id = $id");

    // 3. Scan admin/settings_index.php for type handling
    $content = file_get_contents('admin/settings_index.php');
    if (strpos($content, 'class="type-tabs"') !== false) {
        echo "Confirmed 'settings_index.php' has type tabs.\n";
    } else {
        echo "WARNING: 'settings_index.php' missing type tabs.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
