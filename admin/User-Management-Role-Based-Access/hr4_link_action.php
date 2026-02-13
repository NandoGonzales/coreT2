<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

header('Content-Type: application/json');

// require Super Admin
checkPermission('user_management');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_POST['user_id'] ?? null;
$hr4_employee_id = $_POST['hr4_employee_id'] ?? null;

if (!$action) {
    echo json_encode(['status' => 'error', 'msg' => 'Action required']);
    exit;
}

try {
    switch ($action) {
        case 'link':
            if (!$user_id || !$hr4_employee_id) {
                throw new Exception("User ID and HR4 Employee ID are required for linking.");
            }

            // Validate user exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception("CoreT2 User not found.");
            }
            $stmt->close();

            // Validate HR4 Employee exists
            $stmt = $conn->prepare("SELECT hr4_employee_id FROM hr4_employees WHERE hr4_employee_id = ?");
            $stmt->bind_param("s", $hr4_employee_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception("HR4 Employee not found.");
            }
            $stmt->close();

            // Check if user is already linked elsewhere
            $stmt = $conn->prepare("SELECT hr4_employee_id FROM user_hr4_link WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $linked = $res->fetch_assoc();
                throw new Exception("This user is already linked to HR4 Employee: " . $linked['hr4_employee_id']);
            }
            $stmt->close();

            // Check if employee is already linked to another user
            $stmt = $conn->prepare("SELECT user_id FROM user_hr4_link WHERE hr4_employee_id = ?");
            $stmt->bind_param("s", $hr4_employee_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $linked = $res->fetch_assoc();
                throw new Exception("This HR4 Employee is already linked to user ID: " . $linked['user_id']);
            }
            $stmt->close();

            // Insert link
            $stmt = $conn->prepare("INSERT INTO user_hr4_link (user_id, hr4_employee_id, linked_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("is", $user_id, $hr4_employee_id);
            if ($stmt->execute()) {
                // Log activity
                if (function_exists('log_audit')) {
                    log_audit(
                        $_SESSION['userdata']['user_id'],
                        'Link HR4 Employee',
                        'User Management',
                        $user_id,
                        "Linked User ID $user_id to HR4 Employee $hr4_employee_id"
                    );
                }
                echo json_encode(['status' => 'success', 'msg' => 'Successfully linked user to HR4 employee.']);
            } else {
                throw new Exception("Failed to create link: " . $conn->error);
            }
            $stmt->close();
            break;

        case 'unlink':
            if (!$user_id && !$hr4_employee_id) {
                throw new Exception("User ID or HR4 Employee ID is required for unlinking.");
            }

            if ($user_id) {
                $stmt = $conn->prepare("DELETE FROM user_hr4_link WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
            } else {
                $stmt = $conn->prepare("DELETE FROM user_hr4_link WHERE hr4_employee_id = ?");
                $stmt->bind_param("s", $hr4_employee_id);
            }

            if ($stmt->execute()) {
                // Log activity
                if (function_exists('log_audit')) {
                    log_audit(
                        $_SESSION['userdata']['user_id'],
                        'Unlink HR4 Employee',
                        'User Management',
                        $user_id,
                        "Unlinked " . ($user_id ? "User ID $user_id" : "HR4 Employee $hr4_employee_id")
                    );
                }
                echo json_encode(['status' => 'success', 'msg' => 'Successfully unlinked.']);
            } else {
                throw new Exception("Failed to remove link: " . $conn->error);
            }
            $stmt->close();
            break;

        default:
            throw new Exception("Invalid action: " . $action);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ]);
}
