<?php
/**
 * Finance API - Disbursement Approval Requests (Option B)
 * File: /api/financial/disbursement_requests.php
 *
 * GET  ?status=Pending|Approved|Rejected|Completed
 * POST { action: approve|reject, disbursement_id: int, remarks: string }
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

function respond($code, $data) {
  while (ob_get_level() > 0) ob_end_clean();
  http_response_code($code);
  echo json_encode($data, JSON_PRETTY_PRINT);
  exit;
}

if (!isset($conn) || $conn->connect_error) {
  respond(500, ['success' => false, 'message' => 'DB connection failed']);
}

/**
 * Ensure table exists
 */
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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $status = trim($_GET['status'] ?? 'Pending');

  $allowed = ['Pending','Approved','Rejected','Completed'];
  if (!in_array($status, $allowed, true)) $status = 'Pending';

  $sql = "
    SELECT
      r.request_id,
      r.disbursement_id,
      r.status,
      r.requested_at,
      r.requested_by,
      r.finance_by,
      r.finance_at,
      r.finance_remarks,
      d.loan_id,
      d.member_id,
      d.amount,
      d.disbursement_date,
      d.fund_source,
      d.status AS disbursement_status,
      COALESCE(m.full_name,'N/A') AS member_name
    FROM disbursement_approval_requests r
    LEFT JOIN disbursements d ON d.disbursement_id = r.disbursement_id
    LEFT JOIN members m ON m.member_id = d.member_id
    WHERE r.status = ?
    ORDER BY r.requested_at DESC
  ";

  $stmt = $conn->prepare($sql);
  if (!$stmt) respond(500, ['success' => false, 'message' => 'Prepare failed']);

  $stmt->bind_param('s', $status);
  $stmt->execute();
  $res = $stmt->get_result();

  $rows = [];
  while ($row = $res->fetch_assoc()) $rows[] = $row;
  $stmt->close();

  respond(200, [
    'success' => true,
    'status' => $status,
    'count' => count($rows),
    'data' => $rows
  ]);
}

/**
 * POST approve/reject
 */
if ($method === 'POST') {
  $raw = file_get_contents('php://input');
  $body = json_decode($raw, true);
  if (!is_array($body)) $body = [];

  $action = strtolower(trim($body['action'] ?? ''));
  $disbId = (int)($body['disbursement_id'] ?? 0);
  $remarks = trim($body['remarks'] ?? '');

  if (!in_array($action, ['approve','reject'], true)) {
    respond(400, ['success' => false, 'message' => 'Invalid action']);
  }
  if ($disbId <= 0) {
    respond(400, ['success' => false, 'message' => 'disbursement_id required']);
  }

  // OPTIONAL: if Finance has its own auth, you can pass finance_by in body
  $financeBy = (int)($body['finance_by'] ?? 0);

  // Only allow update if currently Pending
  $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

  $stmt = $conn->prepare("
    UPDATE disbursement_approval_requests
    SET status = ?,
        finance_by = NULLIF(?,0),
        finance_at = NOW(),
        finance_remarks = ?
    WHERE disbursement_id = ?
      AND status = 'Pending'
  ");

  if (!$stmt) respond(500, ['success' => false, 'message' => 'Prepare failed']);

  $stmt->bind_param('sisi', $newStatus, $financeBy, $remarks, $disbId);
  $stmt->execute();
  $affected = $stmt->affected_rows;
  $stmt->close();

  if ($affected <= 0) {
    respond(409, [
      'success' => false,
      'message' => 'No update. Either not found, or not Pending anymore.'
    ]);
  }

  respond(200, [
    'success' => true,
    'message' => "Request {$newStatus}",
    'disbursement_id' => $disbId,
    'status' => $newStatus
  ]);
}

respond(405, ['success' => false, 'message' => 'Method not allowed']);
