<?php
/**
 * ============================================================
 * Financial Team API Endpoint
 * Path: /api/financial/disbursements.php
 *
 * Usage:
 *   GET  → Kumuha ng disbursement records (requires API key)
 *   POST → Tanggapin ang disbursement data mula sa Core2 (API key optional if allowInternalPost=true)
 * ============================================================
 */

while (@ob_end_clean());
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * IMPORTANT:
 * This file is on CORE1 domain.
 * Make sure this require points to CORE1 initialize, not Core2.
 * If Core1 uses a different initialize file, change it here.
 */
require_once(__DIR__ . '/../../initialize_coreT2.php'); // <-- change if needed

// ─── ENSURE TABLES EXIST ──────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS financial_api_keys (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        api_key     VARCHAR(64) NOT NULL UNIQUE,
        label       VARCHAR(100) DEFAULT 'Financial Team',
        is_active   TINYINT(1) DEFAULT 1,
        created_by  INT DEFAULT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at  DATETIME DEFAULT NULL,
        last_used   DATETIME DEFAULT NULL
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS financial_disbursement_log (
        log_id            INT AUTO_INCREMENT PRIMARY KEY,
        disbursement_id   INT NOT NULL,
        loan_id           VARCHAR(50),
        loan_code         VARCHAR(50),
        member_name       VARCHAR(100),
        amount            DECIMAL(12,2),
        fund_source       VARCHAR(100),
        disbursement_date DATE,
        status            VARCHAR(50),
        remarks           TEXT,
        sent_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        api_key_label     VARCHAR(100),
        INDEX idx_loan_code (loan_code),
        INDEX idx_disbursement_id (disbursement_id)
    )
");

// ─── API KEY READ ─────────────────────────────────────────────
$apiKey = $_SERVER['HTTP_X_API_KEY']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? ($_GET['api_key'] ?? '');

$apiKey = str_replace('Bearer ', '', $apiKey);
$apiKey = trim($apiKey);

// ═══════════════════════════════════════════════════════════════
// Allow POST without API key for Core2 internal sync
// Set to false if you want to enforce API key for POST too
// ═══════════════════════════════════════════════════════════════
$allowInternalPost = true;

$keyRow = null;

// If POST + internal allowed + no key, skip validation
if (!($_SERVER['REQUEST_METHOD'] === 'POST' && $allowInternalPost && empty($apiKey))) {

    // Require API key
    if (empty($apiKey)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error'   => 'API key required',
            'hint'    => 'Pass key via header: X-API-Key: YOUR_KEY or ?api_key=YOUR_KEY'
        ]);
        exit;
    }

    // Validate API key
    $keyStmt = $conn->prepare("
        SELECT ak.*, u.full_name as owner_name
        FROM financial_api_keys ak
        LEFT JOIN users u ON ak.created_by = u.user_id
        WHERE ak.api_key = ?
          AND ak.is_active = 1
          AND (ak.expires_at IS NULL OR ak.expires_at > NOW())
        LIMIT 1
    ");

    if (!$keyStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error preparing key validation']);
        exit;
    }

    $keyStmt->bind_param('s', $apiKey);
    $keyStmt->execute();
    $keyRow = $keyStmt->get_result()->fetch_assoc();
    $keyStmt->close();

    if (!$keyRow) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired API key']);
        exit;
    }

    // Update last used safely
    $upd = $conn->prepare("UPDATE financial_api_keys SET last_used = NOW() WHERE api_key = ? LIMIT 1");
    if ($upd) {
        $upd->bind_param('s', $apiKey);
        $upd->execute();
        $upd->close();
    }
}

// ─── HANDLE POST (receive from Core2) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
        exit;
    }

    // Accept formats:
    // 1) { disbursements: [...] }
    // 2) [ {...}, {...} ]
    // 3) single record { ... }
    if (isset($data['disbursements']) && is_array($data['disbursements'])) {
        $records = $data['disbursements'];
    } elseif (isset($data[0])) {
        $records = $data;
    } else {
        $records = [$data];
    }

    $received = 0;
    $updated  = 0;
    $errors   = [];
    $keyLabel = $keyRow['label'] ?? 'Internal Core2';

    foreach ($records as $r) {
        try {
            $disbId   = intval($r['disbursement_id'] ?? 0);
            $loanId   = (string)($r['loan_id'] ?? '');
            $loanCode = (string)($r['loan_code'] ?? '');
            $member   = (string)($r['member_name'] ?? '');
            $amount   = floatval($r['amount'] ?? 0);
            $fund     = (string)($r['fund_source'] ?? '');
            $date     = (string)($r['disbursement_date'] ?? date('Y-m-d'));
            $status   = (string)($r['status'] ?? 'Pending');
            $remarks  = (string)($r['remarks'] ?? '');

            if ($disbId <= 0) {
                $errors[] = "Missing/invalid disbursement_id";
                continue;
            }

            // Check exists
            $checkStmt = $conn->prepare("
                SELECT log_id FROM financial_disbursement_log
                WHERE disbursement_id = ?
                LIMIT 1
            ");

            if (!$checkStmt) {
                $errors[] = "DB prepare failed (check): " . $conn->error;
                continue;
            }

            $checkStmt->bind_param('i', $disbId);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->num_rows > 0;
            $checkStmt->close();

            if ($exists) {
                $updateStmt = $conn->prepare("
                    UPDATE financial_disbursement_log SET
                        loan_id = ?,
                        loan_code = ?,
                        member_name = ?,
                        amount = ?,
                        fund_source = ?,
                        disbursement_date = ?,
                        status = ?,
                        remarks = ?,
                        api_key_label = ?,
                        sent_at = NOW()
                    WHERE disbursement_id = ?
                ");

                if (!$updateStmt) {
                    $errors[] = "DB prepare failed (update): " . $conn->error;
                    continue;
                }

                $updateStmt->bind_param(
                    'sssdsssssi',
                    $loanId, $loanCode, $member, $amount, $fund,
                    $date, $status, $remarks, $keyLabel, $disbId
                );

                if ($updateStmt->execute()) $updated++;
                else $errors[] = "Update failed for disbursement_id {$disbId}: " . $updateStmt->error;

                $updateStmt->close();

            } else {
                $insertStmt = $conn->prepare("
                    INSERT INTO financial_disbursement_log
                        (disbursement_id, loan_id, loan_code, member_name, amount, fund_source,
                         disbursement_date, status, remarks, api_key_label)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if (!$insertStmt) {
                    $errors[] = "DB prepare failed (insert): " . $conn->error;
                    continue;
                }

                $insertStmt->bind_param(
                    'isssdsssss',
                    $disbId, $loanId, $loanCode, $member, $amount, $fund,
                    $date, $status, $remarks, $keyLabel
                );

                if ($insertStmt->execute()) $received++;
                else $errors[] = "Insert failed for disbursement_id {$disbId}: " . $insertStmt->error;

                $insertStmt->close();
            }

            // Optional loan update
            if (!empty($loanCode) && $status === 'Released') {
                $updateLoanStmt = $conn->prepare("
                    UPDATE loans
                    SET disbursement_status = 'disbursed',
                        disbursement_date = ?
                    WHERE loan_code = ?
                    LIMIT 1
                ");
                if ($updateLoanStmt) {
                    $updateLoanStmt->bind_param('ss', $date, $loanCode);
                    $updateLoanStmt->execute();
                    $updateLoanStmt->close();
                }
            }

        } catch (Exception $e) {
            $errors[] = "Exception: " . $e->getMessage();
        }
    }

    $totalProcessed = $received + $updated;
    $message = "Processed {$totalProcessed} record(s)";
    if ($received > 0 && $updated > 0) $message .= " ({$received} new, {$updated} updated)";
    elseif ($received > 0) $message .= " ({$received} new)";
    elseif ($updated > 0) $message .= " ({$updated} updated)";

    while (@ob_end_clean());
    echo json_encode([
        'success'  => true,
        'message'  => $message,
        'received' => $received,
        'updated'  => $updated,
        'total'    => $totalProcessed,
        'errors'   => $errors
    ]);
    exit;
}

// ─── HANDLE GET (financial queries) ────────────────────────────
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = max(1, min(100, intval($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$status = trim($_GET['status'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$search = trim($_GET['search'] ?? '');

$where  = ["1=1"];
$params = [];
$types  = '';

if (!empty($status)) {
    $where[]  = "d.status = ?";
    $params[] = $status;
    $types   .= 's';
}
if (!empty($from)) {
    $where[]  = "d.disbursement_date >= ?";
    $params[] = $from;
    $types   .= 's';
}
if (!empty($to)) {
    $where[]  = "d.disbursement_date <= ?";
    $params[] = $to;
    $types   .= 's';
}
if (!empty($search)) {
    $where[] = "(d.loan_id LIKE ? OR d.loan_code LIKE ? OR d.member_name LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types   .= 'sss';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);
$useLogTable = (isset($_GET['source']) && $_GET['source'] === 'log');

$totalRecords = 0;

if ($useLogTable) {
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM financial_disbursement_log d
        {$whereClause}
    ");
    if ($countStmt) {
        if (!empty($params)) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
    }

    $fetchStmt = $conn->prepare("
        SELECT
            d.log_id,
            d.disbursement_id,
            d.loan_id,
            d.loan_code,
            d.member_name,
            d.disbursement_date,
            d.amount,
            d.fund_source,
            d.status,
            d.remarks,
            d.api_key_label as source,
            d.sent_at as created_at
        FROM financial_disbursement_log d
        {$whereClause}
        ORDER BY d.sent_at DESC, d.log_id DESC
        LIMIT ? OFFSET ?
    ");

} else {
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM disbursements d
        LEFT JOIN members m ON d.member_id = m.member_id
        {$whereClause}
    ");
    if ($countStmt) {
        if (!empty($params)) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
    }

    $fetchStmt = $conn->prepare("
        SELECT
            d.disbursement_id,
            d.loan_id,
            COALESCE(m.full_name, 'N/A') AS member_name,
            d.disbursement_date,
            d.amount,
            d.fund_source,
            d.status,
            d.remarks,
            COALESCE(u.full_name, 'Admin') AS approved_by,
            d.created_at
        FROM disbursements d
        LEFT JOIN members m ON d.member_id  = m.member_id
        LEFT JOIN users   u ON d.approved_by = u.user_id
        {$whereClause}
        ORDER BY d.disbursement_date DESC, d.disbursement_id DESC
        LIMIT ? OFFSET ?
    ");
}

$disbursements = [];
if ($fetchStmt) {
    $fetchParams = array_merge($params, [$limit, $offset]);
    $fetchTypes  = $types . 'ii';
    $fetchStmt->bind_param($fetchTypes, ...$fetchParams);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $disbursements[] = $row;
    }
    $fetchStmt->close();
}

while (@ob_end_clean());
echo json_encode([
    'success'        => true,
    'requested_by'   => $keyRow ? (($keyRow['owner_name'] ?? $keyRow['label']) ?: $keyRow['label']) : 'Anonymous',
    'data_source'    => $useLogTable ? 'Received from Core2' : 'Local disbursements',
    'generated_at'   => date('Y-m-d H:i:s'),
    'total_records'  => $totalRecords,
    'current_page'   => $page,
    'total_pages'    => max(1, ceil($totalRecords / $limit)),
    'disbursements'  => $disbursements
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
