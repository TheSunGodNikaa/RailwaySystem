<?php
session_start();
include "../db.php";
require_once __DIR__ . "/../TwoPL/train_data.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'clerk') {
    header("Location: login.php");
    exit;
}

if (!empty($_SESSION['username'])) {
    railwayTouchClerkLoginStatus($conn, $_SESSION['username']);
    @oci_commit($conn);
}
?>
