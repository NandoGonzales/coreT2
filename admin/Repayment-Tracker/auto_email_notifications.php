<?php
/**
 * auto_email_notifications.php
 * Automatic email notifications — run via cron every day
 * Cron: 0 8 * * * php /path/to/auto_email_notifications.php
 *
 * Also callable via AJAX: GET ?action=run&dry_run=1 (preview)
 */
declare(strict_types=1);
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Manila');

require_once(__DIR__ . '/../../initialize_coreT2.php');

// Load PHPMailer
$autoloadPaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
foreach ($autoloadPaths as $p) {
    if (file_exists($p)) { require_once $p; break; }
}

// Load AI Message Generator
if (file_exists(__DIR__ . '/../../classes/AIMessageGenerator.php')) {
    require_once __DIR__ . '/../../classes/AIMessageGenerator.php';
}

function jsonOut(array $d): void {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Check if called via browser (AJAX)
$isAjax  = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_GET['action']);
$dryRun  = !empty($_GET['dry_run']);

if ($isAjax) {
    // Validate session for browser access
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['userdata']['user_id'])) {
        jsonOut(['success' => false, 'message' => 'Unauthorized']);
    }
}

try {
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    // Get SMTP settings
    $settingsQuery = $conn->query("SELECT setting_key, setting_value FROM notification_settings");
    $smtp = [];
    if ($settingsQuery) {
        while ($row = $settingsQuery->fetch_assoc()) {
            $smtp[$row['setting_key']] = $row['setting_value'];
        }
    }

    $requiredSmtp = ['smtp_host','smtp_username','smtp_password','smtp_port','smtp_from_email','smtp_from_name'];
    foreach ($requiredSmtp as $k) {
        if (empty($smtp[$k])) throw new Exception("Missing SMTP setting: $k");
    }

    // Find loans needing notification:
    // 7 days before due, 3 days before, due today, overdue
    $stmt = $conn->prepare("
        SELECT
            lp.loan_id, lp.loan_code, lp.member_id, lp.loan_type,
            lp.principal_amount, lp.interest_rate, lp.loan_term,
            m.full_name AS member_name, m.email,
            ls.schedule_id, ls.due_date, ls.amount_due, ls.balance, ls.status AS payment_status,
            DATEDIFF(ls.due_date, CURDATE()) AS days_until_due
        FROM loan_portfolio lp
        JOIN members m ON lp.member_id = m.member_id
        JOIN loan_schedule ls ON lp.loan_id = ls.loan_id
        WHERE m.email IS NOT NULL AND m.email != ''
          AND ls.status IN ('Pending','Overdue')
          AND lp.status IN ('Active','Approved')
          AND DATEDIFF(ls.due_date, CURDATE()) IN (7, 3, 0)
             OR (ls.status = 'Overdue' AND lp.status IN ('Active','Approved'))
        ORDER BY ls.due_date ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $aiGenerator = class_exists('AIMessageGenerator') ? new AIMessageGenerator($conn) : null;
    $sent = 0; $skipped = 0; $failed = 0;
    $log  = [];

    while ($loan = $result->fetch_assoc()) {
        $days      = (int)$loan['days_until_due'];
        $loanId    = (int)$loan['loan_id'];
        $memberId  = (int)$loan['member_id'];
        $email     = $loan['email'];

        // Determine type
        if ($loan['payment_status'] === 'Overdue' || $days < 0) {
            $msgType = 'overdue';
        } elseif ($days === 0) {
            $msgType = 'due_today';
        } elseif ($days <= 3) {
            $msgType = '3_days_before';
        } else {
            $msgType = '7_days_before';
        }

        // Check if already sent today for this loan + type
        $chk = $conn->prepare("
            SELECT message_id FROM ai_message_logs
            WHERE loan_id=? AND message_type=? AND DATE(sent_at)=CURDATE()
            LIMIT 1
        ");
        $chk->bind_param('is', $loanId, $msgType);
        $chk->execute();
        $alreadySent = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($alreadySent) { $skipped++; continue; }

        if ($dryRun) {
            $log[] = ['loan_id'=>$loanId,'email'=>$email,'type'=>$msgType,'status'=>'dry_run'];
            $sent++;
            continue;
        }

        // Generate message
        $subject  = 'Payment Reminder — ' . ($loan['loan_code'] ?? 'Loan #'.$loanId);
        $body     = '';

        if ($aiGenerator) {
            $aiMsg = $aiGenerator->generateMessage($loan, $msgType);
            $subject = is_array($aiMsg) ? ($aiMsg['subject'] ?? $subject) : $subject;
            $body    = is_array($aiMsg) ? ($aiMsg['message'] ?? '') : (string)$aiMsg;
        }

        if (empty($body)) {
            // Fallback message
            $dueFormatted = date('F d, Y', strtotime($loan['due_date']));
            $amountDue    = '₱' . number_format($loan['amount_due'], 2);
            $body = $msgType === 'overdue'
                ? "Dear {$loan['member_name']},\n\nYour loan payment of {$amountDue} was due on {$dueFormatted} and is now OVERDUE. Please settle immediately to avoid penalties.\n\nThank you,\n{$smtp['smtp_from_name']}"
                : "Dear {$loan['member_name']},\n\nThis is a reminder that your loan payment of {$amountDue} is due on {$dueFormatted}.\n\nThank you,\n{$smtp['smtp_from_name']}";
        }

        // Send email
        try {
            if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
                throw new Exception('PHPMailer not available');
            }
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['smtp_username'];
            $mail->Password   = $smtp['smtp_password'];
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$smtp['smtp_port'];
            $mail->setFrom($smtp['smtp_from_email'], $smtp['smtp_from_name']);
            $mail->addAddress($email, $loan['member_name']);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
            $mail->AltBody = $body;
            $mail->send();

            // Log success
            $logStmt = $conn->prepare("INSERT INTO ai_message_logs (loan_id,member_id,message_type,message_content,ai_generated,sent_via,sent_at,status) VALUES (?,?,?,?,1,'email',NOW(),'sent')");
            $logStmt->bind_param('iiss', $loanId, $memberId, $msgType, $body);
            $logStmt->execute();
            $logStmt->close();

            $sent++;
            $log[] = ['loan_id'=>$loanId,'email'=>$email,'type'=>$msgType,'status'=>'sent'];

        } catch (Throwable $e) {
            // Log failure
            $err = $e->getMessage();
            $logStmt = $conn->prepare("INSERT INTO ai_message_logs (loan_id,member_id,message_type,message_content,ai_generated,sent_via,sent_at,status,error_message) VALUES (?,?,?,?,1,'email',NOW(),'failed',?)");
            $logStmt->bind_param('iisss', $loanId, $memberId, $msgType, $body, $err);
            $logStmt->execute();
            $logStmt->close();

            $failed++;
            $log[] = ['loan_id'=>$loanId,'email'=>$email,'type'=>$msgType,'status'=>'failed','error'=>$err];
        }
    }
    $stmt->close();

    $msg = "Auto-notification complete: {$sent} sent, {$skipped} skipped, {$failed} failed.";
    if ($dryRun) $msg = "[DRY RUN] Would send: {$sent} emails.";
    error_log("[AUTO EMAIL] $msg");

    jsonOut(['success'=>true,'sent'=>$sent,'skipped'=>$skipped,'failed'=>$failed,'message'=>$msg,'log'=>$log]);

} catch (Throwable $e) {
    error_log("[AUTO EMAIL ERROR] " . $e->getMessage());
    jsonOut(['success'=>false,'message'=>$e->getMessage()]);
}