<?php
$redirect = $_GET['redirect'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Railway Reservation System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3a8a; /* Deep Navy */
            --secondary: #f59e0b; /* Safety Amber */
            --text-main: #1f2937;
            --bg-light: #142f66;
            --radius: 24px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-light);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        body::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(rgba(30, 58, 138, 0.6), rgba(30, 58, 138, 0.8)),
                        url("./assets/360_F_614228326_oNI45dDAFTzWlWIVFGpzmGjaotf331U6.jpg");
            background-size: cover;
            background-position: center;
            filter: blur(8px); /* adjust blur strength */
            z-index: -1; /* keep it behind content */
        }


        /* The Main Immersive Container */
        .login-wrapper {
            display: flex;
            width: 1000px;
            max-width: 100%;
            height: 600px;
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden; /* Clips the image to the rounded corners */
        }

        /* Visual Branding Side */
        .image-section {
            flex: 1.2;
            position: relative;
            background: linear-gradient(rgba(30, 58, 138, 0.6), rgba(30, 58, 138, 0.8)), 
                        url("./assets/360_F_614228326_oNI45dDAFTzWlWIVFGpzmGjaotf331U6.jpg");
            background-size: cover;
            background-position: center;
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .image-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            line-height: 1.1;
        }

        .image-section p {
            font-size: 1.1rem;
            opacity: 1;
            max-width: 400px;
            line-height: 1.5;
        }

        /* Functional Login Side */
        .card {
            flex: 1;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .card h2 {
            font-size: 2rem;
            color: var(--text-main);
            margin-bottom: 8px;
            font-weight: 800;
        }

        .subtitle {
            color: #6b7280;
            margin-bottom: 35px;
            font-size: 0.95rem;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .input-box {
            position: relative;
        }

        .input-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        input {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 1.5px solid #204286;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(30, 58, 138, 0.1);
        }

        button {
            background-color: var(--primary);
            color: white;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 10px;
        }

        button:hover {
            background-color: #172554;
        }

        .link {
            margin-top: 25px;
            text-align: center;
            font-size: 0.95rem;
            color: #4b5563;
        }

        .link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
        }

        .link a:hover {
            text-decoration: underline;
        }

        /* Small Screen Layout */
        @media (max-width: 850px) {
            .image-section { display: none; }
            .login-wrapper { width: 450px; height: auto; }
            .card { padding: 40px; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="image-section">
        <div style="background: var(--secondary); width: 50px; height: 5px; margin-bottom: 20px;"></div>
        <h1>Your Journey <br>Starts Here.</h1>
        <p>Access the unified passenger gateway for seamless travel across the national rail network.</p>
    </div>

    <div class="card">
        <h2>Login</h2>
        <p class="subtitle">Please enter your credentials to continue.</p>

        <form method="post" action="authenticate.php">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
            <div class="input-box">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="username" placeholder="Username" required>
            </div>
            
            <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit">Login</button>
        </form>

        <div class="link">
            New user? <a href="register.php">Create Account</a>
        </div>
    </div>
</div>

</body>
</html>
