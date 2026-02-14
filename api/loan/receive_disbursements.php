<?php
/**
 * Core1 Disbursement Receiver
 * Path: /api/loan/receive_disbursements.php
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

require_once(__DIR__ . '/../../initialize_core1.php'); // make sure this exists on CORE1

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST allowed']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || !is_array($data)) {
        throw new Exception('Invalid JSON payload');
    }

    // Create log table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS disbursement_imports (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            disbursement_id INT,
            loan_code       VARCHAR(50),
            member_name     VARCHAR(100),
            amount          DECIMAL(12,2),
            fund_source     VARCHAR(100),
            status          VARCHAR(50),
            imported_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            source          VARCHAR(50) DEFAULT 'Core2',
            INDEX idx_disbursement_id (disbursement_id),
            INDEX idx_loan_code (loan_code)
        )
    ");

    $stmt = $conn->prepare("
        INSERT INTO disbursement_imports
            (disbursement_id, loan_code, member_name, amount, fund_source, status)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception('DB prepare failed: ' . $conn->error);
    }

    $imported = 0;
    $errors = [];

    foreach ($data as $record) {
        $disbId   = intval($record['disbursement_id'] ?? 0);
        $loanCode = (string)($record['loan_code'] ?? '');
        $member   = (string)($record['member_name'] ?? '');
        $amount   = floatval($record['amount'] ?? 0);
        $fund     = (string)($record['fund_source'] ?? '');
        $status   = (string)($record['status'] ?? '');

        if ($disbId <= 0) {
            $errors[] = "Skipped: invalid disbursement_id";
            continue;
        }

        $stmt->bind_param('issdss', $disbId, $loanCode, $member, $amount, $fund, $status);

        if ($stmt->execute()) {
            $imported++;
        } else {
            $errors[] = "Failed disbursement_id {$disbId}: " . $stmt->error;
        }
    }

    $stmt->close();

    echo json_encode([
        'success'  => true,
        'message'  => "Imported {$imported} record(s)",
        'imported' => $imported,
        'errors'   => $errors
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
