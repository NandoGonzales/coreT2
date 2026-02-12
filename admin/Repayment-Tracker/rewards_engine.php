<?php
/**
 * Rewards Engine
 * Call this AFTER a schedule is marked Paid.
 */

function processOnTimeRewards(mysqli $conn, int $schedule_id): array
{
    // 1) Get schedule + loan + member
    $stmt = $conn->prepare("
        SELECT ls.schedule_id, ls.loan_id, ls.due_date, ls.payment_date, ls.status,
               lp.member_id
        FROM loan_schedule ls
        INNER JOIN loan_portfolio lp ON lp.loan_id = ls.loan_id
        WHERE ls.schedule_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return ['success' => false, 'message' => 'Schedule not found'];
    if ($row['status'] !== 'Paid') return ['success' => false, 'message' => 'Not paid yet'];
    if (empty($row['payment_date']) || empty($row['due_date'])) {
        return ['success' => false, 'message' => 'Missing dates'];
    }

    // On-time check: payment_date <= due_date
    $isOnTime = (strtotime($row['payment_date']) <= strtotime($row['due_date']));
    if (!$isOnTime) {
        // reset streak if late (optional)
        $conn->query("UPDATE member_rewards SET consecutive_on_time = 0 WHERE member_id=".(int)$row['member_id']);
        return ['success' => true, 'message' => 'Paid but late, no reward'];
    }

    $member_id = (int)$row['member_id'];

    // 2) Idempotent check: avoid double reward for same schedule
    $chk = $conn->prepare("
        SELECT 1 FROM reward_transactions
        WHERE member_id = ? AND reference_type = 'loan_schedule' AND reference_id = ?
        LIMIT 1
    ");
    $chk->bind_param("ii", $member_id, $schedule_id);
    $chk->execute();
    $exists = $chk->get_result()->num_rows > 0;
    $chk->close();
    if ($exists) return ['success' => true, 'message' => 'Reward already processed'];

    // 3) Ensure member_rewards row exists
    $conn->query("
        INSERT INTO member_rewards (member_id, points, tier, total_on_time_payments, consecutive_on_time, best_streak)
        VALUES ($member_id, 0, 'Bronze', 0, 0, 0)
        ON DUPLICATE KEY UPDATE member_id = member_id
    ");

    // 4) Update counters
    $conn->query("
        UPDATE member_rewards
        SET
            total_on_time_payments = COALESCE(total_on_time_payments,0) + 1,
            consecutive_on_time = COALESCE(consecutive_on_time,0) + 1,
            best_streak = GREATEST(COALESCE(best_streak,0), COALESCE(consecutive_on_time,0) + 1),
            points = COALESCE(points,0) + 1,
            last_reward_date = NOW()
        WHERE member_id = $member_id
    ");

    // 5) Read updated rewards row
    $res = $conn->query("SELECT * FROM member_rewards WHERE member_id = $member_id LIMIT 1");
    $rw = $res->fetch_assoc();

    $consecutive = (int)($rw['consecutive_on_time'] ?? 0);
    $totalOnTime = (int)($rw['total_on_time_payments'] ?? 0);

    // 6) Always log "earned" for this schedule
    $ins = $conn->prepare("
        INSERT INTO reward_transactions
            (member_id, points_earned, points_redeemed, transaction_type, reference_type, reference_id, description, transaction_date)
        VALUES
            (?, 1, 0, 'earned', 'loan_schedule', ?, 'On-time payment reward (+1 point)', NOW())
    ");
    $ins->bind_param("ii", $member_id, $schedule_id);
    $ins->execute();
    $ins->close();

    // 7) B: 3 straight on-time
    if ($consecutive === 3) {
        // Option: waive late fee next time OR service fee discount next time
        $conn->query("
            UPDATE member_rewards
            SET waive_late_fee_next = 1
            WHERE member_id = $member_id
        ");

        $conn->query("
            INSERT INTO reward_transactions
                (member_id, points_earned, points_redeemed, transaction_type, reference_type, reference_id, description, transaction_date)
            VALUES
                ($member_id, 0, 0, 'bonus', 'loan_schedule', $schedule_id,
                 'Badge: 3 straight on-time. Benefit: waive late fee (next time).', NOW())
        ");
    }

    // 8) C: 10 on-time total => Gold tier + interest discount next loan (-0.5%)
    if ($totalOnTime === 10) {
        $conn->query("
            UPDATE member_rewards
            SET tier = 'Gold',
                interest_discount_next = 0.50
            WHERE member_id = $member_id
        ");

        $conn->query("
            INSERT INTO reward_transactions
                (member_id, points_earned, points_redeemed, transaction_type, reference_type, reference_id, description, transaction_date)
            VALUES
                ($member_id, 0, 0, 'bonus', 'loan_schedule', $schedule_id,
                 'Gold tier reached (10 on-time). Benefit: -0.5% interest next loan.', NOW())
        ");
    }

    return ['success' => true, 'message' => 'On-time reward processed'];
}
