<?php
// verify_images.php
require_once '../config/database.php';

$db = Database::connect();

try {
    // 1. Check if table 'images' exists and has columns
    $stmt = $db->query("SHOW COLUMNS FROM images");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($columns) {
        echo "Table 'images' exists with columns: " . implode(", ", $columns) . "\n";
    } else {
        echo "Table 'images' does not exist or has no columns.\n";
        exit;
    }

    // 2. Select data
    $stmt = $db->query("SELECT * FROM images LIMIT 1");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        echo "Successfully fetched data from 'images' table.\n";
        print_r($data);
    } else {
        echo "Table 'images' is empty.\n";
    }
    
    // 3. Check for specific problematic files
    $files = ['index.php', 'admin/settings_index.php'];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (strpos($content, 'banners') !== false && strpos($content, '$banners') === false && strpos($content, 'banners/') === false) {
             // We allow 'banners' in comments or maybe paths if not changed, but specifically looking for SQL or logic
             // Simple grep check might give false positives, but let's see.
             // Actually, '$banners' variable name was also changed to '$images' in most places, but let's checking for table usage.
             if (preg_match('/FROM\s+banners/i', $content) || preg_match('/INSERT\s+INTO\s+banners/i', $content) || preg_match('/UPDATE\s+banners/i', $content)) {
                 echo "WARNING: Potential leftover table reference 'banners' in $file\n";
             } else {
                 echo "File $file seems clean of 'banners' table SQL usage.\n";
             }
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
