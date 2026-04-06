<?php
session_start();
include "../db.php";
require_once __DIR__ . "/../TwoPL/train_data.php";

if (isset($_SESSION['role']) && $_SESSION['role'] === 'clerk' && !empty($_SESSION['username'])) {
    railwayUpsertClerkLoginStatus($conn, $_SESSION['username'], false);
    railwayAppendMiqsmEvent('clerk_logout', [
        'username' => $_SESSION['username'],
        'source' => 'staff_portal',
    ]);
    @oci_commit($conn);
}

$_SESSION = [];
session_destroy();

header("Location: login.php");
exit;
?>
