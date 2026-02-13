<?php
// create_test_user.php
require_once('initialize_coreT2.php');

$username = 'test_admin';
$password = 'TestAdmin123!';
$hash = password_hash($password, PASSWORD_DEFAULT);
$email = 'test@example.com';
$fullname = 'Test Administrator';
$role = 'Admin';
$status = 'Active';

// Check if user exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo "User $username already exists.<br>";
}
else {
    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, email, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $username, $hash, $email, $fullname, $role, $status);
    if ($stmt->execute()) {
        echo "User $username created successfully with password $password.<br>";
    }
    else {
        echo "Error creating user: " . $conn->error . "<br>";
    }
}
?>
