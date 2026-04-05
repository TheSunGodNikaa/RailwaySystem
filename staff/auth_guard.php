<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'clerk') {
    header("Location: login.php");
    exit;
}
?>
