<?php
/**
 * Centralized Real-Time Polling Endpoint
 * admin/inc/poll_new_records.php
 * 
 * Usage: fetch('/admin/inc/poll_new_records.php?module=loans&since=2026-03-05 10:00:00')
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['userdata'])) {
    echo json_encode(['count' => 0, 'records' => []]);
    exit;
}

$module = trim($_GET['module'] ?? '');
$since  = trim($_GET['since']  ?? '');

// Validate since — default to 60 seconds ago
if (!$since || !strtotime($since)) {
    $since = date('Y-m-d H:i:s', time() - 60);
}

$records = [];
$count   = 0;

try {
    switch ($module) {

        // ── Loan Applications ─────────────────────────────────
        case 'loans':
            $since_esc = $conn->real_escape_string($since);
            $res = $conn->query("
                SELECT la.app_id, la.app_code, la.status, la.created_at,
                       m.full_name AS member_name, la.principal_amount AS amount
                FROM loan_applications la
                LEFT JOIN members m ON m.member_id = la.member_id
                WHERE la.created_at > '$since_esc'
                ORDER BY la.created_at DESC LIMIT 10
            ");
            if ($res) while ($r = $res->fetch_assoc()) $records[] = $r;
            break;

        // ── Repayments ────────────────────────────────────────
        case 'repayments':
            $since_esc = $conn->real_escape_string($since);
            $res = $conn->query("
                SELECT r.repayment_id, r.amount, r.repayment_date, r.created_at,
                       m.full_name AS member_name, lp.loan_code
                FROM repayments r
                LEFT JOIN loan_portfolio lp ON lp.loan_id = r.loan_id
                LEFT JOIN members m ON m.member_id = lp.member_id
                WHERE r.created_at > '$since_esc'
                ORDER BY r.created_at DESC LIMIT 10
            ");
            if ($res) while ($r = $res->fetch_assoc()) $records[] = $r;
            break;

        // ── Savings Transactions ──────────────────────────────
        // Note: savings table has no created_at — use saving_id to detect new records
        case 'savings':
            $last_id = intval($_GET['last_id'] ?? 0);
            $res = $conn->query("
                SELECT s.saving_id, s.transaction_type, s.amount, s.balance,
                       s.transaction_date,
                       m.full_name AS member_name
                FROM savings s
                LEFT JOIN members m ON m.member_id = s.member_id
                WHERE s.saving_id > $last_id
                ORDER BY s.saving_id DESC LIMIT 10
            ");
            if ($res) while ($r = $res->fetch_assoc()) $records[] = $r;
            break;

        // ── User Approval Requests ────────────────────────────
        case 'approvals':
            $since_esc = $conn->real_escape_string($since);
            $res = $conn->query("
                SELECT ar.request_id, ar.request_type, ar.status, ar.created_at,
                       u.full_name AS member_name
                FROM approval_requests ar
                LEFT JOIN users u ON u.user_id = ar.user_id
                WHERE ar.created_at > '$since_esc'
                  AND ar.status = 'pending'
                ORDER BY ar.created_at DESC LIMIT 10
            ");
            if ($res) while ($r = $res->fetch_assoc()) $records[] = $r;
            break;
    }

    $count = count($records);
    echo json_encode([
        'count'   => $count,
        'records' => $records,
        'polled_at' => date('Y-m-d H:i:s')
    ]);

} catch (Throwable $e) {
    echo json_encode(['count' => 0, 'records' => [], 'error' => $e->getMessage()]);
}