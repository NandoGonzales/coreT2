<?php
/**
 * Apply Monthly Savings Interest (2.5%)
 * Auto-runs every 1st of the month
 * Also callable manually via POST action=apply
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../../initialize_coreT2.php');

header('Content-Type: application/json; charset=utf-8');
while (@ob_get_level()) @ob_end_clean();

if (!isset($_SESSION['userdata'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId   = (int)($_SESSION['userdata']['user_id'] ?? 0);
$userName = $_SESSION['userdata']['full_name'] ?? 'System';

const INTEREST_RATE = 0.025; // 2.5% monthly
const INTEREST_LOG_TABLE = 'savings_interest_log';

// Auto-create log table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS " . INTEREST_LOG_TABLE . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM format',
        applied_at DATETIME NOT NULL,
        applied_by INT DEFAULT 0,
        applied_by_name VARCHAR(100) DEFAULT 'System',
        members_count INT DEFAULT 0,
        total_interest DECIMAL(14,2) DEFAULT 0.00,
        UNIQUE KEY unique_period (period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

try {
    $raw    = file_get_contents('php://input');
    $body   = json_decode($raw ?: '{}', true) ?: [];
    $action = trim($body['action'] ?? $_GET['action'] ?? 'check');

    $today    = date('Y-m-d');
    $dayOfMonth = (int)date('j');
    $period   = date('Y-m'); // e.g. 2026-03

    // ── CHECK: was interest already applied this month? ──────────────
    $logRow = $conn->query(
        "SELECT * FROM " . INTEREST_LOG_TABLE . " WHERE period = '$period' LIMIT 1"
    )->fetch_assoc();

    $alreadyApplied = !empty($logRow);

    if ($action === 'check') {
        echo json_encode([
            'success'         => true,
            'today'           => $today,
            'day_of_month'    => $dayOfMonth,
            'period'          => $period,
            'already_applied' => $alreadyApplied,
            'last_applied'    => $logRow ? $logRow['applied_at'] : null,
            'should_run'      => ($dayOfMonth === 1 && !$alreadyApplied),
            'interest_rate'   => (INTEREST_RATE * 100) . '%',
        ]);
        exit;
    }

    if ($action !== 'apply') {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    if ($alreadyApplied && empty($body['force'])) {
        echo json_encode([
            'success'  => false,
            'message'  => "Interest already applied for {$period}. Use force=true to override.",
            'period'   => $period,
            'applied_at' => $logRow['applied_at'],
        ]);
        exit;
    }

    // ── GET ALL MEMBERS WITH POSITIVE BALANCE ────────────────────────
    $result = $conn->query("
        SELECT member_id, MAX(balance) AS current_balance
        FROM savings
        WHERE transaction_type IN ('Deposit', 'Interest', 'Withdrawal')
        GROUP BY member_id
        HAVING current_balance > 0
    ");

    if (!$result) throw new Exception('Failed to query member balances: ' . $conn->error);

    $members = [];
    while ($row = $result->fetch_assoc()) {
        // Get the LATEST balance (last transaction's balance)
        $balRes = $conn->query("
            SELECT balance FROM savings
            WHERE member_id = {$row['member_id']}
            ORDER BY saving_id DESC LIMIT 1
        ");
        $latestBal = $balRes ? (float)$balRes->fetch_assoc()['balance'] : 0;
        if ($latestBal > 0) {
            $members[] = [
                'member_id' => (int)$row['member_id'],
                'balance'   => $latestBal,
            ];
        }
    }

    if (empty($members)) {
        echo json_encode([
            'success' => false,
            'message' => 'No members with positive balance found.',
        ]);
        exit;
    }

    // ── APPLY INTEREST TO EACH MEMBER ───────────────────────────────
    $conn->begin_transaction();
    $applied     = [];
    $totalInterest = 0.0;

    $insertStmt = $conn->prepare("
        INSERT INTO savings (member_id, transaction_date, transaction_type, amount, balance, recorded_by)
        VALUES (?, ?, 'Interest', ?, ?, ?)
    ");
    if (!$insertStmt) throw new Exception('Prepare failed: ' . $conn->error);

    foreach ($members as $m) {
        $interest    = round($m['balance'] * INTEREST_RATE, 2);
        $newBalance  = round($m['balance'] + $interest, 2);
        $memberId    = $m['member_id'];

        $insertStmt->bind_param('isddi', $memberId, $today, $interest, $newBalance, $userId);
        if (!$insertStmt->execute()) {
            throw new Exception("Failed to insert interest for member {$memberId}: " . $insertStmt->error);
        }

        $applied[]      = ['member_id' => $memberId, 'interest' => $interest, 'new_balance' => $newBalance];
        $totalInterest += $interest;
    }

    $insertStmt->close();

    // ── LOG THIS APPLICATION ─────────────────────────────────────────
    $count = count($applied);
    $logStmt = $conn->prepare("
        INSERT INTO " . INTEREST_LOG_TABLE . " (period, applied_at, applied_by, applied_by_name, members_count, total_interest)
        VALUES (?, NOW(), ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            applied_at = NOW(), applied_by = VALUES(applied_by),
            applied_by_name = VALUES(applied_by_name),
            members_count = VALUES(members_count),
            total_interest = VALUES(total_interest)
    ");
    $logStmt->bind_param('siisi', $period, $userId, $userName, $count, $totalInterest);
    $logStmt->execute();
    $logStmt->close();

    $conn->commit();

    echo json_encode([
        'success'        => true,
        'message'        => "Interest applied successfully for {$period}!",
        'period'         => $period,
        'interest_rate'  => (INTEREST_RATE * 100) . '%',
        'members_count'  => $count,
        'total_interest' => number_format($totalInterest, 2),
        'applied_at'     => date('Y-m-d H:i:s'),
        'details'        => array_slice($applied, 0, 10), // first 10 only
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) @$conn->rollback();
    error_log("apply_savings_interest.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}