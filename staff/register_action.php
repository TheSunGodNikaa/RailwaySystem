<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

/* 🔹 Collect Data */
$emp_id       = trim($_POST['emp_id']);
$designation  = $_POST['designation'];
$station_code = trim($_POST['station_code']);
$department   = $_POST['department'];

$full_name    = trim($_POST['full_name']);
$email        = trim($_POST['email']);
$joining_date = $_POST['joining_date'];

$username     = trim($_POST['username']);
$passwordRaw  = $_POST['password'];

/* 🔹 Validation */
if (
    $emp_id === '' || $designation === '' || $station_code === '' ||
    $department === '' || $full_name === '' || $email === '' ||
    $joining_date === '' || $username === '' || $passwordRaw === ''
) {
    die("All fields are required");
}

/* 🔐 Hash Password */
$password = password_hash($passwordRaw, PASSWORD_DEFAULT);

/* 🔍 Check duplicate username */
$check_sql = "SELECT USERNAME FROM CLERK WHERE USERNAME = :u";
$check_stid = oci_parse($conn, $check_sql);
oci_bind_by_name($check_stid, ":u", $username);
oci_execute($check_stid);

if (oci_fetch_assoc($check_stid)) {
    die("Username already exists");
}

/* 🔍 Check duplicate EMP_ID */
$check_emp = "SELECT EMP_ID FROM CLERK WHERE EMP_ID = :e";
$check_emp_stid = oci_parse($conn, $check_emp);
oci_bind_by_name($check_emp_stid, ":e", $emp_id);
oci_execute($check_emp_stid);

if (oci_fetch_assoc($check_emp_stid)) {
    die("Employee ID already exists");
}

/* 🔹 Insert Clerk */
$sql = "INSERT INTO CLERK 
        (CLERK_ID, EMP_ID, USERNAME, PASSWORD, FULL_NAME, EMAIL,
         DESIGNATION, DEPARTMENT, STATION_CODE, JOINING_DATE, CREATED_AT)
        VALUES 
        (clerk_seq.NEXTVAL, :e, :u, :p, :fn, :em,
         :des, :dep, :sc, TO_DATE(:jd, 'YYYY-MM-DD'), SYSTIMESTAMP)";

$stid = oci_parse($conn, $sql);

/* 🔹 Bind */
oci_bind_by_name($stid, ":e",  $emp_id);
oci_bind_by_name($stid, ":u",  $username);
oci_bind_by_name($stid, ":p",  $password);
oci_bind_by_name($stid, ":fn", $full_name);
oci_bind_by_name($stid, ":em", $email);
oci_bind_by_name($stid, ":des", $designation);
oci_bind_by_name($stid, ":dep", $department);
oci_bind_by_name($stid, ":sc",  $station_code);
oci_bind_by_name($stid, ":jd",  $joining_date);

/* 🔹 Execute */
if (oci_execute($stid, OCI_COMMIT_ON_SUCCESS)) {

    header("Location: ../login.php?clerk_created=1");
    exit;

} else {
    $e = oci_error($stid);
    echo "Error: " . $e['message'];
}
?>