<?php
// ajax_repayments.php (PURE BACKEND - FIXED)

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Start a buffer so any accidental output won't break JSON/PDF
ob_start();

require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/check_auth.php');

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Send clean JSON and exit (prevents "Unexpected token" issues).
 */
function send_json(array $data, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------------------------------------------------------------
// Load TCPDF only when needed
// ----------------------------------------------------------------------
function loadTCPDF(): bool
{
    if (class_exists('TCPDF')) return true;

    $paths = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../../libs/tcpdf/tcpdf.php',
        __DIR__ . '/../libs/tcpdf/tcpdf.php',
        __DIR__ . '/libs/tcpdf/tcpdf.php'
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            if (class_exists('TCPDF')) return true;
        }
    }
    return false;
}

function outputPdfDownload($pdf, string $filename): void
{
    // Clear all buffers to avoid corrupt PDF binary
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $binary = $pdf->Output($filename, 'S');
    if ($binary === '') {
        send_json(['error' => true, 'message' => 'Generated PDF content is empty.'], 500);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . strlen($binary));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $binary;
    exit;
}

try {
    // ✅ Auth check (avoid redirects/output that break JSON)
    if (!isset($_SESSION['userdata']['user_id'])) {
        send_json(['error' => true, 'message' => 'Not authenticated'], 401);
    }

    if (!isset($conn) || !$conn || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn->connect_error ?? 'Unknown error'));
    }

    // Params
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $riskFilter = isset($_GET['risk']) ? trim((string)$_GET['risk']) : '';
    $typeFilter = isset($_GET['type']) ? trim((string)$_GET['type']) : '';
    $cardFilter = isset($_GET['cardFilter']) ? trim((string)$_GET['cardFilter']) : 'all';
    $export = isset($_GET['export']) ? trim((string)$_GET['export']) : '';
    $pdfPassword = isset($_GET['pdf_password']) ? trim((string)$_GET['pdf_password']) : '';

    $offset = ($page - 1) * $limit;

    // ✅ IMPORTANT: always use COALESCE(ls.overdue_count,0) in WHERE (NOT "overdue_count" alias)
    $whereClauses = [];

    if ($search !== '') {
        $searchParam = $conn->real_escape_string($search);
        $whereClauses[] = "(lp.loan_id LIKE '%$searchParam%' OR m.full_name LIKE '%$searchParam%' OR lp.loan_type LIKE '%$searchParam%')";
    }

    if ($statusFilter !== '') {
        $statusParam = $conn->real_escape_string($statusFilter);
        $whereClauses[] = "lp.status = '$statusParam'";
    }

    if ($typeFilter !== '') {
        $typeParam = $conn->real_escape_string($typeFilter);
        $whereClauses[] = "lp.loan_type = '$typeParam'";
    }

    if ($riskFilter !== '') {
        if ($riskFilter === 'Low') {
            $whereClauses[] = "COALESCE(ls.overdue_count,0) = 0 AND lp.status IN ('Active','Approved')";
        } elseif ($riskFilter === 'Medium') {
            $whereClauses[] = "COALESCE(ls.overdue_count,0) BETWEEN 1 AND 2";
        } elseif ($riskFilter === 'High') {
            $whereClauses[] = "(COALESCE(ls.overdue_count,0) >= 3 OR lp.status = 'Defaulted')";
        }
    }

    if ($cardFilter !== 'all') {
        if ($cardFilter === 'active') {
            $whereClauses[] = "lp.status = 'Active'";
        } elseif ($cardFilter === 'overdue') {
            $whereClauses[] = "COALESCE(ls.overdue_count,0) > 0";
        } elseif ($cardFilter === 'at_risk') {
            $whereClauses[] = "(COALESCE(ls.overdue_count,0) >= 3 OR lp.status = 'Defaulted')";
        }
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    // --- PDF EXPORT ---
    if ($export === 'pdf') {
        if (strlen($pdfPassword) < 6) throw new Exception('PDF password must be at least 6 characters.');
        if (!loadTCPDF()) throw new Exception('TCPDF library not found.');

        if (!class_exists('RepaymentExportPDF')) {
            class RepaymentExportPDF extends TCPDF
            {
                public function Header(): void
                {
                    $leftMargin = 10;
                    $top = 8;
                    $width = 277;

                    $this->SetFillColor(20, 83, 45);
                    $this->SetDrawColor(20, 83, 45);
                    $this->RoundedRect($leftMargin, $top, $width, 20, 2, '1111', 'FD');

                    $logoPath = __DIR__ . '/../../dist/img/logo.jpg';
                    if (is_file($logoPath)) {
                        $this->Image($logoPath, $leftMargin + 3, $top + 2, 16, 16, 'JPG');
                    }

                    $this->SetTextColor(255, 255, 255);
                    $this->SetXY($leftMargin + 22, $top + 4);
                    $this->SetFont('helvetica', 'B', 13);
                    $this->Cell(0, 6, 'Golden Horizons Cooperative', 0, 1, 'L');
                    $this->SetX($leftMargin + 22);
                    $this->SetFont('helvetica', '', 9);
                    $this->Cell(0, 5, 'Collection Monitoring & Recovery Report', 0, 0, 'L');
                }

                public function Footer(): void
                {
                    $this->SetY(-12);
                    $this->SetFont('helvetica', 'I', 8);
                    $this->SetTextColor(20, 83, 45);
                    $this->Cell(0, 8, 'Confidential • Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
                }
            }
        }

        $allLoansSql = "
            SELECT 
                lp.loan_id, lp.member_id, lp.loan_type, lp.principal_amount, lp.interest_rate,
                lp.loan_term, lp.start_date, lp.end_date, lp.status,
                m.full_name AS member_name, m.email,
                COALESCE(ls.overdue_count, 0) AS overdue_count,
                CASE 
                    WHEN lp.status = 'Defaulted' THEN 'High'
                    WHEN COALESCE(ls.overdue_count, 0) >= 3 THEN 'High'
                    WHEN COALESCE(ls.overdue_count, 0) BETWEEN 1 AND 2 THEN 'Medium'
                    ELSE 'Low'
                END AS risk_level
            FROM loan_portfolio lp
            LEFT JOIN members m ON lp.member_id = m.member_id
            LEFT JOIN (
                SELECT loan_id, COUNT(*) AS overdue_count
                FROM loan_schedule
                WHERE status = 'Overdue'
                GROUP BY loan_id
            ) ls ON lp.loan_id = ls.loan_id
            $whereSql
            ORDER BY lp.loan_id DESC
        ";

        $result = $conn->query($allLoansSql);

        $pdf = new RepaymentExportPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Repayment System');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Repayment Tracker Report');

        $ownerPassword = md5(uniqid((string)mt_rand(), true));
        $pdf->SetProtection(['print', 'copy'], $pdfPassword, $ownerPassword, 0, null);

        $pdf->SetMargins(10, 32, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 7, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'L');
        $pdf->Ln(3);

        $html = '
        <style>
            table { border-collapse: collapse; }
            th { background-color: #14532d; color: #ffffff; font-size: 9px; font-weight: bold; padding: 6px; border: 1px solid #166534; text-align: center; }
            td { font-size: 8px; color: #1f2937; padding: 5px; border: 1px solid #bbf7d0; }
            .row-light { background-color: #f0fdf4; }
            .row-alt { background-color: #dcfce7; }
            .center { text-align: center; }
        </style>
        <table width="100%" cellpadding="4">
        <thead>
            <tr>
                <th width="8%">ID</th>
                <th width="20%">Member</th>
                <th width="12%">Type</th>
                <th width="12%">Principal</th>
                <th width="8%">Rate</th>
                <th width="8%">Term</th>
                <th width="12%">Start</th>
                <th width="10%">Status</th>
                <th width="10%">Risk</th>
            </tr>
        </thead>
        <tbody>';

        $n = 0;
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rowClass = ($n % 2 === 0) ? 'row-alt' : 'row-light';
                $html .= '<tr class="' . $rowClass . '">'
                    . '<td class="center">' . $row['loan_id'] . '</td>'
                    . '<td>' . htmlspecialchars((string)$row['member_name'], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars((string)$row['loan_type'], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td class="center">₱' . number_format((float)$row['principal_amount'], 2) . '</td>'
                    . '<td class="center">' . $row['interest_rate'] . '%</td>'
                    . '<td class="center">' . $row['loan_term'] . ' mo</td>'
                    . '<td class="center">' . $row['start_date'] . '</td>'
                    . '<td class="center">' . $row['status'] . '</td>'
                    . '<td class="center">' . $row['risk_level'] . '</td>'
                    . '</tr>';
                $n++;
            }
        } else {
            $html .= '<tr class="row-light"><td colspan="9" class="center">No records found.</td></tr>';
        }

        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');

        outputPdfDownload($pdf, 'repayment_tracker_' . date('Y-m-d_His') . '.pdf');
    }

    // --- SUMMARY CARDS ---
    $summary = [
        'total_loans' => 0,
        'active_loans' => 0,
        'overdue_loans' => 0,
        'at_risk_loans' => 0
    ];

    $result = $conn->query("SELECT COUNT(*) AS cnt FROM loan_portfolio");
    if ($result) $summary['total_loans'] = (int)($result->fetch_assoc()['cnt'] ?? 0);

    $result = $conn->query("SELECT COUNT(*) AS cnt FROM loan_portfolio WHERE status='Active'");
    if ($result) $summary['active_loans'] = (int)($result->fetch_assoc()['cnt'] ?? 0);

    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) AS cnt
        FROM loan_portfolio lp
        INNER JOIN loan_schedule ls ON lp.loan_id = ls.loan_id
        WHERE ls.status = 'Overdue'
    ");
    if ($result) $summary['overdue_loans'] = (int)($result->fetch_assoc()['cnt'] ?? 0);

    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) AS cnt
        FROM loan_portfolio lp
        WHERE lp.status = 'Defaulted' OR (
            SELECT COUNT(*)
            FROM loan_schedule ls
            WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
        ) >= 3
    ");
    if ($result) $summary['at_risk_loans'] = (int)($result->fetch_assoc()['cnt'] ?? 0);

    // --- LOAN STATUS DISTRIBUTION ---
    $statusData = ['labels' => [], 'values' => []];
    $result = $conn->query("
        SELECT status, COUNT(*) AS cnt
        FROM loan_portfolio
        WHERE status IS NOT NULL
        GROUP BY status
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $statusData['labels'][] = $row['status'];
            $statusData['values'][] = (int)$row['cnt'];
        }
    }

    // --- RISK BREAKDOWN ---
    $riskData = ['labels' => ['Low', 'Medium', 'High'], 'values' => [0, 0, 0]];

    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) AS cnt
        FROM loan_portfolio lp
        WHERE lp.status IN ('Active','Approved')
        AND (
            SELECT COUNT(*)
            FROM loan_schedule ls
            WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
        ) = 0
    ");
    if ($result) $riskData['values'][0] = (int)($result->fetch_assoc()['cnt'] ?? 0);

    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) AS cnt
        FROM loan_portfolio lp
        WHERE lp.status IN ('Active','Approved')
        AND (
            SELECT COUNT(*)
            FROM loan_schedule ls
            WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
        ) BETWEEN 1 AND 2
    ");
    if ($result) $riskData['values'][1] = (int)($result->fetch_assoc()['cnt'] ?? 0);

    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) AS cnt
        FROM loan_portfolio lp
        WHERE lp.status = 'Defaulted' OR (
            lp.status IN ('Active','Approved')
            AND (
                SELECT COUNT(*)
                FROM loan_schedule ls
                WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
            ) >= 3
        )
    ");
    if ($result) $riskData['values'][2] = (int)($result->fetch_assoc()['cnt'] ?? 0);

    // --- LOAN TYPES ---
    $loanTypes = [];
    $result = $conn->query("SELECT DISTINCT loan_type FROM loan_portfolio WHERE loan_type IS NOT NULL ORDER BY loan_type");
    if ($result) {
        while ($row = $result->fetch_assoc()) $loanTypes[] = $row['loan_type'];
    }

    // --- COUNT TOTAL RECORDS (WITH SAME JOINS) ---
    $countSql = "
        SELECT COUNT(DISTINCT lp.loan_id) AS cnt
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id = m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) AS overdue_count
            FROM loan_schedule
            WHERE status = 'Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id = ls.loan_id
        $whereSql
    ";
    $result = $conn->query($countSql);
    $totalRecords = (int)($result ? ($result->fetch_assoc()['cnt'] ?? 0) : 0);
    $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

    // --- FETCH LOANS ---
    $sql = "
        SELECT 
            lp.loan_id, lp.member_id, lp.loan_type, lp.principal_amount, lp.interest_rate,
            lp.loan_term, lp.start_date, lp.end_date, lp.status,
            m.full_name AS member_name, m.email,
            COALESCE(ls.overdue_count, 0) AS overdue_count,
            CASE 
                WHEN lp.status = 'Defaulted' THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) >= 3 THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) BETWEEN 1 AND 2 THEN 'Medium'
                ELSE 'Low'
            END AS risk_level,
            (
                SELECT MIN(due_date)
                FROM loan_schedule
                WHERE loan_id = lp.loan_id
                AND status = 'Pending'
                LIMIT 1
            ) AS next_due
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id = m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) AS overdue_count
            FROM loan_schedule
            WHERE status = 'Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id = ls.loan_id
        $whereSql
        ORDER BY lp.loan_id DESC
        LIMIT $offset, $limit
    ";

    $result = $conn->query($sql);
    $loans = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) $loans[] = $row;
    }

    // --- ALL LOANS (optional) ---
    $allLoansSql = "
        SELECT 
            lp.loan_id, lp.member_id, lp.loan_type, lp.principal_amount, lp.interest_rate,
            lp.loan_term, lp.start_date, lp.end_date, lp.status,
            m.full_name AS member_name, m.email,
            COALESCE(ls.overdue_count, 0) AS overdue_count,
            CASE 
                WHEN lp.status = 'Defaulted' THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) >= 3 THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) BETWEEN 1 AND 2 THEN 'Medium'
                ELSE 'Low'
            END AS risk_level
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id = m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) AS overdue_count
            FROM loan_schedule
            WHERE status = 'Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id = ls.loan_id
        $whereSql
        ORDER BY lp.loan_id DESC
    ";

    $result = $conn->query($allLoansSql);
    $allLoans = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) $allLoans[] = $row;
    }

    send_json([
        'success' => true,
        'summary' => $summary,
        'loan_status' => $statusData,
        'risk_breakdown' => $riskData,
        'loan_types' => $loanTypes,
        'loans' => $loans,
        'all_loans' => $allLoans,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
        ]
    ]);

} catch (Throwable $e) {
    send_json(['error' => true, 'message' => $e->getMessage()], 500);
}
