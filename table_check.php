<?php
require_once('initialize_coreT2.php');
$tables = ['members', 'loan_portfolio', 'savings', 'collections', 'repayments', 'disbursements'];
$output = "";
foreach ($tables as $t) {
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    $exists = ($res && $res->num_rows > 0) ? "EXISTS" : "MISSING";
    $output .= "Table $t: $exists\n";
    if ($exists == "EXISTS") {
        $res2 = $conn->query("SELECT COUNT(*) as cnt FROM $t");
        $row = $res2->fetch_assoc();
        $output .= "  Rows: " . $row['cnt'] . "\n";
    }
}
file_put_contents('table_check_final.txt', $output);
echo "Check complete\n";
?>
