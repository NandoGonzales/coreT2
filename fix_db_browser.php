<?php
require_once(__DIR__ . '/initialize_coreT2.php');
echo "<h1>DB FIX</h1>";
$sql = "ALTER TABLE approval_requests MODIFY COLUMN request_type VARCHAR(50) NOT NULL";
if ($conn->query($sql)) {
    echo "<p style='color:green'>SUCCESS: request_type updated</p>";
} else {
    echo "<p style='color:red'>ERROR: " . $conn->error . "</p>";
}
$res = $conn->query("DESC approval_requests");
echo "<table border=1><tr><th>Field</th><th>Type</th></tr>";
while ($row = $res->fetch_assoc()) {
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
}
echo "</table>";
?>