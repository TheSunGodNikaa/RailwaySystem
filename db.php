<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!function_exists('oci_connect')) {
    die(
        "Oracle OCI8 extension is not enabled in PHP. " .
        "Enable OCI8 in C:\\xampp\\php\\php.ini and install/configure Oracle Instant Client before running this app."
    );
}

$conn = oci_connect("system", "12345", "//localhost:1521/XE");

if (!$conn) {
    $e = oci_error();
    die("Oracle Connection Failed: " . $e['message']);
}
?>
