<?php
require_once(__DIR__ . '/initialize_coreT2.php');
$output = "Verification Results:\n";
$res = $conn->query("SELECT COUNT(*) FROM user_roles");
$output .= "user_roles count: " . ($res ? $res->fetch_row()[0] : $conn->error) . "\n";
$res = $conn->query("SELECT COUNT(*) FROM role_permissions");
$output .= "role_permissions count: " . ($res ? $res->fetch_row()[0] : $conn->error) . "\n";
file_put_contents(__DIR__ . '/verify_seeding.txt', $output);
echo "VERIFIED";
?>