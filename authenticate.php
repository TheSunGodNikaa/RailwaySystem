<?php
session_start();
include 'db.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$redirect = trim($_POST['redirect'] ?? '');

$sql = "SELECT USER_ID, USERNAME, PASSWORD, FULL_NAME FROM USERS WHERE USERNAME = :u";
$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ":u", $username);
oci_execute($stid);

$row = oci_fetch_assoc($stid);

if (!$row || !password_verify($password, $row['PASSWORD'])) {
    die("Invalid username or password");
}

$_SESSION['user_id'] = $row['USER_ID'];
$_SESSION['username'] = $row['USERNAME'];
$_SESSION['full_name'] = $row['FULL_NAME'];
$_SESSION['role'] = 'passenger';

require_once __DIR__ . "/TwoPL/train_data.php";
railwayAppendMiqsmEvent('passenger_login', [
    'user_id' => $row['USER_ID'],
    'username' => $row['USERNAME'],
]);

$target = 'passenger.php';
if ($redirect !== '' && preg_match('/^[A-Za-z0-9_\/\.\-\?\=\&\%]+$/', $redirect)) {
    $target = $redirect;
}

header("Location: " . $target);
exit;
?>
