<?php
require_once('../initialize_coreT2.php');

$search = 'admin';
echo "Searching for username LIKE '%$search%' in 'users' table...<br>";

$query = $conn->query("SELECT * FROM users WHERE username LIKE '%$search%' OR full_name LIKE '%$search%'");
if ($query->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th></tr>";
    while ($row = $query->fetch_assoc()) {
        echo "<tr><td>{$row['user_id']}</td><td>{$row['username']}</td><td>{$row['full_name']}</td><td>{$row['role']}</td><td>{$row['status']}</td></tr>";
    }
    echo "</table>";
}
else {
    echo "No matching users found in 'users' table.<br>";
}

echo "<h3>Searching in 'hr4_employees' table...</h3>";
$query = $conn->query("SELECT * FROM hr4_employees WHERE full_name LIKE '%$search%' OR hr4_employee_id LIKE '%$search%'");
if ($query->num_rows > 0) {
    echo "<table border='1'><tr><th>EMP ID</th><th>Full Name</th><th>Dept</th><th>Job</th></tr>";
    while ($row = $query->fetch_assoc()) {
        echo "<tr><td>{$row['hr4_employee_id']}</td><td>{$row['full_name']}</td><td>{$row['department']}</td><td>{$row['job_title']}</td></tr>";
    }
    echo "</table>";
}
else {
    echo "No matching employees found in 'hr4_employees' table.<br>";
}
?>
