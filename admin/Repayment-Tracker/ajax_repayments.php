<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

ob_start();

require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/check_auth.php');

if (session_status() === PHP_SESSION_NONE) session_start();

function respondJson(array $payload, int $code = 200): void {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($_SESSION['userdata']['user_id'])) {
        respondJson(['error' => true, 'message' => 'Not authenticated'], 401);
    }

    if (!isset($conn) || !$conn || $conn->connect_error) {
        throw new Exception('Database connection failed.');
    }

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $riskFilter = isset($_GET['risk']) ? trim((string)$_GET['risk']) : '';
    $typeFilter = isset($_GET['type']) ? trim((string)$_GET['type']) : '';
    $cardFilter = isset($_GET['cardFilter']) ? trim((string)$_GET['cardFilter']) : 'all';

    $offset = ($page - 1) * $limit;

    // SUMMARY
    $summary = ['total_loans'=>0,'active_loans'=>0,'overdue_loans'=>0,'at_risk_loans'=>0];

    $r = $conn->query("SELECT COUNT(*) cnt FROM loan_portfolio");
    if ($r) $summary['total_loans'] = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $conn->query("SELECT COUNT(*) cnt FROM loan_portfolio WHERE status='Active'");
    if ($r) $summary['active_loans'] = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $conn->query("SELECT COUNT(DISTINCT lp.loan_id) cnt
        FROM loan_portfolio lp
        INNER JOIN loan_schedule ls ON lp.loan_id = ls.loan_id
        WHERE ls.status='Overdue'");
    if ($r) $summary['overdue_loans'] = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $conn->query("SELECT COUNT(DISTINCT lp.loan_id) cnt
        FROM loan_portfolio lp
        WHERE lp.status='Defaulted' OR (
            SELECT COUNT(*) FROM loan_schedule ls
            WHERE ls.loan_id=lp.loan_id AND ls.status='Overdue'
        ) >= 3");
    if ($r) $summary['at_risk_loans'] = (int)($r->fetch_assoc()['cnt'] ?? 0);

    // STATUS CHART
    $statusData = ['labels'=>[],'values'=>[]];
    $r = $conn->query("SELECT status, COUNT(*) cnt
        FROM loan_portfolio
        WHERE status IS NOT NULL
        GROUP BY status");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $statusData['labels'][] = $row['status'];
            $statusData['values'][] = (int)$row['cnt'];
        }
    }

    // RISK CHART
    $riskData = ['labels'=>['Low','Medium','High'],'values'=>[0,0,0]];

    $r = $conn->query("SELECT COUNT(DISTINCT lp.loan_id) cnt
        FROM loan_portfolio lp
        WHERE lp.status IN ('Active','Approved')
        AND (SELECT COUNT(*) FROM loan_schedule ls WHERE ls.loan_id=lp.loan_id AND ls.status='Overdue') = 0");
    if ($r) $riskData['values'][0] = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $conn->query("SELECT COUNT(DISTINCT lp.loan_id) cnt
        FROM loan_portfolio lp
        WHERE lp.status IN ('Active','Approved')
        AND (SELECT COUNT(*) FROM loan_schedule ls WHERE ls.loan_id=lp.loan_id AND ls.status='Overdue') BETWEEN 1 AND 2");
    if ($r) $riskData['values'][1] = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $conn->query("SELECT COUNT(DISTINCT lp.loan_id) cnt
        FROM loan_portfolio lp
        WHERE lp.status='Defaulted' OR (
            lp.status IN ('Active','Approved')
            AND (SELECT COUNT(*) FROM loan_schedule ls WHERE ls.loan_id=lp.loan_id AND ls.status='Overdue') >= 3
        )");
    if ($r) $riskData['values'][2] = (int)($r->fetch_assoc()['cnt'] ?? 0);

    // TYPES
    $loanTypes = [];
    $r = $conn->query("SELECT DISTINCT loan_type FROM loan_portfolio WHERE loan_type IS NOT NULL ORDER BY loan_type");
    if ($r) while ($row = $r->fetch_assoc()) $loanTypes[] = $row['loan_type'];

    // WHERE CLAUSES
    $where = [];

    if ($search !== '') {
        $s = $conn->real_escape_string($search);
        $where[] = "(lp.loan_id LIKE '%$s%' OR m.full_name LIKE '%$s%' OR lp.loan_type LIKE '%$s%')";
    }
    if ($statusFilter !== '') {
        $st = $conn->real_escape_string($statusFilter);
        $where[] = "lp.status = '$st'";
    }
    if ($typeFilter !== '') {
        $tp = $conn->real_escape_string($typeFilter);
        $where[] = "lp.loan_type = '$tp'";
    }

    // use COALESCE(ls.overdue_count,0) for safety
    if ($riskFilter !== '') {
        if ($riskFilter === 'Low') {
            $where[] = "COALESCE(ls.overdue_count,0) = 0 AND lp.status IN ('Active','Approved')";
        } elseif ($riskFilter === 'Medium') {
            $where[] = "COALESCE(ls.overdue_count,0) BETWEEN 1 AND 2";
        } elseif ($riskFilter === 'High') {
            $where[] = "(COALESCE(ls.overdue_count,0) >= 3 OR lp.status='Defaulted')";
        }
    }

    if ($cardFilter !== 'all') {
        if ($cardFilter === 'active') $where[] = "lp.status='Active'";
        if ($cardFilter === 'overdue') $where[] = "COALESCE(ls.overdue_count,0) > 0";
        if ($cardFilter === 'at_risk') $where[] = "(COALESCE(ls.overdue_count,0) >= 3 OR lp.status='Defaulted')";
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // COUNT
    $countSql = "
        SELECT COUNT(DISTINCT lp.loan_id) cnt
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id=m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) overdue_count
            FROM loan_schedule
            WHERE status='Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id=ls.loan_id
        $whereSql
    ";
    $r = $conn->query($countSql);
    $totalRecords = (int)($r ? ($r->fetch_assoc()['cnt'] ?? 0) : 0);
    $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

    // PAGE DATA
    $sql = "
        SELECT
            lp.loan_id, lp.member_id, lp.loan_type, lp.principal_amount, lp.interest_rate,
            lp.loan_term, lp.start_date, lp.end_date, lp.status,
            m.full_name member_name, m.email,
            COALESCE(ls.overdue_count,0) overdue_count,
            CASE
                WHEN lp.status='Defaulted' THEN 'High'
                WHEN COALESCE(ls.overdue_count,0) >= 3 THEN 'High'
                WHEN COALESCE(ls.overdue_count,0) BETWEEN 1 AND 2 THEN 'Medium'
                ELSE 'Low'
            END risk_level,
            (
                SELECT MIN(due_date)
                FROM loan_schedule
                WHERE loan_id=lp.loan_id AND status='Pending'
                LIMIT 1
            ) next_due
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id=m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) overdue_count
            FROM loan_schedule
            WHERE status='Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id=ls.loan_id
        $whereSql
        ORDER BY lp.loan_id DESC
        LIMIT $offset, $limit
    ";
    $r = $conn->query($sql);
    $loans = [];
    if ($r) while ($row = $r->fetch_assoc()) $loans[] = $row;

    // ALL (for export)
    $allSql = "
        SELECT
            lp.loan_id, lp.member_id, lp.loan_type, lp.principal_amount, lp.interest_rate,
            lp.loan_term, lp.start_date, lp.end_date, lp.status,
            m.full_name member_name, m.email,
            COALESCE(ls.overdue_count,0) overdue_count,
            CASE
                WHEN lp.status='Defaulted' THEN 'High'
                WHEN COALESCE(ls.overdue_count,0) >= 3 THEN 'High'
                WHEN COALESCE(ls.overdue_count,0) BETWEEN 1 AND 2 THEN 'Medium'
                ELSE 'Low'
            END risk_level
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id=m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) overdue_count
            FROM loan_schedule
            WHERE status='Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id=ls.loan_id
        $whereSql
        ORDER BY lp.loan_id DESC
    ";
    $r = $conn->query($allSql);
    $allLoans = [];
    if ($r) while ($row = $r->fetch_assoc()) $allLoans[] = $row;

    respondJson([
        'success' => true,
        'summary' => $summary,
        'loan_status' => $statusData,
        'risk_breakdown' => $riskData,
        'loan_types' => $loanTypes,
        'loans' => $loans,
        'all_loans' => $allLoans,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
        ]
    ]);

} catch (Throwable $e) {
    respondJson(['error' => true, 'message' => $e->getMessage()], 500);
}
