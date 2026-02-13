<?php
require_once('initialize_coreT2.php');

$username_or_email = 'carlvincentjumarangnartea@gmail.com';

echo "Checking for user: $username_or_email\n";

$stmt = $conn->prepare("SELECT user_id, username, email, password_hash, status, type FROM users WHERE username=? OR email=? LIMIT 1");
$stmt->bind_param("ss", $username_or_email, $username_or_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    echo "User found!\n";
    echo "User ID: " . $user['user_id'] . "\n";
    echo "Username: " . $user['username'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Status: " . $user['status'] . "\n";
    echo "Type: " . $user['type'] . "\n";
    echo "Password Hash starts with: " . substr($user['password_hash'], 0, 10) . "...\n";

    // Check if password_hash is empty or null
    if (empty($user['password_hash'])) {
        echo "WARNING: Password hash is EMPTY!\n";
    }
}
else {
    echo "User NOT found.\n";

    // List some users to see what's there
    echo "\nSome users in the table:\n";
    $list = $conn->query("SELECT user_id, username, email FROM users LIMIT 10");
    while ($row = $list->fetch_assoc()) {
        echo "- " . $row['username'] . " (" . $row['email'] . ")\n";
    }
}
?>
