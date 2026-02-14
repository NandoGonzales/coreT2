<?php
require_once(__DIR__ . '/initialize_coreT2.php');
echo "<h1>Role Permissions Diagnostic</h1>";

echo "<h2>user_roles</h2>";
$res = $conn->query("SELECT * FROM user_roles");
if ($res) {
    echo "<table border=1><tr><th>role_id</th><th>role_name</th></tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['role_id']}</td><td>{$row['role_name']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

echo "<h2>role_permissions (count)</h2>";
$res = $conn->query("SELECT COUNT(*) FROM role_permissions");
echo "<p>Total: " . ($res ? $res->fetch_row()[0] : "Error " . $conn->error) . "</p>";

echo "<h2>role_permissions (sample)</h2>";
$res = $conn->query("SELECT * FROM role_permissions LIMIT 10");
if ($res) {
    echo "<table border=1><tr><th>perm_id</th><th>role_id</th><th>module_name</th></tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['perm_id']}</td><td>{$row['role_id']}</td><td>{$row['module_name']}</td></tr>";
    }
    echo "</table>";
}

echo "<h2>Users with role_id</h2>";
$res = $conn->query("SELECT role, role_id, COUNT(*) FROM users GROUP BY role, role_id");
if ($res) {
    echo "<table border=1><tr><th>role (text)</th><th>role_id</th><th>count</th></tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['role']}</td><td>{$row['role_id']}</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
}
?>