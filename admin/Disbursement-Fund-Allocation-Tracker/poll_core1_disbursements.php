<?php
/**
 * Poll Core1 for Disbursement Status Updates (UPDATED - Connected to loan_portfolio)
 * Location: CORE2 - /admin/Disbursement-Fund-Allocation-Tracker/poll_core1_disbursements.php
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

define('CORE1_LOANS_API', 'https://core1.microfinancial-1.com/api/loans');

$STATUS_MAP = [
    'disbursed' => 'Released',
    'pending'   => 'Pending',
    'scheduled' => 'Pending',
    'cancelled' => 'Cancelled',
];

$isCron = (php_sapi_name() === 'cli');
if (!$isCron) {
    if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

try {
    $ch = curl_init(CORE1_LOANS_API);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Accept: application/json']
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) throw new Exception("Cannot connect to Core1: {$curlError}");
    if ($httpCode !== 200) throw new Exception("Core1 API returned HTTP {$httpCode}");

    $loans = json_decode($response, true);
    if (!is_array($loans)) throw new Exception("Core1 returned invalid JSON");

    $updated = 0;
    $skipped = 0;
    $errors  = 0;

    foreach ($loans as $loan) {
        $loanCode          = $loan['loan_code']          ?? null;
        $core1DisbStatus   = strtolower($loan['disbursement_status'] ?? 'pending');
        $core2Status       = $STATUS_MAP[$core1DisbStatus] ?? 'Pending';

        if (!$loanCode) {
            $skipped++;
            continue;
        }

        // Find matching disbursement using loan_portfolio JOIN
        $stmt = $conn->prepare("
            SELECT d.disbursement_id, d.status, d.loan_id
            FROM disbursements d
            LEFT JOIN loan_portfolio lp ON d.loan_id = lp.loan_code
            WHERE lp.loan_code = ?
            LIMIT 1
        ");

        if (!$stmt) { $errors++; continue; }
        
        $stmt->bind_param('s', $loanCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $disb   = $result->fetch_assoc();
        $stmt->close();

        if (!$disb) {
            $skipped++;
            continue;
        }

        $disbId        = $disb['disbursement_id'];
        $currentStatus = $disb['status'];

        if ($currentStatus === $core2Status) {
            $skipped++;
            continue;
        }

        $updateStmt = $conn->prepare("
            UPDATE disbursements
            SET status = ?,
                remarks = CONCAT(COALESCE(remarks, ''), ' | Auto-synced from Core1: ', ?, ' @ ', NOW())
            WHERE disbursement_id = ?
        ");

        if (!$updateStmt) { $errors++; continue; }

        $syncNote = "Core1 status: {$core1DisbStatus}";
        $updateStmt->bind_param('ssi', $core2Status, $syncNote, $disbId);

        if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
            $updated++;
        }
        $updateStmt->close();
    }

    $loansChecked = count($loans);
    $message = "Checked {$loansChecked} loans — Updated: {$updated}, Skipped: {$skipped}, Errors: {$errors}";

    while (@ob_end_clean());
    echo json_encode([
        'success'       => true,
        'message'       => $message,
        'loans_checked' => $loansChecked,
        'updated'       => $updated,
        'skipped'       => $skipped,
        'errors'        => $errors,
        'polled_at'     => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("poll_core1_disbursements.php Error: " . $e->getMessage());
    
    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}