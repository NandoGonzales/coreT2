<?php
require_once(__DIR__ . '/initialize_coreT2.php');
$sql = "ALTER TABLE approval_requests MODIFY COLUMN request_type VARCHAR(50) NOT NULL";
if ($conn->query($sql)) {
    echo "SUCCESS: request_type updated to VARCHAR(50)\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}

$res = $conn->query("DESC approval_requests");
while ($row = $res->fetch_assoc()) {
    if ($row['Field'] == 'request_type') {
        echo "CURRENT_TYPE: " . $row['Type'] . "\n";
    }
}
?>