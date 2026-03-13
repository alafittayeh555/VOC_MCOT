<?php
require_once '../config/database.php';
$db = Database::connect();

try {
    // 1. Fetch all images
    $stmt = $db->query("SELECT id, image_path FROM images");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($images) . " images.<br>";

    $count = 0;
    foreach ($images as $image) {
        $id = $image['id'];
        $oldPath = $image['image_path'];
        // Check if path contains old directory
        if (strpos($oldPath, 'assets/uploads/banners/') !== false) {
            $newPath = str_replace('assets/uploads/banners/', 'assets/img/banner/', $oldPath);
            
            // Check if file exists in old path and move it? 
            // This script assumes files might be moved already or we just update DB.
            
            // Update DB
            $update = $db->prepare("UPDATE images SET image_path = ? WHERE id = ?");
            $update->execute([$newPath, $id]);
            $count++;
            echo "Updated ID {$id}: $oldPath -> $newPath<br>";
        }
    }
    echo "Total updated: $count";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
