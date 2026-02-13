<?php
require_once('../initialize_coreT2.php');
require_once(__DIR__ . '/inc/log_audit_trial.php');

// CONFIGURATION
$new_username = 'temp_admin';
$new_password = 'Admin@123';
$new_full_name = 'Recovery Admin';
$new_email = 'carlvincentjumarangnartea@gmail.com'; // Using your email for OTP
$new_role = 'Super Admin';
$new_status = 'Active';

echo "<h2>CoreT2 Super Admin Recovery Script</h2>";

try {
    // 1. Check if user already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $new_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "User '{$new_username}' already exists. Attempting to update password and activate...<br>";
        $user = $result->fetch_assoc();
        $user_id = $user['user_id'];

        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password_hash = ?, status = 'Active', role = ? WHERE user_id = ?");
        $update->bind_param("ssi", $hash, $new_role, $user_id);

        if ($update->execute()) {
            echo "Successfully updated and activated '{$new_username}'.<br>";
        }
        else {
            throw new Exception("Update failed: " . $conn->error);
        }
        $update->close();
    }
    else {
        echo "Creating new Super Admin user '{$new_username}'...<br>";

        // Get next user_id
        $res = $conn->query("SELECT MAX(user_id) as max_id FROM users");
        $next_id = ($res->fetch_assoc()['max_id'] ?? 0) + 1;

        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $role_id = null; // As seen in user_action.php

        $insert = $conn->prepare("INSERT INTO users (user_id, role_id, username, password_hash, full_name, email, role, status, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $insert->bind_param("iissssss", $next_id, $role_id, $new_username, $hash, $new_full_name, $new_email, $new_role, $new_status);

        if ($insert->execute()) {
            echo "Successfully created Super Admin user '{$new_username}'.<br>";
            $user_id = $next_id;
        }
        else {
            throw new Exception("Insert failed: " . $conn->error);
        }
        $insert->close();
    }

    // 2. Clear any pending OTPs for this user to be safe
    $conn->query("UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE user_id = $user_id");

    echo "<br><b>Access Details:</b><br>";
    echo "Username: <b>{$new_username}</b><br>";
    echo "Password: <b>{$new_password}</b><br>";
    echo "Email: <b>{$new_email}</b> (Check this for OTP)<br>";
    echo "<br><span style='color:red'>IMPORTANT: Please delete this file (" . basename(__FILE__) . ") after you log in!</span>";

}
catch (Exception $e) {
    echo "<b style='color:red'>Error:</b> " . $e->getMessage();
}
?>
