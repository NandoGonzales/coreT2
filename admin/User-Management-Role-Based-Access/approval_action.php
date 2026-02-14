<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

header('Content-Type: application/json');

$current_user_id = $_SESSION['userdata']['user_id'] ?? 0;
$current_role = $_SESSION['userdata']['role'] ?? '';

// Helper function to send email - made robust to avoid crashing
function sendApprovalEmail($to, $subject, $message)
{
    global $conn;

    if (empty($to))
        return false;

    try {
        // First, check if message column exists (safety fallback)
        $checkCol = $conn->query("SHOW COLUMNS FROM email_notifications LIKE 'message'");
        if ($checkCol && $checkCol->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO email_notifications (recipient_email, subject, message, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
            if ($stmt) {
                $stmt->bind_param("sss", $to, $subject, $message);
                return $stmt->execute();
            }
        } else {
            // Fallback if column is missing or different
            error_log("Email Notifications Error: Missing 'message' column");
            return false;
        }
    } catch (Exception $e) {
        error_log("sendApprovalEmail Error: " . $e->getMessage());
        return false;
    }
    return false;
}

// Helper function to log activity - aligned with core audit_trail
function logApprovalActivity($action, $module, $ref_id, $details)
{
    global $current_user_id;
    if (function_exists('log_audit')) {
        log_audit($current_user_id, $action, $module, $ref_id, $details);
    } else {
        global $conn;
        // Try audit_trail first, then fallback
        $table = 'audit_trail';
        $stmt = $conn->prepare("INSERT INTO $table (user_id, action_type, module_name, remarks, action_time) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            $table = 'audit_trial';
            $stmt = $conn->prepare("INSERT INTO $table (user_id, action_type, module, details, timestamp) VALUES (?, ?, ?, ?, NOW())");
        }

        if ($stmt) {
            $stmt->bind_param("isss", $current_user_id, $action, $module, $details);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Helper function to notify all admins
function notifyAdmins($request_id, $message)
{
    global $conn;

    // Get all Super Admin and Admin users
    $stmt = $conn->prepare("SELECT user_id, email FROM users WHERE role IN ('Super Admin', 'Admin') AND status = 'Active'");
    $stmt->execute();
    $result = $stmt->get_result();

    $notified = 0;
    while ($admin = $result->fetch_assoc()) {
        // Insert notification
        $notif_stmt = $conn->prepare("INSERT INTO approval_notifications (request_id, recipient_id, created_at) VALUES (?, ?, NOW())");
        $notif_stmt->bind_param("ii", $request_id, $admin['user_id']);
        if ($notif_stmt->execute()) {
            $notified++;

            // Send email notification to admin
            if (!empty($admin['email'])) {
                sendApprovalEmail(
                    $admin['email'],
                    'New Approval Request',
                    $message
                );
            }
        }
    }

    return $notified;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ============================================
        // SUBMIT APPROVAL REQUEST
        // ============================================
        case 'submit_request':
            // AUTO-FIX: Ensure request_type is large enough for new types (like 'user_creation')
            $conn->query("ALTER TABLE approval_requests MODIFY COLUMN request_type VARCHAR(50) NOT NULL");

            $target_user_id = $_POST['user_id'] ?? null;
            $request_type = $_POST['request_type'] ?? 'profile_update';
            $request_data = $_POST['request_data'] ?? '{}';

            $current_user = null;
            $current_data = '{}';
            $notif_user_name = 'New User';

            if ($target_user_id != 0) {
                // Get current user data for existing users
                $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $target_user_id);
                $stmt->execute();
                $current_user = $stmt->get_result()->fetch_assoc();

                if (!$current_user) {
                    echo json_encode(['status' => 'error', 'msg' => 'User not found']);
                    exit;
                }

                $current_data = json_encode([
                    'username' => $current_user['username'],
                    'full_name' => $current_user['full_name'],
                    'email' => $current_user['email'],
                    'role' => $current_user['role'],
                    'status' => $current_user['status'],
                    'phone' => $current_user['phone'] ?? ''
                ]);
                $notif_user_name = $current_user['full_name'] . " (" . $current_user['username'] . ")";
            } else {
                // For new users or global requests, we use data from the request itself for notification
                $parsed_req = json_decode($request_data, true);
                if (strpos($request_type, 'role_permission_') === 0) {
                    $notif_user_name = "Role: " . ($parsed_req['role_name'] ?? ('ID ' . ($parsed_req['role_id'] ?? 'Unknown'))) . " (Module: " . ($parsed_req['module'] ?? 'Unknown') . ")";
                } else {
                    $notif_user_name = ($parsed_req['full_name'] ?? 'New User') . " (" . ($parsed_req['username'] ?? 'new') . ")";
                }
            }

            // Rule: Only 1 pending request at a time per user
            $stmt = $conn->prepare("SELECT request_id FROM approval_requests WHERE user_id = ? AND request_type = ? AND status = 'pending'");
            $stmt->bind_param("is", $target_user_id, $request_type);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0 && strpos($request_type, 'role_permission_') === false) {
                echo json_encode(['status' => 'error', 'msg' => 'A pending request of this type already exists. Please wait for it to be processed.']);
                exit;
            }

            // Insert approval request
            $stmt = $conn->prepare("INSERT INTO approval_requests (user_id, request_type, request_data, current_data, requested_by, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->bind_param("isssi", $target_user_id, $request_type, $request_data, $current_data, $current_user_id);

            if ($stmt->execute()) {
                $request_id = $conn->insert_id;

                // Notify all admins
                $notif_message = "A new " . str_replace('_', ' ', $request_type) . " approval request has been submitted for: $notif_user_name";
                $notified_count = notifyAdmins($request_id, $notif_message);

                // Log activity
                logApprovalActivity(
                    'submit_request',
                    'Approval System',
                    $request_id,
                    "Submitted $request_type request for User ID: $target_user_id"
                );

                // Send email to user (if existing)
                if ($current_user && $current_user['email']) {
                    sendApprovalEmail(
                        $current_user['email'],
                        'User Request Submitted',
                        "Dear {$current_user['full_name']},\n\nYour request has been submitted for approval. You will be notified once an administrator reviews your request.\n\nThank you!"
                    );
                }

                echo json_encode([
                    'status' => 'success',
                    'msg' => "Approval request submitted successfully. $notified_count admin(s) notified.",
                    'request_id' => $request_id
                ]);
            } else {
                throw new Exception("Failed to submit request: " . $conn->error);
            }
            break;

        // ============================================
        // GET PENDING APPROVALS (Admin/Super Admin only)
        // ============================================
        case 'get_pending':
            // Only admins can view pending approvals
            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
                exit;
            }

            $where = "WHERE ar.status = 'pending'";
            if ($current_role === 'Admin') {
                $where .= " AND u.role = 'Staff'";
            } elseif ($current_role === 'Super Admin') {
                $where .= " AND u.role IN ('Admin', 'Staff')";
            } else {
                // Other roles shouldn't see anything, but just in case
                $where .= " AND 1=0";
            }

            $stmt = $conn->prepare("
                SELECT 
                    ar.request_id,
                    ar.request_type,
                    ar.user_id,
                    u.username,
                    u.full_name,
                    u.email,
                    u.role AS u_role,
                    ar.request_data,
                    ar.current_data,
                    ar.status,
                    ar.created_at,
                    rb.full_name as requested_by_name
                FROM approval_requests ar
                LEFT JOIN users u ON ar.user_id = u.user_id
                LEFT JOIN users rb ON ar.requested_by = rb.user_id
                $where
                ORDER BY ar.created_at DESC
            ");
            $stmt->execute();
            $result = $stmt->get_result();

            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $row['request_data_parsed'] = json_decode($row['request_data'], true);
                $row['current_data_parsed'] = json_decode($row['current_data'], true);
                $requests[] = $row;
            }

            echo json_encode(['status' => 'success', 'requests' => $requests]);
            break;

        // ============================================
        // APPROVE REQUEST
        // ============================================
        case 'approve':
            $request_id = $_POST['request_id'] ?? null;
            $review_notes = $_POST['review_notes'] ?? '';
            $api_key = $_POST['api_key'] ?? '';

            if (!$request_id) {
                echo json_encode(['status' => 'error', 'msg' => 'Request ID required']);
                exit;
            }

            // Check if user is admin
            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                echo json_encode(['status' => 'error', 'msg' => 'Only admins can approve requests']);
                exit;
            }

            // Get request details
            $stmt = $conn->prepare("
                SELECT ar.*, u.email, u.full_name, u.role as target_role 
                FROM approval_requests ar 
                LEFT JOIN users u ON ar.user_id = u.user_id 
                WHERE ar.request_id = ?
            ");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();

            if (!$request) {
                echo json_encode(['status' => 'error', 'msg' => 'Request not found']);
                exit;
            }

            if ($request['status'] !== 'pending') {
                echo json_encode(['status' => 'error', 'msg' => 'This request has already been processed']);
                exit;
            }

            // Rule: Cannot approve your own request
            if ($request['user_id'] != 0 && $request['user_id'] == $current_user_id) {
                echo json_encode(['status' => 'error', 'msg' => 'You cannot approve your own request. Please wait for another administrator to review it.']);
                exit;
            }

            // Hierarchical Rule: 
            // - Admin can only approve Staff
            // - Super Admin can approve Admin and Staff
            $target_role = $request['target_role'] ?? 'Staff';
            if ($request['request_type'] === 'user_creation') {
                $req_data = json_decode($request['request_data'], true);
                $target_role = $req_data['role'] ?? 'Staff';
            }

            if ($current_role === 'Admin' && $target_role !== 'Staff') {
                echo json_encode(['status' => 'error', 'msg' => 'Admins can only approve Staff related changes. Admin level actions require Super Admin approval.']);
                exit;
            }

            // Rule: Sensitive requests require an administrative API key
            if (in_array($request['request_type'], ['profile_update', 'termination', 'removal', 'user_creation', 'role_permission_add', 'role_permission_edit', 'role_permission_delete'])) {
                $stmt_key = $conn->prepare("SELECT meta_value FROM system_info WHERE meta_field = 'admin_api_key'");
                $stmt_key->execute();
                $sys_api_key = $stmt_key->get_result()->fetch_assoc()['meta_value'] ?? 'admin123';
                $stmt_key->close();

                if (empty($api_key) || $api_key !== $sys_api_key) {
                    echo json_encode(['status' => 'error', 'msg' => 'Invalid or missing Administrative API Key. Approval denied.']);
                    exit;
                }
            }

            // Parse request data
            $request_data = json_decode($request['request_data'], true);

            // Handle different request types
            $user_id = $request['user_id'];
            if ($request['request_type'] === 'termination') {
                // Termination: Deactivate user
                $stmt = $conn->prepare("UPDATE users SET status='Inactive' WHERE user_id=?");
                $stmt->bind_param('i', $user_id);
            } elseif ($request['request_type'] === 'removal') {
                // Removal: Delete user
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id=?");
                $stmt->bind_param('i', $user_id);
            } elseif ($request['request_type'] === 'role_permission_add') {
                // Role Permission: Add
                $role_id = $request_data['role_id'];
                $module = $request_data['module'];
                $v = $request_data['can_view'];
                $a = $request_data['can_add'];
                $e = $request_data['can_edit'];
                $d = $request_data['can_delete'];

                $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, module_name, can_view, can_add, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isiiii', $role_id, $module, $v, $a, $e, $d);
            } elseif ($request['request_type'] === 'role_permission_edit') {
                // Role Permission: Edit
                $perm_id = $request_data['perm_id'];
                $role_id = $request_data['role_id'];
                $module = $request_data['module'];
                $v = $request_data['can_view'];
                $a = $request_data['can_add'];
                $e = $request_data['can_edit'];
                $d = $request_data['can_delete'];

                $stmt = $conn->prepare("UPDATE role_permissions SET role_id=?, module_name=?, can_view=?, can_add=?, can_edit=?, can_delete=? WHERE perm_id=?");
                $stmt->bind_param('isiiiii', $role_id, $module, $v, $a, $e, $d, $perm_id);
            } elseif ($request['request_type'] === 'role_permission_delete') {
                // Role Permission: Delete
                $perm_id = $request_data['perm_id'];
                $stmt = $conn->prepare("DELETE FROM role_permissions WHERE perm_id=?");
                $stmt->bind_param('i', $perm_id);
            } else {
                // Profile Update: Update user record
                $full_name = $request_data['full_name'] ?? ($request['full_name'] ?? '');
                $username = $request_data['username'] ?? ($request['username'] ?? '');
                $email = $request_data['email'] ?? ($request['email'] ?? '');
                $role = $request_data['role'] ?? ($request['target_role'] ?? 'Staff');
                $status = $request_data['status'] ?? 'Active';
                $phone = $request_data['phone'] ?? '';

                $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, role=?, status=?, phone=? WHERE user_id=?");
                $stmt->bind_param('ssssssi', $username, $full_name, $email, $role, $status, $phone, $user_id);
            }

            if ($stmt->execute()) {
                // Update approval request status
                $stmt = $conn->prepare("UPDATE approval_requests SET status='approved', reviewed_by=?, review_notes=?, reviewed_at=NOW() WHERE request_id=?");
                $stmt->bind_param("isi", $current_user_id, $review_notes, $request_id);
                $stmt->execute();

                // Mark notifications as read
                $stmt = $conn->prepare("UPDATE approval_notifications SET is_read=1, read_at=NOW() WHERE request_id=?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();

                // Send email confirmation to user (if email available in request data)
                $user_email = $request['email'] ?? ($request_data['email'] ?? null);
                $user_full_name = $request['full_name'] ?? ($request_data['full_name'] ?? 'User');

                if ($user_email) {
                    $subject_map = [
                        'termination' => 'Account Termination Approved',
                        'removal' => 'Account Removal Approved',
                        'profile_update' => 'Profile Update Approved',
                        'user_creation' => 'Account Created',
                        'role_permission_add' => 'Role Permission Approved',
                        'role_permission_edit' => 'Role Permission Edit Approved',
                        'role_permission_delete' => 'Role Permission Deletion Approved'
                    ];
                    $msg_map = [
                        'termination' => "Your account termination request has been approved. Your account has been deactivated.",
                        'removal' => "Your account removal request has been approved. Your account has been permanently removed from the system.",
                        'profile_update' => "Your profile update request has been approved by an administrator.",
                        'user_creation' => "Your account has been created successfully. You can now log in using your credentials.",
                        'role_permission_add' => "The role permission addition has been approved.",
                        'role_permission_edit' => "The role permission update has been approved.",
                        'role_permission_delete' => "The role permission deletion has been approved."
                    ];

                    $subject = $subject_map[$request['request_type']] ?? 'Account Request Approved';
                    $msg_body = $msg_map[$request['request_type']] ?? "Your request has been approved by an administrator.";

                    sendApprovalEmail(
                        $user_email,
                        $subject,
                        "Dear $user_full_name,\n\n$msg_body\n\n" . (!empty($review_notes) ? "Review Notes: $review_notes\n\n" : "") . "Thank you!"
                    );
                }

                // Log activity
                logApprovalActivity(
                    'approve_request',
                    'Approval System',
                    $request_id,
                    "Approved {$request['request_type']} request for User ID: $user_id"
                );

                echo json_encode(['status' => 'success', 'msg' => 'Request approved and user updated successfully']);
            } else {
                throw new Exception("Failed to update user: " . $conn->error);
            }
            break;

        // ============================================
        // REJECT REQUEST
        // ============================================
        case 'reject':
            $request_id = $_POST['request_id'] ?? null;
            $review_notes = $_POST['review_notes'] ?? '';

            // Rule: Rejection requires a reason
            if (empty($review_notes)) {
                echo json_encode(['status' => 'error', 'msg' => 'A reason for rejection is required.']);
                exit;
            }

            // Check if user is admin
            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                echo json_encode(['status' => 'error', 'msg' => 'Only admins can reject requests']);
                exit;
            }

            // Get request details
            $stmt = $conn->prepare("SELECT ar.*, u.email, u.full_name FROM approval_requests ar LEFT JOIN users u ON ar.user_id = u.user_id WHERE ar.request_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();

            if (!$request) {
                echo json_encode(['status' => 'error', 'msg' => 'Request not found']);
                exit;
            }

            if ($request['status'] !== 'pending') {
                echo json_encode(['status' => 'error', 'msg' => 'This request has already been processed']);
                exit;
            }

            // Update approval request status
            $stmt = $conn->prepare("UPDATE approval_requests SET status='rejected', reviewed_by=?, review_notes=?, reviewed_at=NOW() WHERE request_id=?");
            $stmt->bind_param("isi", $current_user_id, $review_notes, $request_id);

            if ($stmt->execute()) {
                // Mark notifications as read
                $stmt = $conn->prepare("UPDATE approval_notifications SET is_read=1, read_at=NOW() WHERE request_id=?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();

                // Send email notification to user
                if ($request['email']) {
                    sendApprovalEmail(
                        $request['email'],
                        'Profile Update Rejected',
                        "Dear {$request['full_name']},\n\nYour profile update request has been rejected by an administrator.\n\n" . (!empty($review_notes) ? "Reason: $review_notes\n\n" : "") . "Please contact your administrator for more information.\n\nThank you!"
                    );
                }

                // Log activity
                logApprovalActivity(
                    'reject_request',
                    'Approval System',
                    $request_id,
                    "Rejected {$request['request_type']} request for User ID: {$request['user_id']}. Reason: $review_notes"
                );

                echo json_encode(['status' => 'success', 'msg' => 'Request rejected successfully']);
            } else {
                throw new Exception("Failed to reject request: " . $conn->error);
            }
            break;

        // ============================================
        // GET NOTIFICATION COUNT (for badge)
        // ============================================
        case 'get_notification_count':
            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                echo json_encode(['status' => 'success', 'count' => 0]);
                exit;
            }

            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM approval_notifications 
                WHERE recipient_id = ? AND is_read = 0
            ");
            $stmt->bind_param("i", $current_user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            echo json_encode(['status' => 'success', 'count' => $result['count']]);
            break;

        default:
            echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
            break;
    }


} catch (Exception $e) {
    error_log("Approval Action Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'msg' => 'An error occurred: ' . $e->getMessage()]);
}