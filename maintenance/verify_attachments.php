<?php
// verify_attachments.php
require_once '../config/database.php';
$db = Database::connect();

// Mock a file upload simulation by inserting a record manually 
// and checking if the logic *would* place it correctly if we used the controllers.
// Actually, we can just check the code changes by reading the files? 
// Or we can try to post to the controller using curl?
// Simpler: Just check if the directories exist and are writable, 
// and maybe verify the last inserted attachment path if possible (but we didn't insert any via UI).

echo "Checking directories...\n";
$dirs = ['assets/file/user', 'assets/file/emp'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "Directory '$dir' exists.\n";
        if (is_writable($dir)) {
             echo "Directory '$dir' is writable.\n";
        } else {
             echo "WARNING: Directory '$dir' is NOT writable.\n";
        }
    } else {
        echo "WARNING: Directory '$dir' does NOT exist.\n";
    }
}

// Check recent attachments in DB to see if any use the new path format
// (This will only show results if someone used the system, which hasn't happened yet probably)
try {
    $stmt = $db->query("SELECT * FROM attachments ORDER BY id DESC LIMIT 5");
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($attachments) {
        echo "\nRecent Attachments in DB:\n";
        foreach ($attachments as $att) {
            echo "ID: " . $att['id'] . " | Path: " . $att['file_path'] . "\n";
        }
    } else {
        echo "\nNo attachments found in DB.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
