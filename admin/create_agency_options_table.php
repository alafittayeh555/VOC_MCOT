<?php
require_once 'config/database.php';
try {
    $db = Database::connect();
    $sql = "CREATE TABLE IF NOT EXISTS agency_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agency_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
    echo "Table 'agency_options' created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage(); 
}
?>
