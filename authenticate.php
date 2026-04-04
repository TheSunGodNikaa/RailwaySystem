<?php
session_start();
include 'db.php';

$username = trim($_POST['username']);
$password = $_POST['password'];

/* 🔹 Get user from USERS table */
$sql = "SELECT USER_ID, USERNAME, PASSWORD, FULL_NAME FROM USERS WHERE USERNAME = :u";
$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ":u", $username);
oci_execute($stid);

$row = oci_fetch_assoc($stid);

/* 🔐 Validate */
if (!$row || !password_verify($password, $row['PASSWORD'])) {
    die("Invalid username or password");
}

/* 🔹 Store session */
$_SESSION['user_id'] = $row['USER_ID'];   // ✅ IMPORTANT
$_SESSION['username'] = $row['USERNAME'];
$_SESSION['full_name'] = $row['FULL_NAME'];
$_SESSION['role'] = 'passenger';

/* 🔹 Redirect */
header("Location: passenger.php");
exit;
?>
