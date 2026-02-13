<?php
/**
 * ============================================================
 * Financial Team API Endpoint
 * Path: /api/financial/disbursements.php
 * 
 * Ibibigay ang URL na ito sa Financial Team.
 * Kailangan nila ng API Key para ma-access ang data.
 * 
 * Usage:
 *   GET  → Kumuha ng disbursement records
 *   POST → Tanggapin ang disbursement data mula sa Core2
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

require_once(__DIR__ . '/../../initialize_coreT2.php');

// ─── API KEY VALIDATION ───────────────────────────────────────
// Kunin ang API key mula sa header o query param
$apiKey = $_SERVER['HTTP_X_API_KEY']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_GET['api_key']
    ?? '';

// Linisin ang "Bearer " prefix kung meron
$apiKey = str_replace('Bearer ', '', $apiKey);
$apiKey = trim($apiKey);

if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'API key required',
        'hint'    => 'Pass key via header: X-API-Key: YOUR_KEY or ?api_key=YOUR_KEY'
    ]);
    exit;
}

// I-validate ang API key sa database
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
    // Table wala pa — auto-create
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
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'API keys table just created. Please add a key first via /api/financial/manage_keys.php']);
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

// Update last used timestamp
$conn->query("UPDATE financial_api_keys SET last_used = NOW() WHERE api_key = '{$conn->real_escape_string($apiKey)}'");

// ─── HANDLE POST (receive from Core2 send_to_finance.php) ────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
        exit;
    }

    // I-log ang natanggap na data sa financial_disbursement_log table
    $conn->query("
        CREATE TABLE IF NOT EXISTS financial_disbursement_log (
            log_id          INT AUTO_INCREMENT PRIMARY KEY,
            disbursement_id INT NOT NULL,
            loan_id         VARCHAR(50),
            member_name     VARCHAR(100),
            amount          DECIMAL(12,2),
            fund_source     VARCHAR(100),
            disbursement_date DATE,
            status          VARCHAR(50),
            remarks         TEXT,
            sent_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            api_key_label   VARCHAR(100)
        )
    ");

    $records   = $data['disbursements'] ?? [$data];
    $received  = 0;
    $errors    = [];

    foreach ($records as $r) {
        $disbId   = intval($r['disbursement_id'] ?? 0);
        $loanId   = $conn->real_escape_string($r['loan_id'] ?? '');
        $member   = $conn->real_escape_string($r['member_name'] ?? '');
        $amount   = floatval($r['amount'] ?? 0);
        $fund     = $conn->real_escape_string($r['fund_source'] ?? '');
        $date     = $conn->real_escape_string($r['disbursement_date'] ?? date('Y-m-d'));
        $status   = $conn->real_escape_string($r['status'] ?? 'Pending');
        $remarks  = $conn->real_escape_string($r['remarks'] ?? '');
        $keyLabel = $conn->real_escape_string($keyRow['label']);

        $ins = $conn->query("
            INSERT INTO financial_disbursement_log
                (disbursement_id, loan_id, member_name, amount, fund_source,
                 disbursement_date, status, remarks, api_key_label)
            VALUES
                ($disbId, '$loanId', '$member', $amount, '$fund',
                 '$date', '$status', '$remarks', '$keyLabel')
        ");

        if ($ins) {
            $received++;
        } else {
            $errors[] = "Failed to log disbursement_id {$disbId}";
        }
    }

    echo json_encode([
        'success'  => true,
        'message'  => "Received {$received} disbursement record(s)",
        'received' => $received,
        'errors'   => $errors
    ]);
    exit;
}

// ─── HANDLE GET (financial team queries records) ──────────────
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = max(1, min(100, intval($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$status = trim($_GET['status'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');

$where  = ["1=1"];
$params = [];
$types  = '';

if (!empty($status)) {
    $where[] = "d.status = ?";
    $params[] = $status;
    $types   .= 's';
}
if (!empty($from)) {
    $where[] = "d.disbursement_date >= ?";
    $params[] = $from;
    $types   .= 's';
}
if (!empty($to)) {
    $where[] = "d.disbursement_date <= ?";
    $params[] = $to;
    $types   .= 's';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Count
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
} else {
    $totalRecords = 0;
}

// Fetch
$fetchStmt = $conn->prepare("
    SELECT
        d.disbursement_id,
        d.loan_id,
        COALESCE(m.full_name, 'N/A')  AS member_name,
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
    'requested_by'   => $keyRow['owner_name'] ?? $keyRow['label'],
    'generated_at'   => date('Y-m-d H:i:s'),
    'total_records'  => $totalRecords,
    'current_page'   => $page,
    'total_pages'    => max(1, ceil($totalRecords / $limit)),
    'disbursements'  => $disbursements
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);