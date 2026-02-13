<?php
// check_schema.php
require_once('initialize_coreT2.php');
$res = $conn->query("DESCRIBE users");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
