<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

header('Content-Type: application/json');

// require Super Admin
checkPermission('user_management');

try {
    $sql = "
        SELECT 
            e.*, 
            l.user_id as linked_user_id,
            u.username as linked_username
        FROM hr4_employees e
        LEFT JOIN user_hr4_link l ON e.hr4_employee_id = l.hr4_employee_id
        LEFT JOIN users u ON l.user_id = u.user_id
        ORDER BY e.full_name ASC
    ";
    
    $result = $conn->query($sql);
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'data' => $employees
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ]);
}
