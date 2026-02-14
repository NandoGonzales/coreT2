<?php
/**
 * Finance API - Get Pending Disbursement Approval Requests
 * Path: /api/finance/get_pending_requests.php
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

define('FINANCE_API_KEY', 'core2_finance_2026_9f3c2b7a1d4e7c8f1a2b3c4d5e6f');

// Only GET allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET allowed']);
    exit;
}

// Read API key header safely (works even if getallheaders missing)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['REDIRECT_HTTP_X_API_KEY'] ?? '';

if ($apiKey === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    $apiKey  = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';
}

if ($apiKey !== FINANCE_API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

try {
    // Ensure table exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS disbursement_approval_requests (
          request_id       INT AUTO_INCREMENT PRIMARY KEY,
          disbursement_id  INT NOT NULL UNIQUE,
          requested_by     INT,
          requested_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          status           VARCHAR(20) DEFAULT 'Pending',
          finance_by       INT NULL,
          finance_at       DATETIME NULL,
          finance_remarks  TEXT NULL
        )
    ");

    $sql = "
        SELECT
            r.request_id,
            r.disbursement_id,
            r.requested_by,
            r.requested_at,
            r.status AS request_status,
            d.loan_id,
            lp.loan_code,
            COALESCE(m.full_name, 'N/A') AS member_name,
            d.amount,
            d.fund_source,
            d.disbursement_date,
            d.status AS disbursement_status,
            d.remarks
        FROM disbursement_approval_requests r
        LEFT JOIN disbursements d ON d.disbursement_id = r.disbursement_id
        LEFT JOIN loan_portfolio lp ON lp.loan_id = d.loan_id
        LEFT JOIN members m ON m.member_id = d.member_id
        WHERE r.status = 'Pending'
        ORDER BY r.requested_at DESC
    ";

    $res = $conn->query($sql);
    if (!$res) throw new Exception('Query failed: ' . $conn->error);

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    while (@ob_end_clean());
    echo json_encode([
        'success' => true,
        'pending_count' => count($rows),
        'data' => $rows
    ]);
    exit;

} catch (Exception $e) {
    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
