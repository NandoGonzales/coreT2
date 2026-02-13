<?php
require_once('../initialize_coreT2.php');
require_once(__DIR__ . '/inc/send_otp.php');

$recipientEmail = 'carlvincentjumarangnartea@gmail.com'; 
$recipientName = 'Carl Vincent';
$otp = '123456';

echo "Testing sendOTPEmail() function...<br>";
echo "Recipient: $recipientEmail<br>";

if(sendOTPEmail($recipientEmail, $recipientName, $otp)) {
    echo "<b>Success!</b> sendOTPEmail() returned true.<br>";
} else {
    echo "<b>Failed!</b> sendOTPEmail() returned false.<br>";
}

echo "Check admin/inc/otp_debug.log for details.";
?>
