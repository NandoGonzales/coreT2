<?php
require_once('../initialize_coreT2.php');
echo "<h2>Audit & Permission Logs (Last 10)</h2>";

$query = $conn->query("SELECT * FROM permission_logs ORDER BY action_time DESC LIMIT 10");
echo "<h3>Permission Logs</h3><table border='1'><tr><th>User</th><th>Module</th><th>Action</th><th>Status</th><th>Time</th></tr>";
while ($row = $query->fetch_assoc()) {
    echo "<tr><td>{$row['user_id']}</td><td>{$row['module_name']}</td><td>{$row['action_name']}</td><td>{$row['action_status']}</td><td>{$row['action_time']}</td></tr>";
}
echo "</table>";

$query = $conn->query("SELECT * FROM audit_trial ORDER BY timestamp DESC LIMIT 10");
echo "<h3>Audit Trial</h3><table border='1'><tr><th>User</th><th>Action</th><th>Module</th><th>Details</th><th>Time</th></tr>";
while ($row = $query->fetch_assoc()) {
    echo "<tr><td>{$row['user_id']}</td><td>{$row['action_type']}</td><td>{$row['module']}</td><td>{$row['details']}</td><td>{$row['timestamp']}</td></tr>";
}
echo "</table>";
?>
