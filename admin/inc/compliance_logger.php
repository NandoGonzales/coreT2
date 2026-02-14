<?php
/**
 * compliance_logger.php
 * Shared helper for logging compliance events.
 *
 * DEPENDS ON: log_audit_trial.php  (must be included first so that
 *             determine_compliance_status() is available)
 *
 * USAGE EXAMPLE:
 *   require_once __DIR__ . '/log_audit_trial.php';
 *   require_once __DIR__ . '/compliance_logger.php';
 *
 *   // Auto-status (recommended):
 *   log_compliance($user_id, 'Login Failed - Wrong Password', 'Authentication', 'Bad password from IP: 1.2.3.4');
 *
 *   // Manual-status override:
 *   log_compliance($user_id, 'Large Loan Approved', 'Loan Portfolio', 'Loan #123 PHP 500,000', 'Under Review');
 */

if (!function_exists('log_compliance')) {
    /**
     * Log a compliance + audit event to the audit_trail table.
     *
     * @param int|null    $user_id           User performing the action
     * @param string      $action_type       Action label (e.g. "OTP Sent", "Delete Record")
     * @param string      $module_name       Module name (e.g. "Authentication")
     * @param string      $description       Detailed description / remarks
     * @param string|null $compliance_status Override ('Compliant'|'Non-Compliant'|'Under Review'|'Pending')
     *                                       Pass null (default) to auto-determine from action_type + description.
     * @return bool
     */
    function log_compliance(
        $user_id,
        string $action_type,
        string $module_name,
        string $description = '',
        ?string $compliance_status = null
    ): bool {
        global $conn;

        if (!$conn) {
            error_log("Compliance Log Error: No DB connection");
            return false;
        }

        // Auto-determine status if not provided
        if ($compliance_status === null) {
            // Reuse the same logic from log_audit_trial.php
            if (function_exists('determine_compliance_status')) {
                $compliance_status = determine_compliance_status($action_type, $description);
            } else {
                $compliance_status = 'Compliant'; // safe fallback
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        try {
            $stmt = $conn->prepare("
                INSERT INTO audit_trail
                    (user_id, action_type, module_name, remarks, ip_address, compliance_status, review_date)
                VALUES 
                    (?, ?, ?, ?, ?, ?, NOW())
            ");

            if (!$stmt) {
                error_log("Compliance Log Prepare Failed: " . $conn->error);
                return false;
            }

            $stmt->bind_param("isssss", $user_id, $action_type, $module_name, $description, $ip, $compliance_status);
            $stmt->execute();
            $stmt->close();
            return true;

        } catch (Exception $e) {
            error_log('Compliance Log Error: ' . $e->getMessage());
            return false;
        }
    }
}

// Alias for backward compatibility
if (!function_exists('log_compliance_event')) {
    function log_compliance_event($user_id, $action_type, $module_name, $description, $status = null)
    {
        return log_compliance($user_id, $action_type, $module_name, $description, $status);
    }
}