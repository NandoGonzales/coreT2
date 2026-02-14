<?php
require_once(__DIR__ . '/initialize_coreT2.php');
$qry = $conn->query("SELECT * FROM system_info WHERE meta_field = 'admin_api_key'");
if ($qry->num_rows > 0) {
    echo "admin_api_key found: " . $qry->fetch_assoc()['meta_value'];
}
else {
    echo "admin_api_key not found. Creating default: admin123";
    $conn->query("INSERT INTO system_info (meta_field, meta_value) VALUES ('admin_api_key', 'admin123')");
}
?>
