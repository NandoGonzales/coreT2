<?php
require_once(__DIR__ . '/initialize_coreT2.php');
echo "Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "\n";
$res = $conn->query("SHOW CREATE TABLE approval_requests");
if ($res) {
    print_r($res->fetch_assoc());
} else {
    echo "Error: " . $conn->error;
}
?>