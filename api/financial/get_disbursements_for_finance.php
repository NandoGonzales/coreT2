<?php
/**
 * Finance Team - Fetch Pending Disbursements
 * Location: CORE2 - /api/get_disbursements_for_finance.php
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

require_once(__DIR__ . '/../initialize_coreT2.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://finance.microfinancial-1.com');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('EXPECTED_API_KEY', 'core2_api_key_for_finance_2026');

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

    $status = trim($_GET['status'] ?? 'Pending');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = max(10, min(100, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $allowedStatuses = ['Pending', 'Finance Approved', 'Released', 'Cancelled'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'Pending';
    }

    // Count total
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM disbursements WHERE status = ?");
    $countStmt->bind_param('s', $status);
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    // Get records WITH loan_portfolio JOIN
    $sql = "
        SELECT 
            d.disbursement_id,
            d.loan_id,
            d.member_id,
            d.amount,
            d.fund_source,
            d.disbursement_date,
            d.status,
            d.remarks,
            d.approved_by,
            d.sent_to_finance_at,
            d.created_at,
            lp.loan_code,
            lp.principal_amount as loan_amount,
            lp.loan_type,
            lp.interest_rate,
            lp.loan_term,
            lp.start_date,
            lp.end_date,
            COALESCE(m.full_name, 'N/A') AS member_name,
            COALESCE(u.full_name, 'Admin') AS approved_by_name
        FROM disbursements d
        LEFT JOIN loan_portfolio lp ON d.loan_id = lp.loan_code
        LEFT JOIN members m ON d.member_id = m.member_id
        LEFT JOIN users u ON d.approved_by = u.user_id
        WHERE d.status = ?
        ORDER BY d.sent_to_finance_at DESC, d.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Query prepare failed: ' . $conn->error);

    $stmt->bind_param('sii', $status, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $disbursements = [];
    while ($row = $result->fetch_assoc()) {
        $disbursements[] = [
            'disbursement_id'   => (int)$row['disbursement_id'],
            'loan_id'           => $row['loan_id'],
            'loan_code'         => $row['loan_code'],
            'member_id'         => (int)$row['member_id'],
            'member_name'       => $row['member_name'],
            'amount'            => (float)$row['amount'],
            'fund_source'       => $row['fund_source'],
            'disbursement_date' => $row['disbursement_date'],
            'status'            => $row['status'],
            'remarks'           => $row['remarks'],
            'approved_by_name'  => $row['approved_by_name'],
            'sent_to_finance_at'=> $row['sent_to_finance_at'],
            'loan_details'      => [
                'loan_code'     => $row['loan_code'],
                'loan_amount'   => (float)$row['loan_amount'],
                'loan_type'     => $row['loan_type'],
                'interest_rate' => (float)$row['interest_rate'],
                'loan_term'     => (int)$row['loan_term'],
                'start_date'    => $row['start_date'],
                'end_date'      => $row['end_date']
            ]
        ];
    }
    $stmt->close();

    while (@ob_end_clean());
    echo json_encode([
        'success'        => true,
        'filter_status'  => $status,
        'total_records'  => $totalRecords,
        'current_page'   => $page,
        'total_pages'    => ceil($totalRecords / $limit),
        'per_page'       => $limit,
        'data'           => $disbursements,
        'fetched_at'     => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    error_log("get_disbursements_for_finance.php Error: " . $e->getMessage());
    
    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}