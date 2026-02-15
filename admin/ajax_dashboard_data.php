<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'success', 'columns' => [], 'rows' => []];

$type   = $_GET['type']   ?? '';
$filter = $_GET['filter'] ?? '';

// Sanitize filter to prevent SQL injection
$filter = $conn->real_escape_string($filter);

try {
    switch ($type) {

        // ── MEMBERS ──────────────────────────────────────────────────────
        case 'members':
            $sql = "SELECT 
                        member_id        AS 'ID',
                        member_code      AS 'Code',
                        full_name        AS 'Full Name',
                        contact_no       AS 'Contact No',
                        address          AS 'Address',
                        membership_date  AS 'Date Joined',
                        status           AS 'Status'
                    FROM members
                    WHERE status = 'Active'
                    ORDER BY full_name";
            break;

        // ── LOANS ─────────────────────────────────────────────────────────
        case 'loans':
            $whereClause = $filter ? "WHERE l.status = '$filter'" : '';
            $sql = "SELECT 
                        l.loan_id           AS 'ID',
                        l.loan_code         AS 'Loan Code',
                        m.full_name         AS 'Member Name',
                        l.loan_type         AS 'Loan Type',
                        l.principal_amount  AS 'Principal (₱)',
                        l.interest_rate     AS 'Interest (%)',
                        l.status            AS 'Status',
                        l.start_date        AS 'Start Date',
                        l.end_date          AS 'End Date'
                    FROM loan_portfolio l
                    LEFT JOIN members m ON m.member_id = l.member_id
                    $whereClause
                    ORDER BY l.loan_id DESC";
            break;

        // ── SAVINGS ───────────────────────────────────────────────────────
        case 'savings':
            $sql = "SELECT 
                        s.saving_id         AS 'ID',
                        m.full_name         AS 'Member Name',
                        s.transaction_type  AS 'Type',
                        s.amount            AS 'Amount (₱)',
                        s.balance           AS 'Balance (₱)',
                        s.transaction_date  AS 'Date'
                    FROM savings s
                    LEFT JOIN members m ON m.member_id = s.member_id
                    ORDER BY s.transaction_date DESC";
            break;

        // ── DISBURSEMENTS ─────────────────────────────────────────────────
        case 'disbursements':
            $whereClause = $filter ? "WHERE d.status = '$filter'" : '';
            $sql = "SELECT 
                        d.disbursement_id    AS 'ID',
                        m.full_name          AS 'Member Name',
                        d.amount             AS 'Amount (₱)',
                        d.fund_source        AS 'Fund Source',
                        d.status             AS 'Status',
                        d.disbursement_date  AS 'Date Released'
                    FROM disbursements d
                    LEFT JOIN members m ON m.member_id = d.member_id
                    $whereClause
                    ORDER BY d.disbursement_date DESC";
            break;

        // ── OVERDUE LOANS ─────────────────────────────────────────────────
        case 'overdue':
            $sql = "SELECT 
                        r.repayment_id      AS 'ID',
                        m.full_name         AS 'Member Name',
                        l.loan_code         AS 'Loan Code',
                        l.principal_amount  AS 'Principal (₱)',
                        r.amount            AS 'Repayment Amount (₱)',
                        r.repayment_date    AS 'Repayment Date',
                        r.overdue_count     AS 'Overdue Count',
                        r.risk_level        AS 'Risk Level'
                    FROM repayments r
                    LEFT JOIN loan_portfolio l ON r.loan_id = l.loan_id
                    LEFT JOIN members m ON l.member_id = m.member_id
                    WHERE r.overdue_count > 0
                    ORDER BY r.overdue_count DESC";
            break;

        // ── DEFAULTED LOANS ───────────────────────────────────────────────
        case 'defaulted':
            $sql = "SELECT 
                        l.loan_id           AS 'ID',
                        l.loan_code         AS 'Loan Code',
                        m.full_name         AS 'Member Name',
                        l.principal_amount  AS 'Principal (₱)',
                        l.status            AS 'Status',
                        l.end_date          AS 'End Date'
                    FROM loan_portfolio l
                    LEFT JOIN members m ON m.member_id = l.member_id
                    WHERE l.status = 'Defaulted'
                    ORDER BY l.loan_id DESC";
            break;

        // ── PENDING LOANS ─────────────────────────────────────────────────
        case 'pending':
            $sql = "SELECT 
                        l.loan_id           AS 'ID',
                        l.loan_code         AS 'Loan Code',
                        m.full_name         AS 'Member Name',
                        l.principal_amount  AS 'Principal (₱)',
                        l.loan_type         AS 'Loan Type',
                        l.start_date        AS 'Date Applied'
                    FROM loan_portfolio l
                    LEFT JOIN members m ON m.member_id = l.member_id
                    WHERE l.status = 'Pending'
                    ORDER BY l.start_date DESC";
            break;

        // ── TODAY'S REPAYMENTS ────────────────────────────────────────────
        case 'repayments':
            $today = date('Y-m-d');
            $sql = "SELECT 
                        r.repayment_id   AS 'ID',
                        m.full_name      AS 'Member Name',
                        l.loan_code      AS 'Loan Code',
                        r.amount         AS 'Amount (₱)',
                        r.method         AS 'Payment Method',
                        r.repayment_date AS 'Payment Date',
                        r.remarks        AS 'Remarks'
                    FROM repayments r
                    LEFT JOIN loan_portfolio l ON r.loan_id = l.loan_id
                    LEFT JOIN members m ON l.member_id = m.member_id
                    WHERE DATE(r.repayment_date) = '$today'
                    ORDER BY r.repayment_date DESC";
            break;

        // ── COMPLIANCE ────────────────────────────────────────────────────
        case 'compliance':
            $sql = "SELECT 
                        a.audit_id           AS 'ID',
                        u.full_name          AS 'User',
                        a.action_type        AS 'Action',
                        a.module_name        AS 'Module',
                        a.compliance_status  AS 'Status',
                        a.action_time        AS 'Date/Time',
                        a.remarks            AS 'Remarks'
                    FROM audit_trail a
                    LEFT JOIN users u ON u.user_id = a.user_id
                    WHERE a.compliance_status IS NOT NULL
                    ORDER BY a.action_time DESC
                    LIMIT 100";
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
            exit();
    }

    $res = $conn->query($sql);

    if (!$res) {
        throw new Exception('Query failed: ' . $conn->error);
    }

    if ($res->num_rows > 0) {
        $firstRow = $res->fetch_assoc();
        $response['columns'] = array_keys($firstRow);
        $response['rows'][]  = $firstRow;
        while ($row = $res->fetch_assoc()) {
            $response['rows'][] = $row;
        }
    }

} catch (Throwable $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);