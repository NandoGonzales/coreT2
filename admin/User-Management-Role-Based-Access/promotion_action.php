<?php
/**
 * promotion_action.php
 * Backend for Staff Promotions module
 * Handles: submit, get_requests, approve, reject
 *
 * REQUIRES: add_position_promotions.sql to have been run first
 * (adds request_type, current_position, requested_position to promotion_requests)
 */
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');

header('Content-Type: application/json');

$current_user_id = (int)($_SESSION['userdata']['user_id'] ?? 0);
$current_role    = $_SESSION['userdata']['role'] ?? '';
$current_name    = $_SESSION['userdata']['full_name'] ?? 'Unknown';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Valid positions list — must match the dropdown in promotions.php
$valid_positions = [
    'Branch Manager', 'Loan Officer', 'Account Officer', 'Credit Investigator',
    'Loan Processor', 'Collection Officer', 'Cashier', 'Teller',
    'Compliance Officer', 'System Administrator', 'Bookkeeper',
    'Field Officer', 'Customer Service', 'Encoder'
];

function json_out($arr) {
    while (ob_get_level()) ob_end_clean();
    echo json_encode($arr);
    exit;
}

// ── Guard: must be logged in ──────────────────────────────────
if (!$current_user_id) {
    json_out(['success' => false, 'msg' => 'Session expired. Please log in again.']);
}

try {
    switch ($action) {

        // ─────────────────────────────────────────────────────
        // SUBMIT PROMOTION REQUEST (Staff only)
        // ─────────────────────────────────────────────────────
        case 'submit':
            if ($current_role !== 'Staff') {
                json_out(['success' => false, 'msg' => 'Only Staff members can submit promotion requests.']);
            }

            $request_type       = trim($_POST['request_type'] ?? 'role_promotion');
            $requested_role     = trim($_POST['requested_role'] ?? '');
            $requested_position = trim($_POST['requested_position'] ?? '');
            $reason             = trim($_POST['reason'] ?? '');

            // Validate reason
            if (empty($reason)) {
                json_out(['success' => false, 'msg' => 'Please provide a reason for your request.']);
            }
            if (strlen($reason) < 10) {
                json_out(['success' => false, 'msg' => 'Please provide a more detailed reason (at least 10 characters).']);
            }

            // Validate based on type
            if ($request_type === 'role_promotion') {
                if (!in_array($requested_role, ['Admin', 'Super Admin'])) {
                    json_out(['success' => false, 'msg' => 'Invalid role requested. Must be Admin or Super Admin.']);
                }
            } elseif ($request_type === 'position_change') {
                global $valid_positions;
                if (empty($requested_position)) {
                    json_out(['success' => false, 'msg' => 'Please select a position.']);
                }
                if (!in_array($requested_position, $valid_positions)) {
                    json_out(['success' => false, 'msg' => 'Invalid position selected.']);
                }
            } else {
                json_out(['success' => false, 'msg' => 'Invalid request type.']);
            }

            // Get current user data from DB (fresh, not session)
            $stmt = $conn->prepare("SELECT role, position FROM users WHERE user_id = ?");
            $stmt->bind_param('i', $current_user_id);
            $stmt->execute();
            $usr = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$usr) {
                json_out(['success' => false, 'msg' => 'User not found.']);
            }

            $current_role_val     = $usr['role'] ?? 'Staff';
            $current_position_val = $usr['position'] ?? '';

            // Can't request same position you already have
            if ($request_type === 'position_change' && $current_position_val === $requested_position) {
                json_out(['success' => false, 'msg' => 'You already have this position.']);
            }

            // Can't request same role you already have
            if ($request_type === 'role_promotion' && $current_role_val === $requested_role) {
                json_out(['success' => false, 'msg' => 'You already have the ' . $requested_role . ' role.']);
            }

            // Block duplicate pending request of same type
            $stmt = $conn->prepare(
                "SELECT request_id FROM promotion_requests
                 WHERE user_id = ? AND request_type = ? AND status = 'pending'"
            );
            $stmt->bind_param('is', $current_user_id, $request_type);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $label = $request_type === 'role_promotion' ? 'role promotion' : 'position change';
                json_out(['success' => false, 'msg' => "You already have a pending $label request. Please wait for it to be processed."]);
            }
            $stmt->close();

            // Insert request
            $stmt = $conn->prepare("
                INSERT INTO promotion_requests
                    (user_id, request_type, current_role, requested_role,
                     current_position, requested_position, reason, status, request_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->bind_param('issssss',
                $current_user_id,
                $request_type,
                $current_role_val,
                $requested_role,
                $current_position_val,
                $requested_position,
                $reason
            );

            if ($stmt->execute()) {
                $rid = $conn->insert_id;
                $stmt->close();

                if (function_exists('log_audit')) {
                    log_audit($current_user_id, 'Submit Promotion Request', 'Promotions',
                        $rid, "$current_name submitted a $request_type request.");
                }

                json_out([
                    'success'    => true,
                    'msg'        => 'Request submitted successfully. Pending admin review.',
                    'request_id' => $rid
                ]);
            } else {
                $err = $conn->error;
                $stmt->close();
                throw new Exception("DB error on insert: $err");
            }
            break;

        // ─────────────────────────────────────────────────────
        // GET REQUESTS
        // ─────────────────────────────────────────────────────
        case 'get_requests':
            $status_filter = trim($_GET['status'] ?? '');
            $type_filter   = trim($_GET['type'] ?? '');

            // ── Total counts (always unfiltered, for summary cards) ──
            $count_where  = '';
            $count_params = [];
            $count_types  = '';
            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                $count_where    = 'WHERE pr.user_id = ?';
                $count_params[] = $current_user_id;
                $count_types    = 'i';
            }

            $count_stmt = $conn->prepare("
                SELECT status, COUNT(*) as cnt
                FROM promotion_requests pr
                $count_where
                GROUP BY status
            ");
            if ($count_types) $count_stmt->bind_param($count_types, ...$count_params);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_stmt->close();

            $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
            while ($row = $count_result->fetch_assoc()) {
                if (isset($counts[$row['status']])) {
                    $counts[$row['status']] = (int)$row['cnt'];
                }
            }
            $counts['total'] = array_sum($counts);

            // ── Filtered rows ──
            $where  = [];
            $params = [];
            $types  = '';

            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                $where[]  = 'pr.user_id = ?';
                $params[] = $current_user_id;
                $types   .= 'i';
            }
            if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
                $where[]  = 'pr.status = ?';
                $params[] = $status_filter;
                $types   .= 's';
            }
            if ($type_filter && in_array($type_filter, ['role_promotion', 'position_change'])) {
                $where[]  = 'pr.request_type = ?';
                $params[] = $type_filter;
                $types   .= 's';
            }

            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $conn->prepare("
                SELECT
                    pr.*,
                    u.full_name    AS staff_name,
                    u.username     AS staff_username,
                    u.email        AS staff_email,
                    u.position     AS staff_current_position,
                    rv.full_name   AS reviewer_name
                FROM promotion_requests pr
                LEFT JOIN users u  ON pr.user_id     = u.user_id
                LEFT JOIN users rv ON pr.reviewed_by = rv.user_id
                $whereSQL
                ORDER BY
                    FIELD(pr.status, 'pending', 'approved', 'rejected'),
                    pr.request_date DESC
            ");

            if ($types) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            json_out(['success' => true, 'requests' => $rows, 'counts' => $counts]);
            break;

        // ─────────────────────────────────────────────────────
        // APPROVE (Admin / Super Admin only)
        // ─────────────────────────────────────────────────────
        case 'approve':
            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                json_out(['success' => false, 'msg' => 'Unauthorized.']);
            }

            $request_id  = (int)($_POST['request_id'] ?? 0);
            $admin_notes = trim($_POST['admin_notes'] ?? 'Approved');

            if (!$request_id) json_out(['success' => false, 'msg' => 'Invalid request ID.']);

            // Get full request details
            $stmt = $conn->prepare("
                SELECT pr.*, u.full_name AS staff_name
                FROM promotion_requests pr
                LEFT JOIN users u ON pr.user_id = u.user_id
                WHERE pr.request_id = ?
            ");
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $req = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$req) json_out(['success' => false, 'msg' => 'Request not found.']);
            if ($req['status'] !== 'pending') json_out(['success' => false, 'msg' => 'This request has already been processed.']);

            // Hierarchy: Admin cannot approve Super Admin promotions
            if ($current_role === 'Admin'
                && $req['request_type'] === 'role_promotion'
                && $req['requested_role'] === 'Super Admin') {
                json_out(['success' => false, 'msg' => 'Only a Super Admin can approve Super Admin promotions.']);
            }

            $conn->begin_transaction();
            try {
                if ($req['request_type'] === 'role_promotion') {
                    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                    $stmt->bind_param('si', $req['requested_role'], $req['user_id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // position_change
                    $stmt = $conn->prepare("UPDATE users SET position = ? WHERE user_id = ?");
                    $stmt->bind_param('si', $req['requested_position'], $req['user_id']);
                    $stmt->execute();
                    $stmt->close();
                }

                // Mark as approved
                $stmt = $conn->prepare("
                    UPDATE promotion_requests
                    SET status = 'approved', reviewed_by = ?, admin_notes = ?, review_date = NOW()
                    WHERE request_id = ?
                ");
                $stmt->bind_param('isi', $current_user_id, $admin_notes, $request_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                if (function_exists('log_audit')) {
                    $label = $req['request_type'] === 'role_promotion'
                        ? "Promoted {$req['staff_name']} (ID {$req['user_id']}) to role: {$req['requested_role']}"
                        : "Changed position of {$req['staff_name']} (ID {$req['user_id']}) to: {$req['requested_position']}";
                    log_audit($current_user_id, 'Approve Promotion', 'Promotions', $request_id, $label);
                }

                $change = $req['request_type'] === 'role_promotion'
                    ? "Role updated to <strong>{$req['requested_role']}</strong>"
                    : "Position updated to <strong>{$req['requested_position']}</strong>";

                json_out([
                    'success' => true,
                    'msg'     => "Approved! {$req['staff_name']}'s account has been updated. $change"
                ]);

            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        // ─────────────────────────────────────────────────────
        // REJECT (Admin / Super Admin only)
        // ─────────────────────────────────────────────────────
        case 'reject':
            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                json_out(['success' => false, 'msg' => 'Unauthorized.']);
            }

            $request_id  = (int)($_POST['request_id'] ?? 0);
            $admin_notes = trim($_POST['admin_notes'] ?? '');

            if (!$request_id) json_out(['success' => false, 'msg' => 'Invalid request ID.']);
            if (strlen($admin_notes) < 5) {
                json_out(['success' => false, 'msg' => 'Please provide a reason for rejection (at least 5 characters).']);
            }

            $stmt = $conn->prepare("SELECT status, user_id FROM promotion_requests WHERE request_id = ?");
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $req = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$req) json_out(['success' => false, 'msg' => 'Request not found.']);
            if ($req['status'] !== 'pending') json_out(['success' => false, 'msg' => 'Request has already been processed.']);

            $stmt = $conn->prepare("
                UPDATE promotion_requests
                SET status = 'rejected', reviewed_by = ?, admin_notes = ?, review_date = NOW()
                WHERE request_id = ?
            ");
            $stmt->bind_param('isi', $current_user_id, $admin_notes, $request_id);

            if ($stmt->execute()) {
                $stmt->close();
                if (function_exists('log_audit')) {
                    log_audit($current_user_id, 'Reject Promotion', 'Promotions',
                        $request_id, "Rejected request ID $request_id. Reason: $admin_notes");
                }
                json_out(['success' => true, 'msg' => 'Request has been rejected.']);
            } else {
                $err = $conn->error;
                $stmt->close();
                throw new Exception("DB error: $err");
            }
            break;

        default:
            json_out(['success' => false, 'msg' => 'Invalid action.']);
    }

} catch (Exception $e) {
    error_log("promotion_action.php Error: " . $e->getMessage());
    json_out(['success' => false, 'msg' => 'Server error: ' . $e->getMessage()]);
}