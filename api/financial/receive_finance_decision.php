<?php
/**
 * Receive Finance Team Decision (CORRECTED)
 * Location: CORE 2 - /api/receive_finance_decision.php
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
    error_log("Core2: Invalid API key received");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    error_log("Core2 received from Finance: " . $raw);

    if (!is_array($data)) {
        throw new Exception('Invalid JSON payload');
    }

    $action = $data['action'] ?? '';
    $disbursementId = (int)($data['disbursement_id'] ?? 0);
    $decision = $data['decision'] ?? '';
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

    // Get disbursement WITH loan_portfolio info
    $stmt = $conn->prepare("
        SELECT 
            d.disbursement_id,
            d.loan_id,
            d.amount,
            d.disbursement_date,
            d.status,
            d.remarks,
            lp.loan_code
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
    $currentStatus = $disbursement['status'];

    // Check if already approved/rejected
    if (!in_array($currentStatus, ['For Finance Approval', 'Pending'], true)) {
        throw new Exception("Disbursement already processed. Current status: {$currentStatus}");
    }

    // Update Core2 disbursements
    $conn->begin_transaction();

    try {
        // Set new status based on Finance decision
        $newStatus = ($decision === 'Approved') ? 'Finance Approved' : 'Cancelled';

        $updateStmt = $conn->prepare("
            UPDATE disbursements
            SET status = ?,
                finance_decision = ?,
                finance_decided_at = NOW(),
                finance_decided_by = ?,
                remarks = CONCAT(
                    COALESCE(remarks, ''),
                    '\n[', NOW(), '] Finance Team: ', ?, ' by ', ?,
                    IF(? != '', CONCAT(' - ', ?), '')
                )
            WHERE disbursement_id = ?
        ");

        if (!$updateStmt) throw new Exception('Update prepare failed: ' . $conn->error);

        $updateStmt->bind_param(
            'sssssssi',
            $newStatus,
            $decision,
            $financeByName,
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

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception('Database update failed: ' . $e->getMessage());
    }

    $message = "Disbursement {$decision} successfully.";
    
    if ($decision === 'Approved') {
        $message .= " Status changed to 'Finance Approved'. User can now click approve button to release funds.";
    } else {
        $message .= " Status changed to 'Cancelled'. Disbursement cannot be processed.";
    }

    // Success response
    while (@ob_end_clean());
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'disbursement_id' => $disbursementId,
            'loan_code'       => $loanCode,
            'decision'        => $decision,
            'old_status'      => $currentStatus,
            'new_status'      => $newStatus
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