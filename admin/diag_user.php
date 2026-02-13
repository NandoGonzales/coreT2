<?php
require_once('../initialize_coreT2.php');

header('Content-Type: text/plain');

$username = 'admin';
$stmt = $conn->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    echo "User found: " . $user['username'] . "\n";
    echo "Status: " . $user['status'] . "\n";
    echo "Password Hash: " . $user['password_hash'] . "\n";
    echo "Full Name: " . $user['full_name'] . "\n";
    echo "Email: " . $user['email'] . "\n";

    // Test common passwords
    $test_passwords = ['Admin@123', 'admin123', 'password', '123456'];
    foreach ($test_passwords as $pw) {
        $bcrypt_match = password_verify($pw, $user['password_hash']);
        $md5_match = (md5($pw) === $user['password_hash']);
        echo "Testing password '$pw': Bcrypt=" . ($bcrypt_match ? 'YES' : 'NO') . ", MD5=" . ($md5_match ? 'YES' : 'NO') . "\n";
    }
}
else {
    echo "User 'admin' not found.\n";
}

$stmt->close();

// List all users
echo "\n--- All Users ---\n";
$result = $conn->query("SELECT user_id, username, status, email FROM users");
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['user_id'] . " | Username: " . $row['username'] . " | Status: " . $row['status'] . " | Email: " . $row['email'] . "\n";
}
?>
