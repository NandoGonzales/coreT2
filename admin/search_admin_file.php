<?php
require_once('../initialize_coreT2.php');
ob_start();

$search = 'admin';
echo "Searching for username LIKE '%$search%' in 'users' table...\n";

$query = $conn->query("SELECT * FROM users WHERE username LIKE '%$search%' OR full_name LIKE '%$search%'");
if ($query->num_rows > 0) {
    echo "ID | Username | Full Name | Role | Status\n";
    while ($row = $query->fetch_assoc()) {
        echo "{$row['user_id']} | {$row['username']} | {$row['full_name']} | {$row['role']} | {$row['status']}\n";
    }
}
else {
    echo "No matching users found in 'users' table.\n";
}

echo "\nSearching in 'hr4_employees' table...\n";
$query = $conn->query("SELECT * FROM hr4_employees WHERE full_name LIKE '%$search%' OR hr4_employee_id LIKE '%$search%'");
if ($query->num_rows > 0) {
    echo "EMP ID | Full Name | Dept | Job\n";
    while ($row = $query->fetch_assoc()) {
        echo "{$row['hr4_employee_id']} | {$row['full_name']} | {$row['department']} | {$row['job_title']}\n";
    }
}
else {
    echo "No matching employees found in 'hr4_employees' table.\n";
}

$output = ob_get_clean();
file_put_contents('search_result.txt', $output);
echo "Result written to search_result.txt\n";
?>
