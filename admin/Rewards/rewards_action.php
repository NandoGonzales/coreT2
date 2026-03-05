<?php
/**
 * Rewards Action Handler
 * Path: admin/Rewards/rewards_action.php
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
$userName = $_SESSION['userdata']['full_name'] ?? 'Admin';

// ── Points config ────────────────────────────────────────────────────
define('PTS_ONTIME',     10);   // on-time payment
define('PTS_EARLY',      20);   // early payment bonus
define('PTS_COMPLETION', 50);   // full loan completion bonus
define('PTS_MANUAL_MAX', 500);  // max manual points per action

// Tier thresholds
function getTier(int $pts): string {
    if ($pts >= 1000) return 'Platinum';
    if ($pts >= 500)  return 'Gold';
    if ($pts >= 200)  return 'Silver';
    return 'Bronze';
}

// Auto-create rewards_log table
$conn->query("
    CREATE TABLE IF NOT EXISTS rewards_log (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        points INT NOT NULL,
        reason VARCHAR(200) NOT NULL,
        reference_id INT DEFAULT NULL COMMENT 'repayment_id or loan_id',
        recorded_by INT DEFAULT 0,
        recorded_by_name VARCHAR(100) DEFAULT 'System',
        created_at DATETIME DEFAULT NOW()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Helper: upsert member_rewards ───────────────────────────────────
function upsertReward(mysqli $conn, int $memberId, int $addPoints, string $reason,
                      int $refId, int $userId, string $userName,
                      int $addOnTime = 0, int $addConsecutive = 0,
                      bool $resetConsecutive = false): array
{
    // Get or create reward record
    $row = $conn->query("SELECT * FROM member_rewards WHERE member_id = $memberId LIMIT 1")->fetch_assoc();

    if (!$row) {
        $conn->query("INSERT INTO member_rewards (member_id, points, tier, total_on_time_payments, consecutive_on_time, best_streak, last_reward_date)
                      VALUES ($memberId, 0, 'Bronze', 0, 0, 0, NOW())");
        $row = $conn->query("SELECT * FROM member_rewards WHERE member_id = $memberId LIMIT 1")->fetch_assoc();
    }

    $newPoints      = max(0, (int)$row['points'] + $addPoints);
    $newOnTime      = (int)$row['total_on_time_payments'] + $addOnTime;
    $newConsecutive = $resetConsecutive ? 0 : ((int)$row['consecutive_on_time'] + $addConsecutive);
    $bestStreak     = max((int)$row['best_streak'], $newConsecutive);
    $newTier        = getTier($newPoints);

    $stmt = $conn->prepare("
        UPDATE member_rewards SET
            points = ?, tier = ?, total_on_time_payments = ?,
            consecutive_on_time = ?, best_streak = ?, last_reward_date = NOW()
        WHERE member_id = ?
    ");
    $stmt->bind_param('isiiii', $newPoints, $newTier, $newOnTime, $newConsecutive, $bestStreak, $memberId);
    $stmt->execute();
    $stmt->close();

    // Log it
    $logStmt = $conn->prepare("INSERT INTO rewards_log (member_id, points, reason, reference_id, recorded_by, recorded_by_name) VALUES (?,?,?,?,?,?)");
    $logStmt->bind_param('isiisi', $memberId, $addPoints, $reason, $refId, $userId, $userName);
    $logStmt->execute();
    $logStmt->close();

    return ['new_points' => $newPoints, 'new_tier' => $newTier];
}

try {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true) ?: [];
    $action = trim($body['action'] ?? $_GET['action'] ?? 'list');

    // ── LIST all members rewards ────────────────────────────────────
    if ($action === 'list') {
        $search = trim($_GET['search'] ?? '');
        $tier   = trim($_GET['tier']   ?? '');
        $limit  = min(100, max(10, (int)($_GET['limit'] ?? 20)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));

        $where = ['1=1'];
        $params = []; $types = '';

        if ($search) {
            $where[] = 'm.full_name LIKE ?';
            $params[] = "%$search%"; $types .= 's';
        }
        if ($tier) {
            $where[] = 'COALESCE(r.tier, "Bronze") = ?';
            $params[] = $tier; $types .= 's';
        }

        $whereSQL = implode(' AND ', $where);

        // Count
        $cntSQL  = "SELECT COUNT(*) AS c FROM members m LEFT JOIN member_rewards r ON m.member_id = r.member_id WHERE $whereSQL";
        $cntStmt = $conn->prepare($cntSQL);
        if ($types) $cntStmt->bind_param($types, ...$params);
        $cntStmt->execute();
        $total = (int)$cntStmt->get_result()->fetch_assoc()['c'];
        $cntStmt->close();

        $sql = "SELECT m.member_id, m.full_name, m.email,
                    COALESCE(r.points, 0) AS points,
                    COALESCE(r.tier, 'Bronze') AS tier,
                    COALESCE(r.total_on_time_payments, 0) AS total_on_time_payments,
                    COALESCE(r.consecutive_on_time, 0) AS consecutive_on_time,
                    COALESCE(r.best_streak, 0) AS best_streak,
                    r.waive_late_fee_next, r.service_fee_discount_next, r.interest_discount_next,
                    r.last_reward_date,
                    (SELECT COUNT(*) FROM loan_portfolio lp WHERE lp.member_id = m.member_id AND lp.status='Active') AS active_loans
                FROM members m
                LEFT JOIN member_rewards r ON m.member_id = r.member_id
                WHERE $whereSQL
                ORDER BY COALESCE(r.points,0) DESC
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);
        $allParams = array_merge($params, [$limit, $offset]);
        $allTypes  = $types . 'ii';
        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();

        // Stats
        $stats = $conn->query("SELECT
            COALESCE(SUM(points),0) AS total_points,
            SUM(tier='Platinum') AS platinum,
            SUM(tier='Gold') AS gold,
            SUM(tier='Silver') AS silver,
            SUM(tier='Bronze') AS bronze_cnt,
            COUNT(*) AS total_members
            FROM member_rewards")->fetch_assoc();

        echo json_encode(['success' => true, 'records' => $rows, 'total' => $total, 'stats' => $stats]);
        exit;
    }

    // ── GET member reward log ───────────────────────────────────────
    if ($action === 'log') {
        $memberId = (int)($_GET['member_id'] ?? 0);
        if (!$memberId) throw new Exception('member_id required');

        $logs = [];
        $res  = $conn->query("SELECT * FROM rewards_log WHERE member_id = $memberId ORDER BY created_at DESC LIMIT 30");
        while ($r = $res->fetch_assoc()) $logs[] = $r;

        echo json_encode(['success' => true, 'logs' => $logs]);
        exit;
    }

    // ── MANUAL ADD points ───────────────────────────────────────────
    if ($action === 'manual_add') {
        $memberId = (int)($body['member_id'] ?? 0);
        $points   = min(PTS_MANUAL_MAX, max(1, (int)($body['points'] ?? 0)));
        $reason   = trim($body['reason'] ?? 'Manual adjustment by admin');

        if (!$memberId) throw new Exception('member_id required');
        if (!$points)   throw new Exception('Points must be > 0');

        $res = upsertReward($conn, $memberId, $points, $reason, 0, $userId, $userName);

        echo json_encode(['success' => true, 'message' => "+{$points} points added!", 'new_points' => $res['new_points'], 'new_tier' => $res['new_tier']]);
        exit;
    }

    // ── PROCESS PAYMENT REWARD (called from repayment system) ────────
    if ($action === 'process_payment') {
        $memberId    = (int)($body['member_id']    ?? 0);
        $loanId      = (int)($body['loan_id']      ?? 0);
        $repaymentId = (int)($body['repayment_id'] ?? 0);
        $lateDays    = (int)($body['late_days']    ?? 0);
        $isCompletion = !empty($body['is_completion']);

        if (!$memberId) throw new Exception('member_id required');

        $earned = 0; $reasons = [];

        if ($isCompletion) {
            $earned    += PTS_COMPLETION;
            $reasons[]  = 'Full loan completion bonus';
        }

        if ($lateDays <= 0) {
            // On-time or early
            $earned    += PTS_ONTIME;
            $reasons[]  = 'On-time payment';

            if ($lateDays < 0) {
                // Early payment (negative late_days = paid before due)
                $earned    += PTS_EARLY;
                $reasons[]  = 'Early payment bonus';
            }
        }

        if ($earned <= 0) {
            echo json_encode(['success' => true, 'message' => 'No points earned (late payment)', 'points_earned' => 0]);
            exit;
        }

        $reason    = implode(' + ', $reasons);
        $addOnTime = ($lateDays <= 0 && !$isCompletion) ? 1 : 0;
        $addConsec = ($lateDays <= 0 && !$isCompletion) ? 1 : 0;

        $res = upsertReward($conn, $memberId, $earned, $reason, $repaymentId ?: $loanId, $userId, $userName, $addOnTime, $addConsec);

        echo json_encode(['success' => true, 'points_earned' => $earned, 'reason' => $reason, 'new_points' => $res['new_points'], 'new_tier' => $res['new_tier']]);
        exit;
    }

    // ── SYNC — scan all repayments and award missing rewards ────────
    if ($action === 'sync_all') {
        set_time_limit(120);
        $synced = 0; $totalPts = 0;

        // Use LEFT JOIN instead of NOT IN for performance
        $res = $conn->query("
            SELECT r.repayment_id, r.loan_id,
                   COALESCE(r.late_days, 0) AS late_days,
                   lp.member_id,
                   IF(lp.status = 'Completed', 1, 0) AS is_completion
            FROM repayments r
            INNER JOIN loan_portfolio lp ON r.loan_id = lp.loan_id
            LEFT JOIN rewards_log rl ON rl.reference_id = r.repayment_id
                AND rl.reason LIKE '%payment%'
            WHERE rl.log_id IS NULL
              AND (COALESCE(r.late_days,0) <= 0 OR lp.status = 'Completed')
            ORDER BY r.repayment_id ASC
            LIMIT 300
        ");

        if (!$res) throw new Exception('Sync query failed: ' . $conn->error);

        while ($row = $res->fetch_assoc()) {
            try {
                $pts     = PTS_ONTIME;
                $reasons = ['On-time payment'];

                if ((int)$row['late_days'] < 0)  { $pts += PTS_EARLY;       $reasons[] = 'Early payment bonus'; }
                if ((int)$row['is_completion'])   { $pts += PTS_COMPLETION;  $reasons[] = 'Full loan completion'; }

                upsertReward(
                    $conn, (int)$row['member_id'], $pts,
                    implode(' + ', $reasons),
                    (int)$row['repayment_id'],
                    $userId, $userName, 1, 1
                );
                $synced++;
                $totalPts += $pts;
            } catch (Exception $rowErr) {
                error_log("Sync skip repayment {$row['repayment_id']}: " . $rowErr->getMessage());
            }
        }

        echo json_encode([
            'success'              => true,
            'message'              => "Synced {$synced} payment(s).",
            'total_points_awarded' => $totalPts,
            'synced_count'         => $synced
        ]);
        exit;
    }


    // ── APPLY PENALTIES — deduct points for missed payments / inactive ─
    if ($action === 'apply_penalties') {
        set_time_limit(120);
        $deducted   = 0;
        $totalDeduct = 0;
        $log        = [];

        // ── Rule 1: Missed payments — 2 months overdue (-30 pts) ──────
        $res = $conn->query("
            SELECT lp.member_id, m.full_name,
                   COUNT(*) AS missed_count,
                   MAX(r.next_due) AS last_due
            FROM loan_portfolio lp
            INNER JOIN members m ON m.member_id = lp.member_id
            LEFT JOIN repayments r ON r.loan_id = lp.loan_id
            WHERE lp.status IN ('Active','Approved')
              AND r.next_due < DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
              AND lp.member_id NOT IN (
                  SELECT DISTINCT lp2.member_id
                  FROM repayments r2
                  INNER JOIN loan_portfolio lp2 ON lp2.loan_id = r2.loan_id
                  WHERE r2.repayment_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
              )
            GROUP BY lp.member_id, m.full_name
        ");

        if ($res) while ($row = $res->fetch_assoc()) {
            $memberId = (int)$row['member_id'];
            $months   = (int)$row['missed_count'];
            
            // -30 pts per 2 months missed, -50 pts per 3+ months
            $penalty  = $months >= 3 ? 50 : 30;
            $reason   = "Penalty: {$months} missed payment(s) — " . date('Y-m-d');

            // Check if already penalized this month
            $already = $conn->query("
                SELECT log_id FROM rewards_log 
                WHERE member_id = $memberId 
                  AND reason LIKE 'Penalty: %missed payment%'
                  AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')
                LIMIT 1
            ");
            if ($already && $already->num_rows > 0) continue;

            upsertReward($conn, $memberId, -$penalty, $reason, 0, $userId, $userName);
            $deducted++;
            $totalDeduct += $penalty;
            $log[] = "⚠️ {$row['full_name']}: -{$penalty} pts ({$months} months missed)";
        }

        // ── Rule 2: 3 months no payment at all (-50 pts) ──────────────
        $res2 = $conn->query("
            SELECT lp.member_id, m.full_name
            FROM loan_portfolio lp
            INNER JOIN members m ON m.member_id = lp.member_id
            INNER JOIN member_rewards mr ON mr.member_id = lp.member_id
            WHERE lp.status IN ('Active','Approved')
              AND mr.points > 0
              AND lp.member_id NOT IN (
                  SELECT DISTINCT lp2.member_id
                  FROM repayments r2
                  INNER JOIN loan_portfolio lp2 ON lp2.loan_id = r2.loan_id
                  WHERE r2.repayment_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
              )
        ");

        if ($res2) while ($row = $res2->fetch_assoc()) {
            $memberId = (int)$row['member_id'];
            $reason   = "Penalty: No payment for 3+ months — " . date('Y-m-d');

            $already = $conn->query("
                SELECT log_id FROM rewards_log 
                WHERE member_id = $memberId 
                  AND reason LIKE 'Penalty: No payment%'
                  AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')
                LIMIT 1
            ");
            if ($already && $already->num_rows > 0) continue;

            upsertReward($conn, $memberId, -50, $reason, 0, $userId, $userName);
            $deducted++;
            $totalDeduct += 50;
            $log[] = "🚫 {$row['full_name']}: -50 pts (3+ months no payment)";
        }

        // ── Rule 3: Inactive members — reset consecutive streak ───────
        $res3 = $conn->query("
            SELECT mr.member_id, m.full_name, mr.consecutive_on_time
            FROM member_rewards mr
            INNER JOIN members m ON m.member_id = mr.member_id
            WHERE mr.consecutive_on_time > 0
              AND mr.member_id NOT IN (
                  SELECT DISTINCT lp.member_id
                  FROM loan_portfolio lp
                  WHERE lp.status IN ('Active','Approved')
              )
        ");

        if ($res3) while ($row = $res3->fetch_assoc()) {
            $memberId = (int)$row['member_id'];
            // Reset consecutive streak for inactive members
            $conn->query("UPDATE member_rewards SET consecutive_on_time = 0 WHERE member_id = $memberId");
            $logStmt = $conn->prepare("INSERT INTO rewards_log (member_id, points, reason, recorded_by, recorded_by_name) VALUES (?,?,?,?,?)");
            $pts = 0;
            $rsn = "Streak reset: No active loans — " . date('Y-m-d');
            $logStmt->bind_param('iisis', $memberId, $pts, $rsn, $userId, $userName);
            $logStmt->execute();
            $logStmt->close();
            $log[] = "🔄 {$row['full_name']}: streak reset (no active loans)";
        }

        echo json_encode([
            'success'         => true,
            'message'         => "Penalties applied to {$deducted} member(s). Total deducted: {$totalDeduct} pts.",
            'deducted_count'  => $deducted,
            'total_deducted'  => $totalDeduct,
            'details'         => $log
        ]);
        exit;
    }

    throw new Exception('Invalid action: ' . $action);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) @$conn->rollback();
    error_log("rewards_action.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}