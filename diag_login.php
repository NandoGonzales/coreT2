<?php
require_once('initialize_coreT2.php');

$email = 'carlvincentjumarangnartea@gmail.com';
$password_to_test = 'nartea123'; // From the screenshot the user provided

echo "--- CORET2 Login Diagnostics ---\n";
echo "Testing for: $email\n\n";

$stmt = $conn->prepare("SELECT user_id, username, email, password_hash, status FROM users WHERE email=? OR username=? LIMIT 1");
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    echo "✅ User Record Found:\n";
    echo "User ID: " . $user['user_id'] . "\n";
    echo "Username: " . $user['username'] . "\n";
    echo "Status: " . $user['status'] . "\n";

    $hash = $user['password_hash'];
    echo "Password Hash (truncated): " . substr($hash, 0, 10) . "...\n";

    // Determine hashing method
    if (strpos($hash, '$2y$') === 0 || strpos($hash, '$2a$') === 0) {
        echo "Hashing Method: Bcrypt (Correct for password_verify)\n";
    }
    elseif (strlen($hash) === 32) {
        echo "Hashing Method: MD5 (Incorrect for current login code)\n";
    }
    else {
        echo "Hashing Method: Unknown (" . strlen($hash) . " chars)\n";
    }

    // Test verification
    echo "\n--- Verification Tests ---\n";
    $bcrypt_ok = password_verify($password_to_test, $hash);
    echo "Bcrypt verification (nartea123): " . ($bcrypt_ok ? "✅ SUCCESS" : "❌ FAILED") . "\n";

    if (strlen($hash) === 32) {
        $md5_match = (md5($password_to_test) === $hash);
        echo "MD5 match (nartea123): " . ($md5_match ? "✅ SUCCESS" : "❌ FAILED") . "\n";
    }


}
else {
    echo "❌ User record NOT found in database.\n";
}

// Check for other users
$all = $conn->query("SELECT username, email FROM users LIMIT 5");
echo "\n--- Other Users Samples ---\n";
while ($r = $all->fetch_assoc()) {
    echo "- " . $r['username'] . " (" . $r['email'] . ")\n";
}
?>
