<?php
// check_user.php
require_once('initialize_coreT2.php');
$email = 'carlvincentjumaranarnartea@gmail.com';
$stmt = $conn->prepare("SELECT user_id, username, email, status FROM users WHERE email=? OR username=?");
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$result = $stmt->get_result();
if ($user = $result->fetch_assoc()) {
    echo "User Found:\n";
    print_r($user);
}
else {
    echo "User NOT Found: $email\n";
}
?>
