<?php
require_once 'config/database.php';

try {
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT id, username, email, role_id, is_active FROM employees WHERE username = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "User found:\n";
        print_r($user);
    } else {
        echo "User 'admin' not found.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
