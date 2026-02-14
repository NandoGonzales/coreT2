<?php
/**
 * compliance_status_test.php
 * 
 * Quick reference / test para makita kung anong compliance_status
 * ang ia-assign sa bawat action_type.
 * 
 * PAANO GAMITIN:
 * Buksan ito sa browser (admin/Compliance-Audith-Trail-System/compliance_status_test.php)
 * 
 * DELETE THIS FILE IN PRODUCTION.
 */

require_once('../../initialize_coreT2.php');
require_once('../inc/log_audit_trial.php'); // contains determine_compliance_status()

$tests = [
    // ✅ COMPLIANT - Normal successful actions
    ['OTP Verified - Login Complete',       'Authentication',       'User logged in from IP: 1.2.3.4'],
    ['OTP Sent',                            'Authentication',       'OTP sent to email: user@test.com'],
    ['Logout',                              'Authentication',       'User Fernando logged out'],
    ['Create Loan',                         'Loan Portfolio',       'New loan created for member #5'],
    ['Update Record',                       'Savings Monitoring',   'Savings record updated'],
    ['Add Member',                          'User Management',      'New member added'],
    ['Delete Record',                       'Loan Portfolio',       'Old loan record removed'],
    ['Export CSV',                          'Compliance & Audit',   'Data exported by admin'],
    ['View Report',                         'Dashboard',            'Monthly report accessed'],

    // ❌ NON-COMPLIANT - Failed / security violations
    ['Login Failed - Wrong Password',       'Authentication',       'Incorrect password from IP: 9.9.9.9'],
    ['OTP Verification Failed - Wrong Code','Authentication',       'Incorrect OTP entered'],
    ['Login Failed - Inactive',             'Authentication',       'Inactive user tried login'],
    ['Login Failed - Unknown User',         'Authentication',       'Unknown username from IP: 9.9.9.9'],
    ['Unauthorized Access',                 'Authentication',       'Attempted access without login'],
    ['OTP Send Failed',                     'Authentication',       'Email sending failed'],
    ['OTP Generation Failed',              'Authentication',       'Database error storing OTP'],
    ['Access Denied',                       'Loan Portfolio',       'Staff tried to approve high loan without permission'],
    ['Invalid API Key',                     'API',                  'Request with invalid key rejected'],
    ['Session Expired',                     'Authentication',       'Session timed out due to inactivity'],

    // 🟡 UNDER REVIEW - Needs admin verification
    ['Large Loan Approved',                 'Loan Portfolio',       'Loan PHP 500,000 approved - needs review'],
    ['AI Credit Score Generated',          'AI Scoring',           'AI scored member #12 as high-risk'],
    ['High Risk Member Flagged',           'Risk Management',      'Member flagged by AI system'],
    ['Role Change',                         'User Management',      'User role changed from Staff to Admin'],
    ['Permission Change',                   'RBAC',                 'New permission granted to user'],
    ['Bulk Delete',                         'Loan Portfolio',       'Mass deletion of old records'],

    // 🟠 PENDING - Started but not yet done
    ['OTP Sent',                            'Authentication',       'OTP sent - awaiting verification'],
    ['Disbursement Request',               'Disbursement',         'Fund request submitted for approval'],
    ['Loan Request Submitted',             'Loan Portfolio',       'Loan application queued for review'],
];

$counts = ['Compliant' => 0, 'Non-Compliant' => 0, 'Under Review' => 0, 'Pending' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Compliance Status Logic Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', sans-serif; }
        .badge-compliant    { background: #10b981; color: white; }
        .badge-noncompliant { background: #ef4444; color: white; }
        .badge-review       { background: #3b82f6; color: white; }
        .badge-pending      { background: #f59e0b; color: #1f2937; }
    </style>
</head>
<body class="p-4">
<div class="container-fluid">
    <h3 class="mb-1">🛡️ Compliance Status Logic Test</h3>
    <p class="text-muted mb-4">This page shows how <code>determine_compliance_status()</code> classifies each action.</p>

    <table class="table table-bordered table-hover table-sm">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Action Type</th>
                <th>Module</th>
                <th>Remarks</th>
                <th>Auto Status</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        foreach ($tests as $test) {
            [$action, $module, $remark] = $test;
            $status = determine_compliance_status($action, $remark);
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            $badgeClass = match ($status) {
                'Compliant'     => 'badge-compliant',
                'Non-Compliant' => 'badge-noncompliant',
                'Under Review'  => 'badge-review',
                'Pending'       => 'badge-pending',
                default         => 'bg-secondary text-white',
            };

            $icon = match ($status) {
                'Compliant'     => '✅',
                'Non-Compliant' => '❌',
                'Under Review'  => '🟡',
                'Pending'       => '🟠',
                default         => '⚪',
            };
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><code><?= htmlspecialchars($action) ?></code></td>
                <td><?= htmlspecialchars($module) ?></td>
                <td class="small text-muted"><?= htmlspecialchars($remark) ?></td>
                <td>
                    <span class="badge <?= $badgeClass ?> px-2 py-1">
                        <?= $icon ?> <?= $status ?>
                    </span>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

    <div class="row mt-3">
        <?php foreach ($counts as $status => $count):
            $badgeClass = match ($status) {
                'Compliant'     => 'bg-success',
                'Non-Compliant' => 'bg-danger',
                'Under Review'  => 'bg-primary',
                'Pending'       => 'bg-warning text-dark',
                default         => 'bg-secondary',
            };
        ?>
        <div class="col-md-3 mb-2">
            <div class="card text-center p-3">
                <span class="badge <?= $badgeClass ?> fs-6 mb-1"><?= $status ?></span>
                <strong class="fs-4"><?= $count ?></strong>
                <small class="text-muted">entries</small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="alert alert-warning mt-3">
        ⚠️ <strong>IMPORTANT:</strong> Delete this test file before going live in production.
    </div>
</div>
</body>
</html>