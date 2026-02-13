<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');

$queries = [
    "CREATE TABLE IF NOT EXISTS hr4_employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hr4_employee_id VARCHAR(30) UNIQUE NOT NULL,
        hr4_internal_id INT NULL,
        full_name VARCHAR(200),
        email VARCHAR(200),
        phone VARCHAR(30),
        department VARCHAR(120),
        job_title VARCHAR(160),
        work_location VARCHAR(120),
        employment_status VARCHAR(50),
        employment_type VARCHAR(50),
        hr_status VARCHAR(30),
        hr_updated_at DATETIME NULL,
        raw_json LONGTEXT,
        synced_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    "CREATE TABLE IF NOT EXISTS user_hr4_link (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        hr4_employee_id VARCHAR(30) NOT NULL UNIQUE,
        linked_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($queries as $q) {
    if (!$conn->query($q)) {
        die("Error creating table: " . $conn->error);
    }
}

echo "HR4 tables checked/created successfully.";
?>
