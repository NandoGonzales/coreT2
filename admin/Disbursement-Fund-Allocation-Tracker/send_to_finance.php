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

$userId = (int)($_SESSION['userdata']['user_id'] ?? 0);

/* ---------- Helper Functions ---------- */

function tableExists(mysqli $conn, string $table): bool {
  $tbl = $conn->real_escape_string($table);
  $res = $conn->query("SHOW TABLES LIKE '{$tbl}'");
  return ($res && $res->num_rows > 0);
}

function indexExists(mysqli $conn, string $table, string $indexName): bool {
  $tbl = $conn->real_escape_string($table);
  $idx = $conn->real_escape_string($indexName);
  $res = $conn->query("SHOW INDEX FROM `{$tbl}` WHERE Key_name = '{$idx}'");
  return ($res && $res->num_rows > 0);
}

function statusAllowsValue(mysqli $conn, string $value): bool {
  $res = $conn->query("SHOW COLUMNS FROM `disbursements` LIKE 'status'");
  if (!$res || $res->num_rows === 0) return false;

  $row = $res->fetch_assoc();
  $type = strtolower($row['Type'] ?? '');

  if (strpos($type, 'enum(') === 0 || strpos($type, 'set(') === 0) {
    return (stripos($type, "'" . $value . "'") !== false);
  }

  if (
    strpos($type, 'varchar') === 0 ||
    strpos($type, 'text') !== false ||
    strpos($type, 'char') === 0
  ) {
    return true;
  }

  return false;
}

try {

  /* ---------- Ensure table ---------- */

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

  if (tableExists($conn, 'disbursement_approval_requests') &&
      !indexExists($conn, 'disbursement_approval_requests', 'idx_status')) {
    @$conn->query("CREATE INDEX idx_status ON disbursement_approval_requests(status)");
  }

  /* ---------- Read body ---------- */

  $raw  = file_get_contents('php://input');
  $body = json_decode($raw, true);
  if (!is_array($body)) $body = [];

  $action = trim($body['action'] ?? ($_POST['action'] ?? ''));
  if ($action !== 'send') {
    throw new Exception("Invalid action");
  }

  $ids = $body['disbursement_ids'] ?? [];
  if (!is_array($ids) || empty($ids)) {
    throw new Exception('No disbursements selected');
  }

  $ids = array_values(array_filter(array_map('intval', $ids)));
  if (empty($ids)) {
    throw new Exception('No valid disbursement IDs');
  }

  /* ---------- Insert Requests ---------- */

  $ins = $conn->prepare("
    INSERT INTO disbursement_approval_requests (disbursement_id, requested_by, status)
    VALUES (?, ?, 'Pending')
    ON DUPLICATE KEY UPDATE status = status
  ");

  if (!$ins) throw new Exception("Prepare failed: " . $conn->error);

  $created = 0;
  $skipped = 0;

  foreach ($ids as $disbId) {
    $ins->bind_param('ii', $disbId, $userId);
    if ($ins->execute()) {
      if ($ins->affected_rows === 1) $created++;
      else $skipped++;
    } else {
      $skipped++;
    }
  }

  $ins->close();

  /* ---------- Optional Status Update ---------- */

  $targetStatus = 'For Approval';

  if (statusAllowsValue($conn, $targetStatus)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $upd = $conn->prepare("UPDATE disbursements SET status = '{$targetStatus}' WHERE disbursement_id IN ($placeholders)");
    if ($upd) {
      $upd->bind_param($types, ...$ids);
      @$upd->execute();
      $upd->close();
    }
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
