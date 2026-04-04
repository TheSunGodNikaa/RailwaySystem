<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

/* 🔹 Collect Inputs */
$full_name = trim($_POST['full_name']);
$email     = trim($_POST['email']);
$phone     = trim($_POST['phone']);
$gender    = $_POST['gender'];
$username  = trim($_POST['username']);
$passwordRaw = $_POST['password'];

/* 🔹 Basic Validation */
if ($full_name === '' || $email === '' || $phone === '' || 
    $gender === '' || $username === '' || $passwordRaw === '') {
    die("All fields are required");
}

/* 🔐 Hash Password */
$password = password_hash($passwordRaw, PASSWORD_DEFAULT);

/* 🔍 Check if username already exists */
$check_sql = "SELECT USERNAME FROM USERS WHERE USERNAME = :u";
$check_stid = oci_parse($conn, $check_sql);
oci_bind_by_name($check_stid, ":u", $username);
oci_execute($check_stid);

if (oci_fetch_assoc($check_stid)) {
    die("Username already exists");
}

/* 🔹 Insert Query */
$sql = "INSERT INTO USERS 
        (USER_ID, USERNAME, PASSWORD, FULL_NAME, EMAIL, PHONE, GENDER, CREATED_AT)
        VALUES 
        (user_seq.NEXTVAL, :u, :p, :fn, :em, :ph, :gd, SYSTIMESTAMP)";

$stid = oci_parse($conn, $sql);

/* 🔹 Bind Values */
oci_bind_by_name($stid, ':u',  $username);
oci_bind_by_name($stid, ':p',  $password);
oci_bind_by_name($stid, ':fn', $full_name);
oci_bind_by_name($stid, ':em', $email);
oci_bind_by_name($stid, ':ph', $phone);
oci_bind_by_name($stid, ':gd', $gender);

/* 🔹 Execute */
if (oci_execute($stid, OCI_COMMIT_ON_SUCCESS)) {
    header("Location: login.php?success=1");
    exit;
} else {
    $e = oci_error($stid);
    echo "Error: " . $e['message'];
}
?>