<?php
/**
 * Send Disbursement to Finance Team (CORRECTED - Status: For Finance Approval)
 * Location: CORE2 - /admin/Disbursement-Fund-Allocation-Tracker/send_to_finance.php
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

// Authentication
if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$userId = (int)($_SESSION['userdata']['user_id'] ?? 0);
$userName = $_SESSION['userdata']['full_name'] ?? 'Unknown User';

try {
  $raw  = file_get_contents('php://input');
  $body = json_decode($raw, true);
  if (!is_array($body)) $body = [];

  $action = trim($body['action'] ?? '');
  $ids = $body['disbursement_ids'] ?? [];
  
  if ($action !== 'send') throw new Exception("Invalid action");
  if (!is_array($ids) || empty($ids)) throw new Exception('No disbursements selected');

  $ids = array_values(array_filter(array_map('intval', $ids)));
  if (empty($ids)) throw new Exception('No valid disbursement IDs');

  // Get disbursement data WITH loan_portfolio JOIN
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));

  $stmt = $conn->prepare("
    SELECT 
      d.disbursement_id,
      d.loan_id,
      d.member_id,
      d.amount,
      d.fund_source,
      d.disbursement_date,
      d.status,
      d.remarks,
      d.approved_by,
      lp.loan_code,
      lp.principal_amount as loan_amount,
      COALESCE(m.full_name, 'N/A') AS member_name
    FROM disbursements d
    LEFT JOIN loan_portfolio lp ON d.loan_id = lp.loan_code
    LEFT JOIN members m ON d.member_id = m.member_id
    WHERE d.disbursement_id IN ($placeholders)
      AND d.status = 'Pending'
  ");

  if (!$stmt) throw new Exception("Database prepare failed: " . $conn->error);

  $stmt->bind_param($types, ...$ids);
  $stmt->execute();
  $result = $stmt->get_result();

  $disbursements = [];
  while ($row = $result->fetch_assoc()) {
    $disbursements[] = [
      'disbursement_id'   => (int)$row['disbursement_id'],
      'loan_id'           => $row['loan_id'],
      'loan_code'         => $row['loan_code'],
      'member_id'         => $row['member_id'],
      'member_name'       => $row['member_name'],
      'amount'            => (float)$row['amount'],
      'fund_source'       => $row['fund_source'] ?? 'General Fund',
      'disbursement_date' => $row['disbursement_date'],
      'status'            => $row['status'],
      'remarks'           => $row['remarks'] ?? '',
      'requested_by'      => $userName,
      'requested_by_id'   => $userId
    ];
  }
  $stmt->close();

  if (empty($disbursements)) {
    throw new Exception('No valid disbursements found. Only Pending disbursements can be sent.');
  }

  // Update status to "For Finance Approval"
  $conn->begin_transaction();
  
  try {
    $updateStmt = $conn->prepare("
      UPDATE disbursements 
      SET status = 'For Finance Approval',
          sent_to_finance_at = NOW(),
          remarks = CONCAT(
            COALESCE(remarks, ''), 
            '\n[', NOW(), '] Sent to Finance Team by ', ?
          )
      WHERE disbursement_id IN ($placeholders)
    ");
    
    if ($updateStmt) {
      $updateParams = array_merge([$userName], $ids);
      $updateTypes = 's' . $types;
      $updateStmt->bind_param($updateTypes, ...$updateParams);
      $updateStmt->execute();
      $updateStmt->close();
    }

    $conn->commit();

  } catch (Exception $e) {
    $conn->rollback();
    throw new Exception("Failed to update database: " . $e->getMessage());
  }

  // Success response
  while (@ob_end_clean());
  echo json_encode([
    'success'       => true,
    'message'       => "Successfully sent " . count($disbursements) . " disbursement(s) to Finance Team. Status: For Finance Approval",
    'records_sent'  => count($disbursements),
    'disbursement_ids' => $ids,
    'new_status'    => 'For Finance Approval',
    'next_step'     => 'Waiting for Finance Team decision. Approve button will be enabled after Finance approval.'
  ]);
  exit;

} catch (Exception $e) {
  error_log("send_to_finance.php Error: " . $e->getMessage());
  
  while (@ob_end_clean());
  http_response_code(500);
  echo json_encode([
    'success' => false, 
    'message' => $e->getMessage()
  ]);
  exit;
}