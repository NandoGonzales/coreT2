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

define('FINANCE_API_KEY', 'CHANGE_THIS_TO_SECRET_KEY');

$headers = function_exists('getallheaders') ? getallheaders() : [];
$apiKey  = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';

if ($apiKey !== FINANCE_API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
    exit;
}

try {

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = [];

    $requestId = (int)($body['request_id'] ?? 0);
    $action    = strtolower(trim($body['action'] ?? ''));
    $financeBy = (int)($body['finance_user_id'] ?? 0);
    $remarks   = trim($body['remarks'] ?? '');

    if ($requestId <= 0) throw new Exception('request_id required');
    if (!in_array($action, ['approve','reject'], true)) throw new Exception('Invalid action');

    $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

    // Get disbursement_id first
    $stmt = $conn->prepare("
        SELECT disbursement_id 
        FROM disbursement_approval_requests
        WHERE request_id = ?
          AND status = 'Pending'
    ");
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        throw new Exception('Request not found or already processed');
    }

    $row = $res->fetch_assoc();
    $disbursementId = (int)$row['disbursement_id'];
    $stmt->close();

    // Update approval table
    $stmt = $conn->prepare("
        UPDATE disbursement_approval_requests
        SET status = ?,
            finance_by = ?,
            finance_at = NOW(),
            finance_remarks = ?
        WHERE request_id = ?
    ");
    $stmt->bind_param('sisi', $newStatus, $financeBy, $remarks, $requestId);
    $stmt->execute();
    $stmt->close();

    // Update disbursement status
    if ($action === 'approve') {
        $disbStatus = 'Finance Approved';
    } else {
        $disbStatus = 'Cancelled';
    }

    $stmt = $conn->prepare("
        UPDATE disbursements
        SET status = ?
        WHERE disbursement_id = ?
    ");
    $stmt->bind_param('si', $disbStatus, $disbursementId);
    $stmt->execute();
    $stmt->close();

    while (@ob_end_clean());
    echo json_encode([
        'success' => true,
        'message' => "Request processed successfully",
        'approval_status' => $newStatus,
        'disbursement_status' => $disbStatus
    ]);
    exit;

} catch (Exception $e) {
    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
