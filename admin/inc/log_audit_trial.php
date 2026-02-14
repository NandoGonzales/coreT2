<?php
/**
 * Log Audit Trail Function
 * Logs user actions to the audit_trail table
 * 
 * ✅ UPDATED: Auto-determines compliance_status based on action_type
 * 
 * COMPLIANCE STATUS RULES:
 * ✅ Compliant     - Successful, normal, authorized actions
 * ❌ Non-Compliant - Failed logins, wrong OTP, unauthorized attempts, security violations
 * 🟡 Under Review  - Large transactions, AI results, actions needing manual verification
 * 🟠 Pending       - Actions initiated but not yet completed/approved
 */

if (!function_exists('determine_compliance_status')) {
    /**
     * Automatically determine compliance status based on action_type and remarks.
     *
     * @param string $action_type  The action performed
     * @param string|null $remarks Additional context
     * @return string 'Compliant' | 'Non-Compliant' | 'Under Review' | 'Pending'
     */
    function determine_compliance_status(string $action_type, ?string $remarks = null): string
    {
        $action = strtolower(trim($action_type));
        $remark = strtolower(trim($remarks ?? ''));

        // ============================================================
        // ❌ NON-COMPLIANT — Security violations, failed attempts
        // ============================================================
        $nonCompliantKeywords = [
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
            'brute',
            'banned',
            'locked',
            'inactive',
            'unknown user',
            'otp verification failed',
            'login failed',
            'otp failed',
        ];

        foreach ($nonCompliantKeywords as $keyword) {
            if (str_contains($action, $keyword) || str_contains($remark, $keyword)) {
                return 'Non-Compliant';
            }
        }

        // ============================================================
        // 🟡 UNDER REVIEW — Needs manual admin verification
        // ============================================================
        $underReviewKeywords = [
            'large',
            'high amount',
            'high-risk',
            'high risk',
            'ai result',
            'ai scored',
            'credit score',
            'credit scoring',
            'ai decision',
            'manual verification',
            'review needed',
            'flagged',
            'suspicious activity',
            'override',
            'bulk',
            'mass',
            'role change',
            'permission change',
            'high value',
        ];

        foreach ($underReviewKeywords as $keyword) {
            if (str_contains($action, $keyword) || str_contains($remark, $keyword)) {
                return 'Under Review';
            }
        }

        // ============================================================
        // 🟠 PENDING — Action initiated but not yet completed/approved
        // ============================================================
        $pendingKeywords = [
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

        foreach ($pendingKeywords as $keyword) {
            if (str_contains($action, $keyword) || str_contains($remark, $keyword)) {
                return 'Pending';
            }
        }

        // ============================================================
        // ✅ COMPLIANT — Default for all successful, normal actions
        // ============================================================
        return 'Compliant';
    }
}

if (!function_exists('log_audit_trial')) {
    /**
     * Insert a record into the audit_trail table.
     *
     * @param int|null    $user_id         The user performing the action (null for system/unknown)
     * @param string      $action_type     What was done (e.g. "OTP Sent", "Login Failed - Wrong Password")
     * @param string|null $module_name     Which module (e.g. "Authentication", "Loan Portfolio")
     * @param string|null $remarks         Additional details/description
     * @param string|null $compliance_status  Override status (null = auto-determine)
     * @return bool
     */
    function log_audit_trial(
        $user_id,
        string $action_type,
        ?string $module_name = null,
        ?string $remarks = null,
        ?string $compliance_status = null   // ← null means: auto-determine
    ): bool {
        global $conn;

        // Validate database connection
        if (!$conn || $conn->connect_error) {
            error_log("Audit Trail Error: Database connection not available");
            return false;
        }

        // ─── Auto-determine compliance_status if not explicitly provided ───
        if ($compliance_status === null) {
            $compliance_status = determine_compliance_status($action_type, $remarks);
        } else {
            // Validate the manually-provided status
            $validStatuses = ['Compliant', 'Non-Compliant', 'Under Review', 'Pending'];
            if (!in_array($compliance_status, $validStatuses)) {
                $compliance_status = determine_compliance_status($action_type, $remarks);
            }
        }

        // ─── Sanitize user_id ───
        // Set to NULL for invalid/unknown users to avoid FK constraint errors
        if ($user_id === 0 || $user_id === '0' || $user_id === null || $user_id === '') {
            $user_id = null;
        } else {
            $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
            if ($checkStmt) {
                $checkStmt->bind_param("i", $user_id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult->num_rows === 0) {
                    $remarks = ($remarks ? $remarks . " | " : "") . "Invalid user_id: " . $user_id;
                    $user_id = null;
                }

                $checkStmt->close();
            }
        }

        // ─── Insert into audit_trail ───
        try {
            $stmt = $conn->prepare("
                INSERT INTO audit_trail 
                    (user_id, action_type, module_name, ip_address, remarks, compliance_status, review_date)
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                error_log("Audit Trail Error: Failed to prepare statement - " . $conn->error);
                return false;
            }

            $ip_address  = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $review_date = date('Y-m-d H:i:s');

            $stmt->bind_param(
                "issssss",
                $user_id,
                $action_type,
                $module_name,
                $ip_address,
                $remarks,
                $compliance_status,
                $review_date
            );

            $result = $stmt->execute();

            if (!$result) {
                error_log("Audit Trail Error: Failed to execute - " . $stmt->error);
                $stmt->close();
                return false;
            }

            $stmt->close();
            return true;

        } catch (Exception $e) {
            error_log("Audit Trail Exception: " . $e->getMessage());
            return false;
        }
    }
}