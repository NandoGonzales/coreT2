<?php
/**
 * ============================================================
 * Finance Team - Get Pending Approval Requests
 * Location: FINANCE - /api/get_pending_requests.php
 * 
 * Usage: Finance team UI calls this to display pending disbursements
 * ============================================================
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

// Start session for Finance team authentication
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../initialize.php');
header('Content-Type: application/json; charset=utf-8');

// ══════════════════════════════════════════════════════════════
// AUTHENTICATION CHECK
// ══════════════════════════════════════════════════════════════
// Option 1: Session-based (if Finance team logs in to this system)
if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
    // Option 2: API Key based (if external system)
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($apiKey) && function_exists('getallheaders')) {
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';
    }
    
    $validApiKey = 'finance_internal_key_2026';
    
    if ($apiKey !== $validApiKey) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

try {
    // Only GET allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }

    // ══════════════════════════════════════════════════════════════
    // GET PARAMETERS
    // ══════════════════════════════════════════════════════════════
    $status = trim($_GET['status'] ?? 'Pending');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(10, min(100, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // Allowed status values
    $allowedStatuses = ['Pending', 'Approved', 'Rejected', 'All'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'Pending';
    }

    // ══════════════════════════════════════════════════════════════
    // BUILD QUERY
    // ══════════════════════════════════════════════════════════════
    $whereClause = '';
    $params = [];
    $types = '';

    if ($status !== 'All') {
        $whereClause = 'WHERE finance_status = ?';
        $params[] = $status;
        $types = 's';
    }

    // Count total
    $countSql = "SELECT COUNT(*) as total FROM finance_disbursement_requests {$whereClause}";
    
    if (!empty($params)) {
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
    } else {
        $totalRecords = (int)$conn->query($countSql)->fetch_assoc()['total'];
    }

    // Get records
    $sql = "
        SELECT 
            request_id,
            disbursement_id,
            loan_id,
            loan_code,
            member_id,
            member_name,
            amount,
            fund_source,
            disbursement_date,
            original_status,
            remarks,
            requested_by,
            requested_by_id,
            received_at,
            finance_status,
            finance_decision,
            finance_remarks,
            decided_by,
            decided_by_id,
            decided_at
        FROM finance_disbursement_requests
        {$whereClause}
        ORDER BY 
            CASE 
                WHEN finance_status = 'Pending' THEN 1
                WHEN finance_status = 'Approved' THEN 2
                WHEN finance_status = 'Rejected' THEN 3
                ELSE 4
            END,
            received_at DESC
        LIMIT ? OFFSET ?
    ";

    $queryParams = array_merge($params, [$limit, $offset]);
    $queryTypes = $types . 'ii';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param($queryTypes, ...$queryParams);
    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = [
            'request_id'       => (int)$row['request_id'],
            'disbursement_id'  => (int)$row['disbursement_id'],
            'loan_id'          => $row['loan_id'],
            'loan_code'        => $row['loan_code'],
            'member_id'        => $row['member_id'],
            'member_name'      => $row['member_name'],
            'amount'           => (float)$row['amount'],
            'fund_source'      => $row['fund_source'],
            'disbursement_date'=> $row['disbursement_date'],
            'original_status'  => $row['original_status'],
            'remarks'          => $row['remarks'],
            'requested_by'     => $row['requested_by'],
            'requested_by_id'  => (int)$row['requested_by_id'],
            'received_at'      => $row['received_at'],
            'finance_status'   => $row['finance_status'],
            'finance_decision' => $row['finance_decision'],
            'finance_remarks'  => $row['finance_remarks'],
            'decided_by'       => $row['decided_by'],
            'decided_by_id'    => (int)$row['decided_by_id'],
            'decided_at'       => $row['decided_at']
        ];
    }
    $stmt->close();

    // ══════════════════════════════════════════════════════════════
    // GET SUMMARY COUNTS
    // ══════════════════════════════════════════════════════════════
    $summaryResult = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN finance_status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN finance_status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN finance_status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN finance_status = 'Pending' THEN amount ELSE 0 END) as pending_amount
        FROM finance_disbursement_requests
    ");

    $summary = $summaryResult->fetch_assoc();

    // ══════════════════════════════════════════════════════════════
    // SUCCESS RESPONSE
    // ══════════════════════════════════════════════════════════════
    while (@ob_end_clean());
    echo json_encode([
        'success' => true,
        'filter_status' => $status,
        'total_records' => $totalRecords,
        'current_page' => $page,
        'total_pages' => ceil($totalRecords / $limit),
        'per_page' => $limit,
        'data' => $requests,
        'summary' => [
            'total' => (int)$summary['total'],
            'pending' => (int)$summary['pending'],
            'approved' => (int)$summary['approved'],
            'rejected' => (int)$summary['rejected'],
            'pending_amount' => (float)$summary['pending_amount']
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    error_log("get_pending_requests.php Error: " . $e->getMessage());
    
    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}