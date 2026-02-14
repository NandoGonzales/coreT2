<?php
// ==================================================
// Compliance Logger Helper
// ==================================================

// ✅ determine_compliance_status() is defined HERE because
//    compliance_logger.php is loaded first via initialize_coreT2.php
//    (before log_audit_trial.php). This makes the function available
//    to both files.

if (!function_exists('determine_compliance_status')) {
    function determine_compliance_status(string $action_type, ?string $description = null): string
    {
        $action = strtolower(trim($action_type));
        $desc   = strtolower(trim($description ?? ''));

        // ❌ NON-COMPLIANT — Failed / security violations
        // Includes Low Risk, Medium Risk, High Risk failed attempts
        $nonCompliant = [
            'failed',
            'wrong',
            'invalid',
            'incorrect',
            'unauthorized',
            'denied',
            'blocked',
            'expired',
            'error',
            'rejected',
            'violation',
            'suspicious',
            'banned',
            'locked',
            'inactive',
            'unknown user',
            'low risk',       // ← Non-Compliant pero low risk (typo lang)
            'medium risk',    // ← Non-Compliant + warning
            'high risk',      // ← Non-Compliant + auto-lock
        ];
        foreach ($nonCompliant as $kw) {
            if (str_contains($action, $kw) || str_contains($desc, $kw)) {
                return 'Non-Compliant';
            }
        }

        // 🟡 UNDER REVIEW — Needs admin verification
        $underReview = [
            'large',
            'high amount',
            'ai result',
            'ai scored',
            'credit score',
            'credit scoring',
            'ai decision',
            'manual verification',
            'review needed',
            'flagged',
            'override',
            'bulk',
            'mass',
            'role change',
            'permission change',
            'high value',
        ];
        foreach ($underReview as $kw) {
            if (str_contains($action, $kw) || str_contains($desc, $kw)) {
                return 'Under Review';
            }
        }

        // 🟠 PENDING — Started but not yet completed
        $pending = [
            'otp sent',
            'awaiting',
            'waiting',
            'pending approval',
            'disbursement request',
            'loan request',
            'submitted',
            'queued',
            'in progress',
            'processing',
            'sent for approval',
        ];
        foreach ($pending as $kw) {
            if (str_contains($action, $kw) || str_contains($desc, $kw)) {
                return 'Pending';
            }
        }

        // ✅ COMPLIANT — Default for all successful actions
        return 'Compliant';
    }
}

if (!function_exists('log_compliance')) {
    function log_compliance($user_id, $action_type, $module_name, $description, $status = null)
    {
        global $conn;

        if (!$conn) {
            error_log("Compliance Log Error: No DB connection");
            return false;
        }

        // Auto-determine status if not provided
        if ($status === null) {
            $status = determine_compliance_status($action_type, $description);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        $stmt = $conn->prepare("
            INSERT INTO compliance_logs (user_id, action_type, module_name, description, status, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("Compliance Log Prepare Failed: " . $conn->error);
            return false;
        }

        $stmt->bind_param("isssss", $user_id, $action_type, $module_name, $description, $status, $ip);
        $stmt->execute();
        $stmt->close();
        return true;
    }
}

// Optional wrapper to match old calls
if (!function_exists('log_compliance_event')) {
    function log_compliance_event($user_id, $action_type, $module_name, $description, $status = null)
    {
        return log_compliance($user_id, $action_type, $module_name, $description, $status);
    }
}