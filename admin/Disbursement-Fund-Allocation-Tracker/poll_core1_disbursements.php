<?php
/**
 * ============================================================
 * Core1 Disbursement Status Poller
 * Path: /admin/Disbursement-Fund-Allocation-Tracker/poll_core1_disbursements.php
 *
 * Tinatawag via AJAX (every 5 mins) o cron job.
 * Nag-c-check ng disbursement_status sa Core1 loans API,
 * then ina-update ang disbursements table sa Core2.
 *
 * Cron setup (every 5 mins):
 *   * /5 * * * * php /path/to/poll_core1_disbursements.php
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

// ─── CONFIG ───────────────────────────────────────────────────
define('CORE1_LOANS_API', 'https://core1.microfinancial-1.com/api/loans');

// Status mapping: Core1 disbursement_status → Core2 disbursements.status
define('STATUS_MAP', [
    'disbursed' => 'Released',
    'pending'   => 'Pending',
    'scheduled' => 'Pending',
    'cancelled' => 'Cancelled',
]);

// ─── ENSURE POLL LOG TABLE EXISTS ────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS core1_poll_log (
        log_id        INT AUTO_INCREMENT PRIMARY KEY,
        polled_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        loans_checked INT DEFAULT 0,
        updated       INT DEFAULT 0,
        errors        INT DEFAULT 0,
        message       TEXT
    )
");

// ─── AUTH CHECK (skip if running via cron) ────────────────────
$isCron = (php_sapi_name() === 'cli');
if (!$isCron) {
    if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

try {
    // ── 1. FETCH LOANS FROM CORE1 ─────────────────────────────
    $ch = curl_init(CORE1_LOANS_API);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json'
        ]
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Cannot connect to Core1: {$curlError}");
    }
    if ($httpCode !== 200) {
        throw new Exception("Core1 API returned HTTP {$httpCode}");
    }

    $loans = json_decode($response, true);
    if (!is_array($loans)) {
        throw new Exception("Core1 returned invalid JSON");
    }

    // ── 2. PROCESS EACH LOAN ──────────────────────────────────
    $updated = 0;
    $skipped = 0;
    $errors  = 0;
    $changes = []; // track what changed for response

    $statusMap = STATUS_MAP;

    foreach ($loans as $loan) {
        $loanCode          = $loan['loan_code']          ?? null;
        $disbursementDate  = $loan['disbursement_date']  ?? null;
        $core1DisbStatus   = strtolower($loan['disbursement_status'] ?? 'pending');
        $core2Status       = $statusMap[$core1DisbStatus] ?? 'Pending';

        if (!$loanCode) {
            $skipped++;
            continue;
        }

        // Find matching disbursement in Core2 by loan_code
        // Core2 disbursements.loan_id = loan_portfolio.loan_id where loan_code matches
        $stmt = $conn->prepare("
            SELECT d.disbursement_id, d.status, d.loan_id
            FROM disbursements d
            JOIN loan_portfolio lp ON d.loan_id = lp.loan_id
            WHERE lp.loan_code = ?
            LIMIT 1
        ");

        if (!$stmt) {
            // Try direct loan_id match if no loan_portfolio join available
            $stmt = $conn->prepare("
                SELECT disbursement_id, status, loan_id
                FROM disbursements
                WHERE loan_id = ?
                LIMIT 1
            ");
            if (!$stmt) { $errors++; continue; }
            $loanId = (int)($loan['id'] ?? 0);
            $stmt->bind_param('i', $loanId);
        } else {
            $stmt->bind_param('s', $loanCode);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $disb   = $result->fetch_assoc();
        $stmt->close();

        if (!$disb) {
            // No matching disbursement in Core2 yet — skip
            $skipped++;
            continue;
        }

        $disbId      = $disb['disbursement_id'];
        $currentStatus = $disb['status'];

        // Only update if status actually changed
        if ($currentStatus === $core2Status) {
            $skipped++;
            continue;
        }

        // ── UPDATE disbursements table ────────────────────────
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
            $changes[] = [
                'disbursement_id' => $disbId,
                'loan_code'       => $loanCode,
                'old_status'      => $currentStatus,
                'new_status'      => $core2Status,
                'core1_status'    => $core1DisbStatus
            ];
        }
        $updateStmt->close();
    }

    // ── 3. LOG THE POLL ───────────────────────────────────────
    $loansChecked = count($loans);
    $message      = "Checked {$loansChecked} loans — Updated: {$updated}, Skipped: {$skipped}, Errors: {$errors}";
    $msgEsc       = $conn->real_escape_string($message);

    $conn->query("
        INSERT INTO core1_poll_log (loans_checked, updated, errors, message)
        VALUES ({$loansChecked}, {$updated}, {$errors}, '{$msgEsc}')
    ");

    // ── 4. RESPOND ────────────────────────────────────────────
    while (@ob_end_clean());
    echo json_encode([
        'success'       => true,
        'message'       => $message,
        'loans_checked' => $loansChecked,
        'updated'       => $updated,
        'skipped'       => $skipped,
        'errors'        => $errors,
        'changes'       => $changes,
        'polled_at'     => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("poll_core1_disbursements.php Error: " . $e->getMessage());

    // Log the failed poll
    $errMsg = $conn->real_escape_string($e->getMessage());
    $conn->query("
        INSERT INTO core1_poll_log (loans_checked, updated, errors, message)
        VALUES (0, 0, 1, 'ERROR: {$errMsg}')
    ");

    while (@ob_end_clean());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}