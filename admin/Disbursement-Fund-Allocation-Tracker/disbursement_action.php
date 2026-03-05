<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../initialize_coreT2.php');
if (file_exists(__DIR__ . '/../inc/log_staff_action.php')) require_once(__DIR__ . '/../inc/log_staff_action.php');

// Header will be set per action

// CHECK AUTHENTICATION
if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
    error_log("disbursement_action.php - Authentication failed - no userdata in session");
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error', 
        'msg' => 'Unauthorized - Please login again'
    ]);
    exit;
}

// Update last activity time
if (isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

// Check database connection
if (!isset($conn)) {
    error_log("disbursement_action.php - Database connection not found");
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'msg' => 'Database connection failed'
    ]);
    exit;
}

// ══════════════════════════════════════════════════════════════
// CORE1 SYNC CONFIGURATION
// ══════════════════════════════════════════════════════════════
define('CORE1_UPDATE_API', 'https://core1.microfinancial-1.com/api/disbursement/update_status.php');
define('ENABLE_CORE1_SYNC', true); // Set to false to disable sync

/**
 * Send disbursement approval to Core1
 */
function syncToCore1($loanCode, $amount, $date, $approvedBy) {
    if (!ENABLE_CORE1_SYNC || empty($loanCode)) {
        return ['success' => false, 'message' => 'Sync disabled or no loan_code'];
    }
    
    $payload = json_encode([
        'loan_code' => $loanCode,
        'disbursement_status' => 'disbursed',
        'disbursement_date' => $date,
        'disbursed_amount' => $amount,
        'approved_by' => $approvedBy,
        'source' => 'Core2 Financial'
    ]);
    
    $ch = curl_init(CORE1_UPDATE_API);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'message' => "cURL Error: $curlError", 'http_code' => 0];
    }
    
    $responseData = json_decode($response, true);
    
    return [
        'success' => ($httpCode === 200 && isset($responseData['success']) && $responseData['success']),
        'message' => $responseData['message'] ?? 'Unknown response',
        'http_code' => $httpCode,
        'response' => $responseData
    ];
}

// Load TCPDF only when needed
function loadTCPDF() {
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

function outputPdfDownload($pdf, string $filename): void {
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

// ── Real-time Finance Approval Check (polling) ───────────────────
if (isset($_GET['check_finance_approved'])) {
    header('Content-Type: application/json; charset=utf-8');
    $res = $conn->query("
        SELECT d.disbursement_id, d.amount, d.status,
               m.full_name AS member_name
        FROM disbursements d
        LEFT JOIN members m ON d.member_id = m.member_id
        WHERE d.status = 'Finance Approved'
        ORDER BY d.created_at DESC
        LIMIT 20
    ");
    $ids = [];
    $records = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = $row['disbursement_id'];
            $records[] = $row;
        }
    }
    echo json_encode(['ids' => $ids, 'records' => $records]);
    exit;
}

try {
    // ══════════════════════════════════════════════════════════════
    // PDF EXPORT
    // ══════════════════════════════════════════════════════════════
    if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
        $fundFilter = isset($_GET['fund']) ? trim($_GET['fund']) : '';
        $dateFilter = isset($_GET['date']) ? trim($_GET['date']) : '';
        $cardFilter = isset($_GET['cardFilter']) ? trim($_GET['cardFilter']) : 'all';
        $pdfPassword = isset($_GET['pdf_password']) ? trim($_GET['pdf_password']) : '';

        if (strlen($pdfPassword) < 6) {
            throw new Exception("PDF password must be at least 6 characters.");
        }

        if (!loadTCPDF()) {
            throw new Exception("TCPDF library not found.");
        }

        if (!class_exists('DisbursementExportPDF')) {
            class DisbursementExportPDF extends TCPDF {
                public function Header(): void {
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
                    $this->Cell(0, 5, 'Disbursement Tracker Report', 0, 0, 'L');
                }

                public function Footer(): void {
                    $this->SetY(-12);
                    $this->SetFont('helvetica', 'I', 8);
                    $this->SetTextColor(20, 83, 45);
                    $this->Cell(0, 8, 'Confidential • Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
                }
            }
        }

        // Build WHERE
        $whereSql = "WHERE 1=1";
        if ($search) {
            $s = $conn->real_escape_string($search);
            $whereSql .= " AND (d.disbursement_id LIKE '%$s%' OR d.loan_id LIKE '%$s%' OR m.full_name LIKE '%$s%' OR d.fund_source LIKE '%$s%')";
        }
        if ($statusFilter) {
            $st = $conn->real_escape_string($statusFilter);
            $whereSql .= " AND d.status = '$st'";
        }
        if ($fundFilter) {
            $f = $conn->real_escape_string($fundFilter);
            $whereSql .= " AND d.fund_source = '$f'";
        }
        if ($dateFilter) {
            $dt = $conn->real_escape_string($dateFilter);
            $whereSql .= " AND d.disbursement_date = '$dt'";
        }
        if ($cardFilter !== 'all') {
            $c = $conn->real_escape_string($cardFilter);
            $whereSql .= " AND d.status = '$c'";
        }

        $sql = "SELECT d.*, m.full_name as member_name, u.full_name as approved_by_name 
                FROM disbursements d 
                LEFT JOIN members m ON d.member_id = m.member_id 
                LEFT JOIN users u ON d.approved_by = u.user_id 
                $whereSql ORDER BY d.disbursement_date DESC";
        $result = $conn->query($sql);

        $pdf = new DisbursementExportPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Microfinance EIS');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Disbursement Tracker Report');

        $ownerPassword = md5(uniqid(mt_rand(), true));
        $pdf->SetProtection(['print', 'copy'], $pdfPassword, $ownerPassword, 0, null);

        $pdf->SetMargins(10, 32, 10);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();

        $pdf->SetTextColor(34, 34, 34);
        $pdf->SetFillColor(220, 252, 231);

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
                        <th width="10%">Loan ID</th>
                        <th width="20%">Member</th>
                        <th width="12%">Date</th>
                        <th width="12%">Amount</th>
                        <th width="14%">Fund Source</th>
                        <th width="14%">Approved By</th>
                        <th width="10%">Status</th>
                    </tr>
                </thead>
                <tbody>';

        $n = 0;
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rowClass = ($n % 2 === 0) ? 'row-alt' : 'row-light';
                $html .= '<tr class="' . $rowClass . '">'
                    . '<td class="center">' . $row['disbursement_id'] . '</td>'
                    . '<td class="center">' . $row['loan_id'] . '</td>'
                    . '<td>' . htmlspecialchars($row['member_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td class="center">' . $row['disbursement_date'] . '</td>'
                    . '<td class="center">₱' . number_format($row['amount'], 2) . '</td>'
                    . '<td>' . htmlspecialchars($row['fund_source'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($row['approved_by_name'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td class="center">' . $row['status'] . '</td>'
                    . '</tr>';
                $n++;
            }
        } else {
            $html .= '<tr class="row-light"><td colspan="8" class="center">No records found.</td></tr>';
        }

        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        
        outputPdfDownload($pdf, 'disbursement_tracker_' . date('Y-m-d_His') . '.pdf');
    }

    // ══════════════════════════════════════════════════════════════
    // CSV EXPORT
    // ══════════════════════════════════════════════════════════════
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        while (ob_get_level() > 0) ob_end_clean();
        
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $fund = $_GET['fund'] ?? '';
        $date = $_GET['date'] ?? '';
        $cardFilter = $_GET['cardFilter'] ?? 'all';
        $exportPassword = trim($_GET['pdf_password'] ?? '');

        $where = ["1=1"];
        $params = [];
        $types = "";

        if ($search !== "") {
            $where[] = "(d.disbursement_id LIKE ? OR d.loan_id LIKE ? OR m.full_name LIKE ? OR d.fund_source LIKE ?)";
            $s = "%$search%";
            $params = array_merge($params, [$s, $s, $s, $s]);
            $types .= "ssss";
        }
        if ($status !== "") {
            $where[] = "d.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        if ($fund !== "") {
            $where[] = "d.fund_source = ?";
            $params[] = $fund;
            $types .= "s";
        }
        if ($date !== "") {
            $where[] = "d.disbursement_date = ?";
            $params[] = $date;
            $types .= "s";
        }
        if ($cardFilter !== "all" && $cardFilter !== "") {
            if ($cardFilter === "released") $where[] = "d.status = 'Released'";
            elseif ($cardFilter === "pending") $where[] = "d.status = 'Pending'";
        }

        $sql = "SELECT d.*, m.full_name as member_name, u.full_name as approved_by_name 
                FROM disbursements d 
                LEFT JOIN members m ON d.member_id = m.member_id 
                LEFT JOIN users u ON d.approved_by = u.user_id 
                WHERE " . implode(" AND ", $where) . " 
                ORDER BY d.disbursement_id DESC";

        $stmt = $conn->prepare($sql);
        if ($types !== "") $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $filename_base = 'disbursements_export_' . date('Y-m-d_His');
        $csv_filename = $filename_base . '.csv';

        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', 'Loan ID', 'Member', 'Date', 'Amount', 'Fund Source', 'Approved By', 'Status', 'Remarks']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['disbursement_id'],
                $row['loan_id'],
                $row['member_name'] ?? 'N/A',
                $row['disbursement_date'],
                $row['amount'],
                $row['fund_source'] ?? '-',
                $row['approved_by_name'] ?? '-',
                $row['status'],
                $row['remarks'] ?? ''
            ]);
        }
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        $stmt->close();

        if ($exportPassword !== '') {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                $zip_filename = $filename_base . '.zip';
                $temp_file = tempnam(sys_get_temp_dir(), 'zip');
                if ($zip->open($temp_file, ZipArchive::CREATE) === TRUE) {
                    $zip->addFromString($csv_filename, $csv_content);
                    if (method_exists($zip, 'setEncryptionName')) {
                        $zip->setEncryptionName($csv_filename, ZipArchive::EM_AES_256, $exportPassword);
                    }
                    $zip->close();
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
                    header('Content-Length: ' . filesize($temp_file));
                    readfile($temp_file);
                    unlink($temp_file);
                    exit;
                }
            }
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csv_filename . '"');
        echo $csv_content;
        exit;
    }

    // ══════════════════════════════════════════════════════════════
    // APPROVE ACTION
    // ══════════════════════════════════════════════════════════════
    $action = $_POST['action'] ?? '';
    $disbursementId = (int)($_POST['id'] ?? 0);
    
    $userId = $_SESSION['userdata']['user_id'] ?? 0;
    $userName = $_SESSION['userdata']['full_name'] ?? 'Unknown User';
    
    error_log("disbursement_action.php - Action: {$action}, ID: {$disbursementId}, User: {$userId} ({$userName})");
    
    if (empty($disbursementId)) {
        throw new Exception('Disbursement ID is required');
    }
    
    if ($action === 'approve') {
        $conn->begin_transaction();
        
        try {
            // Get disbursement details including loan_code
            $checkStmt = $conn->prepare("
                SELECT 
                    d.status, 
                    d.loan_id, 
                    d.amount,
                    d.disbursement_date,
                    lp.loan_code
                FROM disbursements d
                LEFT JOIN loan_portfolio lp ON d.loan_id = lp.loan_id
                WHERE d.disbursement_id = ?
            ");
            
            if (!$checkStmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $checkStmt->bind_param('s', $disbursementId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows === 0) {
                $checkStmt->close();
                throw new Exception('Disbursement not found');
            }
            
            $disbursement = $checkResult->fetch_assoc();
            $checkStmt->close();
            
            // Allow release from Pending OR Finance Approved
            if (!in_array($disbursement['status'], ['Pending', 'Finance Approved'])) {
                throw new Exception('Only Pending or Finance Approved disbursements can be released. Current status: ' . $disbursement['status']);
            }
            
            $loanCode = $disbursement['loan_code'];
            $amount = $disbursement['amount'];
            $disbDate = $disbursement['disbursement_date'];
            
            // Update disbursement status
            $columnsResult = $conn->query("SHOW COLUMNS FROM disbursements LIKE 'approved_by'");
            
            if ($columnsResult && $columnsResult->num_rows > 0) {
                $updateStmt = $conn->prepare("
                    UPDATE disbursements 
                    SET status = 'Released', 
                        approved_by = ?
                    WHERE disbursement_id = ?
                ");
                if (!$updateStmt) {
                    throw new Exception("Prepare update failed: " . $conn->error);
                }
                $updateStmt->bind_param('ii', $userId, $disbursementId);
            } else {
                $updateStmt = $conn->prepare("
                    UPDATE disbursements 
                    SET status = 'Released'
                    WHERE disbursement_id = ?
                ");
                if (!$updateStmt) {
                    throw new Exception("Prepare update failed: " . $conn->error);
                }
                $updateStmt->bind_param('i', $disbursementId);
            }
            
            $result = $updateStmt->execute();
            
            if (!$result) {
                error_log("disbursement_action.php - Update failed: " . $conn->error);
                throw new Exception('Database update failed: ' . $conn->error);
            }
            
            $affectedRows = $updateStmt->affected_rows;
            $updateStmt->close();
            
            if ($affectedRows === 0) {
                throw new Exception('No rows were updated');
            }
            
            // ══════════════════════════════════════════════════════════════
            // 🆕 SYNC TO CORE1
            // ══════════════════════════════════════════════════════════════
            $syncResult = null;
            $syncMessage = '';
            
            if (!empty($loanCode) && ENABLE_CORE1_SYNC) {
                $syncResult = syncToCore1($loanCode, $amount, $disbDate, $userName);
                
                // Create sync log table if not exists
                $conn->query("
                    CREATE TABLE IF NOT EXISTS disbursement_core1_sync_log (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        disbursement_id INT,
                        loan_code VARCHAR(50),
                        http_code INT DEFAULT 0,
                        success TINYINT(1) DEFAULT 0,
                        message TEXT,
                        response TEXT,
                        synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_disbursement_id (disbursement_id),
                        INDEX idx_loan_code (loan_code)
                    )
                ");
                
                // Log sync attempt
                $loanCodeEsc = $conn->real_escape_string($loanCode);
                $msgEsc = $conn->real_escape_string($syncResult['message'] ?? '');
                $respEsc = $conn->real_escape_string(json_encode($syncResult['response'] ?? []));
                $httpCode = intval($syncResult['http_code'] ?? 0);
                $syncSuccess = $syncResult['success'] ? 1 : 0;
                
                $conn->query("
                    INSERT INTO disbursement_core1_sync_log 
                        (disbursement_id, loan_code, http_code, success, message, response)
                    VALUES 
                        ($disbursementId, '$loanCodeEsc', $httpCode, $syncSuccess, '$msgEsc', '$respEsc')
                ");
                
                if ($syncResult['success']) {
                    $syncMessage = ' (Synced to Core1 ✓)';
                    error_log("Core1 sync successful for loan_code: $loanCode");
                } else {
                    $syncMessage = ' (Core1 sync failed - will retry)';
                    error_log("Core1 sync failed for loan_code: $loanCode - " . $syncResult['message']);
                }
            } elseif (empty($loanCode)) {
                error_log("No loan_code found for disbursement_id: $disbursementId - cannot sync to Core1");
                $syncMessage = ' (No loan_code - sync skipped)';
            }
            
            // Log audit
            if (function_exists('log_audit')) {
                log_audit(
                    $userId,
                    'Approve Disbursement',
                    'Disbursement Tracker',
                    $disbursementId,
                    "User {$userName} approved disbursement #{$disbursementId}" . (!empty($loanCode) ? " for loan {$loanCode}" : "") . $syncMessage
                );
            }
            
            $conn->commit();
            
            error_log("disbursement_action.php - Disbursement {$disbursementId} approved successfully by user {$userId}" . $syncMessage);
            if (function_exists('log_staff_action')) {
                log_staff_action('Disbursement Released', 'Disbursement Tracker', "Disbursement #$disbursementId" . (!empty($loanCode) ? " | Loan: $loanCode" : '') . $syncMessage, (int)$disbursementId);
            }
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'ok', 
                'msg' => 'Disbursement released successfully' . $syncMessage,
                'core1_sync' => $syncResult
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("disbursement_action.php - Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error', 
        'msg' => $e->getMessage()
    ]);
}
?>