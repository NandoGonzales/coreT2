<?php
/**
 * ============================================================================
 * Penalty Calculator - Automatic Penalty Generation for Overdue Loans
 * ============================================================================
 * Path: /admin/Loan-Portfolio-Risk-Management/calculate_penalties.php
 * 
 * This script calculates penalties for overdue loan payments and inserts them
 * into the loan_penalties table.
 * 
 * Can be run:
 * 1. Manually via browser: /admin/Loan-Portfolio-Risk-Management/calculate_penalties.php
 * 2. Via AJAX call from UI button
 * 3. Via cron job (scheduled daily)
 * ============================================================================
 */

require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'message' => '',
    'penalties_calculated' => 0,
    'total_penalty_amount' => 0,
    'loans_affected' => [],
    'errors' => []
];

try {
    $conn->begin_transaction();
    
    // ────────────────────────────────────────────────────────────
    // STEP 1: Check if penalty columns exist in loan_portfolio
    // ────────────────────────────────────────────────────────────
    $check_penalty_rate = $conn->query("SHOW COLUMNS FROM loan_portfolio LIKE 'penalty_rate'");
    $check_late_fee = $conn->query("SHOW COLUMNS FROM loan_portfolio LIKE 'late_fee'");
    $check_grace = $conn->query("SHOW COLUMNS FROM loan_portfolio LIKE 'grace_period_days'");
    $check_payment_num = $conn->query("SHOW COLUMNS FROM loan_penalties LIKE 'payment_number'");
    
    $columns_missing = [];
    
    if ($check_penalty_rate->num_rows === 0) {
        $conn->query("ALTER TABLE loan_portfolio ADD COLUMN penalty_rate DECIMAL(5,2) DEFAULT 2.00 COMMENT 'Penalty % of overdue amount'");
        $columns_missing[] = 'penalty_rate';
    }
    
    if ($check_late_fee->num_rows === 0) {
        $conn->query("ALTER TABLE loan_portfolio ADD COLUMN late_fee DECIMAL(10,2) DEFAULT 50.00 COMMENT 'Fixed late fee per overdue payment'");
        $columns_missing[] = 'late_fee';
    }
    
    if ($check_grace->num_rows === 0) {
        $conn->query("ALTER TABLE loan_portfolio ADD COLUMN grace_period_days INT DEFAULT 0 COMMENT 'Days before penalty starts'");
        $columns_missing[] = 'grace_period_days';
    }
    
    if ($check_payment_num->num_rows === 0) {
        $conn->query("ALTER TABLE loan_penalties ADD COLUMN payment_number INT NULL AFTER loan_code");
        $columns_missing[] = 'payment_number (in loan_penalties)';
    }
    
    if (!empty($columns_missing)) {
        $response['success'] = true;
        $response['message'] = 'Database updated! Added columns: ' . implode(', ', $columns_missing) . '. Please run the script again.';
        $response['columns_added'] = $columns_missing;
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ────────────────────────────────────────────────────────────
    // STEP 2: Find all overdue payments
    // ────────────────────────────────────────────────────────────
    $sql = "
        SELECT 
            ls.schedule_id,
            ls.loan_code,
            ls.loan_id,
            ls.payment_number,
            ls.due_date,
            ls.amount_due,
            ls.amount_paid,
            lp.penalty_rate,
            lp.late_fee,
            lp.grace_period_days,
            lp.loan_type,
            COALESCE(m.full_name, 'Unknown') as member_name,
            DATEDIFF(CURDATE(), ls.due_date) as days_overdue
        FROM loan_schedule ls
        INNER JOIN loan_portfolio lp ON ls.loan_code = lp.loan_code
        LEFT JOIN members m ON lp.member_id = m.member_id
        WHERE ls.status IN ('Overdue', 'Pending')
        AND ls.due_date < CURDATE()
        AND ls.amount_paid < ls.amount_due
        AND DATEDIFF(CURDATE(), ls.due_date) > COALESCE(lp.grace_period_days, 0)
        ORDER BY lp.loan_code, ls.payment_number
    ";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $penalties_calculated = 0;
    $total_penalty_amount = 0;
    $processed_loans = [];
    
    // ────────────────────────────────────────────────────────────
    // STEP 3: Calculate and insert penalties
    // ────────────────────────────────────────────────────────────
    while ($row = $result->fetch_assoc()) {
        $loan_code = $row['loan_code'];
        $days_overdue = (int)$row['days_overdue'];
        $amount_due = (float)$row['amount_due'];
        $amount_paid = (float)$row['amount_paid'];
        $penalty_rate = (float)$row['penalty_rate'];
        $late_fee = (float)$row['late_fee'];
        $payment_number = $row['payment_number'];
        
        // Calculate outstanding balance
        $outstanding = $amount_due - $amount_paid;
        
        if ($outstanding <= 0) {
            continue; // Skip if already paid
        }
        
        // ─── PENALTY CALCULATION ───
        // Option 1: Percentage of outstanding amount
        $percentage_penalty = $outstanding * ($penalty_rate / 100);
        
        // Option 2: Fixed late fee (one-time charge)
        // Only charge late fee once per payment (not daily)
        $fixed_fee = $late_fee;
        
        // Total penalty for this payment
        $total_penalty = $fixed_fee + $percentage_penalty;
        
        if ($total_penalty <= 0) {
            continue; // Skip if no penalty configured
        }
        
        // ─── CHECK IF PENALTY ALREADY EXISTS ───
        // We check if penalty was already added TODAY for this specific payment
        $check = $conn->prepare("
            SELECT id, penalty_amount 
            FROM loan_penalties 
            WHERE loan_code = ? 
            AND payment_number = ?
            AND DATE(calculated_at) = CURDATE()
        ");
        $check->bind_param('si', $loan_code, $payment_number);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();
        
        if ($existing) {
            // Penalty already calculated today, skip
            continue;
        }
        
        // ─── INSERT NEW PENALTY ───
        $insert = $conn->prepare("
            INSERT INTO loan_penalties 
            (loan_code, payment_number, penalty_amount, days_overdue, calculated_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        if (!$insert) {
            $response['errors'][] = "Failed to prepare insert for {$loan_code} payment {$payment_number}: " . $conn->error;
            continue;
        }
        
        $insert->bind_param('sidi', $loan_code, $payment_number, $total_penalty, $days_overdue);
        
        if ($insert->execute()) {
            $penalties_calculated++;
            $total_penalty_amount += $total_penalty;
            
            // Track which loans were affected
            if (!isset($processed_loans[$loan_code])) {
                $processed_loans[$loan_code] = [
                    'loan_code' => $loan_code,
                    'member' => $row['member_name'],
                    'loan_type' => $row['loan_type'],
                    'penalties' => 0,
                    'penalty_amount' => 0
                ];
            }
            
            $processed_loans[$loan_code]['penalties']++;
            $processed_loans[$loan_code]['penalty_amount'] += $total_penalty;
            
        } else {
            $response['errors'][] = "Failed to insert penalty for {$loan_code} payment {$payment_number}: " . $insert->error;
        }
        
        $insert->close();
    }
    
    // ────────────────────────────────────────────────────────────
    // STEP 4: Update loan_schedule status to Overdue
    // ────────────────────────────────────────────────────────────
    $update_status = $conn->query("
        UPDATE loan_schedule 
        SET status = 'Overdue' 
        WHERE due_date < CURDATE() 
        AND amount_paid < amount_due 
        AND status = 'Pending'
    ");
    
    // ────────────────────────────────────────────────────────────
    // STEP 5: Commit transaction
    // ────────────────────────────────────────────────────────────
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = "Successfully calculated {$penalties_calculated} penalties totaling ₱" . number_format($total_penalty_amount, 2);
    $response['penalties_calculated'] = $penalties_calculated;
    $response['total_penalty_amount'] = round($total_penalty_amount, 2);
    $response['loans_affected'] = array_values($processed_loans);
    
    // Add summary info
    if ($penalties_calculated === 0) {
        $response['message'] = 'No new penalties to calculate. All overdue payments already have penalties for today.';
    }
    
} catch (Exception $e) {
    $conn->rollback();
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
    $response['errors'][] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;