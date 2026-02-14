<?php
require_once(__DIR__ . '/initialize_coreT2.php');

// Try to update the ENUM
$sql = "ALTER TABLE approval_requests MODIFY COLUMN request_type ENUM('profile_update', 'termination', 'removal') NOT NULL";
if ($conn->query($sql)) {
    echo "SUCCESS: Changed request_type to include 'removal'.\n";
}
else {
    // If ENUM fails (maybe it's not and ENUM), try to make it a VARCHAR if it's too short
    echo "ENUM UPDATE FAILED: " . $conn->error . "\n";
    $sql = "ALTER TABLE approval_requests MODIFY COLUMN request_type VARCHAR(50) NOT NULL";
    if ($conn->query($sql)) {
        echo "SUCCESS: Changed request_type to VARCHAR(50).\n";
    }
    else {
        echo "VARCHAR UPDATE FAILED: " . $conn->error . "\n";
    }
}
?>
