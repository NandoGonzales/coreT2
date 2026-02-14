<?php
/**
 * Send Disbursement Approval Request to Finance (Core2)
 * Path: /admin/Disbursement-Fund-Allocation-Tracker/send_to_finance.php
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$userId = $_SESSION['userdata']['user_id'] ?? 0;

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
$conn->query("CREATE INDEX IF NOT EXISTS idx_status ON disbursement_approval_requests(status)");

try {
  $raw = file_get_contents('php://input');
  $body = json_decode($raw, true);
  if (!is_array($body)) $body = [];

  $action = trim($body['action'] ?? ($_POST['action'] ?? ''));
  if ($action !== 'send') throw new Exception("Invalid action: {$action}");

  $ids = $body['disbursement_ids'] ?? [];
  if (!is_array($ids) || empty($ids)) throw new Exception('No disbursements selected');

  $ids = array_values(array_filter(array_map('intval', $ids)));
  if (empty($ids)) throw new Exception('No valid disbursement IDs');

  // Insert requests (ignore duplicates)
  $ins = $conn->prepare("
    INSERT INTO disbursement_approval_requests (disbursement_id, requested_by, status)
    VALUES (?, ?, 'Pending')
    ON DUPLICATE KEY UPDATE
      status = status
  ");
  if (!$ins) throw new Exception("Prepare failed: " . $conn->error);

  $created = 0;
  $skipped = 0;

  foreach ($ids as $disbId) {
    $ins->bind_param('ii', $disbId, $userId);
    if ($ins->execute()) {
      // affected_rows 1 = inserted, 2 = updated (but ours does nothing), 0 = no change
      if ($ins->affected_rows === 1) $created++;
      else $skipped++;
    } else {
      $skipped++;
    }
  }
  $ins->close();

  // Optional: update disbursement status to "For Approval"
  // Only do this if your disbursements.status accepts this value
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  $upd = $conn->prepare("UPDATE disbursements SET status = 'For Approval' WHERE disbursement_id IN ($placeholders)");
  if ($upd) {
    $upd->bind_param($types, ...$ids);
    $upd->execute();
    $upd->close();
  }

  while (@ob_end_clean());
  echo json_encode([
    'success' => true,
    'message' => "Sent {$created} request(s) to Finance for approval",
    'created' => $created,
    'skipped' => $skipped
  ]);
  exit;

} catch (Exception $e) {
  while (@ob_end_clean());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
  exit;
}
