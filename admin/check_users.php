<?php
require_once('../initialize_coreT2.php');
$query = $conn->query("SELECT count(*) as count FROM users");
$row = $query->fetch_assoc();
echo "User Count: " . $row['count'] . "\n";

$query = $conn->query("SELECT user_id, username, role, status FROM users LIMIT 10");
while ($row = $query->fetch_assoc()) {
    echo "ID: " . $row['user_id'] . " | User: " . $row['username'] . " | Role: " . $row['role'] . " | Status: " . $row['status'] . "\n";
}
?>
