<?php
/**
 * Admin Fetch API for Subsystems
 * Path: api/admin_fetch.php
 */

require_once(__DIR__ . '/../initialize_coreT2.php');

header('Content-Type: application/json; charset=utf-8');

// 1. API Key Authentication
$input_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? $_POST['api_key'] ?? '';

$stmt = $conn->prepare("SELECT meta_value FROM system_info WHERE meta_field = 'admin_api_key'");
$stmt->execute();
$sys_key = $stmt->get_result()->fetch_assoc()['meta_value'] ?? '';
$stmt->close();

if (empty($input_key) || $input_key !== $sys_key) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid API Key']);
    exit;
}

// 2. Fetch Data Based on Action
$action = $_GET['action'] ?? 'users';

try {
    switch ($action) {
        case 'users':
            $stmt = $conn->prepare("SELECT user_id, username, full_name, email, role, status, date_created FROM users ORDER BY user_id DESC");
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            echo json_encode(['success' => true, 'action' => $action, 'data' => $data]);
            break;

        case 'pending_requests':
            $stmt = $conn->prepare("
                SELECT 
                    ar.request_id, ar.user_id, u.username, u.full_name, 
                    ar.request_type, ar.request_data, ar.created_at 
                FROM approval_requests ar 
                JOIN users u ON ar.user_id = u.user_id 
                WHERE ar.status = 'pending' 
                ORDER BY ar.created_at DESC
            ");
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($data as &$row) {
                $row['request_data'] = json_decode($row['request_data'], true);
            }
            $stmt->close();
            echo json_encode(['success' => true, 'action' => $action, 'data' => $data]);
            break;

        case 'system_info':
            $stmt = $conn->prepare("SELECT meta_field, meta_value FROM system_info");
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            echo json_encode(['success' => true, 'action' => $action, 'data' => $data]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action requested']);
            break;
    }
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal Server Error: ' . $e->getMessage()]);
}
?>
