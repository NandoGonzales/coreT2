<?php
require_once('initialize_coreT2.php');
header('Content-Type: text/plain');

$queries = [
    "KPI Members" => "SELECT COUNT(*) AS val FROM members WHERE status='Active'",
    "KPI Loans" => "SELECT COUNT(*) AS val FROM loan_portfolio WHERE status IN ('Active','Approved')",
    "KPI Savings" => "SELECT SUM(CASE WHEN transaction_type = 'Deposit' THEN amount ELSE -amount END) AS val FROM savings",
    "KPI Disbursed" => "SELECT IFNULL(SUM(amount),0) AS val FROM disbursements WHERE status='Released'",
    "Collection Chart" => "SELECT MONTH(repayment_date) AS m, SUM(amount) AS total FROM repayments WHERE YEAR(repayment_date)=2026 GROUP BY MONTH(repayment_date)",
    "Audit Trail" => "SELECT a.module_name,a.action_type,a.remarks,a.action_time,u.full_name FROM audit_trail a LEFT JOIN users u ON a.user_id=u.user_id ORDER BY a.action_time DESC LIMIT 1"
];

$output = "QUERY VERIFICATION RESULTS:\n";
foreach ($queries as $name => $sql) {
    try {
        $res = $conn->query($sql);
        if ($res) {
            $output .= "✅ $name: Success\n";
        // Optional: output sample data if needed
        }
        else {
            $output .= "❌ $name: Error - " . $conn->error . "\n";
        }
    }
    catch (Throwable $e) {
        $output .= "❌ $name: Exception - " . $e->getMessage() . "\n";
    }
}

file_put_contents('verify_queries_output.txt', $output);
echo "Verification complete\n";
?>
