<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Manila');

/**
 * This file supports:
 * - POST action=meta
 * - POST action=list
 * - POST action=get
 * - POST action=breakdown
 * - GET export=pdf|csv  (basic export support)
 *
 * Tables used based on your SQL:
 * - savings(saving_id, member_id, transaction_date, transaction_type, amount, balance, recorded_by)
 * - members(member_id, full_name)
 * - users(id, firstname, lastname)
 */

// ---- DB helper (mysqli preferred) ----
function db_is_mysqli() {
    return isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli;
}

function db_query_all($sql, $types = '', $params = []) {
    if (!db_is_mysqli()) {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $stmt = $GLOBALS['pdo']->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        throw new Exception('Database connection not found.');
    }

    $conn = $GLOBALS['conn'];
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

function db_query_one($sql, $types = '', $params = []) {
    $rows = db_query_all($sql, $types, $params);
    return $rows ? $rows[0] : null;
}

function as_money($v) {
    return number_format((float)$v, 2, '.', '');
}

function build_where(&$types, &$params, $input) {
    $where = " WHERE 1=1 ";

    // filter card
    if (!empty($input['filter'])) {
        if ($input['filter'] === 'deposit') {
            $where .= " AND s.transaction_type IN ('Deposit','Interest') ";
        } elseif ($input['filter'] === 'withdrawal') {
            $where .= " AND s.transaction_type = 'Withdrawal' ";
        }
    }

    // type
    if (!empty($input['type'])) {
        $where .= " AND s.transaction_type = ? ";
        $types .= "s";
        $params[] = $input['type'];
    }

    // member
    if (!empty($input['member_id'])) {
        $where .= " AND s.member_id = ? ";
        $types .= "i";
        $params[] = (int)$input['member_id'];
    }

    // recorded by
    if ($input['recorded_by'] !== '' && $input['recorded_by'] !== null && $input['recorded_by'] !== '0') {
        $where .= " AND s.recorded_by = ? ";
        $types .= "i";
        $params[] = (int)$input['recorded_by'];
    }

    // date range
    if (!empty($input['date_from'])) {
        $where .= " AND s.transaction_date >= ? ";
        $types .= "s";
        $params[] = $input['date_from'];
    }
    if (!empty($input['date_to'])) {
        $where .= " AND s.transaction_date <= ? ";
        $types .= "s";
        $params[] = $input['date_to'];
    }

    // search
    $search = isset($input['search']) ? trim($input['search']) : '';
    $searchBy = isset($input['search_by']) ? $input['search_by'] : 'auto';
    if ($search !== '') {
        if ($searchBy === 'member_id') {
            $where .= " AND s.member_id LIKE ? ";
            $types .= "s";
            $params[] = "%{$search}%";
        } elseif ($searchBy === 'transaction_type') {
            $where .= " AND s.transaction_type LIKE ? ";
            $types .= "s";
            $params[] = "%{$search}%";
        } elseif ($searchBy === 'transaction_date') {
            $where .= " AND s.transaction_date LIKE ? ";
            $types .= "s";
            $params[] = "%{$search}%";
        } elseif ($searchBy === 'recorded_by_name') {
            $where .= " AND (CASE WHEN s.recorded_by=0 THEN 'System' ELSE CONCAT(u.firstname,' ',u.lastname) END) LIKE ? ";
            $types .= "s";
            $params[] = "%{$search}%";
        } else {
            // auto
            $where .= " AND (
                CAST(s.member_id AS CHAR) LIKE ?
                OR s.transaction_type LIKE ?
                OR s.transaction_date LIKE ?
                OR (CASE WHEN s.recorded_by=0 THEN 'System' ELSE CONCAT(u.firstname,' ',u.lastname) END) LIKE ?
            ) ";
            $types .= "ssss";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
    }

    return $where;
}

// -------------------- EXPORT (GET) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['export'])) {
    $format = $_GET['export'];

    // reuse same filters used in list
    $input = [
        'filter' => $_GET['filter'] ?? 'all',
        'type' => $_GET['type'] ?? '',
        'member_id' => $_GET['member_id'] ?? '',
        'recorded_by' => $_GET['recorded_by'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'search' => $_GET['search'] ?? '',
        'search_by' => $_GET['search_by'] ?? 'auto'
    ];

    try {
        $types = '';
        $params = [];
        $where = build_where($types, $params, $input);

        $rows = db_query_all("
            SELECT
                s.saving_id, s.member_id, s.transaction_date, s.transaction_type,
                s.amount, s.balance,
                CASE
                    WHEN s.recorded_by = 0 THEN 'System'
                    ELSE CONCAT(u.firstname,' ',u.lastname)
                END AS recorded_by_name
            FROM savings s
            LEFT JOIN users u ON u.id = s.recorded_by
            $where
            ORDER BY s.transaction_date DESC, s.saving_id DESC
        ", $types, $params);

        if ($format === 'csv') {
            // create CSV inside ZIP to match your frontend expectation
            $csv = "Saving ID,Member ID,Date,Type,Amount,Balance,Recorded By\n";
            foreach ($rows as $r) {
                $csv .= "{$r['saving_id']},{$r['member_id']},{$r['transaction_date']},{$r['transaction_type']},{$r['amount']},{$r['balance']},\"{$r['recorded_by_name']}\"\n";
            }

            $zip = new ZipArchive();
            $tmpZip = tempnam(sys_get_temp_dir(), 'svzip_');
            $zip->open($tmpZip, ZipArchive::OVERWRITE);
            $zip->addFromString("savings_export_" . date('Y-m-d') . ".csv", $csv);
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="savings_export_' . date('Y-m-d') . '.zip"');
            readfile($tmpZip);
            @unlink($tmpZip);
            exit;
        }

        if ($format === 'pdf') {
            // very simple PDF (no external lib) - enough to open in PDF viewers
            $lines = [];
            $lines[] = "Savings Export - " . date('Y-m-d H:i:s');
            $lines[] = " ";
            foreach ($rows as $r) {
                $lines[] = "{$r['transaction_date']} | MID {$r['member_id']} | {$r['transaction_type']} | PHP " . as_money($r['amount']) . " | Bal " . as_money($r['balance']) . " | {$r['recorded_by_name']}";
            }

            $pdf = simple_pdf_from_lines($lines);

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="savings_export_' . date('Y-m-d') . '.pdf"');
            echo $pdf;
            exit;
        }

        echo json_encode(['status'=>'error','msg'=>'Invalid export format']);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','msg'=>$e->getMessage()]);
        exit;
    }
}

// -------------------- POST actions --------------------
$action = $_POST['action'] ?? '';

try {
    if ($action === 'meta') {
        $members = db_query_all("SELECT DISTINCT member_id FROM savings ORDER BY member_id ASC");
        $memberIds = array_map(fn($r) => $r['member_id'], $members);

        $users = db_query_all("
            SELECT id AS user_id, CONCAT(firstname,' ',lastname) AS full_name
            FROM users
            ORDER BY firstname ASC, lastname ASC
        ");

        echo json_encode([
            'status' => 'success',
            'members' => $memberIds,
            'recorded_by' => $users
        ]);
        exit;
    }

    if ($action === 'list') {
        $page = max(1, (int)($_POST['page'] ?? 1));
        $limit = max(1, (int)($_POST['limit'] ?? 10));
        $offset = ($page - 1) * $limit;

        $input = [
            'filter' => $_POST['filter'] ?? 'all',
            'type' => $_POST['type'] ?? '',
            'member_id' => $_POST['member_id'] ?? '',
            'recorded_by' => $_POST['recorded_by'] ?? '',
            'date_from' => $_POST['date_from'] ?? '',
            'date_to' => $_POST['date_to'] ?? '',
            'search' => $_POST['search'] ?? '',
            'search_by' => $_POST['search_by'] ?? 'auto'
        ];

        $types = '';
        $params = [];
        $where = build_where($types, $params, $input);

        // total count
        $countRow = db_query_one("
            SELECT COUNT(*) AS cnt
            FROM savings s
            LEFT JOIN users u ON u.id = s.recorded_by
            $where
        ", $types, $params);
        $totalRecords = (int)($countRow['cnt'] ?? 0);
        $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

        // page rows
        $rows = db_query_all("
            SELECT
                s.saving_id, s.member_id, s.transaction_date, s.transaction_type,
                s.amount, s.balance,
                CASE
                    WHEN s.recorded_by = 0 THEN 'System'
                    ELSE CONCAT(u.firstname,' ',u.lastname)
                END AS recorded_by_name
            FROM savings s
            LEFT JOIN users u ON u.id = s.recorded_by
            $where
            ORDER BY s.transaction_date DESC, s.saving_id DESC
            LIMIT $limit OFFSET $offset
        ", $types, $params);

        // summary (same filters)
        $sumRow = db_query_one("
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN s.transaction_type='Deposit' THEN s.amount ELSE 0 END),0) AS total_deposits,
                COALESCE(SUM(CASE WHEN s.transaction_type='Withdrawal' THEN s.amount ELSE 0 END),0) AS total_withdrawals,
                COALESCE(SUM(CASE WHEN s.transaction_type='Interest' THEN s.amount ELSE 0 END),0) AS total_interest
            FROM savings s
            LEFT JOIN users u ON u.id = s.recorded_by
            $where
        ", $types, $params);

        // last balance (latest record with same filters)
        $lastRow = db_query_one("
            SELECT s.balance
            FROM savings s
            LEFT JOIN users u ON u.id = s.recorded_by
            $where
            ORDER BY s.transaction_date DESC, s.saving_id DESC
            LIMIT 1
        ", $types, $params);

        echo json_encode([
            'status' => 'success',
            'rows' => $rows,
            'summary' => [
                'total' => (int)($sumRow['total'] ?? 0),
                'total_deposits' => as_money($sumRow['total_deposits'] ?? 0),
                'total_withdrawals' => as_money($sumRow['total_withdrawals'] ?? 0),
                'total_interest' => as_money($sumRow['total_interest'] ?? 0),
                'last_balance' => as_money($lastRow['balance'] ?? 0),
            ],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords
            ]
        ]);
        exit;
    }

    if ($action === 'get') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status'=>'error','msg'=>'Invalid ID']);
            exit;
        }

        $row = db_query_one("
            SELECT
                s.saving_id, s.member_id, s.transaction_date, s.transaction_type,
                s.amount, s.balance,
                CASE
                    WHEN s.recorded_by = 0 THEN 'System'
                    ELSE CONCAT(u.firstname,' ',u.lastname)
                END AS recorded_by_name
            FROM savings s
            LEFT JOIN users u ON u.id = s.recorded_by
            WHERE s.saving_id = ?
            LIMIT 1
        ", "i", [$id]);

        if (!$row) {
            echo json_encode(['status'=>'error','msg'=>'Transaction not found']);
            exit;
        }

        echo json_encode(['status'=>'success','row'=>$row]);
        exit;
    }

    if ($action === 'breakdown') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($memberId <= 0) {
            echo json_encode(['status'=>'error','msg'=>'Invalid member_id']);
            exit;
        }

        $member = db_query_one("SELECT member_id, full_name FROM members WHERE member_id = ? LIMIT 1", "i", [$memberId]);
        $memberName = $member ? ($member['full_name'] ?? ("Member " . $memberId)) : ("Member " . $memberId);

        $sum = db_query_one("
            SELECT
                COALESCE(SUM(CASE WHEN transaction_type='Deposit' THEN amount ELSE 0 END),0) AS total_deposits,
                COALESCE(SUM(CASE WHEN transaction_type='Withdrawal' THEN amount ELSE 0 END),0) AS total_withdrawals,
                COALESCE(SUM(CASE WHEN transaction_type='Interest' THEN amount ELSE 0 END),0) AS total_interest,
                COUNT(*) AS total_transactions,
                SUM(CASE WHEN transaction_type='Deposit' THEN 1 ELSE 0 END) AS deposit_count,
                SUM(CASE WHEN transaction_type='Withdrawal' THEN 1 ELSE 0 END) AS withdrawal_count,
                SUM(CASE WHEN transaction_type='Interest' THEN 1 ELSE 0 END) AS interest_count
            FROM savings
            WHERE member_id = ?
        ", "i", [$memberId]);

        $last = db_query_one("
            SELECT balance
            FROM savings
            WHERE member_id = ?
            ORDER BY transaction_date DESC, saving_id DESC
            LIMIT 1
        ", "i", [$memberId]);

        $txns = db_query_all("
            SELECT
                s.saving_id, s.member_id, s.transaction_date, s.transaction_type,
                s.amount, s.balance,
                CASE
                    WHEN s.recorded_by = 0 THEN 'System'
                    ELSE CONCAT(u.firstname,' ',u.lastname)
                END AS recorded_by_name
            FROM savings s
            LEFT JOIN users u ON u.id = s.recorded_by
            WHERE s.member_id = ?
            ORDER BY s.transaction_date DESC, s.saving_id DESC
        ", "i", [$memberId]);

        echo json_encode([
            'status' => 'success',
            'member_info' => [
                'member_id' => $memberId,
                'name' => $memberName
            ],
            'summary' => [
                'total_deposits' => as_money($sum['total_deposits'] ?? 0),
                'total_withdrawals' => as_money($sum['total_withdrawals'] ?? 0),
                'total_interest' => as_money($sum['total_interest'] ?? 0),
                'deposit_count' => (int)($sum['deposit_count'] ?? 0),
                'withdrawal_count' => (int)($sum['withdrawal_count'] ?? 0),
                'interest_count' => (int)($sum['interest_count'] ?? 0),
                'total_transactions' => (int)($sum['total_transactions'] ?? 0),
                'current_balance' => as_money($last['balance'] ?? 0),
            ],
            'transactions' => $txns
        ]);
        exit;
    }

    echo json_encode(['status'=>'error','msg'=>'Invalid action']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','msg'=>$e->getMessage()]);
    exit;
}

// -------- simple PDF generator (no external library) --------
function simple_pdf_from_lines($lines) {
    // Minimal PDF with Helvetica, fixed text lines
    $text = "";
    $y = 780;
    foreach ($lines as $line) {
        $safe = str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $line);
        $text .= "BT /F1 10 Tf 50 $y Td ($safe) Tj ET\n";
        $y -= 14;
        if ($y < 50) break;
    }

    $stream = $text;
    $len = strlen($stream);

    $objects = [];
    $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
    $objects[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n";
    $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj\n";
    $objects[] = "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";
    $objects[] = "5 0 obj << /Length $len >> stream\n$stream\nendstream endobj\n";

    $pdf = "%PDF-1.4\n";
    $xref = [];
    $offset = strlen($pdf);

    foreach ($objects as $obj) {
        $xref[] = $offset;
        $pdf .= $obj;
        $offset = strlen($pdf);
    }

    $xrefStart = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($xref as $off) {
        $pdf .= str_pad((string)$off, 10, "0", STR_PAD_LEFT) . " 00000 n \n";
    }

    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n$xrefStart\n%%EOF";
    return $pdf;
}
