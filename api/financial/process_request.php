<?php
/**
 * Finance API - Approve/Reject a Disbursement Approval Request
 * Path: /api/finance/process_request.php
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

// OPTIONAL SIMPLE API KEY (recommended)
define('FINANCE_API_KEY', 'CHANGE_THIS_TO_A_SECRET_KEY');

// Check API Key header
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

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = [];

    $requestId = (int)($body['request_id'] ?? 0);
    $action    = strtolower(trim($body['action'] ?? ''));
    $financeBy = (int)($body['finance_user_id'] ?? 0);
    $remarks   = trim($body['remarks'] ?? '');

    if ($requestId <= 0) throw new Exception('request_id is required');
    if (!in_array($action, ['approve', 'reject'], true)) throw new Exception('action must be approve or reject');

    $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

    // Only allow processing when Pending
    $stmt = $conn->prepare("
        UPDATE disbursement_approval_requests
        SET status = ?,
            finance_by = ?,
            finance_at = NOW(),
            finance_remarks = ?
        WHERE request_id = ?
          AND status = 'Pending'
    ");
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    $stmt->bind_param('sisi', $newStatus, $financeBy, $remarks, $requestId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        throw new Exception('Request not found or already processed');
    }
    $stmt->close();

    while (@ob_end_clean());
    echo json_encode([
        'success' => true,
        'message' => "Request {$requestId} marked as {$newStatus}",
        'status'  => $newStatus
    ]);
    exit;

} catch (Exception $e) {
    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
