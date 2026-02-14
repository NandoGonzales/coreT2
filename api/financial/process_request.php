<?php
/**
 * ============================================================
 * Finance Team - Process Approval/Rejection
 * Location: FINANCE - /api/process_request.php
 * 
 * Flow: Finance team UI → This file → Update DB → Send decision to Core2
 * ============================================================
 */

while (@ob_end_clean());
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../initialize.php');
header('Content-Type: application/json; charset=utf-8');

// ══════════════════════════════════════════════════════════════
// AUTHENTICATION
// ══════════════════════════════════════════════════════════════
if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($apiKey) && function_exists('getallheaders')) {
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';
    }
    
    if ($apiKey !== 'finance_internal_key_2026') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    // Create dummy session for API calls
    $financeUserId = 1; // Default finance user
    $financeUserName = 'Finance Team';
} else {
    $financeUserId = (int)($_SESSION['userdata']['user_id'] ?? 0);
    $financeUserName = $_SESSION['userdata']['full_name'] ?? 'Finance User';
}

/**
 * Send decision back to Core2
 */
function sendDecisionToCore2($disbursementId, $decision, $remarks, $financeByName, $callbackUrl) {
    if (empty($callbackUrl)) {
        error_log("No callback URL for disbursement {$disbursementId}");
        return ['success' => false, 'message' => 'No callback URL'];
    }

    $payload = json_encode([
        'action'         => 'finance_decision',
        'disbursement_id'=> $disbursementId,
        'decision'       => $decision, // 'Approved' or 'Rejected'
        'remarks'        => $remarks,
        'finance_by'     => $financeByName,
        'finance_by_name'=> $financeByName,
        'decided_at'     => date('Y-m-d H:i:s')
    ]);

    error_log("Sending decision to Core2: {$callbackUrl}");
    error_log("Payload: {$payload}");

    $ch = curl_init($callbackUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: finance_core2_secure_key_2026_v1',
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    error_log("Core2 Response Code: {$httpCode}");
    error_log("Core2 Response: {$response}");

    if ($curlError) {
        return ['success' => false, 'message' => "Core2 Error: {$curlError}"];
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
    // READ REQUEST
    // ══════════════════════════════════════════════════════════════
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    error_log("Finance process_request received: " . $raw);

    if (!is_array($data)) {
        throw new Exception('Invalid JSON payload');
    }

    $action = strtolower(trim($data['action'] ?? ''));
    $requestId = (int)($data['request_id'] ?? 0);
    $financeRemarks = trim($data['remarks'] ?? '');

    if (!in_array($action, ['approve', 'reject'], true)) {
        throw new Exception('Invalid action. Must be approve or reject');
    }

    if ($requestId <= 0) {
        throw new Exception('request_id is required');
    }

    $decision = ($action === 'approve') ? 'Approved' : 'Rejected';

    // ══════════════════════════════════════════════════════════════
    // GET REQUEST INFO
    // ══════════════════════════════════════════════════════════════
    $stmt = $conn->prepare("
        SELECT 
            request_id,
            disbursement_id,
            loan_code,
            member_name,
            amount,
            finance_status,
            core2_callback
        FROM finance_disbursement_requests
        WHERE request_id = ?
        LIMIT 1
    ");

    if (!$stmt) throw new Exception('Query prepare failed: ' . $conn->error);

    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception("Request ID {$requestId} not found");
    }

    $request = $result->fetch_assoc();
    $stmt->close();

    // Check if already processed
    if ($request['finance_status'] !== 'Pending') {
        throw new Exception("Request already processed with status: " . $request['finance_status']);
    }

    $disbursementId = (int)$request['disbursement_id'];
    $loanCode = $request['loan_code'];
    $callbackUrl = $request['core2_callback'];

    // ══════════════════════════════════════════════════════════════
    // UPDATE DATABASE
    // ══════════════════════════════════════════════════════════════
    $conn->begin_transaction();

    try {
        $updateStmt = $conn->prepare("
            UPDATE finance_disbursement_requests
            SET finance_status = ?,
                finance_decision = ?,
                finance_remarks = ?,
                decided_by = ?,
                decided_by_id = ?,
                decided_at = NOW()
            WHERE request_id = ?
              AND finance_status = 'Pending'
        ");

        if (!$updateStmt) throw new Exception('Update prepare failed: ' . $conn->error);

        $updateStmt->bind_param(
            'ssssii',
            $decision,
            $decision,
            $financeRemarks,
            $financeUserName,
            $financeUserId,
            $requestId
        );

        $updateStmt->execute();
        $affected = $updateStmt->affected_rows;
        $updateStmt->close();

        if ($affected === 0) {
            throw new Exception('No rows updated. Request may have been processed already.');
        }

        // Log activity
        $logStmt = $conn->prepare("
            INSERT INTO finance_activity_log 
                (request_id, action, details, user_id, user_name, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if ($logStmt) {
            $logAction = "{$decision} Disbursement";
            $logDetails = "Loan: {$loanCode}, Amount: " . $request['amount'] . ", Remarks: {$financeRemarks}";
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            $logStmt->bind_param(
                'ississ',
                $requestId,
                $logAction,
                $logDetails,
                $financeUserId,
                $financeUserName,
                $ipAddress
            );
            $logStmt->execute();
            $logStmt->close();
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception('Database update failed: ' . $e->getMessage());
    }

    // ══════════════════════════════════════════════════════════════
    // SEND DECISION TO CORE2
    // ══════════════════════════════════════════════════════════════
    $core2Result = sendDecisionToCore2(
        $disbursementId,
        $decision,
        $financeRemarks,
        $financeUserName,
        $callbackUrl
    );

    $core2Message = '';
    if ($core2Result['success']) {
        $core2Message = ' Decision sent to Core2 successfully.';
        error_log("Core2 notification successful for disbursement {$disbursementId}");
    } else {
        $core2Message = ' WARNING: Decision saved but Core2 notification failed. Core2 needs manual sync.';
        error_log("Core2 notification failed for disbursement {$disbursementId}: " . $core2Result['message']);
    }

    // ══════════════════════════════════════════════════════════════
    // SUCCESS RESPONSE
    // ══════════════════════════════════════════════════════════════
    while (@ob_end_clean());
    echo json_encode([
        'success' => true,
        'message' => "Disbursement request {$decision}." . $core2Message,
        'data' => [
            'request_id'      => $requestId,
            'disbursement_id' => $disbursementId,
            'loan_code'       => $loanCode,
            'decision'        => $decision,
            'decided_by'      => $financeUserName,
            'core2_notified'  => $core2Result['success'],
            'core2_response'  => $core2Result
        ]
    ]);
    exit;

} catch (Exception $e) {
    error_log("Finance process_request.php Error: " . $e->getMessage());
    
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