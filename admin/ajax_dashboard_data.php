<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'success', 'columns' => [], 'rows' => []];

$type   = $_GET['type']   ?? '';
$filter = $_GET['filter'] ?? '';
$filter = $conn->real_escape_string($filter);

try {
    switch ($type) {

        case 'members':
            $sql = "SELECT 
                        member_id       AS ID,
                        member_code     AS Code,
                        full_name       AS Name,
                        contact_no      AS Contact,
                        address         AS Address,
                        membership_date AS Joined,
                        status          AS Status
                    FROM members
                    WHERE status = 'Active'
                    ORDER BY full_name";
            break;

        case 'loans':
            $where = $filter ? "WHERE l.status = '$filter'" : '';
            $sql = "SELECT 
                        l.loan_id          AS ID,
                        l.loan_code        AS Code,
                        m.full_name        AS Member,
                        l.loan_type        AS Type,
                        l.principal_amount AS Principal,
                        l.interest_rate    AS Rate,
                        l.status           AS Status,
                        l.start_date       AS Started,
                        l.end_date         AS EndDate
                    FROM loan_portfolio l
                    LEFT JOIN members m ON m.member_id = l.member_id
                    $where
                    ORDER BY l.loan_id DESC";
            break;

        case 'savings':
            $sql = "SELECT 
                        s.saving_id        AS ID,
                        m.full_name        AS Member,
                        s.transaction_type AS Type,
                        s.amount           AS Amount,
                        s.balance          AS Balance,
                        s.transaction_date AS Date
                    FROM savings s
                    LEFT JOIN members m ON m.member_id = s.member_id
                    ORDER BY s.transaction_date DESC";
            break;

        case 'disbursements':
            $where = $filter ? "WHERE d.status = '$filter'" : '';
            $sql = "SELECT 
                        d.disbursement_id   AS ID,
                        m.full_name         AS Member,
                        d.amount            AS Amount,
                        d.fund_source       AS Source,
                        d.status            AS Status,
                        d.disbursement_date AS Released
                    FROM disbursements d
                    LEFT JOIN members m ON m.member_id = d.member_id
                    $where
                    ORDER BY d.disbursement_date DESC";
            break;

        case 'overdue':
            $sql = "SELECT 
                        r.repayment_id   AS ID,
                        m.full_name      AS Member,
                        l.loan_code      AS LoanCode,
                        l.principal_amount AS Principal,
                        r.amount         AS Repayment,
                        r.repayment_date AS Date,
                        r.overdue_count  AS OverdueCount,
                        r.risk_level     AS RiskLevel
                    FROM repayments r
                    LEFT JOIN loan_portfolio l ON r.loan_id = l.loan_id
                    LEFT JOIN members m ON l.member_id = m.member_id
                    WHERE r.overdue_count > 0
                    ORDER BY r.overdue_count DESC";
            break;

        case 'defaulted':
            $sql = "SELECT 
                        l.loan_id          AS ID,
                        l.loan_code        AS Code,
                        m.full_name        AS Member,
                        l.principal_amount AS Principal,
                        l.status           AS Status,
                        l.end_date         AS EndDate
                    FROM loan_portfolio l
                    LEFT JOIN members m ON m.member_id = l.member_id
                    WHERE l.status = 'Defaulted'
                    ORDER BY l.loan_id DESC";
            break;

        case 'pending':
            $sql = "SELECT 
                        l.loan_id          AS ID,
                        l.loan_code        AS Code,
                        m.full_name        AS Member,
                        l.principal_amount AS Principal,
                        l.loan_type        AS Type,
                        l.start_date       AS Applied
                    FROM loan_portfolio l
                    LEFT JOIN members m ON m.member_id = l.member_id
                    WHERE l.status = 'Pending'
                    ORDER BY l.start_date DESC";
            break;

        case 'repayments':
            $today = date('Y-m-d');
            $sql = "SELECT 
                        r.repayment_id   AS ID,
                        m.full_name      AS Member,
                        l.loan_code      AS LoanCode,
                        r.amount         AS Amount,
                        r.method         AS Method,
                        r.repayment_date AS Date,
                        r.remarks        AS Remarks
                    FROM repayments r
                    LEFT JOIN loan_portfolio l ON r.loan_id = l.loan_id
                    LEFT JOIN members m ON l.member_id = m.member_id
                    WHERE DATE(r.repayment_date) = '$today'
                    ORDER BY r.repayment_date DESC";
            break;

        case 'compliance':
            $sql = "SELECT 
                        a.audit_id          AS ID,
                        u.full_name         AS User,
                        a.action_type       AS Action,
                        a.module_name       AS Module,
                        a.compliance_status AS Status,
                        a.action_time       AS DateTime,
                        a.remarks           AS Remarks
                    FROM audit_trail a
                    LEFT JOIN users u ON u.user_id = a.user_id
                    WHERE a.compliance_status IS NOT NULL
                    ORDER BY a.action_time DESC
                    LIMIT 100";
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid type: ' . $type]);
            exit();
    }

    $res = $conn->query($sql);

    if (!$res) {
        throw new Exception('Query failed: ' . $conn->error . ' | SQL: ' . $sql);
    }

    if ($res->num_rows > 0) {
        $firstRow = $res->fetch_assoc();
        $response['columns'] = array_keys($firstRow);
        $response['rows'][]  = $firstRow;
        while ($row = $res->fetch_assoc()) {
            $response['rows'][] = $row;
        }
    } else {
        $response['rows'] = [];
    }

} catch (Throwable $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);