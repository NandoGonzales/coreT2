<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

header('Content-Type: application/json');

if (!in_array($_SESSION['userdata']['role'], ['Super Admin', 'Admin'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        case 'list':
            // Only show Staff actions (exclude Super Admin and Admin self-actions if needed)
            $limit  = min(2000, max(1, intval($_GET['limit'] ?? 2000)));
            $search = trim($_GET['search'] ?? '');
            $staff  = intval($_GET['staff'] ?? 0);
            $module = trim($_GET['module'] ?? '');
            $date   = trim($_GET['date']   ?? '');

            $where  = [];
            $params = [];
            $types  = '';

            // Only show Staff user activities
            $where[]  = "u.role = 'Staff'";

            if ($staff > 0) {
                $where[]  = 'a.user_id = ?';
                $params[] = $staff;
                $types   .= 'i';
            }

            if ($module !== '') {
                $where[]  = 'a.module_name = ?';
                $params[] = $module;
                $types   .= 's';
            }

            if ($date !== '') {
                $where[]  = 'DATE(a.action_time) = ?';
                $params[] = $date;
                $types   .= 's';
            }

            if ($search !== '') {
                $like     = "%$search%";
                $where[]  = '(a.action_type LIKE ? OR a.module_name LIKE ? OR a.remarks LIKE ? OR u.full_name LIKE ?)';
                $params   = array_merge($params, [$like, $like, $like, $like]);
                $types   .= 'ssss';
            }

            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT
                    a.audit_id,
                    a.user_id,
                    u.full_name,
                    u.username,
                    u.role AS user_role,
                    a.action_type,
                    a.module_name,
                    a.record_id,
                    a.remarks,
                    a.compliance_status,
                    DATE_FORMAT(a.action_time, '%Y-%m-%d %H:%i:%s') AS action_time,
                    a.ip_address
                FROM audit_trail a
                LEFT JOIN users u ON a.user_id = u.user_id
                $whereSQL
                ORDER BY a.action_time DESC
                LIMIT ?
            ";

            $params[] = $limit;
            $types   .= 'i';

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception('Query failed: ' . $conn->error);
            if ($types) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();

            echo json_encode(['status' => 'success', 'rows' => $rows]);
            break;

        default:
            echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log('staff_activity_action.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
}