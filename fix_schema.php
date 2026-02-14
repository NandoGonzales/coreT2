<?php
require_once(__DIR__ . '/initialize_coreT2.php');
$qry = $conn->query("DESCRIBE approval_requests");
$fields = [];
while ($row = $qry->fetch_assoc()) {
    $fields[] = $row;
}
echo json_encode($fields, JSON_PRETTY_PRINT);

// Check if removal is in request_type ENUM (if it is ENUM)
foreach ($fields as $f) {
    if ($f['Field'] == 'request_type') {
        if (strpos($f['Type'], 'enum') !== false) {
            echo "\nFound ENUM type for request_type: " . $f['Type'] . "\n";
            if (strpos($f['Type'], 'removal') === false) {
                echo "Attempting to update ENUM to include 'removal'...\n";
                // Assuming current values are 'profile_update', 'termination' based on previous research
                $sql = "ALTER TABLE approval_requests MODIFY COLUMN request_type ENUM('profile_update', 'termination', 'removal') NOT NULL";
                if ($conn->query($sql)) {
                    echo "Update successful!\n";
                }
                else {
                    echo "Update failed: " . $conn->error . "\n";
                }
            }
            else {
                echo "'removal' already exists in ENUM.\n";
            }
        }
        else {
            echo "\nrequest_type is not ENUM: " . $f['Type'] . "\n";
        // If it's a VARCHAR(20) or something, 'removal' might still fit, but 'termination' is 11 chars, 'profile_update' is 14. 
        // 'removal' is only 7. Truncation usually means the field isn't long enough or it's an ENUM.
        }
    }
}
?>
