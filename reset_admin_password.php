<?php
require_once 'config/database.php';

try {
    $pdo = Database::connect();
    $password = 'Password123';
    // Use PASSWORD_DEFAULT covering both bcrypt and future algo updates
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $username = 'admin';

    // Update employees table
    $stmt = $pdo->prepare("UPDATE employees SET password_hash = :hash WHERE username = :username");
    $stmt->execute([':hash' => $hash, ':username' => $username]);

    if ($stmt->rowCount() > 0) {
        echo "Password reset successfully for user: $username\n";
    } else {
        echo "No changes made for user: $username. User may not exist or password is already set to this value.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
