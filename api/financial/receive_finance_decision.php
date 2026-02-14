<?php
/**
 * ============================================================
 * Receive Finance Team Decision (Approve/Reject)
 * Location: CORE 2 - /api/receive_finance_decision.php
 * 
 * Flow: Finance Team → Core2 (this file) → Update disbursements → Send to Core1
 * ============================================================
 */

while (@ob_end_clean());
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

require_once(__DIR__ . '/../initialize_coreT2.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://finance.microfinancial-1.com');
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
    error_log("Invalid API key received: {$apiKey}");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

// ══════════════════════════════════════════════════════════════
// CORE1 UPDATE CONFIGURATION
// ══════════════════════════════════════════════════════════════
define('CORE1_UPDATE_URL', 'https://core1.microfinancial-1.com/api/update_loan_disbursement.php');
define('CORE1_API_KEY', 'core2_to_core1_secure_key_2026');

/**
 * Send final disbursement status to Core1
 */
function sendToCore1($loanCode, $status, $amount, $date, $remarks) {
    if (empty($loanCode)) {
        error_log("Cannot send to Core1: No loan_code provided");
        return ['success' => false, 'message' => 'No loan_code'];
    }

    $payload = json_encode([
        'action'              => 'update_disbursement',
        'loan_code'           => $loanCode,
        'disbursement_status' => ($status === 'Approved') ? 'Released' : 'Rejected',
        'disbursement_date'   => $date,
        'disbursed_amount'    => $amount,
        'remarks'             => $remarks,
        'source'              => 'Core2 Finance Approval',
        'updated_at'          => date('Y-m-d H:i:s')
    ]);

    error_log("Sending to Core1: " . CORE1_UPDATE_URL);
    error_log("Core1 Payload: " . $payload);

    $ch = curl_init(CORE1_UPDATE_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . CORE1_API_KEY,
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    error_log("Core1 Response Code: {$httpCode}");
    error_log("Core1 Response: {$response}");

    if ($curlError) {
        return ['success' => false, 'message' => "Core1 Error: {$curlError}"];
    }

    $responseData = json_decode($response, true);
    
    return [
        'success'   => ($httpCode === 200 && isset($responseData['success']) && $responseData['success']),
        'message'   => $responseData['message'] ?? 'Unknown response',
        'http_code' => $httpCode,
        'response'  => $responseData
    ];
}

try {
    // Only POST allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    // ══════════════════════════════════════════════════════════════
    // READ REQUEST FROM FINANCE TEAM
    // ══════════════════════════════════════════════════════════════
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    error_log("Received from Finance: " . $raw);

    if (!is_array($data)) {
        throw new Exception('Invalid JSON payload');
    }

    $action = $data['action'] ?? '';
    $disbursementId = (int)($data['disbursement_id'] ?? 0);
    $decision = $data['decision'] ?? ''; // 'Approved' or 'Rejected'
    $financeRemarks = $data['remarks'] ?? '';
    $financeBy = $data['finance_by'] ?? 'Finance Team';
    $financeByName = $data['finance_by_name'] ?? 'Finance Team';

    if ($action !== 'finance_decision') {
        throw new Exception('Invalid action');
    }

    if ($disbursementId <= 0) {
        throw new Exception('disbursement_id is required');
    }

    if (!in_array($decision, ['Approved', 'Rejected'], true)) {
        throw new Exception('Invalid decision. Must be Approved or Rejected');
    }

    // ══════════════════════════════════════════════════════════════
    // GET DISBURSEMENT INFO
    // ══════════════════════════════════════════════════════════════
    $stmt = $conn->prepare("
        SELECT 
            d.disbursement_id,
            d.loan_id,
            COALESCE(lp.loan_code, d.loan_id) AS loan_code,
            d.amount,
            d.disbursement_date,
            d.status,
            d.remarks
        FROM disbursements d
        LEFT JOIN loan_portfolio lp ON d.loan_id = lp.loan_id
        WHERE d.disbursement_id = ?
        LIMIT 1
    ");

    if (!$stmt) throw new Exception('Database prepare failed: ' . $conn->error);

    $stmt->bind_param('i', $disbursementId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception("Disbursement ID {$disbursementId} not found");
    }

    $disbursement = $result->fetch_assoc();
    $stmt->close();

    $loanCode = $disbursement['loan_code'];
    $amount = $disbursement['amount'];
    $disbDate = $disbursement['disbursement_date'];
    $currentStatus = $disbursement['status'];

    // ══════════════════════════════════════════════════════════════
    // UPDATE CORE2 DISBURSEMENTS TABLE
    // ══════════════════════════════════════════════════════════════
    $conn->begin_transaction();

    try {
        // Determine new status
        if ($decision === 'Approved') {
            $newStatus = 'Released';
        } else {
            $newStatus = 'Cancelled';
        }

        // Update disbursements table
        $updateStmt = $conn->prepare("
            UPDATE disbursements
            SET status = ?,
                remarks = CONCAT(
                    COALESCE(remarks, ''),
                    '\n[', NOW(), '] Finance Team: ', ?, ' by ', ?
                    IF(? != '', CONCAT(' - ', ?), '')
                )
            WHERE disbursement_id = ?
        ");

        if (!$updateStmt) throw new Exception('Update prepare failed: ' . $conn->error);

        $updateStmt->bind_param(
            'sssssi',
            $newStatus,
            $decision,
            $financeByName,
            $financeRemarks,
            $financeRemarks,
            $disbursementId
        );

        $updateStmt->execute();
        $affected = $updateStmt->affected_rows;
        $updateStmt->close();

        if ($affected === 0) {
            throw new Exception('No rows updated');
        }

        // Update tracking table
        $trackStmt = $conn->prepare("
            UPDATE disbursement_finance_requests
            SET finance_status = ?,
                finance_response = ?,
                updated_at = NOW()
            WHERE disbursement_id = ?
        ");

        if ($trackStmt) {
            $trackStmt->bind_param('ssi', $decision, $financeRemarks, $disbursementId);
            $trackStmt->execute();
            $trackStmt->close();
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception('Database update failed: ' . $e->getMessage());
    }

    // ══════════════════════════════════════════════════════════════
    // SEND STATUS TO CORE1 (if Approved)
    // ══════════════════════════════════════════════════════════════
    $core1Result = null;
    $core1Message = '';

    if ($decision === 'Approved' && !empty($loanCode)) {
        $core1Result = sendToCore1(
            $loanCode,
            $decision,
            $amount,
            $disbDate,
            "Finance Approved: {$financeRemarks}"
        );

        if ($core1Result['success']) {
            $core1Message = ' | Core1 updated successfully';
            error_log("Core1 update successful for loan_code: {$loanCode}");
        } else {
            $core1Message = ' | Core1 update failed - will retry';
            error_log("Core1 update failed for loan_code: {$loanCode} - " . $core1Result['message']);
        }
    } elseif ($decision === 'Rejected') {
        $core1Message = ' | Disbursement rejected, Core1 not notified';
    }

    // ══════════════════════════════════════════════════════════════
    // SUCCESS RESPONSE
    // ══════════════════════════════════════════════════════════════
    while (@ob_end_clean());
    echo json_encode([
        'success' => true,
        'message' => "Disbursement {$decision} successfully" . $core1Message,
        'data' => [
            'disbursement_id' => $disbursementId,
            'loan_code'       => $loanCode,
            'decision'        => $decision,
            'new_status'      => $newStatus,
            'core1_updated'   => $core1Result['success'] ?? false,
            'core1_response'  => $core1Result
        ]
    ]);
    exit;

} catch (Exception $e) {
    error_log("receive_finance_decision.php Error: " . $e->getMessage());
    
    if (isset($conn) && $conn->ping()) {
        @$conn->rollback();
    }

    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}