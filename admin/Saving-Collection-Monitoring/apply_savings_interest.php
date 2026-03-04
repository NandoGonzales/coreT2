<?php
/**
 * Apply Monthly Savings Interest (2.5%)
 * - Auto-detects missed months and applies retroactively
 * - Records interest with correct backdated date (1st of each month)
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

define('INTEREST_RATE', 0.025); // 2.5% monthly

// Auto-create log table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS savings_interest_log (
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
    $force  = !empty($body['force']);

    // ── Find earliest savings transaction to know start month ───────
    $firstRow = $conn->query("SELECT MIN(transaction_date) AS first_date FROM savings")->fetch_assoc();
    $firstDate = $firstRow['first_date'] ?? date('Y-m-01');
    
    // Start from the month AFTER first transaction
    $startMonth = date('Y-m', strtotime($firstDate . ' +1 month'));
    $startMonth = min($startMonth, date('Y-m')); // don't go beyond current month

    // ── Get all months that should have had interest applied ─────────
    $allMonths = [];
    $cursor = strtotime($startMonth . '-01');
    $now    = strtotime(date('Y-m') . '-01');
    while ($cursor <= $now) {
        $allMonths[] = date('Y-m', $cursor);
        $cursor = strtotime('+1 month', $cursor);
    }

    // ── Get already-applied months ───────────────────────────────────
    $appliedRes = $conn->query("SELECT period FROM savings_interest_log");
    $appliedMonths = [];
    while ($r = $appliedRes->fetch_assoc()) {
        $appliedMonths[] = $r['period'];
    }

    $missedMonths = array_values(array_diff($allMonths, $appliedMonths));

    // ── CHECK action ─────────────────────────────────────────────────
    if ($action === 'check') {
        echo json_encode([
            'success'        => true,
            'today'          => date('Y-m-d'),
            'current_period' => date('Y-m'),
            'missed_months'  => $missedMonths,
            'missed_count'   => count($missedMonths),
            'should_run'     => count($missedMonths) > 0,
            'interest_rate'  => (INTEREST_RATE * 100) . '%',
        ]);
        exit;
    }

    if ($action !== 'apply') {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    // ── Manual: use specific month if provided, else all missed ─────
    $targetMonths = !empty($body['month']) ? [$body['month']] : $missedMonths;

    if (empty($targetMonths)) {
        echo json_encode([
            'success' => false,
            'message' => 'Walang missed months. Interest already applied sa lahat ng months.',
        ]);
        exit;
    }

    // ── APPLY INTEREST PER MISSED MONTH ─────────────────────────────
    $conn->begin_transaction();
    $summary = [];

    $insertStmt = $conn->prepare("
        INSERT INTO savings (member_id, transaction_date, transaction_type, amount, balance, recorded_by)
        VALUES (?, ?, 'Interest', ?, ?, ?)
    ");
    if (!$insertStmt) throw new Exception('Prepare failed: ' . $conn->error);

    $logStmt = $conn->prepare("
        INSERT INTO savings_interest_log (period, applied_at, applied_by, applied_by_name, members_count, total_interest)
        VALUES (?, NOW(), ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            applied_at = NOW(), members_count = VALUES(members_count),
            total_interest = VALUES(total_interest)
    ");

    foreach ($targetMonths as $period) {
        // Interest date = 1st of that month
        $interestDate = $period . '-01';

        // Get each member's balance as of end of PREVIOUS month
        // (last transaction before the interest date)
        $membersRes = $conn->query("
            SELECT s1.member_id, s1.balance AS balance_before
            FROM savings s1
            INNER JOIN (
                SELECT member_id, MAX(saving_id) AS max_id
                FROM savings
                WHERE transaction_date < '$interestDate'
                GROUP BY member_id
            ) s2 ON s1.member_id = s2.member_id AND s1.saving_id = s2.max_id
            WHERE s1.balance > 0
        ");

        if (!$membersRes) continue;

        $monthTotal   = 0.0;
        $monthCount   = 0;

        while ($m = $membersRes->fetch_assoc()) {
            $balanceBefore = (float)$m['balance_before'];
            if ($balanceBefore <= 0) continue;

            $interest   = round($balanceBefore * INTEREST_RATE, 2);
            $newBalance = round($balanceBefore + $interest, 2);
            $memberId   = (int)$m['member_id'];

            $insertStmt->bind_param('isddi', $memberId, $interestDate, $interest, $newBalance, $userId);
            if (!$insertStmt->execute()) {
                throw new Exception("Insert failed for member {$memberId} period {$period}: " . $insertStmt->error);
            }

            $monthTotal += $interest;
            $monthCount++;
        }

        // Log this period
        $logStmt->bind_param('siisi', $period, $userId, $userName, $monthCount, $monthTotal);
        $logStmt->execute();

        $summary[] = [
            'period'         => $period,
            'interest_date'  => $interestDate,
            'members_count'  => $monthCount,
            'total_interest' => number_format($monthTotal, 2),
        ];
    }

    $insertStmt->close();
    $logStmt->close();
    $conn->commit();

    $grandTotal = array_sum(array_map(fn($s) => (float)str_replace(',','',$s['total_interest']), $summary));

    echo json_encode([
        'success'            => true,
        'message'            => count($summary) . ' month(s) na na-apply ng interest!',
        'months_applied'     => count($summary),
        'grand_total_interest' => number_format($grandTotal, 2),
        'interest_rate'      => (INTEREST_RATE * 100) . '%',
        'applied_at'         => date('Y-m-d H:i:s'),
        'details'            => $summary,
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) @$conn->rollback();
    error_log("apply_savings_interest.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}