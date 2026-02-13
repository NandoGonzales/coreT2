<?php
// test_email_diagnostic.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Redirect error_log to a local file
ini_set('error_log', __DIR__ . '/diagnostic_error.log');

require_once('initialize_coreT2.php');
require_once('admin/inc/send_otp.php');

echo "Starting email diagnostic...\n";
error_log("--- START DIAGNOSTIC ---");

$test_email = 'test@example.com';
$test_name = 'Test User';
$test_otp = '123456';

echo "Attempting to send test email to $test_email...\n";
$result = sendOTPEmail($test_email, $test_name, $test_otp);

if ($result) {
    echo "SUCCESS: Email sent (or at least PHPMailer didn't throw an error).\n";
}
else {
    echo "FAILURE: Email failed to send. Check diagnostic_error.log for details.\n";
}

error_log("--- END DIAGNOSTIC ---");
echo "Done. Check diagnostic_error.log for details.\n";
?>
