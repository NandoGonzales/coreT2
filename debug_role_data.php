<?php
require_once(__DIR__ . '/initialize_coreT2.php');
$sql = "SELECT COUNT(*) FROM role_permissions";
$res = $conn->query($sql);
$count = $res->fetch_row()[0];
echo "role_permissions count: " . $count . "\n";

if ($count == 0) {
    echo "Table is empty. Checking user_roles...\n";
    $res = $conn->query("SELECT * FROM user_roles");
    while ($row = $res->fetch_assoc()) {
        echo "Found role: " . $row['role_name'] . " (ID: " . $row['role_id'] . ")\n";
    }
} else {
    echo "Sample data:\n";
    $res = $conn->query("SELECT * FROM role_permissions LIMIT 5");
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
?>