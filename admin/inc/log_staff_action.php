<?php
/**
 * log_staff_action()
 * - Saves to audit_trail (Compliance & Audit Trail)
 * - Saves to staff_action_notifications (for Admin/SuperAdmin bell)
 *
 * Usage:
 *   require_once(__DIR__ . '/../inc/log_staff_action.php');
 *   log_staff_action('Payment Recorded', 'Collection Monitoring', 'Ben Wallace nagbayad ng ₱500', $repayment_id);
 */

if (!function_exists('log_staff_action')) {
    function log_staff_action(
        string $action_type,
        string $module_name,
        string $details = '',
        int    $record_id = 0
    ): void {
        global $conn;
        if (!$conn || $conn->connect_error) return;

        $userId   = $_SESSION['userdata']['user_id']  ?? 0;
        $userName = $_SESSION['userdata']['full_name'] ?? 'Staff';
        $userRole = $_SESSION['userdata']['role']      ?? 'Staff';
        $ip       = $_SERVER['REMOTE_ADDR']            ?? 'Unknown';
        $now      = date('Y-m-d H:i:s');

        // ── 1. Save to audit_trail ────────────────────────────────────
        if (function_exists('log_audit_trial')) {
            log_audit_trial($userId, $action_type, $module_name, $details);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO audit_trail (user_id, action_type, module_name, record_id, ip_address, remarks, compliance_status, review_date)
                VALUES (?, ?, ?, ?, ?, ?, 'Compliant', ?)
            ");
            if ($stmt) {
                $stmt->bind_param('ississ s', $userId, $action_type, $module_name, $record_id, $ip, $details, $now);
                $stmt->execute();
                $stmt->close();
            }
        }

        // ── 2. Save to staff_action_notifications ─────────────────────
        $conn->query("
            CREATE TABLE IF NOT EXISTS staff_action_notifications (
                notif_id    INT AUTO_INCREMENT PRIMARY KEY,
                user_id     INT NOT NULL,
                user_name   VARCHAR(100) NOT NULL,
                user_role   VARCHAR(50)  NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                module_name VARCHAR(100) DEFAULT NULL,
                details     TEXT DEFAULT NULL,
                record_id   INT DEFAULT 0,
                created_at  DATETIME DEFAULT NOW(),
                is_read     TINYINT(1) DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $stmt2 = $conn->prepare("
            INSERT INTO staff_action_notifications (user_id, user_name, user_role, action_type, module_name, details, record_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt2) {
            $stmt2->bind_param('isssssi', $userId, $userName, $userRole, $action_type, $module_name, $details, $record_id);
            $stmt2->execute();
            $stmt2->close();
        }
    }
}