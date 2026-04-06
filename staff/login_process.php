<?php
session_start();
include '../db.php';
require_once __DIR__ . '/../TwoPL/train_data.php';

$username = $_POST['username'];
$password = $_POST['password'];

/* ===================================
   🔐 1. ADMIN LOGIN
=================================== */

if ($username === "admin") {

    $sql = "SELECT * FROM ADMIN WHERE USERNAME = :u";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":u", $username);
    oci_execute($stid);

    $row = oci_fetch_assoc($stid);

    if (!$row) {
        die("Admin not found");
    }

    if ($password !== $row['PASSWORD']) {
        die("Invalid admin password");
    }

    session_regenerate_id(true);
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'admin';

    $pythonPath = "C:\\Users\\adity\\AppData\\Local\\Programs\\Python\\Python313\\python.exe";
    $moduleDir = "C:\\xampp\\htdocs\\railway_auth\\MIQSM_Module";
    $scriptPath = $moduleDir . "\\web_interface.py";

    if (!file_exists($pythonPath)) {
        die("Python executable not found");
    }

    if (!file_exists($scriptPath)) {
        die("MIQSM web interface script not found");
    }

    $psPythonPath = str_replace("'", "''", $pythonPath);
    $psScriptPath = str_replace("'", "''", $scriptPath);
    $psModuleDir = str_replace("'", "''", $moduleDir);

    $command = "powershell -WindowStyle Hidden -Command \"Start-Process -WindowStyle Hidden -FilePath '$psPythonPath' -ArgumentList '$psScriptPath' -WorkingDirectory '$psModuleDir'\"";

    pclose(popen($command, "r"));

    sleep(5);

    header("Location: http://127.0.0.1:5000");
    exit;
}


/* ===================================
   👨‍💼 2. CLERK LOGIN
=================================== */

$sql = "SELECT * FROM CLERK WHERE USERNAME = :u";
$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ":u", $username);
oci_execute($stid);

$row = oci_fetch_assoc($stid);

if ($row) {

    if (!password_verify($password, $row['PASSWORD'])) {
        die("Invalid clerk password");
    }

    session_regenerate_id(true);
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'clerk';
    railwayUpsertClerkLoginStatus($conn, $username, true);
    railwayAppendMiqsmEvent('clerk_login', [
        'username' => $username,
        'source' => 'staff_portal',
    ]);
    oci_commit($conn);

    header("Location: clerk.php");
    exit;
}


/* ===================================
   ❌ NO ACCESS FOR USERS HERE
=================================== */

die("Access denied. Use passenger login.");
?>
