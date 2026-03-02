<?php
// ==================================================
// Compliance Logger Helper
// ==================================================
// IMPORTANT: determine_compliance_status() is defined
// in ../inc/log_audit_trial.php — that file must be
// loaded BEFORE this one (compliance_logs_action.php
// handles this automatically).
// ==================================================

// --------------------------------------------------
// get_compliance_reason()
// --------------------------------------------------
if (!function_exists('get_compliance_reason')) {
    function get_compliance_reason(string $action_type, ?string $description = null): string
    {
        $action = strtolower(trim($action_type));
        $desc   = strtolower(trim($description ?? ''));

        if (str_contains($action, 'login failed') || str_contains($action, 'wrong password'))
            return 'User attempted to log in with incorrect credentials. This may indicate unauthorized access or a forgotten password.';
        if (str_contains($action, 'otp') && (str_contains($action, 'failed') || str_contains($action, 'invalid') || str_contains($action, 'expired')))
            return 'One-Time Password (OTP) verification failed or expired. The user could not complete two-factor authentication.';
        if (str_contains($action, 'locked') || str_contains($action, 'banned'))
            return 'Account has been locked or banned due to repeated failed attempts or policy violation. Access is restricted until resolved by an administrator.';
        if (str_contains($action, 'unauthorized') || str_contains($action, 'denied'))
            return 'User attempted to access a resource or perform an action without proper authorization. This violates Role-Based Access Control (RBAC) policies.';
        if (str_contains($action, 'suspicious') || str_contains($action, 'brute'))
            return 'Suspicious login activity detected — possible brute force attack. The system has flagged this for security review.';
        if (str_contains($action, 'blocked'))
            return 'Action was blocked by the system due to a policy or security rule violation.';
        if (str_contains($action, 'inactive'))
            return 'User account is inactive. Access has been denied in compliance with account lifecycle policies.';
        if (str_contains($action, 'expired'))
            return 'A credential, token, or session has expired. The user must re-authenticate or request a new credential.';
        if (str_contains($action, 'loan') && str_contains($action, 'rejected'))
            return 'Loan application was rejected. This may be due to incomplete requirements, failed credit scoring, or non-compliance with lending policies.';
        if (str_contains($action, 'disbursement') && (str_contains($action, 'failed') || str_contains($action, 'error')))
            return 'Loan disbursement failed. The release of funds could not be completed — possible missing approval or incorrect account details.';
        if (str_contains($action, 'loan request') || str_contains($action, 'disbursement request'))
            return 'A loan or disbursement request has been submitted and is awaiting review and approval from an authorized officer.';
        if (str_contains($action, 'pending approval') || str_contains($action, 'sent for approval'))
            return 'This transaction has been submitted and is pending approval from an authorized approver before it can be processed.';
        if (str_contains($action, 'high value') || str_contains($action, 'high amount') || str_contains($action, 'large'))
            return 'Transaction involves a large or high-value amount that requires additional manual review and approval per company policy.';
        if (str_contains($action, 'override'))
            return 'A system rule or limit was overridden. This requires documentation and admin review to ensure compliance with lending policies.';
        if (str_contains($action, 'invalid') || str_contains($action, 'incorrect'))
            return 'The submitted data contains invalid or incorrect values. Records must meet data validation standards before processing.';
        if (str_contains($action, 'error'))
            return 'A system or data error occurred during processing. The transaction may be incomplete — further investigation is required.';
        if (str_contains($action, 'violation'))
            return 'A rule or policy violation was detected. This action does not comply with the established guidelines and must be reviewed.';
        if (str_contains($action, 'credit scor') || str_contains($action, 'ai scor') || str_contains($action, 'ai result') || str_contains($action, 'ai decision'))
            return 'AI-generated credit score or decision requires manual review by an authorized officer before any action is taken.';
        if (str_contains($action, 'flagged'))
            return 'This record has been flagged for review. It may contain unusual activity or data that requires admin verification.';
        if (str_contains($action, 'role change') || str_contains($action, 'permission change'))
            return 'A change in user role or system permission was detected. This requires review to ensure proper access control is maintained.';
        if (str_contains($action, 'bulk') || str_contains($action, 'mass'))
            return 'A bulk or mass operation was performed. These actions affect multiple records and require additional review for accuracy and compliance.';
        if (str_contains($action, 'high risk') || str_contains($desc, 'high risk'))
            return 'This action has been classified as HIGH RISK. Immediate review and possible escalation to management is required per company security policy.';
        if (str_contains($action, 'medium risk') || str_contains($desc, 'medium risk'))
            return 'This action has been classified as MEDIUM RISK. It requires supervisor review before proceeding.';
        if (str_contains($action, 'low risk') || str_contains($desc, 'low risk'))
            return 'This action has been classified as LOW RISK. Minor follow-up may be needed, but no immediate action is required.';
        if (str_contains($action, 'otp sent'))
            return 'An OTP has been sent to the user for verification. Awaiting confirmation to complete the authentication process.';
        if (str_contains($action, 'awaiting') || str_contains($action, 'waiting'))
            return 'This action is currently awaiting a response, approval, or input before it can proceed.';
        if (str_contains($action, 'submitted') || str_contains($action, 'queued'))
            return 'The request has been submitted and is in the queue for processing. No further action is required from the user at this time.';
        if (str_contains($action, 'in progress') || str_contains($action, 'processing'))
            return 'This action is currently being processed by the system. Please wait for the process to complete.';

        return 'This action was completed successfully and is in compliance with all applicable company policies and regulatory requirements.';
    }
}

// --------------------------------------------------
// get_compliance_category()
// --------------------------------------------------
if (!function_exists('get_compliance_category')) {
    function get_compliance_category(string $action_type, ?string $description = null): string
    {
        $action = strtolower(trim($action_type));

        if (str_contains($action, 'login') || str_contains($action, 'logout') ||
            str_contains($action, 'otp')   || str_contains($action, 'password') ||
            str_contains($action, 'locked')|| str_contains($action, 'banned') ||
            str_contains($action, 'unauthorized') || str_contains($action, 'brute') ||
            str_contains($action, 'suspicious')   || str_contains($action, 'blocked') ||
            str_contains($action, 'session')      || str_contains($action, 'token'))
            return 'Security & Authentication';

        if (str_contains($action, 'loan')        || str_contains($action, 'disbursement') ||
            str_contains($action, 'repayment')   || str_contains($action, 'interest') ||
            str_contains($action, 'amortization')|| str_contains($action, 'credit') ||
            str_contains($action, 'borrower'))
            return 'Loan Process';

        if (str_contains($action, 'ai') || str_contains($action, 'credit scor') || str_contains($action, 'scoring'))
            return 'AI & Credit Scoring';

        if (str_contains($action, 'user')       || str_contains($action, 'role') ||
            str_contains($action, 'permission') || str_contains($action, 'account') ||
            str_contains($action, 'member')     || str_contains($action, 'profile'))
            return 'User Management';

        if (str_contains($action, 'import') || str_contains($action, 'export') ||
            str_contains($action, 'bulk')   || str_contains($action, 'mass') ||
            str_contains($action, 'delete') || str_contains($action, 'update') ||
            str_contains($action, 'edit')   || str_contains($action, 'record'))
            return 'Data Management';

        if (str_contains($action, 'payment')    || str_contains($action, 'transaction') ||
            str_contains($action, 'transfer')   || str_contains($action, 'deposit') ||
            str_contains($action, 'withdrawal') || str_contains($action, 'fund'))
            return 'Financial Transactions';

        if (str_contains($action, 'setting')  || str_contains($action, 'config') ||
            str_contains($action, 'system')   || str_contains($action, 'backup') ||
            str_contains($action, 'restore')  || str_contains($action, 'override'))
            return 'System & Configuration';

        return 'General Operations';
    }
}

// --------------------------------------------------
// get_recommended_action()
// --------------------------------------------------
if (!function_exists('get_recommended_action')) {
    function get_recommended_action(string $action_type, ?string $description = null): string
    {
        $action = strtolower(trim($action_type));
        $desc   = strtolower(trim($description ?? ''));
        $status = determine_compliance_status($action_type, $description);

        if ($status === 'Compliant')
            return 'No action required. This activity is compliant and within normal operating parameters.';
        if (str_contains($action, 'login failed') || str_contains($action, 'wrong password'))
            return 'Monitor for repeated failed attempts. If 5 or more failures occur, escalate to the Security Officer and consider temporarily locking the account.';
        if (str_contains($action, 'locked') || str_contains($action, 'banned'))
            return 'Contact the System Administrator to review the account. Unlock only after verifying the user\'s identity and investigating the cause.';
        if (str_contains($action, 'unauthorized') || str_contains($action, 'denied'))
            return 'Review the user\'s role and permission settings. Report to the Security or Compliance Officer if this appears intentional.';
        if (str_contains($action, 'suspicious') || str_contains($action, 'brute'))
            return 'URGENT: Escalate immediately to the Security Officer. Block the IP address if necessary and conduct a full security audit of the affected account.';
        if (str_contains($action, 'high risk') || str_contains($desc, 'high risk'))
            return 'URGENT: Escalate immediately to the Compliance Officer and Branch Manager. Do not proceed with any related transactions until cleared.';
        if (str_contains($action, 'medium risk') || str_contains($desc, 'medium risk'))
            return 'Supervisor review required. Document findings and submit a compliance report within 24 hours.';
        if (str_contains($action, 'low risk') || str_contains($desc, 'low risk'))
            return 'Log and monitor. No immediate action needed but include in the next compliance review report.';
        if (str_contains($action, 'loan') && str_contains($action, 'rejected'))
            return 'Notify the borrower of the rejection with a written explanation. Document the reason for rejection in the loan file.';
        if (str_contains($action, 'disbursement') && (str_contains($action, 'failed') || str_contains($action, 'error')))
            return 'Verify loan approval status and borrower account details. Re-process the disbursement after correcting the identified issue.';
        if (str_contains($action, 'loan request') || str_contains($action, 'pending approval'))
            return 'Assign to the next available loan officer for review. Ensure all required documents are complete before processing.';
        if (str_contains($action, 'override'))
            return 'Require written justification from the officer who performed the override. Submit to the Compliance Officer for approval within 24 hours.';
        if (str_contains($action, 'high value') || str_contains($action, 'high amount') || str_contains($action, 'large'))
            return 'Route to Branch Manager or Senior Loan Officer for additional review and approval per the high-value transaction policy.';
        if (str_contains($action, 'credit scor') || str_contains($action, 'ai') || str_contains($action, 'flagged'))
            return 'Assign to a qualified loan officer for manual review. AI decisions must be validated by a human officer before final approval.';
        if (str_contains($action, 'role change') || str_contains($action, 'permission change'))
            return 'Verify the change was authorized by the System Administrator. Review access logs and confirm it aligns with the principle of least privilege.';
        if (str_contains($action, 'bulk') || str_contains($action, 'mass'))
            return 'Audit the affected records immediately. Bulk operations must be reviewed and approved by a supervisor before being finalized.';
        if (str_contains($action, 'invalid') || str_contains($action, 'incorrect') || str_contains($action, 'error'))
            return 'Review and correct the erroneous data. Re-submit the record after validation. Contact the data owner if the source of the error is unclear.';
        if ($status === 'Pending')
            return 'Assign to the appropriate officer for review and processing. Ensure this is completed within the standard processing time per company SLA.';
        if ($status === 'Under Review')
            return 'An authorized officer must review this record and provide a decision. Document all findings and update the compliance log accordingly.';

        return 'Review this record and consult with the Compliance Officer if further guidance is needed.';
    }
}

// --------------------------------------------------
// get_compliance_rules()
// --------------------------------------------------
if (!function_exists('get_compliance_rules')) {
    function get_compliance_rules(string $action_type, ?string $description = null): array
    {
        $category = get_compliance_category($action_type, $description);

        $allRules = [
            'Security & Authentication' => [
                ['code'=>'SEC-001','title'=>'User Authentication Policy','description'=>'All users must authenticate using valid credentials. Multiple failed login attempts will trigger an account lockout.','source'=>'Company Security Policy'],
                ['code'=>'SEC-002','title'=>'Two-Factor Authentication (2FA)','description'=>'Sensitive operations require OTP verification to confirm the identity of the acting user.','source'=>'Company IT Security Policy'],
                ['code'=>'SEC-003','title'=>'Role-Based Access Control (RBAC)','description'=>'Users may only access modules and perform actions permitted by their assigned role. Unauthorized access attempts are logged and escalated.','source'=>'Company Access Control Policy'],
                ['code'=>'SEC-004','title'=>'Data Privacy Act of 2012 (RA 10173)','description'=>'All personal information must be protected from unauthorized access, disclosure, and use. Security breaches must be reported within 72 hours.','source'=>'Republic Act No. 10173 — Philippines'],
                ['code'=>'SEC-005','title'=>'Session Management Policy','description'=>'User sessions must be invalidated upon logout or after a period of inactivity to prevent unauthorized access.','source'=>'Company IT Security Policy'],
            ],
            'Loan Process' => [
                ['code'=>'LOAN-001','title'=>'Loan Application Requirements','description'=>'All loan applications must include complete borrower information, valid IDs, proof of income, and other required documents before processing.','source'=>'Company Lending Policy Manual'],
                ['code'=>'LOAN-002','title'=>'Loan Approval Process','description'=>'No loan shall be released without proper approval from an authorized loan officer or branch manager, depending on the loan amount.','source'=>'Company Lending Policy Manual'],
                ['code'=>'LOAN-003','title'=>'Interest Rate Compliance','description'=>'Interest rates must comply with BSP guidelines and the Truth in Lending Act. No hidden charges are allowed.','source'=>'BSP Circular / Republic Act No. 3765 (Truth in Lending Act)'],
                ['code'=>'LOAN-004','title'=>'Anti-Money Laundering (AMLA)','description'=>'All loan transactions must comply with Anti-Money Laundering regulations. Suspicious transactions must be reported to the AMLC.','source'=>'Republic Act No. 9160 — Anti-Money Laundering Act'],
                ['code'=>'LOAN-005','title'=>'Know Your Customer (KYC)','description'=>'Borrowers must be properly identified and verified before any loan transaction is processed.','source'=>'BSP KYC Guidelines / AMLC Regulations'],
                ['code'=>'LOAN-006','title'=>'Cooperative Code of the Philippines','description'=>'All lending activities of the cooperative must comply with RA 9520, including proper documentation and member rights.','source'=>'Republic Act No. 9520'],
            ],
            'AI & Credit Scoring' => [
                ['code'=>'AI-001','title'=>'AI Decision Transparency','description'=>'AI-generated credit scores and decisions must be explainable. Borrowers have the right to know why their application was approved or rejected.','source'=>'Company AI Ethics Policy'],
                ['code'=>'AI-002','title'=>'Human Review of AI Decisions','description'=>'All AI-generated credit decisions must be reviewed and validated by an authorized loan officer before being finalized.','source'=>'Company Lending Policy Manual'],
                ['code'=>'AI-003','title'=>'Data Privacy in Credit Scoring','description'=>'Personal data used for credit scoring must be collected with consent and handled in accordance with RA 10173.','source'=>'Republic Act No. 10173 / Company Data Privacy Policy'],
                ['code'=>'AI-004','title'=>'Fair Credit Reporting','description'=>'Credit scoring must not discriminate based on gender, religion, or other protected characteristics.','source'=>'Company Fair Lending Policy'],
            ],
            'User Management' => [
                ['code'=>'USR-001','title'=>'Principle of Least Privilege','description'=>'Users must only be granted the minimum access rights necessary to perform their job functions.','source'=>'Company Access Control Policy'],
                ['code'=>'USR-002','title'=>'Role Change Authorization','description'=>'Changes to user roles or permissions must be authorized by the System Administrator and documented with a valid justification.','source'=>'Company IT Governance Policy'],
                ['code'=>'USR-003','title'=>'Account Lifecycle Management','description'=>'Inactive accounts must be disabled or removed. Access for terminated employees must be revoked immediately upon separation.','source'=>'Company HR and IT Policy'],
            ],
            'Financial Transactions' => [
                ['code'=>'FIN-001','title'=>'Transaction Authorization','description'=>'All financial transactions must be authorized by the appropriate officer based on the transaction amount and type.','source'=>'Company Financial Controls Policy'],
                ['code'=>'FIN-002','title'=>'Anti-Money Laundering (AMLA)','description'=>'Large or suspicious financial transactions must be reported in compliance with the Anti-Money Laundering Act.','source'=>'Republic Act No. 9160 — Anti-Money Laundering Act'],
                ['code'=>'FIN-003','title'=>'Audit Trail Requirement','description'=>'All financial transactions must be logged with a complete audit trail including who performed the action, when, and why.','source'=>'Company Financial Controls Policy / BSP Guidelines'],
            ],
            'Data Management' => [
                ['code'=>'DATA-001','title'=>'Data Accuracy and Integrity','description'=>'All records must be accurate, complete, and up-to-date. Incorrect or invalid data must be corrected immediately and documented.','source'=>'Company Data Management Policy'],
                ['code'=>'DATA-002','title'=>'Bulk Operation Controls','description'=>'Bulk imports, exports, or deletions must be approved by a supervisor and reviewed for accuracy before execution.','source'=>'Company IT Operations Policy'],
                ['code'=>'DATA-003','title'=>'Data Retention Policy','description'=>'Records must be retained for the period required by law or company policy and disposed of securely when no longer needed.','source'=>'Company Data Retention Policy / RA 10173'],
            ],
            'System & Configuration' => [
                ['code'=>'SYS-001','title'=>'Change Management Policy','description'=>'All system configuration changes must follow the change management process, including documentation and approval.','source'=>'Company IT Governance Policy'],
                ['code'=>'SYS-002','title'=>'System Override Controls','description'=>'System overrides are restricted to authorized personnel only and must be logged, justified, and reviewed by the Compliance Officer.','source'=>'Company IT Security Policy'],
            ],
            'General Operations' => [
                ['code'=>'GEN-001','title'=>'General Compliance Policy','description'=>'All system activities must comply with company policies, applicable laws, and regulatory requirements.','source'=>'Company General Compliance Policy'],
                ['code'=>'GEN-002','title'=>'Audit Trail and Accountability','description'=>'All significant actions performed in the system must be logged with the responsible user, timestamp, and details of the action.','source'=>'Company Audit Policy'],
            ],
        ];

        return $allRules[$category] ?? $allRules['General Operations'];
    }
}

// --------------------------------------------------
// get_full_compliance_info()
// --------------------------------------------------
if (!function_exists('get_full_compliance_info')) {
    function get_full_compliance_info(string $action_type, ?string $description = null): array
    {
        return [
            'status'             => determine_compliance_status($action_type, $description),
            'category'           => get_compliance_category($action_type, $description),
            'reason'             => get_compliance_reason($action_type, $description),
            'recommended_action' => get_recommended_action($action_type, $description),
            'rules'              => get_compliance_rules($action_type, $description),
        ];
    }
}

// --------------------------------------------------
// log_compliance()
// --------------------------------------------------
if (!function_exists('log_compliance')) {
    function log_compliance($user_id, $action_type, $module_name, $description, $status = null)
    {
        global $conn;
        if (!$conn) { error_log("Compliance Log Error: No DB connection"); return false; }
        if ($status === null) $status = determine_compliance_status($action_type, $description);
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt = $conn->prepare("
            INSERT INTO compliance_logs (user_id, action_type, module_name, description, status, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) { error_log("Compliance Log Prepare Failed: " . $conn->error); return false; }
        $stmt->bind_param("isssss", $user_id, $action_type, $module_name, $description, $status, $ip);
        $stmt->execute();
        $stmt->close();
        return true;
    }
}

if (!function_exists('log_compliance_event')) {
    function log_compliance_event($user_id, $action_type, $module_name, $description, $status = null)
    {
        return log_compliance($user_id, $action_type, $module_name, $description, $status);
    }
}