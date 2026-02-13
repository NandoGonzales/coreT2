<?php
require_once(__DIR__ . '/../initialize_coreT2.php');
date_default_timezone_set('Asia/Manila');

// ===== SETTINGS =====
const INTEREST_RATE = 0.025;

// If you have a real system/admin user_id, set it here.
// If you want recorded_by to be NULL, set to null.
const SYSTEM_USER_ID = 1;

// OPTIONAL SECURITY (recommended):
// If you want to prevent running via browser, uncomment this block.
/*
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
*/

// If cron runs every 1st day, apply for PREVIOUS month
$target    = new DateTime('first day of last month');
$targetYm  = $target->format('Y-m'); // ex: 2026-01
$postDate  = (new DateTime('first day of this month'))->format('Y-m-d'); // ex: 2026-02-01

// 1) Get latest savings row per member (based on max saving_id)
$sqlMembers = "
    SELECT s.member_id, s.balance
    FROM savings s
    INNER JOIN (
        SELECT member_id, MAX(saving_id) AS max_id
        FROM savings
        GROUP BY member_id
    ) t ON t.member_id = s.member_id AND t.max_id = s.saving_id
";

$res = $conn->query($sqlMembers);
if (!$res) {
    http_response_code(500);
    echo "FAILED: " . $conn->error;
    exit;
}

$applied = 0;
$skipped = 0;

// 2) Duplicate blocker:
// We store interest on $postDate (1st day of current month),
// so we check if Interest already exists for that member on that date.
$checkStmt = $conn->prepare("
    SELECT 1
    FROM savings
    WHERE member_id = ?
      AND transaction_type = 'Interest'
      AND transaction_date = ?
    LIMIT 1
");

// 3) Insert interest row
// recorded_by can be NULL, so we handle bind accordingly.
$insertSqlWithUser = "
    INSERT INTO savings (member_id, transaction_date, transaction_type, amount, balance, recorded_by)
    VALUES (?, ?, 'Interest', ?, ?, ?)
";
$insertSqlNullUser = "
    INSERT INTO savings (member_id, transaction_date, transaction_type, amount, balance, recorded_by)
    VALUES (?, ?, 'Interest', ?, ?, NULL)
";

$insertStmtWithUser = $conn->prepare($insertSqlWithUser);
$insertStmtNullUser = $conn->prepare($insertSqlNullUser);

while ($row = $res->fetch_assoc()) {
    $memberId    = (int)$row['member_id'];
    $lastBalance = (float)$row['balance'];

    // skip if no balance or negative
    if ($lastBalance <= 0) {
        $skipped++;
        continue;
    }

    // duplicate check
    $checkStmt->bind_param("is", $memberId, $postDate);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        $skipped++;
        continue;
    }

    // compute interest + new balance
    $interest = round($lastBalance * INTEREST_RATE, 2);
    if ($interest <= 0) {
        $skipped++;
        continue;
    }

    $newBalance = round($lastBalance + $interest, 2);

    // insert
    $ok = false;
    if (SYSTEM_USER_ID === null) {
        $insertStmtNullUser->bind_param("isdd", $memberId, $postDate, $interest, $newBalance);
        $ok = $insertStmtNullUser->execute();
        if (!$ok) error_log("Interest insert failed (NULL recorded_by) for member {$memberId}: " . $insertStmtNullUser->error);
    } else {
        $recordedBy = (int)SYSTEM_USER_ID;
        $insertStmtWithUser->bind_param("issdi", $memberId, $postDate, $interest, $newBalance, $recordedBy);
        $ok = $insertStmtWithUser->execute();
        if (!$ok) error_log("Interest insert failed for member {$memberId}: " . $insertStmtWithUser->error);
    }

    if ($ok) $applied++;
    else $skipped++;
}

$checkStmt->close();
$insertStmtWithUser->close();
$insertStmtNullUser->close();

// Keep same output style
echo "OK. TargetMonth={$targetYm} PostDate={$postDate} Applied={$applied} Skipped={$skipped}\n";
