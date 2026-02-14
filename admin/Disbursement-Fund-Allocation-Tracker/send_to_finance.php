<?php
/**
 * ============================================================
 * Send Disbursement Approval Request to Finance Team
 * Location: CORE 2 - /admin/Disbursement-Fund-Allocation-Tracker/send_to_finance.php
 * 
 * Flow: Core2 Disbursement Tracker → Finance Team (finance.microfinancial-1.com)
 * ============================================================
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

// ══════════════════════════════════════════════════════════════
// FINANCE TEAM API CONFIGURATION
// ══════════════════════════════════════════════════════════════
define('FINANCE_API_URL', 'https://finance.microfinancial-1.com/api/financial/receive_disbursement_requests.php');
define('FINANCE_API_KEY', 'finance_core2_secure_key_2026_v1'); // IMPORTANT: Same key sa Finance side

// ══════════════════════════════════════════════════════════════
// AUTHENTICATION CHECK
// ══════════════════════════════════════════════════════════════
if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login']);
  exit;
}

$userId = (int)($_SESSION['userdata']['user_id'] ?? 0);
$userName = $_SESSION['userdata']['full_name'] ?? 'Unknown User';

try {
  // ══════════════════════════════════════════════════════════════
  // VALIDATE REQUEST
  // ══════════════════════════════════════════════════════════════
  $raw  = file_get_contents('php://input');
  $body = json_decode($raw, true);
  if (!is_array($body)) $body = [];

  $action = trim($body['action'] ?? '');
  $ids = $body['disbursement_ids'] ?? [];
  
  if ($action !== 'send') {
    throw new Exception("Invalid action");
  }

  if (!is_array($ids) || empty($ids)) {
    throw new Exception('No disbursements selected');
  }

  $ids = array_values(array_filter(array_map('intval', $ids)));
  if (empty($ids)) {
    throw new Exception('No valid disbursement IDs');
  }

  // ══════════════════════════════════════════════════════════════
  // GET DISBURSEMENT DATA FROM CORE2 DATABASE
  // ══════════════════════════════════════════════════════════════
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));

  $stmt = $conn->prepare("
    SELECT 
      d.disbursement_id,
      d.loan_id,
      COALESCE(lp.loan_code, d.loan_id) AS loan_code,
      d.member_id,
      COALESCE(m.full_name, 'N/A') AS member_name,
      d.amount,
      d.fund_source,
      d.disbursement_date,
      d.status,
      d.remarks,
      d.approved_by
    FROM disbursements d
    LEFT JOIN loan_portfolio lp ON d.loan_id = lp.loan_id
    LEFT JOIN members m ON d.member_id = m.member_id
    WHERE d.disbursement_id IN ($placeholders)
      AND d.status IN ('Pending', 'For Approval')
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
    throw new Exception('No valid disbursements found. Only Pending or For Approval disbursements can be sent.');
  }

  // ══════════════════════════════════════════════════════════════
  // ENSURE LOCAL TRACKING TABLE EXISTS
  // ══════════════════════════════════════════════════════════════
  $conn->query("
    CREATE TABLE IF NOT EXISTS disbursement_finance_requests (
      request_id       INT AUTO_INCREMENT PRIMARY KEY,
      disbursement_id  INT NOT NULL,
      requested_by     INT NOT NULL,
      requested_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      finance_status   VARCHAR(50) DEFAULT 'Sent to Finance',
      finance_response TEXT NULL,
      updated_at       DATETIME NULL,
      UNIQUE KEY unique_disbursement (disbursement_id),
      INDEX idx_finance_status (finance_status)
    )
  ");

  // ══════════════════════════════════════════════════════════════
  // SEND TO FINANCE TEAM API
  // ══════════════════════════════════════════════════════════════
  $payload = json_encode([
    'action'        => 'receive_requests',
    'source'        => 'Core2 Disbursement Tracker',
    'disbursements' => $disbursements,
    'sent_at'       => date('Y-m-d H:i:s'),
    'callback_url'  => 'https://core2.microfinancial-1.com/api/receive_finance_decision.php'
  ], JSON_UNESCAPED_UNICODE);

  error_log("Sending to Finance API: " . FINANCE_API_URL);
  error_log("Payload: " . $payload);

  $ch = curl_init(FINANCE_API_URL);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false, // Set to true in production with proper SSL
    CURLOPT_SSL_VERIFYHOST => false, // Set to 2 in production
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/json',
      'X-API-Key: ' . FINANCE_API_KEY,
      'Accept: application/json',
      'User-Agent: Core2-Disbursement-System/1.0'
    ]
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  $curlInfo = curl_getinfo($ch);
  curl_close($ch);

  error_log("Finance API Response Code: {$httpCode}");
  error_log("Finance API Response: {$response}");

  if ($curlError) {
    throw new Exception("Cannot connect to Finance Team: {$curlError}");
  }

  if ($httpCode !== 200) {
    $errorMsg = "Finance API returned HTTP {$httpCode}";
    if (!empty($response)) {
      $errorData = json_decode($response, true);
      if (isset($errorData['message'])) {
        $errorMsg .= " - " . $errorData['message'];
      }
    }
    throw new Exception($errorMsg);
  }

  $responseData = json_decode($response, true);
  
  if (!$responseData || !isset($responseData['success'])) {
    throw new Exception("Invalid response from Finance Team");
  }

  if (!$responseData['success']) {
    throw new Exception($responseData['message'] ?? 'Finance Team rejected the request');
  }

  // ══════════════════════════════════════════════════════════════
  // UPDATE LOCAL DATABASE - MARK AS SENT TO FINANCE
  // ══════════════════════════════════════════════════════════════
  $conn->begin_transaction();

  try {
    // Update disbursements status
    $updateStmt = $conn->prepare("
      UPDATE disbursements 
      SET status = 'For Approval',
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

    // Track in finance_requests table
    $trackStmt = $conn->prepare("
      INSERT INTO disbursement_finance_requests 
        (disbursement_id, requested_by, finance_status, finance_response)
      VALUES (?, ?, 'Sent to Finance', ?)
      ON DUPLICATE KEY UPDATE 
        requested_at = CURRENT_TIMESTAMP,
        finance_status = 'Sent to Finance',
        finance_response = VALUES(finance_response)
    ");

    if ($trackStmt) {
      $financeMsg = json_encode($responseData);
      foreach ($ids as $disbId) {
        $trackStmt->bind_param('iis', $disbId, $userId, $financeMsg);
        $trackStmt->execute();
      }
      $trackStmt->close();
    }

    $conn->commit();

  } catch (Exception $e) {
    $conn->rollback();
    throw new Exception("Failed to update local database: " . $e->getMessage());
  }

  // ══════════════════════════════════════════════════════════════
  // SUCCESS RESPONSE
  // ══════════════════════════════════════════════════════════════
  while (@ob_end_clean());
  echo json_encode([
    'success'       => true,
    'message'       => "Successfully sent " . count($disbursements) . " disbursement(s) to Finance Team",
    'records_sent'  => count($disbursements),
    'disbursement_ids' => $ids,
    'finance_response' => $responseData,
    'next_step'     => 'Finance Team will review and approve/reject the requests'
  ]);
  exit;

} catch (Exception $e) {
  error_log("send_to_finance.php Error: " . $e->getMessage());
  
  while (@ob_end_clean());
  http_response_code(500);
  echo json_encode([
    'success' => false, 
    'message' => $e->getMessage(),
    'debug_info' => [
      'file' => 'send_to_finance.php',
      'line' => __LINE__
    ]
  ]);
  exit;
}