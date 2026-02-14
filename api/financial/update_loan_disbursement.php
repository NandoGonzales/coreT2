<?php
/**
 * ============================================================
 * Core1 - Update Loan Disbursement Status
 * Location: CORE 1 - /api/update_loan_disbursement.php
 * 
 * Flow: Core2 (after Finance approval) → Core1 (this file) → Update loans table
 * ============================================================
 */

while (@ob_end_clean());
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

require_once(__DIR__ . '/../initialize.php'); // Core1 initialization

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
define('EXPECTED_API_KEY', 'core2_to_core1_secure_key_2026');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($apiKey) && function_exists('getallheaders')) {
    $headers = getallheaders();
    $apiKey = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';
}

if ($apiKey !== EXPECTED_API_KEY) {
    error_log("Core1: Invalid API key received");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ENSURE SYNC LOG TABLE EXISTS
// ══════════════════════════════════════════════════════════════
$conn->query("
    CREATE TABLE IF NOT EXISTS core1_disbursement_sync_log (
        log_id             INT AUTO_INCREMENT PRIMARY KEY,
        loan_code          VARCHAR(50),
        action             VARCHAR(50),
        old_status         VARCHAR(50),
        new_status         VARCHAR(50),
        disbursed_amount   DECIMAL(15,2),
        disbursement_date  DATE,
        remarks            TEXT,
        source             VARCHAR(100),
        http_status        INT DEFAULT 200,
        synced_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_loan_code (loan_code),
        INDEX idx_synced_at (synced_at)
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

    error_log("Core1 received: " . $raw);

    if (!is_array($data)) {
        throw new Exception('Invalid JSON payload');
    }

    $action = $data['action'] ?? '';
    $loanCode = trim($data['loan_code'] ?? '');
    $disbursementStatus = trim($data['disbursement_status'] ?? ''); // 'Released' or 'Rejected'
    $disbursementDate = $data['disbursement_date'] ?? date('Y-m-d');
    $disbursedAmount = (float)($data['disbursed_amount'] ?? 0);
    $remarks = trim($data['remarks'] ?? '');
    $source = $data['source'] ?? 'Core2';

    if ($action !== 'update_disbursement') {
        throw new Exception('Invalid action');
    }

    if (empty($loanCode)) {
        throw new Exception('loan_code is required');
    }

    if (!in_array($disbursementStatus, ['Released', 'Rejected', 'disbursed', 'rejected'], true)) {
        throw new Exception('Invalid disbursement_status. Must be Released, Rejected, disbursed, or rejected');
    }

    // Normalize status
    $normalizedStatus = strtolower($disbursementStatus);
    if ($normalizedStatus === 'released') {
        $normalizedStatus = 'disbursed';
    }

    // ══════════════════════════════════════════════════════════════
    // CHECK IF LOAN EXISTS
    // ══════════════════════════════════════════════════════════════
    $checkStmt = $conn->prepare("
        SELECT 
            loan_id,
            loan_code,
            disbursement_status,
            disbursement_date,
            loan_amount
        FROM loans
        WHERE loan_code = ?
        LIMIT 1
    ");

    if (!$checkStmt) throw new Exception('Query prepare failed: ' . $conn->error);

    $checkStmt->bind_param('s', $loanCode);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        $checkStmt->close();
        throw new Exception("Loan with code {$loanCode} not found in Core1");
    }

    $loan = $result->fetch_assoc();
    $checkStmt->close();

    $loanId = $loan['loan_id'];
    $oldStatus = $loan['disbursement_status'] ?? 'pending';

    // ══════════════════════════════════════════════════════════════
    // UPDATE LOANS TABLE
    // ══════════════════════════════════════════════════════════════
    $conn->begin_transaction();

    try {
        // Update main loans table
        $updateStmt = $conn->prepare("
            UPDATE loans
            SET disbursement_status = ?,
                disbursement_date = ?,
                disbursed_amount = ?,
                updated_at = NOW(),
                remarks = CONCAT(
                    COALESCE(remarks, ''),
                    '\n[', NOW(), '] ', ?, ': Status updated to ', ?
                    IF(? != '', CONCAT(' - ', ?), '')
                )
            WHERE loan_code = ?
        ");

        if (!$updateStmt) throw new Exception('Update prepare failed: ' . $conn->error);

        $updateStmt->bind_param(
            'ssdssss',
            $normalizedStatus,
            $disbursementDate,
            $disbursedAmount,
            $source,
            $disbursementStatus,
            $remarks,
            $remarks,
            $loanCode
        );

        $updateStmt->execute();
        $affected = $updateStmt->affected_rows;
        $updateStmt->close();

        // Log the sync
        $logStmt = $conn->prepare("
            INSERT INTO core1_disbursement_sync_log
                (loan_code, action, old_status, new_status, disbursed_amount, 
                 disbursement_date, remarks, source, http_status)
            VALUES (?, 'update_disbursement', ?, ?, ?, ?, ?, 'Core2', 200)
        ");

        if ($logStmt) {
            $logStmt->bind_param(
                'sssdss',
                $loanCode,
                $oldStatus,
                $normalizedStatus,
                $disbursedAmount,
                $disbursementDate,
                $remarks
            );
            $logStmt->execute();
            $logStmt->close();
        }

        $conn->commit();

        error_log("Core1: Successfully updated loan {$loanCode} to status {$normalizedStatus}");

    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception('Database update failed: ' . $e->getMessage());
    }

    // ══════════════════════════════════════════════════════════════
    // SUCCESS RESPONSE
    // ══════════════════════════════════════════════════════════════
    while (@ob_end_clean());
    echo json_encode([
        'success' => true,
        'message' => "Loan {$loanCode} disbursement status updated to {$disbursementStatus}",
        'data' => [
            'loan_id'          => $loanId,
            'loan_code'        => $loanCode,
            'old_status'       => $oldStatus,
            'new_status'       => $normalizedStatus,
            'disbursement_date'=> $disbursementDate,
            'disbursed_amount' => $disbursedAmount,
            'rows_affected'    => $affected ?? 0
        ]
    ]);
    exit;

} catch (Exception $e) {
    error_log("Core1 update_loan_disbursement.php Error: " . $e->getMessage());
    
    // Log failed sync
    if (isset($conn) && $conn->ping() && !empty($loanCode ?? '')) {
        @$conn->query("
            INSERT INTO core1_disbursement_sync_log
                (loan_code, action, remarks, source, http_status)
            VALUES 
                ('{$conn->real_escape_string($loanCode)}', 
                 'update_disbursement', 
                 'ERROR: {$conn->real_escape_string($e->getMessage())}',
                 'Core2',
                 500)
        ");
    }
    
    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}