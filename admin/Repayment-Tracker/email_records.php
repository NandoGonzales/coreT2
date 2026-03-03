<?php
/**
 * email_records.php
 * Returns email notification records from ai_message_logs
 */
declare(strict_types=1);
ob_start();
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');

function jsonOut(array $payload): void {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['userdata']['user_id'])) {
    jsonOut(['success' => false, 'message' => 'Unauthorized']);
}

try {
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    // ── Single record view ──────────────────────────────────
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $stmt = $conn->prepare("
            SELECT a.*, m.full_name AS member_name, m.email AS member_email,
                   u.full_name AS sent_by_name
            FROM ai_message_logs a
            LEFT JOIN members m ON a.member_id = m.member_id
            LEFT JOIN users u ON u.user_id = ?
            WHERE a.message_id = ?
            LIMIT 1
        ");
        $uid = (int)$_SESSION['userdata']['user_id'];
        $stmt->bind_param('ii', $uid, $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        jsonOut(['record' => $row]);
    }

    // ── List all records ────────────────────────────────────
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    $stmt = $conn->prepare("
        SELECT
            a.message_id,
            a.loan_id,
            a.message_type,
            a.status,
            a.sent_at,
            a.error_message,
            m.full_name  AS member_name,
            m.email      AS member_email,
            u.full_name  AS sent_by_name,
            lp.loan_code
        FROM ai_message_logs a
        LEFT JOIN members m  ON a.member_id  = m.member_id
        LEFT JOIN loan_portfolio lp ON a.loan_id = lp.loan_id
        LEFT JOIN users u ON a.loan_id = u.user_id
        ORDER BY a.sent_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result  = $stmt->get_result();
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = [
            'message_id'   => $row['message_id'],
            'loan_id'      => $row['loan_id'],
            'loan_code'    => $row['loan_code'] ?? null,
            'member_name'  => $row['member_name'] ?? '—',
            'member_email' => $row['member_email'] ?? '',
            'message_type' => $row['message_type'],
            'status'       => $row['status'],
            'sent_at'      => $row['sent_at']
                ? date('M d, Y h:i A', strtotime($row['sent_at']))
                : '—',
            'sent_by_name' => $row['sent_by_name'] ?? 'System',
            'error_message'=> $row['error_message'] ?? null,
        ];
    }
    $stmt->close();

    // Total count
    $total = (int)$conn->query("SELECT COUNT(*) AS c FROM ai_message_logs")->fetch_assoc()['c'];

    jsonOut(['success' => true, 'records' => $records, 'total' => $total]);

} catch (Throwable $e) {
    jsonOut(['success' => false, 'message' => $e->getMessage()]);
}