<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Only GET requests are allowed.']);
        exit;
    }

    $loan_code = isset($_GET['loan_code']) ? trim($_GET['loan_code']) : '';
    $loan_id   = isset($_GET['loan_id']) ? intval($_GET['loan_id']) : 0;

    if (empty($loan_code) && !$loan_id) {
        throw new Exception('Loan Code or Loan ID is required.');
    }

    // ─── Check which optional columns exist ───
    $has_penalty_rate  = $conn->query("SHOW COLUMNS FROM loan_portfolio LIKE 'penalty_rate'")->num_rows > 0;
    $has_late_fee      = $conn->query("SHOW COLUMNS FROM loan_portfolio LIKE 'late_fee'")->num_rows > 0;
    $has_grace_period  = $conn->query("SHOW COLUMNS FROM loan_portfolio LIKE 'grace_period_days'")->num_rows > 0;

    $penalty_rate_col = $has_penalty_rate ? 'l.penalty_rate' : '2.00';
    $late_fee_col     = $has_late_fee     ? 'l.late_fee'     : '50.00';
    $grace_col        = $has_grace_period ? 'l.grace_period_days' : '0';

    // ─── Fetch loan details ───
    $select_sql = "
        SELECT 
            l.loan_code,
            l.loan_id,
            l.member_id,
            COALESCE(m.full_name, 'Unknown') AS member_name,
            l.loan_type,
            l.principal_amount,
            l.interest_rate,
            l.loan_term,
            DATE_FORMAT(l.start_date, '%Y-%m-%d') AS start_date,
            DATE_FORMAT(l.end_date, '%Y-%m-%d')   AS end_date,
            l.status,
            COALESCE({$penalty_rate_col}, 2.00)   AS penalty_rate,
            COALESCE({$late_fee_col}, 50.00)       AS late_fee,
            COALESCE({$grace_col}, 0)              AS grace_period_days
        FROM loan_portfolio l
        LEFT JOIN members m ON m.member_id = l.member_id
        WHERE l.{column} = ?
        LIMIT 1
    ";

    if (!empty($loan_code)) {
        $sql = str_replace('{column}', 'loan_code', $select_sql);
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $loan_code);
    } else {
        $sql = str_replace('{column}', 'loan_id', $select_sql);
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $loan_id);
    }

    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$loan) {
        throw new Exception('Loan not found.');
    }

    // ─── STEP 1: Try loan_penalties table ───
    $total_penalties = 0.00;
    $lc = $loan['loan_code'];

    if (!empty($lc)) {
        $table_check = $conn->query("SHOW TABLES LIKE 'loan_penalties'");
        if ($table_check && $table_check->num_rows > 0) {
            $pen_stmt = $conn->prepare("
                SELECT COALESCE(SUM(penalty_amount), 0) AS total_penalties
                FROM loan_penalties
                WHERE loan_code = ?
            ");
            if ($pen_stmt) {
                $pen_stmt->bind_param('s', $lc);
                $pen_stmt->execute();
                $pen_row = $pen_stmt->get_result()->fetch_assoc();
                $pen_stmt->close();
                $total_penalties = (float)($pen_row['total_penalties'] ?? 0);
            }
        }
    }

    // ─── STEP 2: If still zero, compute from overdue schedules ───
    if ($total_penalties == 0 && !empty($lc)) {
        $penalty_rate = (float)$loan['penalty_rate'];
        $late_fee     = (float)$loan['late_fee'];
        $grace_days   = (int)$loan['grace_period_days'];

        $ov_stmt = $conn->prepare("
            SELECT 
                amount_due,
                amount_paid
            FROM loan_schedule
            WHERE loan_code = ?
              AND due_date < CURDATE()
              AND amount_paid < amount_due
              AND DATEDIFF(CURDATE(), due_date) > ?
        ");

        if ($ov_stmt) {
            $ov_stmt->bind_param('si', $lc, $grace_days);
            $ov_stmt->execute();
            $ov_result = $ov_stmt->get_result();

            while ($ov = $ov_result->fetch_assoc()) {
                $outstanding        = (float)$ov['amount_due'] - (float)$ov['amount_paid'];
                $percentage_penalty = $outstanding * ($penalty_rate / 100);
                $total_penalties   += $late_fee + $percentage_penalty;
            }
            $ov_stmt->close();
        }
    }

    $loan['total_penalties'] = round($total_penalties, 2);

    // ─── Fetch payment schedules ───
    if (!empty($loan['loan_code'])) {
        $stmt = $conn->prepare("
            SELECT 
                schedule_id,
                DATE_FORMAT(due_date, '%Y-%m-%d')     AS due_date,
                amount_due,
                amount_paid,
                DATE_FORMAT(payment_date, '%Y-%m-%d') AS payment_date,
                status
            FROM loan_schedule
            WHERE loan_code = ?
            ORDER BY due_date ASC
        ");
        $stmt->bind_param('s', $loan['loan_code']);
    } else {
        $stmt = $conn->prepare("
            SELECT 
                schedule_id,
                DATE_FORMAT(due_date, '%Y-%m-%d')     AS due_date,
                amount_due,
                amount_paid,
                DATE_FORMAT(payment_date, '%Y-%m-%d') AS payment_date,
                status
            FROM loan_schedule
            WHERE loan_id = ?
            ORDER BY due_date ASC
        ");
        $stmt->bind_param('i', $loan['loan_id']);
    }
    $stmt->execute();

    $schedules = [];
    $result    = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success'   => true,
        'loan'      => $loan,
        'schedules' => $schedules
    ], JSON_UNESCAPED_UNICODE);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
    error_log('loan_crud.php MySQLi Error: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    error_log('loan_crud.php Error: ' . $e->getMessage());
}