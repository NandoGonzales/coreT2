<?php
/**
 * Core1 Team - Fetch Disbursement Status
 * Location: CORE2 - /api/get_disbursement_status_for_core1.php
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

require_once(__DIR__ . '/../initialize_coreT2.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://core1.microfinancial-1.com');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('EXPECTED_API_KEY', 'core2_api_key_for_core1_2026');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($apiKey) && function_exists('getallheaders')) {
    $headers = getallheaders();
    $apiKey = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';
}

if ($apiKey !== EXPECTED_API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }

    $loanCode  = trim($_GET['loan_code'] ?? '');
    $status    = trim($_GET['status'] ?? 'Released');
    $fromDate  = trim($_GET['from_date'] ?? '');
    $toDate    = trim($_GET['to_date'] ?? '');
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $limit     = max(10, min(100, (int)($_GET['limit'] ?? 50)));
    $offset    = ($page - 1) * $limit;

    $whereClause = ['1=1'];
    $params = [];
    $types = '';

    // Status filter
    $allowedStatuses = ['Released', 'Cancelled', 'Finance Approved'];
    if (!empty($status) && in_array($status, $allowedStatuses, true)) {
        $whereClause[] = 'd.status = ?';
        $params[] = $status;
        $types .= 's';
    } else {
        $whereClause[] = "d.status IN ('Released', 'Cancelled')";
    }

    // Loan code filter
    if (!empty($loanCode)) {
        $whereClause[] = 'lp.loan_code = ?';
        $params[] = $loanCode;
        $types .= 's';
    }

    // Date range filters
    if (!empty($fromDate)) {
        $whereClause[] = 'd.disbursement_date >= ?';
        $params[] = $fromDate;
        $types .= 's';
    }
    if (!empty($toDate)) {
        $whereClause[] = 'd.disbursement_date <= ?';
        $params[] = $toDate;
        $types .= 's';
    }

    $whereSql = implode(' AND ', $whereClause);

    // Count total
    $countSql = "
        SELECT COUNT(*) as total 
        FROM disbursements d
        LEFT JOIN loan_portfolio lp ON d.loan_id = lp.loan_code
        WHERE {$whereSql}
    ";
    
    if (!empty($params)) {
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
    } else {
        $totalRecords = (int)$conn->query($countSql)->fetch_assoc()['total'];
    }

    // Get records WITH loan_portfolio JOIN
    $sql = "
        SELECT 
            d.disbursement_id,
            d.loan_id,
            d.amount as disbursed_amount,
            d.disbursement_date,
            d.status as disbursement_status,
            d.remarks,
            d.finance_decision,
            d.finance_decided_at,
            lp.loan_code,
            lp.principal_amount as loan_amount,
            lp.loan_type,
            lp.interest_rate,
            lp.loan_term,
            lp.status as loan_status,
            COALESCE(m.full_name, 'N/A') AS member_name,
            COALESCE(u.full_name, 'Admin') AS approved_by_name
        FROM disbursements d
        LEFT JOIN loan_portfolio lp ON d.loan_id = lp.loan_code
        LEFT JOIN members m ON d.member_id = m.member_id
        LEFT JOIN users u ON d.approved_by = u.user_id
        WHERE {$whereSql}
        ORDER BY d.disbursement_date DESC, d.disbursement_id DESC
        LIMIT ? OFFSET ?
    ";

    $queryParams = array_merge($params, [$limit, $offset]);
    $queryTypes = $types . 'ii';

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Query prepare failed: ' . $conn->error);

    $stmt->bind_param($queryTypes, ...$queryParams);
    $stmt->execute();
    $result = $stmt->get_result();

    $disbursements = [];
    while ($row = $result->fetch_assoc()) {
        $disbursements[] = [
            'loan_code'          => $row['loan_code'],
            'disbursement_status'=> ($row['disbursement_status'] === 'Released') ? 'disbursed' : strtolower($row['disbursement_status']),
            'disbursement_date'  => $row['disbursement_date'],
            'disbursed_amount'   => (float)$row['disbursed_amount'],
            'loan_amount'        => (float)$row['loan_amount'],
            'member_name'        => $row['member_name'],
            'approved_by'        => $row['approved_by_name'],
            'remarks'            => $row['remarks'],
            'finance_decision'   => $row['finance_decision'],
            'finance_decided_at' => $row['finance_decided_at']
        ];
    }
    $stmt->close();

    while (@ob_end_clean());
    echo json_encode([
        'success'        => true,
        'total_records'  => $totalRecords,
        'current_page'   => $page,
        'total_pages'    => ceil($totalRecords / $limit),
        'per_page'       => $limit,
        'filters'        => [
            'loan_code' => $loanCode,
            'status'    => $status,
            'from_date' => $fromDate,
            'to_date'   => $toDate
        ],
        'data'           => $disbursements,
        'fetched_at'     => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    error_log("get_disbursement_status_for_core1.php Error: " . $e->getMessage());
    
    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}