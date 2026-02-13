<?php
require_once('../initialize_coreT2.php');
require_once(__DIR__ . '/inc/log_audit_trial.php');

// CONFIGURATION
$users_to_ensure = [
    [
        'username' => 'admin',
        'password' => 'Admin@123',
        'full_name' => 'Original Admin',
        'email' => 'carlvincentjumarangnartea@gmail.com',
        'role' => 'Super Admin'
    ],
    [
        'username' => 'temp_admin',
        'password' => 'Admin@123',
        'full_name' => 'Recovery Admin',
        'email' => 'carlvincentjumarangnartea@gmail.com',
        'role' => 'Super Admin'
    ]
];

echo "<h2>CoreT2 Super Admin Recovery Script</h2>";

foreach ($users_to_ensure as $u) {
    $username = $u['username'];
    $password = $u['password'];
    $full_name = $u['full_name'];
    $email = $u['email'];
    $role = $u['role'];

    try {
        // 1. Check if user already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo "User '{$username}' already exists. Updating password, email, and activating...<br>";
            $user = $result->fetch_assoc();
            $user_id = $user['user_id'];

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password_hash = ?, email = ?, status = 'Active', role = ? WHERE user_id = ?");
            $update->bind_param("sssi", $hash, $email, $role, $user_id);

            if ($update->execute()) {
                echo "Successfully updated and activated '{$username}'.<br>";
            }
            else {
                throw new Exception("Update failed: " . $conn->error);
            }
            $update->close();
        }
        else {
            echo "Creating new user '{$username}'...<br>";

            // Get next user_id
            $res = $conn->query("SELECT MAX(user_id) as max_id FROM users");
            $next_id = ($res->fetch_assoc()['max_id'] ?? 0) + 1;

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role_id = null;

            $insert = $conn->prepare("INSERT INTO users (user_id, role_id, username, password_hash, full_name, email, role, status, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $insert->bind_param("iissssss", $next_id, $role_id, $username, $hash, $full_name, $email, $role, 'Active');

            if ($insert->execute()) {
                echo "Successfully created user '{$username}'.<br>";
                $user_id = $next_id;
            }
            else {
                throw new Exception("Insert failed: " . $conn->error);
            }
            $insert->close();
        }

        // 2. Clear any pending OTPs
        $conn->query("UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE user_id = $user_id");

    }
    catch (Exception $e) {
        echo "<b style='color:red'>Error for {$username}:</b> " . $e->getMessage() . "<br>";
    }
}

echo "<br><b>Access Details:</b><br>";
echo "Recovered User: <b>admin</b> / <b>Admin@123</b><br>";
echo "Fallback User: <b>temp_admin</b> / <b>Admin@123</b><br>";
echo "<br><span style='color:red'>IMPORTANT: Please delete this file (" . basename(__FILE__) . ") after you log in!</span>";
?>
