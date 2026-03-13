<?php
require_once 'config/database.php';
$db = Database::connect();
$cols = $db->query("DESCRIBE complaints")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . "\n";
}
?>
