<?php
// verify_all_attachments.php
require_once '../config/database.php';

$db = Database::connect();
$stmt = $db->query("SELECT * FROM attachments");
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Verifying Attachments</h2>";
echo "<p>Total Attachments: " . count($attachments) . "</p>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>ID</th><th>File Name</th><th>File Path (DB)</th><th>Calculated Path</th><th>Status</th></tr>";

$missing_count = 0;

foreach ($attachments as $att) {
    $filePath = $att['file_path'];
    $calculatedPath = '';
    
    // Logic mirroring the application
    if (strpos($filePath, 'assets/') === false) {
        $calculatedPath = 'assets/uploads/complaints-file/' . $filePath;
    } else {
        $calculatedPath = $filePath;
    }
    
    // Check if file exists (relative to script execution, which is root)
    if (file_exists($calculatedPath)) {
        $status = "<span style='color:green;'>OK</span>";
    } else {
        $status = "<span style='color:red;'>MISSING</span>";
        $missing_count++;
    }
    
    echo "<tr>";
    echo "<td>{$att['id']}</td>";
    echo "<td>{$att['file_name']}</td>";
    echo "<td>{$att['file_path']}</td>";
    echo "<td>{$calculatedPath}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";
echo "<br><h3>Summary: " . ($missing_count == 0 ? "All files found." : "$missing_count files missing.") . "</h3>";
?>
