<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');
require_once(__DIR__ . '/../../classes/HR4EmployeeClient.php');

header('Content-Type: application/json');

// require Super Admin
checkPermission('user_management');

try {
    $client = new HR4EmployeeClient();
    $response = $client->fetchAllEmployees();

    if (!isset($response['status']) || $response['status'] !== 'success' || !isset($response['data']) || !is_array($response['data'])) {
        throw new Exception("Invalid response format from HR4 API");
    }

    $employees = $response['data'];
    $synced_count = 0;
    $updated_count = 0;

    $stmt = $conn->prepare("
        INSERT INTO hr4_employees (
            hr4_employee_id, hr4_internal_id, full_name, email, phone, 
            department, job_title, work_location, employment_status, 
            employment_type, hr_status, hr_updated_at, raw_json, synced_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            hr4_internal_id = VALUES(hr4_internal_id),
            full_name = VALUES(full_name),
            email = VALUES(email),
            phone = VALUES(phone),
            department = VALUES(department),
            job_title = VALUES(job_title),
            work_location = VALUES(work_location),
            employment_status = VALUES(employment_status),
            employment_type = VALUES(employment_type),
            hr_status = VALUES(hr_status),
            hr_updated_at = VALUES(hr_updated_at),
            raw_json = VALUES(raw_json),
            synced_at = NOW()
    ");

    foreach ($employees as $emp) {
        $hr4_employee_id = $emp['employee_id'] ?? null;
        if (!$hr4_employee_id) continue;

        $hr4_internal_id = $emp['id'] ?? null;
        $full_name = $emp['full_name'] ?? null;
        $email = $emp['email'] ?? null;
        $phone = $emp['phone'] ?? null;
        $work_location = $emp['work_location'] ?? null;
        $employment_status = $emp['employment_status'] ?? null;
        $employment_type = $emp['employment_type'] ?? null;
        $hr_status = isset($emp['status']) ? trim($emp['status']) : null;
        
        $department = $emp['position']['department'] ?? null;
        $job_title = $emp['job']['job_title'] ?? null;
        
        $hr_updated_at = null;
        if (!empty($emp['updated_at'])) {
            $hr_updated_at = date('Y-m-d H:i:s', strtotime($emp['updated_at']));
        }

        $raw_json = json_encode($emp);

        $stmt->bind_param(
            "sisssssssssss",
            $hr4_employee_id, $hr4_internal_id, $full_name, $email, $phone,
            $department, $job_title, $work_location, $employment_status,
            $employment_type, $hr_status, $hr_updated_at, $raw_json
        );

        if ($stmt->execute()) {
            $synced_count++;
        }
    }

    $stmt->close();

    // Log the sync activity
    if (function_exists('log_audit')) {
        log_audit(
            $_SESSION['userdata']['user_id'],
            'Sync HR4 Employees',
            'User Management',
            null,
            "Successfully synced $synced_count employees from HR4 directory."
        );
    }

    echo json_encode([
        'status' => 'success',
        'msg' => "Successfully synced $synced_count employees.",
        'count' => $synced_count
    ]);

} catch (Exception $e) {
    error_log("HR4 Sync Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'msg' => "Sync failed: " . $e->getMessage()
    ]);
}
