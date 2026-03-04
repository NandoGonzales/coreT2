<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['userdata'])) {
    echo json_encode(['status' => 'error', 'count' => 0, 'notifications' => []]);
    exit();
}

$user_id   = $_SESSION['userdata']['user_id'] ?? 0;
$user_role = $_SESSION['userdata']['role'] ?? 'Staff';
$notifications = [];

try {
    // Auto-add column if not exists
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_last_seen DATETIME DEFAULT NULL");

    // Get last seen time for this user
    $seenRow = $conn->query("SELECT notif_last_seen FROM users WHERE user_id = " . (int)$user_id . " LIMIT 1")->fetch_assoc();
    $lastSeen = $seenRow['notif_last_seen'] ?? null;


    // ══════════════════════════════════════════════════════════════
    // SUPER ADMIN
    // ══════════════════════════════════════════════════════════════
    if ($user_role === 'Super Admin') {

        // 1. Pending loans (last 7 days)
        $res = $conn->query("
            SELECT l.loan_id, m.full_name, l.principal_amount, l.start_date
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status = 'Pending'
              AND l.start_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY l.start_date DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'loan',
                'icon'    => 'bi-file-earmark-text text-primary',
                'message' => 'New loan: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['principal_amount']),
                'time'    => $row['start_date'],
                'link'    => '/admin/Loan-Portfolio/loan_details.php?id=' . $row['loan_id'],
            ];
        }

        // 2. Payments today
        $res = $conn->query("
            SELECT r.repayment_id, m.full_name, r.amount, r.repayment_date
            FROM repayments r
            LEFT JOIN loan_portfolio l ON l.loan_id = r.loan_id
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE DATE(r.repayment_date) = CURDATE()
            ORDER BY r.repayment_id DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'payment',
                'icon'    => 'bi-check-circle text-success',
                'message' => 'Payment received: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['amount'], 2),
                'time'    => $row['repayment_date'],
                'link'    => '/admin/Collection-Monitoring/collection_monitoring.php',
            ];
        }

        // 3. Non-compliant (last 24 hrs)
        $res = $conn->query("
            SELECT a.audit_id, u.full_name, a.action_type, a.action_time
            FROM audit_trail a
            LEFT JOIN users u ON u.user_id = a.user_id
            WHERE a.compliance_status = 'Non-Compliant'
              AND a.action_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY a.action_time DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'compliance',
                'icon'    => 'bi-exclamation-triangle text-warning',
                'message' => 'Compliance alert: ' . htmlspecialchars($row['full_name'] ?? 'Unknown') . ' — ' . htmlspecialchars($row['action_type']),
                'time'    => $row['action_time'],
                'link'    => '/admin/Compliance-Audith-Trail-System/compliance_logs.php',
            ];
        }

        // 4. Failed logins / security (last 24 hrs)
        $res = $conn->query("
            SELECT a.audit_id, a.action_type, a.ip_address, a.action_time
            FROM audit_trail a
            WHERE a.action_type LIKE '%Failed%'
              AND a.action_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY a.action_time DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'security',
                'icon'    => 'bi-shield-exclamation text-danger',
                'message' => 'Security: ' . htmlspecialchars($row['action_type']) . ' from ' . htmlspecialchars($row['ip_address'] ?? 'Unknown'),
                'time'    => $row['action_time'],
                'link'    => '/admin/User-Management-Role-Based-Access/permission_logs.php',
            ];
        }

        // 5. Overdue loans — Approved/Active na lagpas na ang end_date
        $res = $conn->query("
            SELECT l.loan_id, m.full_name, l.principal_amount, l.end_date
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status IN ('Approved', 'Active')
              AND l.end_date < CURDATE()
            ORDER BY l.end_date ASC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'overdue',
                'icon'    => 'bi-clock-history text-danger',
                'message' => 'Overdue loan: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['principal_amount']),
                'time'    => $row['end_date'],
                'link'    => '/admin/Loan-Portfolio/loan_details.php?id=' . $row['loan_id'],
            ];
        }

        // 6. New member (last 7 days) — gumagamit ng membership_date
        $res = $conn->query("
            SELECT member_id, full_name, membership_date
            FROM members
            WHERE membership_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY membership_date DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'member',
                'icon'    => 'bi-person-plus text-info',
                'message' => 'New member registered: ' . htmlspecialchars($row['full_name']),
                'time'    => $row['membership_date'],
                'link'    => '/admin/User-Management-Role-Based-Access/user_management.php',
            ];
        }

        // 7. Disbursement released (last 7 days) — gumagamit ng disbursement_date
        $res = $conn->query("
            SELECT d.disbursement_id, m.full_name, d.amount, d.disbursement_date
            FROM disbursements d
            LEFT JOIN members m ON m.member_id = d.member_id
            WHERE d.status = 'Released'
              AND d.disbursement_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY d.disbursement_date DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'disbursement',
                'icon'    => 'bi-cash-stack text-success',
                'message' => 'Disbursement released: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['amount'], 2),
                'time'    => $row['disbursement_date'],
                'link'    => '/admin/Disbursement-Fund-Allocation-Tracker/disbursement_tracker.php',
            ];
        }

        // 8. Approval requests pending — gumagamit ng lowercase 'pending'
        $res = $conn->query("
            SELECT ar.request_id, u.full_name, ar.created_at, ar.request_type
            FROM approval_requests ar
            LEFT JOIN users u ON u.user_id = ar.user_id
            WHERE ar.status = 'pending'
            ORDER BY ar.created_at DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $label = $row['request_type'] === 'termination'
                ? 'Deactivation request' : 'Profile update request';
            $notifications[] = [
                'type'    => 'approval',
                'icon'    => 'bi-person-check text-primary',
                'message' => $label . ': ' . htmlspecialchars($row['full_name'] ?? 'Unknown'),
                'time'    => $row['created_at'],
                'link'    => '/admin/User-Management-Role-Based-Access/approval_requests.php',
            ];
        }

        // 9. Delinquent loans
        $res = $conn->query("
            SELECT l.loan_id, m.full_name, l.principal_amount, l.end_date
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status = 'Delinquent'
            ORDER BY l.end_date DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'default',
                'icon'    => 'bi-x-circle text-danger',
                'message' => 'Delinquent loan: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['principal_amount']),
                'time'    => $row['end_date'],
                'link'    => '/admin/Loan-Portfolio/loan_details.php?id=' . $row['loan_id'],
            ];
        }

        // 10. Staff actions (last 24 hrs)
        $conn->query("CREATE TABLE IF NOT EXISTS staff_action_notifications (
            notif_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL, user_name VARCHAR(100) NOT NULL,
            user_role VARCHAR(50) NOT NULL, action_type VARCHAR(100) NOT NULL,
            module_name VARCHAR(100) DEFAULT NULL, details TEXT DEFAULT NULL,
            record_id INT DEFAULT 0, created_at DATETIME DEFAULT NOW(), is_read TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $res = $conn->query("
            SELECT notif_id, user_name, action_type, module_name, details, created_at
            FROM staff_action_notifications
            WHERE user_role = 'Staff'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'staff',
                'icon'    => 'bi-person-gear text-info',
                'message' => '👤 ' . htmlspecialchars($row['user_name']) . ': ' . htmlspecialchars($row['action_type']) . ($row['details'] ? ' — ' . htmlspecialchars(substr($row['details'], 0, 60)) : ''),
                'time'    => $row['created_at'],
                'link'    => '/admin/Compliance-Audith-Trail-System/compliance_logs.php',
            ];
        }

    // ══════════════════════════════════════════════════════════════
    // ADMIN
    // ══════════════════════════════════════════════════════════════
    } elseif ($user_role === 'Admin') {

        // 1. Pending loans
        $res = $conn->query("
            SELECT l.loan_id, m.full_name, l.principal_amount, l.start_date
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status = 'Pending'
            ORDER BY l.start_date DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'loan',
                'icon'    => 'bi-file-earmark-text text-primary',
                'message' => 'Pending loan: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['principal_amount']),
                'time'    => $row['start_date'],
                'link'    => '/admin/Loan-Portfolio/loan_details.php?id=' . $row['loan_id'],
            ];
        }

        // 2. Payments today
        $res = $conn->query("
            SELECT r.repayment_id, m.full_name, r.amount, r.repayment_date
            FROM repayments r
            LEFT JOIN loan_portfolio l ON l.loan_id = r.loan_id
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE DATE(r.repayment_date) = CURDATE()
            ORDER BY r.repayment_id DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'payment',
                'icon'    => 'bi-check-circle text-success',
                'message' => 'Payment: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['amount'], 2),
                'time'    => $row['repayment_date'],
                'link'    => '/admin/Collection-Monitoring/collection_monitoring.php',
            ];
        }

        // 3. Non-compliant (last 24 hrs)
        $res = $conn->query("
            SELECT a.audit_id, u.full_name, a.action_type, a.action_time
            FROM audit_trail a
            LEFT JOIN users u ON u.user_id = a.user_id
            WHERE a.compliance_status = 'Non-Compliant'
              AND a.action_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY a.action_time DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'compliance',
                'icon'    => 'bi-exclamation-triangle text-warning',
                'message' => 'Non-compliant: ' . htmlspecialchars($row['full_name'] ?? 'Unknown') . ' — ' . htmlspecialchars($row['action_type']),
                'time'    => $row['action_time'],
                'link'    => '/admin/Compliance-Audith-Trail-System/compliance_logs.php',
            ];
        }

        // 4. Overdue repayments (overdue_count > 0)
        $res = $conn->query("
            SELECT r.repayment_id, m.full_name, r.overdue_count, r.repayment_date
            FROM repayments r
            LEFT JOIN loan_portfolio l ON l.loan_id = r.loan_id
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE r.overdue_count > 0
            ORDER BY r.overdue_count DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'overdue',
                'icon'    => 'bi-clock text-danger',
                'message' => 'Overdue: ' . htmlspecialchars($row['full_name']) . ' (' . $row['overdue_count'] . 'x overdue)',
                'time'    => $row['repayment_date'],
                'link'    => '/admin/Collection-Monitoring/collection_monitoring.php',
            ];
        }

        // 5. Overdue loans (end_date < today)
        $res = $conn->query("
            SELECT l.loan_id, m.full_name, l.principal_amount, l.end_date
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status IN ('Approved', 'Active')
              AND l.end_date < CURDATE()
            ORDER BY l.end_date ASC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'overdue',
                'icon'    => 'bi-clock-history text-danger',
                'message' => 'Overdue loan: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['principal_amount']),
                'time'    => $row['end_date'],
                'link'    => '/admin/Loan-Portfolio/loan_details.php?id=' . $row['loan_id'],
            ];
        }

        // 6. New member (last 7 days)
        $res = $conn->query("
            SELECT member_id, full_name, membership_date
            FROM members
            WHERE membership_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY membership_date DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'member',
                'icon'    => 'bi-person-plus text-info',
                'message' => 'New member: ' . htmlspecialchars($row['full_name']),
                'time'    => $row['membership_date'],
                'link'    => '/admin/User-Management-Role-Based-Access/user_management.php',
            ];
        }

        // 7. Disbursement released (last 7 days)
        $res = $conn->query("
            SELECT d.disbursement_id, m.full_name, d.amount, d.disbursement_date
            FROM disbursements d
            LEFT JOIN members m ON m.member_id = d.member_id
            WHERE d.status = 'Released'
              AND d.disbursement_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY d.disbursement_date DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'disbursement',
                'icon'    => 'bi-cash-stack text-success',
                'message' => 'Disbursement released: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['amount'], 2),
                'time'    => $row['disbursement_date'],
                'link'    => '/admin/Disbursement-Fund-Allocation-Tracker/disbursement_tracker.php',
            ];
        }

        // 8. Staff actions (last 24 hrs) — visible to Admin too
        $res = $conn->query("
            SELECT notif_id, user_name, action_type, module_name, details, created_at
            FROM staff_action_notifications
            WHERE user_role = 'Staff'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'staff',
                'icon'    => 'bi-person-gear text-info',
                'message' => '👤 ' . htmlspecialchars($row['user_name']) . ': ' . htmlspecialchars($row['action_type']) . ($row['details'] ? ' — ' . htmlspecialchars(substr($row['details'], 0, 60)) : ''),
                'time'    => $row['created_at'],
                'link'    => '/admin/Compliance-Audith-Trail-System/compliance_logs.php',
            ];
        }

    // ══════════════════════════════════════════════════════════════
    // STAFF
    // ══════════════════════════════════════════════════════════════
    } else {

        // 1. Own payments recorded today
        $stmt = $conn->prepare("
            SELECT r.repayment_id, m.full_name, r.amount, r.repayment_date
            FROM repayments r
            LEFT JOIN loan_portfolio l ON l.loan_id = r.loan_id
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE r.created_by = ?
              AND DATE(r.repayment_date) = CURDATE()
            ORDER BY r.repayment_id DESC LIMIT 5");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'payment',
                'icon'    => 'bi-check-circle text-success',
                'message' => 'Payment recorded: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['amount'], 2),
                'time'    => $row['repayment_date'],
                'link'    => '/admin/Collection-Monitoring/collection_monitoring.php',
            ];
        }
        $stmt->close();

        // 2. Pending loans
        $res = $conn->query("
            SELECT l.loan_id, m.full_name, l.principal_amount, l.start_date
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status = 'Pending'
            ORDER BY l.start_date DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'loan',
                'icon'    => 'bi-file-earmark-text text-primary',
                'message' => 'Pending loan: ' . htmlspecialchars($row['full_name']),
                'time'    => $row['start_date'],
                'link'    => '/admin/Loan-Portfolio/loan_details.php?id=' . $row['loan_id'],
            ];
        }

        // 3. Overdue loans — para malaman ng staff kung sino kailangang kolektahan
        $res = $conn->query("
            SELECT l.loan_id, m.full_name, l.principal_amount, l.end_date
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status IN ('Approved', 'Active')
              AND l.end_date < CURDATE()
            ORDER BY l.end_date ASC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'overdue',
                'icon'    => 'bi-clock-history text-danger',
                'message' => 'Overdue: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['principal_amount']),
                'time'    => $row['end_date'],
                'link'    => '/admin/Collection-Monitoring/collection_monitoring.php',
            ];
        }

        // 4. New member (last 7 days)
        $res = $conn->query("
            SELECT member_id, full_name, membership_date
            FROM members
            WHERE membership_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY membership_date DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'member',
                'icon'    => 'bi-person-plus text-info',
                'message' => 'New member: ' . htmlspecialchars($row['full_name']),
                'time'    => $row['membership_date'],
                'link'    => '/admin/User-Management-Role-Based-Access/user_management.php',
            ];
        }

        // 5. Disbursement released (last 7 days)
        $res = $conn->query("
            SELECT d.disbursement_id, m.full_name, d.amount, d.disbursement_date
            FROM disbursements d
            LEFT JOIN members m ON m.member_id = d.member_id
            WHERE d.status = 'Released'
              AND d.disbursement_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY d.disbursement_date DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'disbursement',
                'icon'    => 'bi-cash-stack text-success',
                'message' => 'Disbursement released: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['amount'], 2),
                'time'    => $row['disbursement_date'],
                'link'    => '/admin/Disbursement-Fund-Allocation-Tracker/disbursement_tracker.php',
            ];
        }
    }

    // ── Sort newest first, limit 10 ───────────────────────────────
    usort($notifications, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
    $notifications = array_slice($notifications, 0, 10);

    // ── Human-readable time labels + is_new flag ─────────────────
    $unreadCount = 0;
    foreach ($notifications as &$n) {
        $diff = time() - strtotime($n['time']);
        if ($diff < 60)        $n['time_label'] = 'Just now';
        elseif ($diff < 3600)  $n['time_label'] = floor($diff / 60) . ' mins ago';
        elseif ($diff < 86400) $n['time_label'] = floor($diff / 3600) . ' hrs ago';
        else                   $n['time_label'] = date('M j', strtotime($n['time']));

        // Mark as new if newer than last seen
        $n['is_new'] = (!$lastSeen || strtotime($n['time']) > strtotime($lastSeen)) ? 1 : 0;
        if ($n['is_new']) $unreadCount++;
    }

    echo json_encode([
        'status'        => 'success',
        'count'         => $unreadCount,      // only UNREAD count for badge
        'total'         => count($notifications),
        'notifications' => $notifications,
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'status'        => 'error',
        'message'       => $e->getMessage(),
        'count'         => 0,
        'notifications' => [],
    ]);
}
?>