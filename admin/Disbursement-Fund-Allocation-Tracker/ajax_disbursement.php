<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../initialize_coreT2.php');

header('Content-Type: application/json; charset=utf-8');

// CHECK AUTHENTICATION - Your system uses $_SESSION['userdata']
if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
    error_log("ajax_disbursement.php - Authentication failed - no userdata in session");
    http_response_code(401);
    echo json_encode([
        'error' => true,
        'message' => 'Unauthorized - Please login again'
    ]);
    exit;
}

// Update last activity time (for your session timeout system)
if (isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

// Initialize response
$response = [
    'error' => false,
    'message' => 'Success',
    'disbursements' => [],
    'all_disbursements' => [],
    'summary' => [
        'total' => 0,
        'released' => 0,
        'pending' => 0,
        'cancelled' => 0,
        'total_amount' => 0
    ],
    'fund_sources' => [],
    'pagination' => [
        'current_page' => 1,
        'total_pages' => 1,
        'total_records' => 0,
        'limit' => 10
    ]
];

try {
    // Check if database connection exists
    if (!isset($conn)) {
        throw new Exception("Database connection not found");
    }

    // Get parameters
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(10, min(100, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    // Get filter parameters
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $fund = trim($_GET['fund'] ?? '');
    $date = trim($_GET['date'] ?? '');
    $cardFilter = trim($_GET['cardFilter'] ?? 'all');

    // Build WHERE clause for filters
    $whereConditions = [];
    $params = [];
    $types = '';

    if (!empty($search)) {
        // PATCHED: search by member full_name via JOIN
        $whereConditions[] = "(d.loan_id LIKE ? OR m.full_name LIKE ? OR d.fund_source LIKE ? OR d.remarks LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ssss';
    }

    // Status filter (from dropdown OR card click)
    if (!empty($status)) {
        $whereConditions[] = "d.status = ?";
        $params[] = $status;
        $types .= 's';
    } elseif ($cardFilter !== 'all' && in_array($cardFilter, ['Released', 'Pending', 'Cancelled'])) {
        $whereConditions[] = "d.status = ?";
        $params[] = $cardFilter;
        $types .= 's';
    }

    if (!empty($fund)) {
        $whereConditions[] = "d.fund_source = ?";
        $params[] = $fund;
        $types .= 's';
    }

    if (!empty($date)) {
        $whereConditions[] = "DATE(d.disbursement_date) >= ?";
        $params[] = $date;
        $types .= 's';
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get summary statistics (ALWAYS all records, no filters)
    $summaryQuery = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Released' THEN 1 ELSE 0 END) as released,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
            COALESCE(SUM(CASE WHEN status = 'Released' THEN amount ELSE 0 END), 0) as total_amount
        FROM disbursements
    ";
    
    $summaryResult = $conn->query($summaryQuery);
    if (!$summaryResult) {
        throw new Exception("Summary query failed: " . $conn->error);
    }
    $summary = $summaryResult->fetch_assoc();

    // PATCHED: count query now uses JOINs so WHERE aliases work
    $countQuery = "SELECT COUNT(*) as total FROM disbursements d LEFT JOIN members m ON d.member_id = m.member_id LEFT JOIN users u ON d.approved_by = u.user_id {$whereClause}";
    
    if (!empty($params)) {
        $countStmt = $conn->prepare($countQuery);
        if ($countStmt) {
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $totalRecords = $countResult->fetch_assoc()['total'];
            $countStmt->close();
        } else {
            throw new Exception("Count query preparation failed: " . $conn->error);
        }
    } else {
        $countResult = $conn->query($countQuery);
        $totalRecords = $countResult->fetch_assoc()['total'];
    }

    $totalPages = ceil($totalRecords / $limit);

    // PATCHED: main query with LEFT JOIN to members + users
    $query = "
        SELECT d.*,
            COALESCE(m.full_name, 'N/A') AS member_name,
            COALESCE(u.full_name, 'Admin') AS approved_by_name
        FROM disbursements d
        LEFT JOIN members m ON d.member_id = m.member_id
        LEFT JOIN users u ON d.approved_by = u.user_id
        {$whereClause}
        ORDER BY d.disbursement_date DESC, d.disbursement_id DESC LIMIT ? OFFSET ?
    ";
    
    // Add limit and offset to params
    $queryParams = $params;
    $queryParams[] = $limit;
    $queryParams[] = $offset;
    $queryTypes = $types . 'ii';

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }

    $stmt->bind_param($queryTypes, ...$queryParams);
    $stmt->execute();
    $result = $stmt->get_result();

    $disbursements = [];
    while ($row = $result->fetch_assoc()) {
        $disbursements[] = [
            'disbursement_id' => $row['disbursement_id'],
            'loan_id' => $row['loan_id'],
            'member_id' => $row['member_id'] ?? '',
            'member_name' => $row['member_name'] ?? 'N/A',       // PATCHED: was $row['member_id']
            'disbursement_date' => date('M d, Y', strtotime($row['disbursement_date'])),
            'amount' => floatval($row['amount']),
            'fund_source' => $row['fund_source'] ?? 'N/A',
            'status' => $row['status'],
            'approved_by' => $row['approved_by'] ?? 0,
            'approved_by_name' => $row['approved_by_name'] ?? 'Admin', // PATCHED: was hardcoded 'Admin'
            'remarks' => $row['remarks'] ?? ''
        ];
    }
    $stmt->close();

    // PATCHED: all disbursements query also uses JOINs
    $allQuery = "
        SELECT d.*,
            COALESCE(m.full_name, 'N/A') AS member_name,
            COALESCE(u.full_name, 'Admin') AS approved_by_name
        FROM disbursements d
        LEFT JOIN members m ON d.member_id = m.member_id
        LEFT JOIN users u ON d.approved_by = u.user_id
        {$whereClause}
        ORDER BY d.disbursement_date DESC, d.disbursement_id DESC
    ";
    
    if (!empty($params)) {
        $allStmt = $conn->prepare($allQuery);
        if ($allStmt) {
            $allStmt->bind_param($types, ...$params);
            $allStmt->execute();
            $allResult = $allStmt->get_result();
        } else {
            throw new Exception("All query preparation failed: " . $conn->error);
        }
    } else {
        $allResult = $conn->query($allQuery);
    }

    $allDisbursements = [];
    while ($row = $allResult->fetch_assoc()) {
        $allDisbursements[] = [
            'disbursement_id' => $row['disbursement_id'],
            'loan_id' => $row['loan_id'],
            'member_id' => $row['member_id'] ?? '',
            'member_name' => $row['member_name'] ?? 'N/A',       // PATCHED
            'disbursement_date' => date('M d, Y', strtotime($row['disbursement_date'])),
            'amount' => floatval($row['amount']),
            'fund_source' => $row['fund_source'] ?? 'N/A',
            'status' => $row['status'],
            'approved_by' => $row['approved_by'] ?? 0,
            'approved_by_name' => $row['approved_by_name'] ?? 'Admin', // PATCHED
            'remarks' => $row['remarks'] ?? ''
        ];
    }
    
    if (isset($allStmt)) {
        $allStmt->close();
    }

    // Get fund sources
    $fundQuery = "SELECT DISTINCT fund_source FROM disbursements WHERE fund_source IS NOT NULL AND fund_source != '' ORDER BY fund_source";
    $fundResult = $conn->query($fundQuery);
    $fundSources = [];
    if ($fundResult) {
        while ($row = $fundResult->fetch_assoc()) {
            $fundSources[] = $row['fund_source'];
        }
    }

    // Build successful response
    $response = [
        'error' => false,
        'disbursements' => $disbursements,
        'all_disbursements' => $allDisbursements,
        'summary' => [
            'total' => intval($summary['total']),
            'released' => intval($summary['released']),
            'pending' => intval($summary['pending']),
            'cancelled' => intval($summary['cancelled']),
            'total_amount' => floatval($summary['total_amount'])
        ],
        'fund_sources' => $fundSources,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => intval($totalRecords),
            'limit' => $limit
        ]
    ];

    error_log("ajax_disbursement.php - Success: Returned " . count($disbursements) . " disbursements");

} catch (Exception $e) {
    error_log("ajax_disbursement.php - Error: " . $e->getMessage());
    http_response_code(500);
    $response = [
        'error' => true,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
?>