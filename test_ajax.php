<?php
// Mocking session for testing
$_SESSION['userdata'] = ['user_id' => 1];

ob_start();
require_once('admin/ajax_dashboard_data.php');
$output = ob_get_clean();

file_put_contents('test_ajax_output.json', $output);
echo "Done\n";
?>
