<?php
session_start();
if(!isset($_SESSION['otp'])) die("Unauthorized");
?>

<!DOCTYPE html>
<html>
<head><title>Admin OTP</title></head>
<body>

<h3>OTP Verification</h3>

<p><b>Demo OTP:</b> <?=$_SESSION['otp']?></p>

<form method="post" action="verify_otp.php">
<input type="number" name="otp" required>
<button type="submit">Verify OTP</button>
</form>

</body>
</html>
