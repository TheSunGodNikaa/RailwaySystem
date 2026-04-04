<?php
session_start();
include 'db.php';

$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE username = :u";
$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ":u", $username);
oci_execute($stid);

$row = oci_fetch_assoc($stid);

if (!$row || !password_verify($password, $row['PASSWORD'])) {
    die("Invalid login");
}

// 🚫 BLOCK STAFF HERE
if ($row['ROLE'] !== 'passenger') {
    die("Staff must login via Staff Portal");
}

$_SESSION['username'] = $username;
$_SESSION['role'] = $row['ROLE'];

header("Location: passenger_dashboard.php");
exit;
?>
