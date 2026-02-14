<?php
/**
 * ============================================================
 * Send to Finance AJAX Handler
 * Path: /admin/Disbursement-Fund-Allocation-Tracker/send_to_finance.php
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

if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId   = $_SESSION['userdata']['user_id']   ?? 0;
$userName = $_SESSION['userdata']['full_name'] ?? 'Admin';

// PATCHED: Changed from core2 internal to core1 Laravel API
define('FINANCE_API_URL', 'https://core1.microfinancial-1.com/api/loans');

// PATCHED: CREATE TABLE FIRST — before any INSERT attempt
$conn->query("
    CREATE TABLE IF NOT EXISTS financial_send_log (
        log_id           INT AUTO_INCREMENT PRIMARY KEY,
        sent_by_user_id  INT,
        disbursement_ids TEXT,
        records_sent     INT DEFAULT 0,
        sent_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        api_response     TEXT
    )
");

try {
    $raw    = file_get_contents('php://input');
    $body   = json_decode($raw, true);
    $action = trim($body['action'] ?? $_POST['action'] ?? '');

    // ─── ACTION: send ─────────────────────────────────────
    if ($action === 'send') {
        $disbursementIds = $body['disbursement_ids'] ?? [];

        if (empty($disbursementIds)) {
            throw new Exception('No disbursements selected');
        }

        // Fetch selected disbursement records
        // PATCHED: Added LEFT JOIN loan_portfolio to get loan_code
        $placeholders = implode(',', array_fill(0, count($disbursementIds), '?'));
        $types        = str_repeat('i', count($disbursementIds));

        $stmt = $conn->prepare("
            SELECT
                d.disbursement_id,
                d.loan_id,
                lp.loan_code,
                COALESCE(m.full_name, 'N/A')                 AS member_name,
                DATE_FORMAT(d.disbursement_date, '%Y-%m-%d') AS disbursement_date,
                d.amount,
                d.fund_source,
                d.status,
                d.remarks
            FROM disbursements d
            LEFT JOIN members m         ON d.member_id = m.member_id
            LEFT JOIN loan_portfolio lp ON d.loan_id   = lp.loan_id
            WHERE d.disbursement_id IN ({$placeholders})
        ");

        if (!$stmt) throw new Exception("Query failed: " . $conn->error);
        $stmt->bind_param($types, ...$disbursementIds);
        $stmt->execute();
        $result = $stmt->get_result();

        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        $stmt->close();

        if (empty($records)) throw new Exception('No records found for selected IDs');

        // PATCHED: Send as plain JSON array (Core1 Laravel format)
        // Old: wrapped in { disbursements: [...], sent_by: ... }
        // New: plain array [{ ... }, { ... }]
        $payload = json_encode($records);

        $ch = curl_init(FINANCE_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Connection failed: {$curlError}");
        }

        // PATCHED: Core1 Laravel returns 200 or 201 as success (not just 200)
        if ($httpCode !== 200 && $httpCode !== 201) {
            $errBody = json_decode($response, true);
            throw new Exception(
                "Core1 error (HTTP {$httpCode}): " .
                ($errBody['message'] ?? $errBody['error'] ?? substr($response, 0, 200))
            );
        }

        // Log the send action
        $disbIds      = $conn->real_escape_string(implode(',', $disbursementIds));
        $apiRespClean = $conn->real_escape_string(substr($response, 0, 5000));
        $conn->query("
            INSERT INTO financial_send_log (sent_by_user_id, disbursement_ids, records_sent, api_response)
            VALUES ({$userId}, '{$disbIds}', " . count($records) . ", '{$apiRespClean}')
        ");

        while (@ob_end_clean());
        echo json_encode([
            'success'      => true,
            'message'      => 'Successfully sent ' . count($records) . ' record(s) to Financial Team (Core1)',
            'records_sent' => count($records),
            'api_response' => json_decode($response, true) ?? $response
        ]);
        exit;
    }

    throw new Exception("Invalid action: {$action}");

} catch (Exception $e) {
    error_log("send_to_finance.php Error: " . $e->getMessage());
    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}