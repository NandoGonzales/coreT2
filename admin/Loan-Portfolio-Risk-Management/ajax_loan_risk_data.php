<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * ULTIMATE FIXED VERSION - ajax_loan_risk_data.php
 * ═══════════════════════════════════════════════════════════════
 * Fixes:
 * 1. Interest rate display with % symbol
 * 2. Positive/negative amounts properly formatted
 * 3. Improved PDF layout and readability
 * 4. No data loss on table after PDF export
 * 5. Professional accounting format
 * ═══════════════════════════════════════════════════════════════
 */

// STEP 1: Disable errors for PDF export
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@error_reporting(0);

// STEP 2: Check if PDF export BEFORE any output
$is_pdf_export = (isset($_GET['export']) && $_GET['export'] === 'pdf');

// STEP 3: Clean buffers only for PDF
if ($is_pdf_export) {
    while (@ob_get_level()) {
        @ob_end_clean();
    }
}

ob_start();
require_once(__DIR__ . '/../../initialize_coreT2.php');

while (@ob_get_level() > 1) {
    @ob_end_clean();
}

if (!$is_pdf_export) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
}

// ─────────────────────────────────────────────
// HELPER FUNCTIONS
// ─────────────────────────────────────────────

function loadTCPDF()
{
    if (class_exists('TCPDF')) return true;

    $paths = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../../libs/tcpdf/tcpdf.php',
        __DIR__ . '/../libs/tcpdf/tcpdf.php',
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
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    ob_start();
    $binary = $pdf->Output($filename, 'S');
    ob_end_clean();

    if ($binary === '') {
        throw new Exception('Generated PDF content is empty.');
    }

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

/**
 * Format currency with proper accounting format
 * Negative amounts in parentheses: (₱1,000.00)
 * Positive amounts normal: ₱1,000.00
 */
function formatCurrency($amount, $showSymbol = true) {
    $amount = (float)$amount;
    $symbol = $showSymbol ? '₱' : '';
    
    if ($amount < 0) {
        return '(' . $symbol . number_format(abs($amount), 2) . ')';
    }
    return $symbol . number_format($amount, 2);
}

/**
 * Format interest rate with % symbol
 */
function formatInterestRate($rate) {
    $rate = (float)$rate;
    
    // Handle negative rates (though unusual)
    if ($rate < 0) {
        return '(' . number_format(abs($rate), 2) . '%)';
    }
    
    return number_format($rate, 2) . '%';
}

/**
 * Format percentage (for displays)
 */
function formatPercentage($value) {
    $value = (float)$value;
    
    if ($value < 0) {
        return '(' . number_format(abs($value), 2) . '%)';
    }
    
    return number_format($value, 2) . '%';
}

function calculateTotalAmountDue($conn, $principal, $interestRate, $loanTerm, $loanCode = null) {
    $timeInYears = $loanTerm / 12;
    $totalInterest = $principal * ($interestRate / 100) * $timeInYears;
    
    $totalPenalties = 0;
    if ($loanCode) {
        try {
            @$tableCheck = $conn->query("SHOW TABLES LIKE 'loan_penalties'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                @$stmt = $conn->prepare("
                    SELECT COALESCE(SUM(penalty_amount), 0) as total_penalties
                    FROM loan_penalties
                    WHERE loan_code = ?
                ");
                if ($stmt) {
                    @$stmt->bind_param('s', $loanCode);
                    @$stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $totalPenalties = (float)$row['total_penalties'];
                    }
                    @$stmt->close();
                }
            }
        } catch (Exception $e) {
            $totalPenalties = 0;
        }
    }
    
    return [
        'principal' => $principal,
        'total_interest' => $totalInterest,
        'total_penalties' => $totalPenalties,
        'total_amount_due' => $principal + $totalInterest + $totalPenalties
    ];
}

// --- Get Parameters ---
$page       = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit      = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : ($is_pdf_export ? 10000 : 10);
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';
$status     = isset($_GET['status']) ? trim($_GET['status']) : '';
$risk       = isset($_GET['risk']) ? trim($_GET['risk']) : '';
$type       = isset($_GET['type']) ? trim($_GET['type']) : '';
$cardFilter = isset($_GET['cardFilter']) ? trim($_GET['cardFilter']) : 'all';
$offset     = ($page - 1) * $limit;

$response = [
    'success' => true,
    'message' => '',
    'summary' => ['total_loans' => 0, 'active_loans' => 0, 'overdue_loans' => 0, 'defaulted_loans' => 0],
    'loan_status' => ['labels' => [], 'values' => []],
    'risk_breakdown' => ['labels' => [], 'values' => []],
    'loans' => [],
    'loan_types' => [],
    'pagination' => ['current_page' => $page, 'total_pages' => 1, 'limit' => $limit, 'total_records' => 0]
];

try {
    // Summary queries
    @$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM loan_portfolio");
    @$stmt->execute();
    $response['summary']['total_loans'] = (int)$stmt->get_result()->fetch_assoc()['c'];
    @$stmt->close();

    @$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM loan_portfolio WHERE status='Active'");
    @$stmt->execute();
    $response['summary']['active_loans'] = (int)$stmt->get_result()->fetch_assoc()['c'];
    @$stmt->close();

    @$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM loan_portfolio WHERE status='Defaulted'");
    @$stmt->execute();
    $response['summary']['defaulted_loans'] = (int)$stmt->get_result()->fetch_assoc()['c'];
    @$stmt->close();

    @$table_exists = $conn->query("SHOW TABLES LIKE 'loan_schedule'")->num_rows > 0;
    
    if ($table_exists) {
        @$stmt = $conn->prepare("
            SELECT COUNT(DISTINCT l.loan_id) AS c
            FROM loan_portfolio l
            JOIN loan_schedule s ON (s.loan_code = l.loan_code OR (s.loan_code IS NULL AND s.loan_id = l.loan_id))
            WHERE s.status='Overdue' OR (s.due_date<CURDATE() AND s.amount_paid < s.amount_due)
        ");
        @$stmt->execute();
        $response['summary']['overdue_loans'] = (int)$stmt->get_result()->fetch_assoc()['c'];
        @$stmt->close();
    }

    @$stmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM loan_portfolio GROUP BY status");
    @$stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['loan_status']['labels'][] = $row['status'];
        $response['loan_status']['values'][] = (int)$row['total'];
    }
    @$stmt->close();

    @$stmt = $conn->prepare("SELECT DISTINCT loan_type FROM loan_portfolio WHERE loan_type IS NOT NULL ORDER BY loan_type");
    @$stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['loan_types'][] = $row['loan_type'];
    }
    @$stmt->close();

    // Build filters
    $where_conditions = [];
    $params = [];
    $types = '';

    if ($cardFilter === 'active') {
        $where_conditions[] = "l.status = 'Active'";
    } elseif ($cardFilter === 'overdue' && $table_exists) {
        $where_conditions[] = "EXISTS (
            SELECT 1 FROM loan_schedule ls 
            WHERE (ls.loan_code = l.loan_code OR (ls.loan_code IS NULL AND ls.loan_id = l.loan_id))
            AND (ls.status = 'Overdue' OR (ls.due_date < CURDATE() AND ls.amount_paid < ls.amount_due))
        )";
    } elseif ($cardFilter === 'defaulted') {
        $where_conditions[] = "l.status = 'Defaulted'";
    }

    if ($search !== '') {
        $where_conditions[] = "(l.loan_id LIKE ? OR l.loan_code LIKE ? OR m.full_name LIKE ? OR l.loan_type LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ssss';
    }

    if ($status !== '') {
        $where_conditions[] = "l.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if ($type !== '') {
        $where_conditions[] = "l.loan_type = ?";
        $params[] = $type;
        $types .= 's';
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    $count_sql = "SELECT COUNT(*) AS total FROM loan_portfolio l LEFT JOIN members m ON m.member_id = l.member_id $where_clause";
    
    if ($types) {
        @$stmt = $conn->prepare($count_sql);
        @$stmt->bind_param($types, ...$params);
    } else {
        @$stmt = $conn->prepare($count_sql);
    }

    @$stmt->execute();
    $total_filtered = (int)$stmt->get_result()->fetch_assoc()['total'];
    @$stmt->close();

    $total_pages = max(1, ceil($total_filtered / $limit));
    $response['pagination']['total_pages'] = $total_pages;
    $response['pagination']['total_records'] = $total_filtered;

    $risk_counts = ['Low' => 0, 'Medium' => 0, 'High' => 0];

    $fetch_sql = "
        SELECT l.loan_id, l.loan_code, l.member_id, l.loan_type, l.principal_amount, 
               l.interest_rate, l.loan_term, l.start_date, l.end_date, l.status,
               COALESCE(m.full_name, 'Unknown') AS member_name
        FROM loan_portfolio l
        LEFT JOIN members m ON m.member_id = l.member_id
        $where_clause
        ORDER BY l.loan_id DESC
        LIMIT ? OFFSET ?
    ";

    if ($types) {
        @$stmt = $conn->prepare($fetch_sql);
        @$stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
    } else {
        @$stmt = $conn->prepare($fetch_sql);
        @$stmt->bind_param('ii', $limit, $offset);
    }

    @$stmt->execute();
    $result = $stmt->get_result();

    while ($loan = $result->fetch_assoc()) {
        $loan_id = (int)$loan['loan_id'];
        $loan_code = $loan['loan_code'];
        
        $amounts = calculateTotalAmountDue($conn, (float)$loan['principal_amount'], (float)$loan['interest_rate'], (int)$loan['loan_term'], $loan_code);

        $overdue_count = 0;
        if ($table_exists) {
            if (!empty($loan_code)) {
                @$stmt2 = $conn->prepare("SELECT COUNT(*) AS overdue_count FROM loan_schedule WHERE loan_code = ? AND (status='Overdue' OR (due_date<CURDATE() AND amount_paid < amount_due))");
                @$stmt2->bind_param("s", $loan_code);
            } else {
                @$stmt2 = $conn->prepare("SELECT COUNT(*) AS overdue_count FROM loan_schedule WHERE loan_id = ? AND (status='Overdue' OR (due_date<CURDATE() AND amount_paid < amount_due))");
                @$stmt2->bind_param("i", $loan_id);
            }
            @$stmt2->execute();
            $overdue_count = (int)$stmt2->get_result()->fetch_assoc()['overdue_count'];
            @$stmt2->close();
        }

        $next_due = '-';
        if ($table_exists) {
            if (!empty($loan_code)) {
                @$stmt2 = $conn->prepare("SELECT due_date FROM loan_schedule WHERE loan_code = ? AND status <> 'Paid' ORDER BY due_date ASC LIMIT 1");
                @$stmt2->bind_param("s", $loan_code);
            } else {
                @$stmt2 = $conn->prepare("SELECT due_date FROM loan_schedule WHERE loan_id = ? AND status <> 'Paid' ORDER BY due_date ASC LIMIT 1");
                @$stmt2->bind_param("i", $loan_id);
            }
            @$stmt2->execute();
            $result2 = $stmt2->get_result();
            $next_due_row = $result2->fetch_assoc();
            $next_due = $next_due_row ? date('d M Y', strtotime($next_due_row['due_date'])) : '-';
            @$stmt2->close();
        }

        $risk_level = 'Low';
        if ($loan['status'] === 'Defaulted' || $overdue_count >= 2) $risk_level = 'High';
        else if ($overdue_count === 1) $risk_level = 'Medium';
        $risk_counts[$risk_level]++;

        if ($risk !== '' && $risk_level !== $risk) continue;

        $response['loans'][] = [
            'loan_id'          => $loan_id,
            'loan_code'        => $loan_code,
            'member_id'        => (int)$loan['member_id'],
            'member_name'      => htmlspecialchars($loan['member_name'], ENT_QUOTES, 'UTF-8'),
            'loan_type'        => htmlspecialchars($loan['loan_type'], ENT_QUOTES, 'UTF-8'),
            'principal_amount' => (float)$loan['principal_amount'],
            'interest_rate'    => (float)$loan['interest_rate'],
            'loan_term'        => (int)$loan['loan_term'],
            'start_date'       => $loan['start_date'] ? date('d M Y', strtotime($loan['start_date'])) : '-',
            'end_date'         => $loan['end_date'] ? date('d M Y', strtotime($loan['end_date'])) : '-',
            'status'           => $loan['status'],
            'overdue_count'    => $overdue_count,
            'risk_level'       => $risk_level,
            'next_due'         => $next_due,
            'total_interest'   => round($amounts['total_interest'], 2),
            'total_penalties'  => round($amounts['total_penalties'], 2),
            'total_amount_due' => round($amounts['total_amount_due'], 2)
        ];
    }
    @$stmt->close();

    $response['risk_breakdown']['labels'] = array_keys($risk_counts);
    $response['risk_breakdown']['values'] = array_values($risk_counts);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
}

if ($response['success'] && empty($response['message'])) {
    $response['message'] = 'Successfully loaded ' . count($response['loans']) . ' loans';
}

// ═══════════════════════════════════════════════════════════════
// PDF EXPORT WITH PROPER FORMATTING
// ═══════════════════════════════════════════════════════════════

if ($is_pdf_export) {
    
    if (!loadTCPDF()) {
        error_log("TCPDF library not found.");
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'PDF export failed: TCPDF library not found. Run: composer require tecnickcom/tcpdf'
        ]);
        exit;
    }
    
    if (!class_exists('LoanPortfolioExportPDF')) {
        class LoanPortfolioExportPDF extends TCPDF
        {
            public function Header(): void
            {
                $this->SetFillColor(5, 150, 105);
                $this->SetDrawColor(5, 150, 105);
                $this->RoundedRect(10, 8, 277, 20, 2, '1111', 'FD');
                
                $logoPath = __DIR__ . '/../../dist/img/logo.jpg';
                if (is_file($logoPath)) {
                    $this->Image($logoPath, 13, 10, 16, 16, 'JPG');
                }
                
                $this->SetTextColor(255, 255, 255);
                $this->SetXY(32, 12);
                $this->SetFont('helvetica', 'B', 13);
                $this->Cell(0, 6, 'Golden Horizons Cooperative', 0, 1, 'L');
                $this->SetX(32);
                $this->SetFont('helvetica', '', 9);
                $this->Cell(0, 5, 'Loan Portfolio & Risk Management Report', 0, 0, 'L');
            }

            public function Footer(): void
            {
                $this->SetY(-12);
                $this->SetFont('helvetica', 'I', 8);
                $this->SetTextColor(5, 150, 105);
                $this->Cell(0, 8, 'Confidential • Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
            }
        }
    }
    
    try {
        $pdf_password = isset($_GET['pdf_password']) ? $_GET['pdf_password'] : '';
        
        if (strlen($pdf_password) < 6) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'PDF password must be at least 6 characters.']);
            exit;
        }
        
        $pdf = new LoanPortfolioExportPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        
        $pdf->SetCreator('Golden Horizons Cooperative');
        $pdf->SetAuthor('Loan Portfolio System');
        $pdf->SetTitle('Loan Portfolio & Risk Management Report');
        
        $ownerPassword = md5(uniqid(mt_rand(), true));
        $pdf->SetProtection(['print', 'copy'], $pdf_password, $ownerPassword, 0, null);
        
        $pdf->SetMargins(10, 32, 10);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();
        
        $pdf->SetTextColor(34, 34, 34);
        $pdf->SetFillColor(220, 252, 231);
        
        // Filter info
        $filterInfo = [];
        if ($cardFilter !== 'all') {
            $filterInfo[] = 'Filter: ' . ucfirst($cardFilter) . ' Loans';
        }
        if ($status !== '') {
            $filterInfo[] = 'Status: ' . $status;
        }
        if ($risk !== '') {
            $filterInfo[] = 'Risk: ' . $risk;
        }
        if ($type !== '') {
            $filterInfo[] = 'Type: ' . $type;
        }
        
        $pdf->SetFont('helvetica', 'B', 9);
        if (!empty($filterInfo)) {
            $pdf->Cell(0, 7, implode(' | ', $filterInfo), 0, 1, 'L', true);
        }
        $pdf->Cell(0, 7, 'Generated: ' . date('F d, Y h:i A'), 0, 1, 'L', true);
        $pdf->Ln(3);
        
        // Summary with improved formatting
        $summary_html = '
        <table border="1" cellpadding="6" cellspacing="0" style="border-color: #059669;">
            <tr style="background-color: #14532d; color: #ffffff;">
                <th width="25%" align="center"><strong>Total Loans</strong></th>
                <th width="25%" align="center"><strong>Active Loans</strong></th>
                <th width="25%" align="center"><strong>Overdue Loans</strong></th>
                <th width="25%" align="center"><strong>Defaulted Loans</strong></th>
            </tr>
            <tr style="background-color: #f0fdf4;">
                <td align="center" style="font-size: 18px; color: #3b82f6;"><strong>' . number_format($response['summary']['total_loans']) . '</strong></td>
                <td align="center" style="font-size: 18px; color: #059669;"><strong>' . number_format($response['summary']['active_loans']) . '</strong></td>
                <td align="center" style="font-size: 18px; color: #f59e0b;"><strong>' . number_format($response['summary']['overdue_loans']) . '</strong></td>
                <td align="center" style="font-size: 18px; color: #ef4444;"><strong>' . number_format($response['summary']['defaulted_loans']) . '</strong></td>
            </tr>
        </table><br><br>';
        
        $pdf->writeHTML($summary_html, true, false, true, false, '');
        
        // Loan Details Table with PROPER FORMATTING
        $table_html = '
        <style>
            table { border-collapse: collapse; width: 100%; }
            th { 
                background-color: #14532d; 
                color: #ffffff; 
                font-size: 7px; 
                font-weight: bold; 
                padding: 5px 3px; 
                border: 1px solid #166534; 
                text-align: center;
                vertical-align: middle;
            }
            td { 
                font-size: 7px; 
                color: #1f2937; 
                padding: 4px 3px; 
                border: 1px solid #bbf7d0;
                vertical-align: middle;
            }
            .row-light { background-color: #f0fdf4; }
            .row-alt { background-color: #dcfce7; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .amount { font-family: "Courier New", monospace; }
            .negative { color: #ef4444; }
            .positive { color: #059669; }
        </style>
        <table cellpadding="3">
            <thead>
                <tr>
                    <th width="5%">Code</th>
                    <th width="10%">Member</th>
                    <th width="9%">Type</th>
                    <th width="8%">Principal</th>
                    <th width="6%">Interest<br>Rate</th>
                    <th width="4%">Term<br>(mo)</th>
                    <th width="8%">Interest<br>Amount</th>
                    <th width="7%">Penalties</th>
                    <th width="9%">Total<br>Amount Due</th>
                    <th width="6%">Start<br>Date</th>
                    <th width="6%">End<br>Date</th>
                    <th width="7%">Status</th>
                    <th width="5%">Over-<br>due</th>
                    <th width="5%">Risk</th>
                    <th width="5%">Next<br>Due</th>
                </tr>
            </thead>
            <tbody>';
        
        $n = 1;
        $hasRows = false;
        
        // Totals for summary at bottom
        $totalPrincipal = 0;
        $totalInterest = 0;
        $totalPenalties = 0;
        $totalAmountDue = 0;
        
        foreach ($response['loans'] as $loan) {
            $hasRows = true;
            $rowClass = ($n % 2 === 0) ? 'row-alt' : 'row-light';
            
            // Accumulate totals
            $totalPrincipal += $loan['principal_amount'];
            $totalInterest += $loan['total_interest'];
            $totalPenalties += $loan['total_penalties'];
            $totalAmountDue += $loan['total_amount_due'];
            
            // Format amounts with proper accounting format
            $principalFormatted = formatCurrency($loan['principal_amount']);
            $interestRateFormatted = formatInterestRate($loan['interest_rate']);
            $interestAmountFormatted = formatCurrency($loan['total_interest']);
            $penaltiesFormatted = formatCurrency($loan['total_penalties']);
            $totalDueFormatted = formatCurrency($loan['total_amount_due']);
            
            // Color code for penalties (red if > 0)
            $penaltyClass = $loan['total_penalties'] > 0 ? 'negative' : '';
            
            // Status color coding
            $statusBg = '';
            switch ($loan['status']) {
                case 'Active': $statusBg = 'background-color: #dcfce7;'; break;
                case 'Completed': $statusBg = 'background-color: #dbeafe;'; break;
                case 'Defaulted': $statusBg = 'background-color: #fee2e2;'; break;
                case 'Pending': $statusBg = 'background-color: #fef3c7;'; break;
            }
            
            // Risk color coding
            $riskBg = '';
            switch ($loan['risk_level']) {
                case 'Low': $riskBg = 'background-color: #dcfce7;'; break;
                case 'Medium': $riskBg = 'background-color: #fef3c7;'; break;
                case 'High': $riskBg = 'background-color: #fee2e2;'; break;
            }
            
            $table_html .= '<tr class="' . $rowClass . '">'
                . '<td class="text-center">' . htmlspecialchars($loan['loan_code'] ?: 'OLD-' . $loan['loan_id'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="text-left">' . htmlspecialchars($loan['member_name'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="text-left">' . htmlspecialchars($loan['loan_type'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="text-right amount">' . $principalFormatted . '</td>'
                . '<td class="text-center"><strong>' . $interestRateFormatted . '</strong></td>'
                . '<td class="text-center">' . $loan['loan_term'] . '</td>'
                . '<td class="text-right amount">' . $interestAmountFormatted . '</td>'
                . '<td class="text-right amount ' . $penaltyClass . '">' . $penaltiesFormatted . '</td>'
                . '<td class="text-right amount"><strong>' . $totalDueFormatted . '</strong></td>'
                . '<td class="text-center">' . htmlspecialchars($loan['start_date'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="text-center">' . htmlspecialchars($loan['end_date'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="text-center" style="' . $statusBg . '"><strong>' . htmlspecialchars($loan['status'], ENT_QUOTES, 'UTF-8') . '</strong></td>'
                . '<td class="text-center">' . ($loan['overdue_count'] > 0 ? '<strong style="color: #ef4444;">' . $loan['overdue_count'] . '</strong>' : '0') . '</td>'
                . '<td class="text-center" style="' . $riskBg . '"><strong>' . htmlspecialchars($loan['risk_level'], ENT_QUOTES, 'UTF-8') . '</strong></td>'
                . '<td class="text-center" style="font-size: 6px;">' . htmlspecialchars($loan['next_due'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
            $n++;
        }
        
        if (!$hasRows) {
            $table_html .= '<tr class="row-light"><td colspan="15" class="text-center" style="padding: 15px;">No loan records found for the selected filters.</td></tr>';
        } else {
            // Add totals row
            $table_html .= '<tr style="background-color: #14532d; color: #ffffff; font-weight: bold;">
                <td colspan="3" class="text-right">TOTALS:</td>
                <td class="text-right amount">' . formatCurrency($totalPrincipal) . '</td>
                <td colspan="2"></td>
                <td class="text-right amount">' . formatCurrency($totalInterest) . '</td>
                <td class="text-right amount">' . formatCurrency($totalPenalties) . '</td>
                <td class="text-right amount">' . formatCurrency($totalAmountDue) . '</td>
                <td colspan="6"></td>
            </tr>';
        }
        
        $table_html .= '</tbody></table>';
        
        $pdf->writeHTML($table_html, true, false, true, false, '');
        
        // Footer note
        $pdf->Ln(5);
        $footer_html = '<hr style="border: 1px solid #059669;"><p style="text-align: center; font-size: 8px; color: #6b7280;">
            <strong>Report Summary:</strong> ' . count($response['loans']) . ' loan record(s) | 
            <strong>Total Portfolio Value:</strong> ' . formatCurrency($totalPrincipal) . ' | 
            <strong>Total Interest:</strong> ' . formatCurrency($totalInterest) . ' | 
            <strong>Total Penalties:</strong> ' . formatCurrency($totalPenalties) . '<br>
            Generated by Golden Horizons Cooperative Loan Portfolio System on ' . date('F d, Y') . ' at ' . date('h:i A') . '
        </p>';
        
        $pdf->writeHTML($footer_html, true, false, true, false, '');
        
        $filename = 'Loan_Portfolio_Report_' . date('Y-m-d_His') . '.pdf';
        outputPdfDownload($pdf, $filename);
        
    } catch (Exception $e) {
        error_log("PDF Export Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'PDF export failed: ' . $e->getMessage()]);
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════
// JSON OUTPUT
// ═══════════════════════════════════════════════════════════════

@ob_end_clean();
echo json_encode($response);
exit;