<?php
$c = new mysqli('localhost', 'core2_coreTREWQmic2', 'OuP@Gshtg#9floiP', 'core2_db');
if ($c->connect_error)
    die("Connect Error: " . $c->connect_error);
$sql = "ALTER TABLE approval_requests MODIFY COLUMN request_type VARCHAR(50) NOT NULL";
if ($c->query($sql)) {
    echo "SUCCESS: request_type updated items: " . $c->affected_rows . "\n";
} else {
    echo "ERROR: " . $c->error . "\n";
}
$res = $c->query("DESC approval_requests");
while ($row = $res->fetch_assoc()) {
    if ($row['Field'] == 'request_type') {
        echo "VERIFIED_TYPE: " . $row['Type'] . "\n";
    }
}
?>