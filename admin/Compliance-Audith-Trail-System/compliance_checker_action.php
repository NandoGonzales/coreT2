<?php
/**
 * Compliance Checker Action Handler
 * Path: admin/Compliance-Audith-Trail-System/compliance_checker_action.php
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

// Auto-create checklist table
$conn->query("
    CREATE TABLE IF NOT EXISTS loan_compliance_checklist (
        checklist_id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        member_id INT NOT NULL,
        complete_documents TINYINT(1) DEFAULT 0,
        valid_id TINYINT(1) DEFAULT 0,
        ci_completed TINYINT(1) DEFAULT 0,
        approved_loan TINYINT(1) DEFAULT 0,
        disbursement_record TINYINT(1) DEFAULT 0,
        payment_records TINYINT(1) DEFAULT 0,
        compliance_status ENUM('Compliant','For Verification','Incomplete') DEFAULT 'Incomplete',
        checked_score INT DEFAULT 0 COMMENT '0-6 items checked',
        notes TEXT DEFAULT NULL,
        last_checked_by INT DEFAULT NULL,
        last_checked_by_name VARCHAR(100) DEFAULT NULL,
        last_checked_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT NOW(),
        UNIQUE KEY unique_loan (loan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

function computeStatus(int $score): string {
    if ($score >= 6) return 'Compliant';
    if ($score >= 3) return 'For Verification';
    return 'Incomplete';
}

try {
    $raw    = file_get_contents('php://input');
    $body   = json_decode($raw ?: '{}', true) ?: [];
    $action = trim($body['action'] ?? $_GET['action'] ?? 'list');

    // ── LIST loans with compliance status ──────────────────────────
    if ($action === 'list') {
        $search  = trim($_GET['search'] ?? '');
        $status  = trim($_GET['status'] ?? '');
        $limit   = min(100, max(10, (int)($_GET['limit'] ?? 20)));
        $offset  = max(0, (int)($_GET['offset'] ?? 0));

        $where = ['1=1']; $params = []; $types = '';

        if ($search) {
            $where[] = '(m.full_name LIKE ? OR lp.loan_code LIKE ?)';
            $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'ss';
        }
        if ($status) {
            $where[] = "COALESCE(cc.compliance_status, 'Incomplete') = ?";
            $params[] = $status; $types .= 's';
        }

        $whereSQL = implode(' AND ', $where);

        // Count
        $cntStmt = $conn->prepare("
            SELECT COUNT(*) AS c
            FROM loan_portfolio lp
            LEFT JOIN members m ON lp.member_id = m.member_id
            LEFT JOIN loan_compliance_checklist cc ON lp.loan_id = cc.loan_id
            WHERE $whereSQL
        ");
        if ($types) $cntStmt->bind_param($types, ...$params);
        $cntStmt->execute();
        $total = (int)$cntStmt->get_result()->fetch_assoc()['c'];
        $cntStmt->close();

        // Records
        $allParams = array_merge($params, [$limit, $offset]);
        $allTypes  = $types . 'ii';

        $stmt = $conn->prepare("
            SELECT
                lp.loan_id, lp.loan_code, lp.status AS loan_status,
                lp.principal_amount, lp.start_date, lp.end_date,
                m.member_id, m.full_name, m.email,
                COALESCE(cc.complete_documents,  0) AS complete_documents,
                COALESCE(cc.valid_id,            0) AS valid_id,
                COALESCE(cc.ci_completed,        0) AS ci_completed,
                COALESCE(cc.approved_loan,       0) AS approved_loan,
                COALESCE(cc.disbursement_record, 0) AS disbursement_record,
                COALESCE(cc.payment_records,     0) AS payment_records,
                COALESCE(cc.compliance_status, 'Incomplete') AS compliance_status,
                COALESCE(cc.checked_score, 0) AS checked_score,
                cc.notes, cc.last_checked_by_name, cc.last_checked_at
            FROM loan_portfolio lp
            LEFT JOIN members m ON lp.member_id = m.member_id
            LEFT JOIN loan_compliance_checklist cc ON lp.loan_id = cc.loan_id
            WHERE $whereSQL
            ORDER BY
                FIELD(COALESCE(cc.compliance_status,'Incomplete'), 'Incomplete','For Verification','Compliant'),
                lp.loan_id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();

        // Stats
        $stats = $conn->query("
            SELECT
                COUNT(DISTINCT lp.loan_id) AS total_loans,
                SUM(COALESCE(cc.compliance_status,'Incomplete') = 'Compliant') AS compliant,
                SUM(COALESCE(cc.compliance_status,'Incomplete') = 'For Verification') AS for_verification,
                SUM(COALESCE(cc.compliance_status,'Incomplete') = 'Incomplete') AS incomplete
            FROM loan_portfolio lp
            LEFT JOIN loan_compliance_checklist cc ON lp.loan_id = cc.loan_id
        ")->fetch_assoc();

        echo json_encode(['success' => true, 'records' => $rows, 'total' => $total, 'stats' => $stats]);
        exit;
    }

    // ── AUTO-CHECK: auto-detect from DB ────────────────────────────
    if ($action === 'auto_check') {
        $loanId = (int)($body['loan_id'] ?? 0);
        if (!$loanId) throw new Exception('loan_id required');

        // Get loan info
        $loan = $conn->query("
            SELECT lp.*, m.full_name, m.contact_no, m.address, m.birth_date, m.email
            FROM loan_portfolio lp
            LEFT JOIN members m ON lp.member_id = m.member_id
            WHERE lp.loan_id = $loanId LIMIT 1
        ")->fetch_assoc();

        if (!$loan) throw new Exception('Loan not found');

        $memberId = (int)$loan['member_id'];

        // 1. Complete Documents — member has name, address, contact, birth_date, email
        $completeDocs = (!empty($loan['full_name']) && !empty($loan['address'])
                      && !empty($loan['contact_no']) && !empty($loan['birth_date'])
                      && !empty($loan['email'])) ? 1 : 0;

        // 2. Valid ID — member has email_verified or member_code assigned
        $validId = (!empty($loan['email'])) ? 1 : 0;

        // 3. CI Completed — check audit_trail for CI record
        $ciRow = $conn->query("
            SELECT audit_id FROM audit_trail
            WHERE record_id = $memberId
              AND (module_name LIKE '%Credit%' OR module_name LIKE '%CI%' OR remarks LIKE '%credit investigation%')
            LIMIT 1
        ")->fetch_assoc();
        $ciCompleted = $ciRow ? 1 : 0;

        // 4. Approved Loan — loan status is Active or Approved or Completed
        $approvedLoan = in_array($loan['loan_status'] ?? $loan['status'], ['Active','Approved','Completed','Disbursed']) ? 1 : 0;

        // 5. Disbursement Record — has disbursement entry
        $disbRow = $conn->query("
            SELECT disbursement_id FROM disbursements WHERE loan_id = $loanId LIMIT 1
        ")->fetch_assoc();
        $disbRecord = $disbRow ? 1 : 0;

        // 6. Payment Records — has at least one repayment
        $payRow = $conn->query("
            SELECT repayment_id FROM repayments WHERE loan_id = $loanId LIMIT 1
        ")->fetch_assoc();
        $payRecords = $payRow ? 1 : 0;

        $score  = $completeDocs + $validId + $ciCompleted + $approvedLoan + $disbRecord + $payRecords;
        $status = computeStatus($score);

        echo json_encode([
            'success'             => true,
            'auto_detected'       => true,
            'complete_documents'  => $completeDocs,
            'valid_id'            => $validId,
            'ci_completed'        => $ciCompleted,
            'approved_loan'       => $approvedLoan,
            'disbursement_record' => $disbRecord,
            'payment_records'     => $payRecords,
            'checked_score'       => $score,
            'compliance_status'   => $status,
        ]);
        exit;
    }

    // ── SAVE checklist ──────────────────────────────────────────────
    if ($action === 'save') {
        $loanId   = (int)($body['loan_id'] ?? 0);
        $memberId = (int)($body['member_id'] ?? 0);
        if (!$loanId || !$memberId) throw new Exception('loan_id and member_id required');

        $completeDocs = (int)(!empty($body['complete_documents']));
        $validId      = (int)(!empty($body['valid_id']));
        $ciCompleted  = (int)(!empty($body['ci_completed']));
        $approvedLoan = (int)(!empty($body['approved_loan']));
        $disbRecord   = (int)(!empty($body['disbursement_record']));
        $payRecords   = (int)(!empty($body['payment_records']));
        $notes        = trim($body['notes'] ?? '');

        $score  = $completeDocs + $validId + $ciCompleted + $approvedLoan + $disbRecord + $payRecords;
        $status = computeStatus($score);

        $stmt = $conn->prepare("
            INSERT INTO loan_compliance_checklist
                (loan_id, member_id, complete_documents, valid_id, ci_completed,
                 approved_loan, disbursement_record, payment_records,
                 compliance_status, checked_score, notes,
                 last_checked_by, last_checked_by_name, last_checked_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE
                complete_documents  = VALUES(complete_documents),
                valid_id            = VALUES(valid_id),
                ci_completed        = VALUES(ci_completed),
                approved_loan       = VALUES(approved_loan),
                disbursement_record = VALUES(disbursement_record),
                payment_records     = VALUES(payment_records),
                compliance_status   = VALUES(compliance_status),
                checked_score       = VALUES(checked_score),
                notes               = VALUES(notes),
                last_checked_by     = VALUES(last_checked_by),
                last_checked_by_name= VALUES(last_checked_by_name),
                last_checked_at     = NOW()
        ");

        $stmt->bind_param('iiiiiiiiisisi',
            $loanId, $memberId,
            $completeDocs, $validId, $ciCompleted,
            $approvedLoan, $disbRecord, $payRecords,
            $status, $score, $notes,
            $userId, $userName
        );
        $stmt->execute();
        $stmt->close();

        // Also log to compliance_logs
        $desc = "Loan {$loanId} compliance checked: {$status} ({$score}/6 items)";
        $dbStatus = $status === 'Compliant' ? 'Compliant' :
                   ($status === 'For Verification' ? 'Under Review' : 'Non-Compliant');

        $logStmt = $conn->prepare("
            INSERT INTO compliance_logs (description, compliance_status, review_date)
            VALUES (?, ?, CURDATE())
        ");
        $logStmt->bind_param('ss', $desc, $dbStatus);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode([
            'success'           => true,
            'message'           => "Compliance saved! Status: {$status}",
            'compliance_status' => $status,
            'checked_score'     => $score,
        ]);
        exit;
    }

    // ── SYNC ALL — auto-check all loans ────────────────────────────
    if ($action === 'sync_all') {
        set_time_limit(120);
        $loans = [];
        $res   = $conn->query("SELECT loan_id, member_id FROM loan_portfolio ORDER BY loan_id DESC LIMIT 200");
        while ($r = $res->fetch_assoc()) $loans[] = $r;

        $synced = 0;
        foreach ($loans as $loan) {
            $loanId   = (int)$loan['loan_id'];
            $memberId = (int)$loan['member_id'];

            // Auto-detect each item
            $loanRow = $conn->query("
                SELECT lp.status, lp.loan_id,
                       m.full_name, m.address, m.contact_no, m.birth_date, m.email
                FROM loan_portfolio lp
                LEFT JOIN members m ON lp.member_id = m.member_id
                WHERE lp.loan_id = $loanId LIMIT 1
            ")->fetch_assoc();

            if (!$loanRow) continue;

            $completeDocs = (!empty($loanRow['full_name']) && !empty($loanRow['address'])
                          && !empty($loanRow['contact_no']) && !empty($loanRow['birth_date'])
                          && !empty($loanRow['email'])) ? 1 : 0;
            $validId      = (!empty($loanRow['email'])) ? 1 : 0;
            $ciRow        = $conn->query("SELECT audit_id FROM audit_trail WHERE record_id = $memberId AND (module_name LIKE '%Credit%' OR module_name LIKE '%CI%') LIMIT 1")->fetch_assoc();
            $ciCompleted  = $ciRow ? 1 : 0;
            $approvedLoan = in_array($loanRow['status'], ['Active','Approved','Completed','Disbursed']) ? 1 : 0;
            $disbRow      = $conn->query("SELECT disbursement_id FROM disbursements WHERE loan_id = $loanId LIMIT 1")->fetch_assoc();
            $disbRecord   = $disbRow ? 1 : 0;
            $payRow       = $conn->query("SELECT repayment_id FROM repayments WHERE loan_id = $loanId LIMIT 1")->fetch_assoc();
            $payRecords   = $payRow ? 1 : 0;

            $score  = $completeDocs + $validId + $ciCompleted + $approvedLoan + $disbRecord + $payRecords;
            $status = computeStatus($score);

            $stmt = $conn->prepare("
                INSERT INTO loan_compliance_checklist
                    (loan_id, member_id, complete_documents, valid_id, ci_completed,
                     approved_loan, disbursement_record, payment_records,
                     compliance_status, checked_score,
                     last_checked_by, last_checked_by_name, last_checked_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE
                    complete_documents=VALUES(complete_documents), valid_id=VALUES(valid_id),
                    ci_completed=VALUES(ci_completed), approved_loan=VALUES(approved_loan),
                    disbursement_record=VALUES(disbursement_record), payment_records=VALUES(payment_records),
                    compliance_status=VALUES(compliance_status), checked_score=VALUES(checked_score),
                    last_checked_by=VALUES(last_checked_by), last_checked_by_name=VALUES(last_checked_by_name),
                    last_checked_at=NOW()
            ");
            $stmt->bind_param('iiiiiiiisisi',
                $loanId, $memberId,
                $completeDocs, $validId, $ciCompleted, $approvedLoan, $disbRecord, $payRecords,
                $status, $score, $userId, $userName
            );
            $stmt->execute();
            $stmt->close();
            $synced++;
        }

        echo json_encode(['success' => true, 'message' => "Synced {$synced} loans.", 'synced' => $synced]);
        exit;
    }

    throw new Exception('Invalid action: ' . $action);

} catch (Exception $e) {
    error_log("compliance_checker_action.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}