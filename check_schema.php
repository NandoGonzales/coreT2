<?php
require_once('initialize_coreT2.php');

$res = $conn->query("DESCRIBE members status");
if ($res) {
    $row = $res->fetch_assoc();
    file_put_contents('schema_check.txt', print_r($row, true));

    // Also check current values
    $res2 = $conn->query("SELECT DISTINCT status FROM members");
    $stats = [];
    while ($r = $res2->fetch_assoc())
        $stats[] = $r['status'];
    file_put_contents('schema_check.txt', "\nDistinct status values: " . implode(', ', $stats), FILE_APPEND);
}
else {
    file_put_contents('schema_check.txt', "Failed to describe members table: " . $conn->error);
}
?>
