<?php
require_once('initialize_coreT2.php');

echo "<h1>Database Check</h1>";
if (!$conn) {
    echo "Connection failed: " . $conn->connect_error;
    exit;
}
echo "Connected successfully.<br>";

$tables = ['members', 'loan_portfolio', 'savings', 'disbursements', 'collections', 'compliance_logs', 'audit_trail', 'audit_trial', 'users'];

foreach ($tables as $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    if ($res->num_rows == 0) {
        echo "Table <b>$table</b> does NOT exist.<br>";
        continue;
    }

    $res = $conn->query("SELECT COUNT(*) as cnt FROM $table");
    $row = $res->fetch_assoc();
    echo "Table <b>$table</b>: " . $row['cnt'] . " rows<br>";

    if ($table == 'members') {
        $res2 = $conn->query("SELECT status, COUNT(*) as cnt FROM members GROUP BY status");
        while ($r = $res2->fetch_assoc()) {
            echo "&nbsp;&nbsp;- Status '{$r['status']}': {$r['cnt']}<br>";
        }
    }
    if ($table == 'loan_portfolio') {
        $res2 = $conn->query("SELECT status, COUNT(*) as cnt FROM loan_portfolio GROUP BY status");
        while ($r = $res2->fetch_assoc()) {
            echo "&nbsp;&nbsp;- Status '{$r['status']}': {$r['cnt']}<br>";
        }
    }
}
?>
