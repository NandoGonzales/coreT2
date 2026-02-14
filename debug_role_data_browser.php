<?php
require_once(__DIR__ . '/initialize_coreT2.php');
echo "<h1>Role Permissions Diagnostic</h1>";

$sql = "SELECT COUNT(*) FROM role_permissions";
$res = $conn->query($sql);
$count = $res ? $res->fetch_row()[0] : "Error " . $conn->error;
echo "<p>role_permissions count: " . $count . "</p>";

if ($count == 0) {
    echo "<p>Table is empty. Checking user_roles...</p>";
    $res = $conn->query("SELECT * FROM user_roles");
    echo "<table border=1><tr><th>role_id</th><th>role_name</th></tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['role_id']}</td><td>{$row['role_name']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<h3>Sample data:</h3>";
    $res = $conn->query("SELECT * FROM role_permissions LIMIT 5");
    echo "<pre>";
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
}
?>