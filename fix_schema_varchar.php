<?php
require_once(__DIR__ . '/initialize_coreT2.php');
$res = $conn->query("ALTER TABLE approval_requests MODIFY COLUMN request_type VARCHAR(50) NOT NULL");
if ($res) {
    echo "SUCCESS: Column request_type modified to VARCHAR(50)\n";
}
else {
    echo "ERROR: " . $conn->error . "\n";
}
?>
