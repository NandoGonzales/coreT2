<?php
/**
 * ============================================================
 * Finance Team - Receive Disbursement Approval Requests
 * Location: FINANCE - /api/receive_disbursement_requests.php
 * 
 * Flow: Core2 → Finance (this file) → Store in database
 * ============================================================
 */

while (@ob_end_clean());
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

require_once(__DIR__ . '/../initialize.php'); // Finance domain initialization

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://core2.microfinancial-1.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ══════════════════════════════════════════════════════════════
// API KEY VALIDATION
// ══════════════════════════════════════════════════════════════
define('EXPECTED_API_KEY', 'finance_core2_secure_key_2026_v1');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($apiKey) && function_exists('getallheaders')) {
    $headers = getallheaders();
    $apiKey = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';
}

if ($apiKey !== EXPECTED_API_KEY) {
    error_log("Finance: Invalid API key received");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ENSURE TABLES EXIST
// ══════════════════════════════════════════════════════════════
$conn->query("
    CREATE TABLE IF NOT EXISTS finance_disbursement_requests (
        request_id       INT AUTO_INCREMENT PRIMARY KEY,
        disbursement_id  INT NOT NULL,
        loan_id          VARCHAR(50),
        loan_code        VARCHAR(50),
        member_id        VARCHAR(50),
        member_name      VARCHAR(200),
        amount           DECIMAL(15,2),
        fund_source      VARCHAR(100),
        disbursement_date DATE,
        original_status  VARCHAR(50),
        remarks          TEXT,
        requested_by     VARCHAR(100),
        requested_by_id  INT,
        received_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        finance_status   VARCHAR(50) DEFAULT 'Pending',
        finance_decision VARCHAR(50) NULL,
        finance_remarks  TEXT NULL,
        decided_by       VARCHAR(100) NULL,
        decided_by_id    INT NULL,
        decided_at       DATETIME NULL,
        core2_callback   VARCHAR(255) NULL,
        
        UNIQUE KEY unique_disbursement (disbursement_id),
        INDEX idx_finance_status (finance_status),
        INDEX idx_loan_code (loan_code),
        INDEX idx_received_at (received_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->query("
    CREATE TABLE IF NOT EXISTS finance_activity_log (
        log_id      INT AUTO_INCREMENT PRIMARY KEY,
        request_id  INT,
        action      VARCHAR(100),
        details     TEXT,
        user_id     INT NULL,
        user_name   VARCHAR(100) NULL,
        ip_address  VARCHAR(45) NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_request_id (request_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

try {
    // Only POST allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    // ══════════════════════════════════════════════════════════════
    // READ REQUEST FROM CORE2
    // ══════════════════════════════════════════════════════════════
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    error_log("Finance received: " . $raw);

    if (!is_array($data)) {
        throw new Exception('Invalid JSON payload');
    }

    $action = $data['action'] ?? '';
    $disbursements = $data['disbursements'] ?? [];
    $callbackUrl = $data['callback_url'] ?? '';

    if ($action !== 'receive_requests') {
        throw new Exception('Invalid action');
    }

    if (!is_array($disbursements) || empty($disbursements)) {
        throw new Exception('No disbursements provided');
    }

    // ══════════════════════════════════════════════════════════════
    // SAVE EACH DISBURSEMENT REQUEST
    // ══════════════════════════════════════════════════════════════
    $received = 0;
    $updated = 0;
    $errors = [];

    $insertStmt = $conn->prepare("
        INSERT INTO finance_disbursement_requests (
            disbursement_id, loan_id, loan_code, member_id, member_name,
            amount, fund_source, disbursement_date, original_status,
            remarks, requested_by, requested_by_id, finance_status, core2_callback
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
        ON DUPLICATE KEY UPDATE
            amount = VALUES(amount),
            fund_source = VALUES(fund_source),
            disbursement_date = VALUES(disbursement_date),
            remarks = VALUES(remarks),
            requested_by = VALUES(requested_by),
            received_at = CURRENT_TIMESTAMP,
            finance_status = 'Pending'
    ");

    if (!$insertStmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    foreach ($disbursements as $disb) {
        try {
            $disbId         = (int)($disb['disbursement_id'] ?? 0);
            $loanId         = $disb['loan_id'] ?? '';
            $loanCode       = $disb['loan_code'] ?? '';
            $memberId       = $disb['member_id'] ?? '';
            $memberName     = $disb['member_name'] ?? '';
            $amount         = (float)($disb['amount'] ?? 0);
            $fundSource     = $disb['fund_source'] ?? '';
            $disbDate       = $disb['disbursement_date'] ?? date('Y-m-d');
            $status         = $disb['status'] ?? '';
            $remarks        = $disb['remarks'] ?? '';
            $requestedBy    = $disb['requested_by'] ?? '';
            $requestedById  = (int)($disb['requested_by_id'] ?? 0);

            if ($disbId <= 0) {
                $errors[] = "Invalid disbursement_id";
                continue;
            }

            $insertStmt->bind_param(
                'issssdsssssis',
                $disbId, $loanId, $loanCode, $memberId, $memberName,
                $amount, $fundSource, $disbDate, $status,
                $remarks, $requestedBy, $requestedById, $callbackUrl
            );

            if ($insertStmt->execute()) {
                if ($insertStmt->affected_rows === 1) {
                    $received++;
                } else {
                    $updated++;
                }
            } else {
                $errors[] = "DB Error for disbursement_id {$disbId}: " . $insertStmt->error;
            }

        } catch (Exception $e) {
            $errors[] = "Exception: " . $e->getMessage();
        }
    }

    $insertStmt->close();

    // ══════════════════════════════════════════════════════════════
    // LOG ACTIVITY
    // ══════════════════════════════════════════════════════════════
    $logStmt = $conn->prepare("
        INSERT INTO finance_activity_log (action, details, ip_address)
        VALUES ('Received Requests from Core2', ?, ?)
    ");

    if ($logStmt) {
        $details = "Received: {$received}, Updated: {$updated}, Errors: " . count($errors);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logStmt->bind_param('ss', $details, $ipAddress);
        $logStmt->execute();
        $logStmt->close();
    }

    // ══════════════════════════════════════════════════════════════
    // SUCCESS RESPONSE
    // ══════════════════════════════════════════════════════════════
    $totalProcessed = $received + $updated;

    while (@ob_end_clean());
    echo json_encode([
        'success' => true,
        'message' => "Received {$totalProcessed} disbursement request(s) for approval",
        'received' => $received,
        'updated' => $updated,
        'total' => $totalProcessed,
        'errors' => $errors,
        'next_step' => 'Finance team can now review and approve/reject via the dashboard'
    ]);
    exit;

} catch (Exception $e) {
    error_log("Finance receive_disbursement_requests.php Error: " . $e->getMessage());
    
    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}