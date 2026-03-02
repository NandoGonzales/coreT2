<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');
require_once __DIR__ . '/../inc/check_auth.php';
require_once __DIR__ . '/compliance_logger.php'; // for get_full_compliance_info()

// Enforce RBAC
checkPermission('compliance_logs');

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'msg' => 'Database connection failed']);
    exit;
}

/**
 * Build WHERE clause for filtering
 */
function buildWhereClause($search, $start, $end, $status)
{
    $where  = [];
    $params = [];
    $types  = '';

    if ($search !== '') {
        $where[]     = "(a.action_type LIKE ? OR a.module_name LIKE ? OR u.full_name LIKE ? OR a.remarks LIKE ?)";
        $sp          = "%$search%";
        $params      = array_merge($params, [$sp, $sp, $sp, $sp]);
        $types      .= 'ssss';
    }

    if ($start !== '' && $end !== '') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $where[]  = "DATE(a.action_time) BETWEEN ? AND ?";
            $params[] = $start;
            $params[] = $end;
            $types   .= 'ss';
        }
    }

    if ($status !== '') {
        $valid = ['Compliant', 'Non-Compliant', 'Under Review', 'Pending'];
        if (in_array($status, $valid)) {
            $where[]  = "a.compliance_status = ?";
            $params[] = $status;
            $types   .= 's';
        }
    }

    return [
        'sql'    => count($where) ? 'WHERE ' . implode(' AND ', $where) : '',
        'params' => $params,
        'types'  => $types,
    ];
}

// ── TCPDF loader ──────────────────────────────────────────────────────
function loadTCPDF()
{
    if (class_exists('TCPDF')) return true;
    $paths = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../../libs/tcpdf/tcpdf.php',
        __DIR__ . '/../libs/tcpdf/tcpdf.php',
        __DIR__ . '/libs/tcpdf/tcpdf.php',
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) { require_once($p); if (class_exists('TCPDF')) return true; }
    }
    return false;
}

function outputPdfDownload($pdf, string $filename): void
{
    while (ob_get_level() > 0) ob_end_clean();
    ob_start();
    $binary = $pdf->Output($filename, 'S');
    ob_end_clean();
    if ($binary === '') throw new Exception('Generated PDF content is empty.');
    if (!headers_sent()) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . strlen($binary));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
    }
    echo $binary;
    exit;
}

// ======================================================================
// GET: ?detail=1&id=XX  — Row-click compliance detail modal
// ======================================================================
if (isset($_GET['detail']) && $_GET['detail'] === '1') {
    header('Content-Type: application/json');

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['status' => 'error', 'msg' => 'Invalid ID']); exit; }

    $stmt = $conn->prepare("
        SELECT a.*, u.full_name, u.username
        FROM audit_trail a
        LEFT JOIN users u ON a.user_id = u.user_id
        WHERE a.audit_id = ?
        LIMIT 1
    ");

    $row = null;
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$row) { echo json_encode(['status' => 'error', 'msg' => 'Record not found']); exit; }

    $action_type = $row['action_type'] ?? '';
    $description = $row['remarks']     ?? '';
    $status_val  = $row['compliance_status'] ?? '';
    $created_at  = $row['action_time'] ?? '-';

    if ($created_at && $created_at !== '-') {
        $ts = strtotime($created_at);
        if ($ts) $created_at = date('Y-m-d h:i A', $ts);
    }

    $info = get_full_compliance_info($action_type, $description);

    echo json_encode([
        'status'     => 'success',
        'record'     => [
            'user'        => $row['full_name'] ?? $row['username'] ?? 'System',
            'action_type' => $action_type,
            'module'      => $row['module_name'] ?? '',
            'description' => $description,
            'status'      => $status_val ?: $info['status'],
            'ip_address'  => $row['ip_address'] ?? '-',
            'created_at'  => $created_at,
        ],
        'compliance' => $info,
    ]);
    exit;
}

// ======================================================================
// GET: CSV Export
// ======================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $search  = trim($_GET['search'] ?? '');
        $start   = trim($_GET['start']  ?? '');
        $end     = trim($_GET['end']    ?? '');
        $status  = trim($_GET['status'] ?? '');

        $f        = buildWhereClause($search, $start, $end, $status);
        $whereSQL = $f['sql'];
        $params   = $f['params'];
        $types    = $f['types'];

        $sql = "
            SELECT
                a.audit_id, u.full_name, u.username,
                a.action_type, a.module_name, a.remarks,
                a.compliance_status,
                DATE_FORMAT(a.action_time, '%Y-%m-%d %h:%i %p') as action_time,
                a.ip_address
            FROM audit_trail a
            LEFT JOIN users u ON a.user_id = u.user_id
            $whereSQL
            ORDER BY a.action_time DESC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Failed to prepare statement: " . $conn->error);
        if ($types !== '' && count($params) > 0) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception("Failed to execute query: " . $stmt->error);

        $result        = $stmt->get_result();
        $fn_base       = 'compliance_logs_' . date('Y-m-d_His');
        $csv_fn        = $fn_base . '.csv';
        $export_pass   = trim($_GET['pdf_password'] ?? '');

        $csv = fopen('php://temp', 'r+');
        fprintf($csv, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($csv, ['ID','User','Username','Action Type','Module','Description','Compliance Status','Date/Time','IP Address']);

        while ($row = $result->fetch_assoc()) {
            fputcsv($csv, [
                $row['audit_id']          ?? '',
                $row['full_name']         ?? 'System',
                $row['username']          ?? '',
                $row['action_type']       ?? '',
                $row['module_name']       ?? '',
                $row['remarks']           ?? '',
                $row['compliance_status'] ?? '',
                $row['action_time']       ?? '',
                $row['ip_address']        ?? '',
            ]);
        }
        rewind($csv);
        $csv_content = stream_get_contents($csv);
        fclose($csv);
        $stmt->close();

        if ($export_pass !== '' && class_exists('ZipArchive')) {
            $zip  = new ZipArchive();
            $tmp  = tempnam(sys_get_temp_dir(), 'zip');
            if ($zip->open($tmp, ZipArchive::CREATE) === TRUE) {
                $zip->addFromString($csv_fn, $csv_content);
                if (method_exists($zip, 'setEncryptionName'))
                    $zip->setEncryptionName($csv_fn, ZipArchive::EM_AES_256, $export_pass);
                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $fn_base . '.zip"');
                header('Content-Length: ' . filesize($tmp));
                readfile($tmp);
                unlink($tmp);
                exit;
            }
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csv_fn . '"');
        echo $csv_content;
        exit;

    } catch (Exception $e) {
        error_log("CSV Export Error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'msg' => 'CSV export failed: ' . $e->getMessage()]);
        exit;
    }
}

// ======================================================================
// GET: JSON Export
// ======================================================================
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    header('Content-Type: application/json');
    try {
        $search = trim($_GET['search'] ?? '');
        $start  = trim($_GET['start']  ?? '');
        $end    = trim($_GET['end']    ?? '');
        $status = trim($_GET['status'] ?? '');

        $f = buildWhereClause($search, $start, $end, $status);

        $sql = "SELECT a.audit_id, a.user_id, a.action_type, a.module_name, a.record_id,
                       a.remarks, a.compliance_status,
                       DATE_FORMAT(a.action_time, '%Y-%m-%d %h:%i %p') as action_time,
                       a.ip_address, u.full_name, u.username
                FROM audit_trail a LEFT JOIN users u ON a.user_id = u.user_id
                {$f['sql']} ORDER BY a.action_time DESC";

        $stmt = $conn->prepare($sql);
        if ($f['types'] !== '') $stmt->bind_param($f['types'], ...$f['params']);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;

        echo json_encode(['status' => 'success', 'rows' => $rows]);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
        exit;
    }
}

// ======================================================================
// GET: PDF Export
// ======================================================================
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    ini_set('display_errors', '0');

    if (!loadTCPDF()) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'msg' => 'PDF export failed: TCPDF library not found.']);
        exit;
    }

    if (!class_exists('ComplianceExportPDF')) {
        class ComplianceExportPDF extends TCPDF
        {
            public function Header(): void
            {
                $lm = 10; $top = 8; $w = 277;
                $this->SetFillColor(20, 83, 45); $this->SetDrawColor(20, 83, 45);
                $this->RoundedRect($lm, $top, $w, 20, 2, '1111', 'FD');
                $logo = __DIR__ . '/../../dist/img/logo.jpg';
                if (is_file($logo)) $this->Image($logo, $lm + 3, $top + 2, 16, 16, 'JPG');
                $this->SetTextColor(255, 255, 255);
                $this->SetXY($lm + 22, $top + 4); $this->SetFont('helvetica', 'B', 13);
                $this->Cell(0, 6, 'Golden Horizons Cooperative', 0, 1, 'L');
                $this->SetX($lm + 22); $this->SetFont('helvetica', '', 9);
                $this->Cell(0, 5, 'Compliance & Audit Trail Report', 0, 0, 'L');
            }
            public function Footer(): void
            {
                $this->SetY(-12); $this->SetFont('helvetica', 'I', 8); $this->SetTextColor(20, 83, 45);
                $this->Cell(0, 8, 'Confidential • Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
            }
        }
    }

    try {
        $search      = trim($_GET['search']       ?? '');
        $start       = trim($_GET['start']        ?? '');
        $end         = trim($_GET['end']          ?? '');
        $status      = trim($_GET['status']       ?? '');
        $pdfPassword = trim($_GET['pdf_password'] ?? '');

        if (strlen($pdfPassword) < 6) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'msg' => 'PDF password must be at least 6 characters.']);
            exit;
        }

        $f = buildWhereClause($search, $start, $end, $status);

        $sql = "
            SELECT
                a.audit_id, a.user_id, a.action_type, a.module_name, a.remarks,
                a.compliance_status,
                DATE_FORMAT(a.action_time, '%Y-%m-%d %h:%i %p') as action_time,
                a.ip_address, u.full_name, u.username
            FROM audit_trail a
            LEFT JOIN users u ON a.user_id = u.user_id
            {$f['sql']}
            ORDER BY a.action_time DESC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Failed to prepare statement: " . $conn->error);
        if ($f['types'] !== '' && count($f['params']) > 0) $stmt->bind_param($f['types'], ...$f['params']);
        if (!$stmt->execute()) throw new Exception("Failed to execute query: " . $stmt->error);

        $result = $stmt->get_result();

        $pdf = new ComplianceExportPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Compliance System');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Compliance & Audit Trail Logs');
        $pdf->SetProtection(['print', 'copy'], $pdfPassword, md5(uniqid(mt_rand(), true)), 0, null);
        $pdf->SetMargins(10, 32, 10);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();

        $pdf->SetTextColor(34, 34, 34);
        $pdf->SetFillColor(220, 252, 231);

        $periodText = ($start && $end) ? 'Period: ' . $start . ' to ' . $end : 'Period: All Dates';
        $statusText = $status ? 'Status: ' . $status : 'Status: All';

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(138.5, 7, $periodText, 0, 0, 'L', true);
        $pdf->Cell(138.5, 7, $statusText, 0, 1, 'R', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'L');
        $pdf->Ln(3);

        $html = '
            <style>
                table { border-collapse: collapse; }
                th { background-color: #14532d; color: #ffffff; font-size: 9px; font-weight: bold; padding: 6px; border: 1px solid #166534; text-align: center; }
                td { font-size: 8px; color: #1f2937; padding: 5px; border: 1px solid #bbf7d0; }
                .row-light { background-color: #f0fdf4; }
                .row-alt   { background-color: #dcfce7; }
                .center    { text-align: center; }
            </style>
            <table width="100%" cellpadding="4">
                <thead>
                    <tr>
                        <th width="4%">#</th>
                        <th width="14%">User</th>
                        <th width="11%">Action</th>
                        <th width="11%">Module</th>
                        <th width="28%">Description</th>
                        <th width="10%">Status</th>
                        <th width="13%">Date/Time</th>
                        <th width="9%">IP</th>
                    </tr>
                </thead>
                <tbody>';

        $n = 1; $hasRows = false;
        while ($row = $result->fetch_assoc()) {
            $hasRows  = true;
            $rc       = ($n % 2 === 0) ? 'row-alt' : 'row-light';
            $html .= '<tr class="' . $rc . '">'
                . '<td class="center">' . $n . '</td>'
                . '<td>' . htmlspecialchars($row['full_name'] ?? $row['username'] ?? 'System', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars($row['action_type'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars($row['module_name'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars($row['remarks'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="center">' . htmlspecialchars($row['compliance_status'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="center">' . htmlspecialchars($row['action_time'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="center">' . htmlspecialchars($row['ip_address'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
            $n++;
        }

        if (!$hasRows)
            $html .= '<tr class="row-light"><td colspan="8" class="center">No compliance logs found for the selected filters.</td></tr>';

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $stmt->close();

        outputPdfDownload($pdf, 'compliance_logs_' . date('Y-m-d_His') . '.pdf');

    } catch (Exception $e) {
        error_log("PDF Export Error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'msg' => 'PDF export failed: ' . $e->getMessage()]);
        exit;
    }
}

// ======================================================================
// POST: AJAX Handlers
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $action = $_POST['action'] ?? '';

        // ── Status Summary ──────────────────────────────────────────
        if ($action === 'status_summary') {
            $search = trim($_POST['search'] ?? '');
            $start  = trim($_POST['start']  ?? '');
            $end    = trim($_POST['end']    ?? '');

            $f = buildWhereClause($search, $start, $end, '');

            $sql = "
                SELECT a.compliance_status, COUNT(*) as cnt
                FROM audit_trail a
                LEFT JOIN users u ON a.user_id = u.user_id
                {$f['sql']}
                GROUP BY a.compliance_status
            ";

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Failed to prepare summary statement: " . $conn->error);
            if ($f['types'] !== '' && count($f['params']) > 0) $stmt->bind_param($f['types'], ...$f['params']);
            if (!$stmt->execute()) throw new Exception("Failed to execute summary query: " . $stmt->error);

            $result  = $stmt->get_result();
            $summary = ['Compliant' => 0, 'Non-Compliant' => 0, 'Pending' => 0, 'Under Review' => 0];

            while ($row = $result->fetch_assoc()) {
                if (isset($summary[$row['compliance_status']]))
                    $summary[$row['compliance_status']] = (int)$row['cnt'];
            }
            $stmt->close();

            echo json_encode(['status' => 'success', 'summary' => $summary]);
            exit;
        }

        // ── List Records ────────────────────────────────────────────
        if ($action === 'list') {
            $page   = max(1, intval($_POST['page']  ?? 1));
            $limit  = max(1, min(100, intval($_POST['limit'] ?? 10)));
            $offset = ($page - 1) * $limit;

            $search = trim($_POST['search'] ?? '');
            $start  = trim($_POST['start']  ?? '');
            $end    = trim($_POST['end']    ?? '');
            $status = trim($_POST['status'] ?? '');

            $f        = buildWhereClause($search, $start, $end, $status);
            $whereSQL = $f['sql'];
            $params   = $f['params'];
            $types    = $f['types'];

            // Count total
            $countSQL = "SELECT COUNT(*) AS total FROM audit_trail a LEFT JOIN users u ON a.user_id = u.user_id $whereSQL";
            $stmt     = $conn->prepare($countSQL);
            if (!$stmt) throw new Exception("Failed to prepare count statement: " . $conn->error);
            if ($types !== '' && count($params) > 0) $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) throw new Exception("Failed to execute count query: " . $stmt->error);
            $total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
            $stmt->close();

            // Fetch records
            $sql = "
                SELECT
                    a.audit_id, a.user_id, a.action_type, a.module_name, a.record_id,
                    DATE_FORMAT(a.action_time, '%Y-%m-%d %h:%i %p') as action_time,
                    a.ip_address, a.remarks, a.compliance_status,
                    u.full_name, u.username
                FROM audit_trail a
                LEFT JOIN users u ON a.user_id = u.user_id
                $whereSQL
                ORDER BY a.action_time DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Failed to prepare select statement: " . $conn->error);

            if ($types !== '' && count($params) > 0) {
                $stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
            } else {
                $stmt->bind_param('ii', $limit, $offset);
            }

            if (!$stmt->execute()) throw new Exception("Failed to execute select query: " . $stmt->error);

            $result = $stmt->get_result();
            $rows   = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'audit_id'          => $row['audit_id'],
                    'user_id'           => $row['user_id'],
                    'username'          => $row['username']          ?? '',
                    'full_name'         => $row['full_name']         ?? '',
                    'action_type'       => $row['action_type']       ?? '',
                    'module_name'       => $row['module_name']       ?? '',
                    'record_id'         => $row['record_id']         ?? '',
                    'remarks'           => $row['remarks']           ?? '',
                    'compliance_status' => $row['compliance_status'] ?? '',
                    'action_time'       => $row['action_time']       ?? '',
                    'ip_address'        => $row['ip_address']        ?? '',
                ];
            }
            $stmt->close();

            echo json_encode([
                'status'      => 'success',
                'rows'        => $rows,
                'total'       => intval($total),
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => ceil($total / $limit),
            ]);
            exit;
        }

        echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
        exit;

    } catch (Exception $e) {
        error_log("Compliance Logs Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'msg' => 'Database error occurred']);
        exit;
    }
}

// Invalid request
http_response_code(400);
echo json_encode(['status' => 'error', 'msg' => 'Invalid request method']);
exit;