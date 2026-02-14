<?php
require_once(__DIR__ . '/initialize_coreT2.php');
$output = "";
$res = $conn->query("SELECT COUNT(*) FROM user_roles");
$output .= "user_roles count: " . ($res ? $res->fetch_row()[0] : $conn->error) . "\n";
$res = $conn->query("SELECT COUNT(*) FROM role_permissions");
$output .= "role_permissions count: " . ($res ? $res->fetch_row()[0] : $conn->error) . "\n";
$res = $conn->query("SELECT * FROM user_roles");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $output .= "role_id: {$row['role_id']}, role_name: {$row['role_name']}\n";
    }
}
file_put_contents(__DIR__ . '/db_check_results.txt', $output);
echo "DONE";
?>