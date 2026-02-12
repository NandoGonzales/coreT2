<?php
// ===========================
// update_profile.php
// Backend handler for profile updates
// ===========================

session_start();
require_once(__DIR__ . '/../../initialize_coreT2.php');

// Set JSON header
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check authentication
if (!isset($_SESSION['userdata']['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not authenticated']);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
$full_name = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if ($full_name === '' || $email === '') {
    echo json_encode(['status' => 'error', 'msg' => 'Full Name and Email cannot be empty']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid email format']);
    exit;
}

$user_id = $_SESSION['userdata']['user_id'];

try {
    $db = new DBConnection();
    
    // Check if email is already taken by another user
    $stmt = $db->conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param('si', $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'msg' => 'Email address is already in use']);
        exit;
    }
    $stmt->close();
    
    // Update profile
    if ($password !== '') {
        // Update with password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->conn->prepare("UPDATE users SET full_name = ?, email = ?, password_hash = ? WHERE user_id = ?");
        $stmt->bind_param('sssi', $full_name, $email, $password_hash, $user_id);
    } else {
        // Update without password
        $stmt = $db->conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
        $stmt->bind_param('ssi', $full_name, $email, $user_id);
    }
    
    if ($stmt->execute()) {
        // Update session data
        $_SESSION['userdata']['full_name'] = $full_name;
        $_SESSION['userdata']['email'] = $email;
        
        error_log("Profile updated successfully for user $user_id");
        
        echo json_encode([
            'status' => 'success',
            'msg' => 'Profile updated successfully'
        ]);
    } else {
        error_log("Database error for user $user_id: " . $stmt->error);
        echo json_encode(['status' => 'error', 'msg' => 'Database error: ' . $stmt->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Exception updating profile for user $user_id: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'msg' => 'Error: ' . $e->getMessage()]);
}
?>