<?php
session_start();

function launchMiqsmDashboard(): void {
    $moduleDir = "C:\\xampp\\htdocs\\railway_auth\\MIQSM_Module";
    $scriptPath = $moduleDir . "\\web_interface.py";
    $logPath = $moduleDir . "\\logs\\web_interface_startup.log";
    $pythonPath = "C:\\Users\\adity\\AppData\\Local\\Programs\\Python\\Python313\\python.exe";

    if (!file_exists($pythonPath)) {
        return;
    }

    $innerCommand = 'cd /d "' . $moduleDir . '" && "' . $pythonPath . '" "' . $scriptPath . '" >> "' . $logPath . '" 2>&1';
    $launchCommand = 'start "" /B cmd /c ' . escapeshellarg($innerCommand);

    @pclose(@popen($launchCommand, "r"));
}

if(time() > $_SESSION['otp_expiry']){
    die("OTP Expired");
}

if($_POST['otp'] == $_SESSION['otp']){
    unset($_SESSION['otp']);
    launchMiqsmDashboard();
    header("Location: http://127.0.0.1:5000");
}else{
    die("Invalid OTP");
}
?>
