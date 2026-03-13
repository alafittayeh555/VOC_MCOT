<?php
// add_role.php
require_once '../config/database.php';

try {
    $db = Database::connect();
    
    // Check if role exists
    $check = $db->prepare("SELECT * FROM roles WHERE id = 5");
    $check->execute();
    
    if ($check->rowCount() == 0) {
        // Add the role
        $sql = "INSERT INTO roles (id, role_name) VALUES (5, 'Employee')";
        $db->exec($sql);
        echo "Successfully added 'Employee' role (ID 5) to 'roles' table.\n";
    } else {
        echo "'Employee' role (ID 5) already exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
