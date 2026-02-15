<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['userdata'])) {
    echo json_encode(['status' => 'error', 'count' => 0, 'notifications' => []]);
    exit();
}

$user_id   = $_SESSION['userdata']['user_id'] ?? 0;
$user_role = $_SESSION['userdata']['role'] ?? 'Staff';
$notifications = [];

try {
    if ($user_role === 'Super Admin') {

        // New pending loans (last 7 days)
        $res = $conn->query("SELECT l.loan_id, m.full_name, l.principal_amount, l.start_date
            FROM loan_portfolio l LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.status = 'Pending' AND l.start_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY l.start_date DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = ['type'=>'loan','icon'=>'bi-file-earmark-text text-primary',
                'message'=>'New loan: '.htmlspecialchars($row['full_name']).' — ₱'.number_format($row['principal_amount']),
                'time'=>$row['start_date'],'link'=>'/admin/Loan-Portfolio/loan_details.php?id='.$row['loan_id']];
        }

        // Payments today
        $res = $conn->query("SELECT r.repayment_id, m.full_name, r.amount, r.repayment_date
            FROM repayments r LEFT JOIN loan_portfolio l ON l.loan_id=r.loan_id
            LEFT JOIN members m ON m.member_id=l.member_id
            WHERE DATE(r.repayment_date)=CURDATE() ORDER BY r.repayment_id DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = ['type'=>'payment','icon'=>'bi-check-circle text-success',
                'message'=>'Payment received: '.htmlspecialchars($row['full_name']).' — ₱'.number_format($row['amount'],2),
                'time'=>$row['repayment_date'],'link'=>'/admin/Collection-Monitoring/collection_monitoring.php'];
        }

        // Non-compliant (last 24 hrs)
        $res = $conn->query("SELECT a.audit_id, u.full_name, a.action_type, a.action_time
            FROM audit_trail a LEFT JOIN users u ON u.user_id=a.user_id
            WHERE a.compliance_status='Non-Compliant' AND a.action_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY a.action_time DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = ['type'=>'compliance','icon'=>'bi-exclamation-triangle text-warning',
                'message'=>'Compliance alert: '.htmlspecialchars($row['full_name']??'Unknown').' — '.htmlspecialchars($row['action_type']),
                'time'=>$row['action_time'],'link'=>'/admin/Compliance-Audit/compliance_logs.php'];
        }

        // Failed logins / security (last 24 hrs)
        $res = $conn->query("SELECT a.audit_id, a.action_type, a.ip_address, a.action_time
            FROM audit_trail a WHERE a.action_type LIKE '%Failed%'
            AND a.action_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY a.action_time DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = ['type'=>'security','icon'=>'bi-shield-exclamation text-danger',
                'message'=>'Security: '.htmlspecialchars($row['action_type']).' from '.htmlspecialchars($row['ip_address']??'Unknown'),
                'time'=>$row['action_time'],'link'=>'/admin/User-Management-Role-Based-Access/permission_logs.php'];
        }

        // Defaulted loans (last 7 days)
        $res = $conn->query("SELECT l.loan_id, m.full_name, l.principal_amount, l.end_date
            FROM loan_portfolio l LEFT JOIN members m ON m.member_id=l.member_id
            WHERE l.status='Defaulted' AND l.end_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY l.end_date DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = ['type'=>'default','icon'=>'bi-x-circle text-danger',
                'message'=>'Loan defaulted: '.htmlspecialchars($row['full_name']).' — ₱'.number_format($row['principal_amount']),
                'time'=>$row['end_date'],'link'=>'/admin/Loan-Portfolio/loan_details.php?id='.$row['loan_id']];
        }

    } elseif ($user_role === 'Admin') {

        $res = $conn->query("SELECT l.loan_id, m.full_name, l.principal_amount, l.start_date
            FROM loan_portfolio l LEFT JOIN members m ON m.member_id=l.member_id
            WHERE l.status='Pending' ORDER BY l.start_date DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = ['type'=>'loan','icon'=>'bi-file-earmark-text text-primary',
                'message'=>'Pending loan: '.htmlspecialchars($row['full_name']).' — ₱'.number_format($row['principal_amount']),
                'time'=>$row['start_date'],'link'=>'/admin/Loan-Portfolio/loan_details.php?id='.$row['loan_id']];
        }

        $res = $conn->query("SELECT r.repayment_id, m.full_name, r.amount, r.repayment_date
            FROM repayments r LEFT JOIN loan_portfolio l ON l.loan_id=r.loan_id
            LEFT JOIN members m ON m.member_id=l.member_id
            WHERE DATE(r.repayment_date)=CURDATE() ORDER BY r.repayment_id DESC LIMIT 5");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = ['type'=>'payment','icon'=>'bi-check-circle text-success',
                'message'=>'Payment: '.htmlspecialchars($row['full_name']).' — ₱'.number_format($row['amount'],2),
                'time'=>$row['repayment_date'],'link'=>'/admin/Collection-Monitoring/collection_monitoring.php'];
        }

        $res = $conn->query("SELECT a.audit_id, u.full_name, a.action_type, a.action_time
            FROM audit_trail a LEFT JOIN users u ON u.user_id=a.user_id
            WHERE a.compliance_status='Non-Compliant' AND a.action_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY a.action_time DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = ['type'=>'compliance','icon'=>'bi-exclamation-triangle text-warning',
                'message'=>'Non-compliant: '.htmlspecialchars($row['full_name']??'Unknown').' — '.htmlspecialchars($row['action_type']),
                'time'=>$row['action_time'],'link'=>'/admin/Compliance-Audit/compliance_logs.php'];
        }

        $res = $conn->query("SELECT r.repayment_id, m.full_name, r.overdue_count, r.repayment_date
            FROM repayments r LEFT JOIN loan_portfolio l ON l.loan_id=r.loan_id
            LEFT JOIN members m ON m.member_id=l.member_id
            WHERE r.overdue_count > 0 ORDER BY r.overdue_count DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = ['type'=>'overdue','icon'=>'bi-clock text-danger',
                'message'=>'Overdue: '.htmlspecialchars($row['full_name']).' ('.$row['overdue_count'].'x overdue)',
                'time'=>$row['repayment_date'],'link'=>'/admin/Collection-Monitoring/collection_monitoring.php'];
        }

    } else { // Staff

        $stmt = $conn->prepare("SELECT r.repayment_id, m.full_name, r.amount, r.repayment_date
            FROM repayments r LEFT JOIN loan_portfolio l ON l.loan_id=r.loan_id
            LEFT JOIN members m ON m.member_id=l.member_id
            WHERE r.created_by=? AND DATE(r.repayment_date)=CURDATE()
            ORDER BY r.repayment_id DESC LIMIT 5");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = ['type'=>'payment','icon'=>'bi-check-circle text-success',
                'message'=>'Payment recorded: '.htmlspecialchars($row['full_name']).' — ₱'.number_format($row['amount'],2),
                'time'=>$row['repayment_date'],'link'=>'/admin/Collection-Monitoring/collection_monitoring.php'];
        }

        $res = $conn->query("SELECT l.loan_id, m.full_name, l.principal_amount, l.start_date
            FROM loan_portfolio l LEFT JOIN members m ON m.member_id=l.member_id
            WHERE l.status='Pending' ORDER BY l.start_date DESC LIMIT 3");
        if ($res) while ($row = $res->fetch_assoc()) {
            $notifications[] = ['type'=>'loan','icon'=>'bi-file-earmark-text text-primary',
                'message'=>'Pending loan: '.htmlspecialchars($row['full_name']),
                'time'=>$row['start_date'],'link'=>'/admin/Loan-Portfolio/loan_details.php?id='.$row['loan_id']];
        }
    }

    usort($notifications, fn($a,$b) => strtotime($b['time']) - strtotime($a['time']));
    $notifications = array_slice($notifications, 0, 10);

    foreach ($notifications as &$n) {
        $diff = time() - strtotime($n['time']);
        if ($diff < 60)        $n['time_label'] = 'Just now';
        elseif ($diff < 3600)  $n['time_label'] = floor($diff/60).' mins ago';
        elseif ($diff < 86400) $n['time_label'] = floor($diff/3600).' hrs ago';
        else                   $n['time_label'] = date('M j', strtotime($n['time']));
    }

    echo json_encode(['status'=>'success','count'=>count($notifications),'notifications'=>$notifications]);

} catch (Throwable $e) {
    echo json_encode(['status'=>'error','message'=>$e->getMessage(),'count'=>0,'notifications'=>[]]);
}
?>