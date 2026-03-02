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

    // ══════════════════════════════════════════════════════════════
    // SUPER ADMIN
    // ══════════════════════════════════════════════════════════════
    if ($user_role === 'Super Admin') {

        // 1. New pending loans (last 7 days)
        $res = $conn->query("
            SELECT l.loan_id, m.full_name, l.principal_amount, l.start_date
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status = 'Pending'
              AND l.start_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
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

        // 5. Defaulted loans (last 7 days)
        $res = $conn->query("
            SELECT l.loan_id, m.full_name, l.principal_amount, l.end_date
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status = 'Defaulted'
              AND l.end_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY l.end_date DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'default',
                'icon'    => 'bi-x-circle text-danger',
                'message' => 'Loan defaulted: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['principal_amount']),
                'time'    => $row['end_date'],
                'link'    => '/admin/Loan-Portfolio/loan_details.php?id=' . $row['loan_id'],
            ];
        }

        // 6. NEW — Overdue loans (last 30 days)
        $res = $conn->query("
            SELECT l.loan_id, m.full_name, l.principal_amount, l.end_date
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status = 'Overdue'
              AND l.end_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
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

        // 7. NEW — New member registered (last 7 days)
        $res = $conn->query("
            SELECT member_id, full_name, created_at
            FROM members
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'member',
                'icon'    => 'bi-person-plus text-info',
                'message' => 'New member registered: ' . htmlspecialchars($row['full_name']),
                'time'    => $row['created_at'],
                'link'    => '/admin/User-Management-Role-Based-Access/user_management.php',
            ];
        }

        // 8. NEW — Savings withdrawal requests (last 7 days)
        $res = $conn->query("
            SELECT sw.id, m.full_name, sw.amount, sw.requested_at
            FROM savings_withdrawals sw
            LEFT JOIN members m ON m.member_id = sw.member_id
            WHERE sw.status = 'Pending'
              AND sw.requested_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY sw.requested_at DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'savings',
                'icon'    => 'bi-piggy-bank text-warning',
                'message' => 'Savings withdrawal request: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['amount'], 2),
                'time'    => $row['requested_at'],
                'link'    => '/admin/Savings-Monitoring/savings_monitoring.php',
            ];
        }

        // 9. NEW — Disbursement approved/released (last 7 days)
        $res = $conn->query("
            SELECT d.id, m.full_name, d.amount, d.released_at
            FROM disbursements d
            LEFT JOIN members m ON m.member_id = d.member_id
            WHERE d.status = 'Released'
              AND d.released_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY d.released_at DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'disbursement',
                'icon'    => 'bi-cash-stack text-success',
                'message' => 'Disbursement released: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['amount'], 2),
                'time'    => $row['released_at'],
                'link'    => '/admin/Disbursement-Fund-Allocation-Tracker/disbursement_tracker.php',
            ];
        }

        // 10. NEW — Profile update approval requests (Super Admin only)
        $res = $conn->query("
            SELECT ar.id, u.full_name, ar.created_at
            FROM approval_requests ar
            LEFT JOIN users u ON u.user_id = ar.user_id
            WHERE ar.request_type = 'profile_update'
              AND ar.status = 'Pending'
            ORDER BY ar.created_at DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'approval',
                'icon'    => 'bi-person-check text-primary',
                'message' => 'Profile update request: ' . htmlspecialchars($row['full_name'] ?? 'Unknown'),
                'time'    => $row['created_at'],
                'link'    => '/admin/User-Management-Role-Based-Access/approval_requests.php',
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

        // 4. Overdue repayments
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

        // 5. NEW — New member registered (last 7 days)
        $res = $conn->query("
            SELECT member_id, full_name, created_at
            FROM members
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'member',
                'icon'    => 'bi-person-plus text-info',
                'message' => 'New member: ' . htmlspecialchars($row['full_name']),
                'time'    => $row['created_at'],
                'link'    => '/admin/User-Management-Role-Based-Access/user_management.php',
            ];
        }

        // 6. NEW — Savings withdrawal requests (last 7 days)
        $res = $conn->query("
            SELECT sw.id, m.full_name, sw.amount, sw.requested_at
            FROM savings_withdrawals sw
            LEFT JOIN members m ON m.member_id = sw.member_id
            WHERE sw.status = 'Pending'
              AND sw.requested_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY sw.requested_at DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'savings',
                'icon'    => 'bi-piggy-bank text-warning',
                'message' => 'Savings withdrawal: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['amount'], 2),
                'time'    => $row['requested_at'],
                'link'    => '/admin/Savings-Monitoring/savings_monitoring.php',
            ];
        }

        // 7. NEW — Disbursement approved/released (last 7 days)
        $res = $conn->query("
            SELECT d.id, m.full_name, d.amount, d.released_at
            FROM disbursements d
            LEFT JOIN members m ON m.member_id = d.member_id
            WHERE d.status = 'Released'
              AND d.released_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY d.released_at DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'disbursement',
                'icon'    => 'bi-cash-stack text-success',
                'message' => 'Disbursement released: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['amount'], 2),
                'time'    => $row['released_at'],
                'link'    => '/admin/Disbursement-Fund-Allocation-Tracker/disbursement_tracker.php',
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

        // 3. NEW — Overdue loans (para malaman ng staff kung sino ang overdue)
        $res = $conn->query("
            SELECT l.loan_id, m.full_name, l.principal_amount, l.end_date
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status = 'Overdue'
              AND l.end_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
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

        // 4. NEW — New member registered (last 7 days)
        $res = $conn->query("
            SELECT member_id, full_name, created_at
            FROM members
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'member',
                'icon'    => 'bi-person-plus text-info',
                'message' => 'New member: ' . htmlspecialchars($row['full_name']),
                'time'    => $row['created_at'],
                'link'    => '/admin/User-Management-Role-Based-Access/user_management.php',
            ];
        }

        // 5. NEW — Disbursement released (para malaman ng staff)
        $res = $conn->query("
            SELECT d.id, m.full_name, d.amount, d.released_at
            FROM disbursements d
            LEFT JOIN members m ON m.member_id = d.member_id
            WHERE d.status = 'Released'
              AND d.released_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY d.released_at DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = [
                'type'    => 'disbursement',
                'icon'    => 'bi-cash-stack text-success',
                'message' => 'Disbursement released: ' . htmlspecialchars($row['full_name']) . ' — ₱' . number_format($row['amount'], 2),
                'time'    => $row['released_at'],
                'link'    => '/admin/Disbursement-Fund-Allocation-Tracker/disbursement_tracker.php',
            ];
        }
    }

    // ── Sort by newest first, limit to 10 ─────────────────────────
    usort($notifications, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
    $notifications = array_slice($notifications, 0, 10);

    // ── Human-readable time labels ─────────────────────────────────
    foreach ($notifications as &$n) {
        $diff = time() - strtotime($n['time']);
        if ($diff < 60)        $n['time_label'] = 'Just now';
        elseif ($diff < 3600)  $n['time_label'] = floor($diff / 60) . ' mins ago';
        elseif ($diff < 86400) $n['time_label'] = floor($diff / 3600) . ' hrs ago';
        else                   $n['time_label'] = date('M j', strtotime($n['time']));
    }

    echo json_encode([
        'status'        => 'success',
        'count'         => count($notifications),
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