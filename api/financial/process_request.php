<?php
/**
 * Finance API - Approve/Reject Disbursement
 * Path: /api/finance/process_request.php
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

define('FINANCE_API_KEY', 'core2_finance_2026_9f3c2b7a1d4e7c8f1a2b3c4d5e6f');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
    exit;
}

// Read API key header safely
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
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = [];

    $requestId = (int)($body['request_id'] ?? 0);
    $action    = strtolower(trim($body['action'] ?? ''));
    $financeBy = (int)($body['finance_user_id'] ?? 0);
    $remarks   = trim($body['remarks'] ?? '');

    if ($requestId <= 0) throw new Exception('request_id required');
    if (!in_array($action, ['approve', 'reject'], true)) throw new Exception('Invalid action');

    $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

    // Transaction para safe
    $conn->begin_transaction();

    // Get disbursement_id (only Pending requests)
    $stmt = $conn->prepare("
        SELECT disbursement_id
        FROM disbursement_approval_requests
        WHERE request_id = ?
          AND status = 'Pending'
        LIMIT 1
    ");
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $stmt->close();
        throw new Exception('Request not found or already processed');
    }

    $row = $res->fetch_assoc();
    $disbursementId = (int)$row['disbursement_id'];
    $stmt->close();

    // Update approval request table
    $stmt = $conn->prepare("
        UPDATE disbursement_approval_requests
        SET status = ?,
            finance_by = ?,
            finance_at = NOW(),
            finance_remarks = ?
        WHERE request_id = ?
    ");
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    $stmt->bind_param('sisi', $newStatus, $financeBy, $remarks, $requestId);
    $stmt->execute();
    $stmt->close();

    // Update disbursements safely
    // NOTE: disbursements.status is ENUM('Pending','Released','Cancelled')
    if ($action === 'reject') {
        // Allowed value
        $disbStatus = 'Cancelled';

        $stmt = $conn->prepare("
            UPDATE disbursements
            SET status = ?,
                remarks = CONCAT(COALESCE(remarks,''), ' | Finance: Rejected', IF(?='', '', CONCAT(' - ', ?)), ' @ ', NOW())
            WHERE disbursement_id = ?
        ");
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

        $stmt->bind_param('sssi', $disbStatus, $remarks, $remarks, $disbursementId);
        $stmt->execute();
        $stmt->close();

    } else {
        // APPROVE: do NOT change disbursements.status to invalid enum
        // Just add note to remarks so Core2 can see it and proceed with final release later.
        $disbStatus = 'Pending';

        $stmt = $conn->prepare("
            UPDATE disbursements
            SET remarks = CONCAT(COALESCE(remarks,''), ' | Finance: Approved', IF(?='', '', CONCAT(' - ', ?)), ' @ ', NOW())
            WHERE disbursement_id = ?
        ");
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

        $stmt->bind_param('ssi', $remarks, $remarks, $disbursementId);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();

    while (@ob_end_clean());
    echo json_encode([
        'success' => true,
        'message' => "Request processed successfully",
        'approval_status' => $newStatus,
        'disbursement_status' => $disbStatus,
        'disbursement_id' => $disbursementId
    ]);
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn->errno === 0) {
        // ignore
    } else {
        // ignore
    }

    if (isset($conn) && $conn->ping()) {
        @$conn->rollback();
    }

    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
