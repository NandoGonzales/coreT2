<?php
// dump_settings.php
require_once('initialize_coreT2.php');
$qry = $conn->query("SELECT * FROM system_info");
while ($row = $qry->fetch_assoc()) {
    echo $row['meta_field'] . ": " . $row['meta_value'] . "\n";
}
?>
