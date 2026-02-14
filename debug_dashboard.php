<?php
require_once('initialize_coreT2.php');

header('Content-Type: text/plain');

$tables = ['members', 'loan_portfolio', 'savings', 'disbursements', 'collections', 'compliance_logs', 'audit_trail', 'users'];

foreach ($tables as $table) {
    try {
        $res = $conn->query("SELECT COUNT(*) as cnt FROM $table");
        if ($res) {
            $row = $res->fetch_assoc();
            echo "Table $table: " . $row['cnt'] . " rows\n";

            // Sample data from loan_portfolio
            if ($table == 'loan_portfolio') {
                $res2 = $conn->query("SELECT status, COUNT(*) as cnt FROM loan_portfolio GROUP BY status");
                while ($r = $res2->fetch_assoc()) {
                    echo "  - Status '{$r['status']}': {$r['cnt']}\n";
                }
                $res3 = $conn->query("SELECT COUNT(*) as cnt FROM loan_portfolio WHERE ai_credit_score IS NOT NULL");
                $r3 = $res3->fetch_assoc();
                echo "  - ai_credit_score IS NOT NULL: {$r3['cnt']}\n";
            }

            // Sample data from members
            if ($table == 'members') {
                $res2 = $conn->query("SELECT status, COUNT(*) as cnt FROM members GROUP BY status");
                while ($r = $res2->fetch_assoc()) {
                    echo "  - Status '{$r['status']}': {$r['cnt']}\n";
                }
            }
        }
        else {
            echo "Table $table: Query failed: " . $conn->error . "\n";
        }
    }
    catch (Exception $e) {
        echo "Table $table: Error: " . $e->getMessage() . "\n";
    }
}
?>
