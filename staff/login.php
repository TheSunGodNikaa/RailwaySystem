<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal | National Railways</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #1e3c72;
            --secondary: #2a5298;
            --accent: #3b82f6;
            --bg: #f8fafc;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            display: flex;
            width: 900px;
            height: 550px;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Left Side: Branding/Image */
        .login-brand {
            flex: 1;
            background: linear-gradient(rgba(30, 60, 114, 0.8), rgba(42, 82, 152, 0.8)), 
                        url("../assets/photo-1474487548417-781cb71495f3.jpg");
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            padding: 40px;
            text-align: center;
        }

        .login-brand h1 { margin: 0; font-size: 2.5rem; }
        .login-brand p { opacity: 0.9; margin-top: 10px; font-weight: 300; }

        /* Right Side: Form */
        .login-form-section {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header { margin-bottom: 30px; }
        .form-header h2 { margin: 0; color: #1e293b; font-size: 1.8rem; }
        .form-header p { color: #64748b; margin-top: 5px; }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        button {
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
            margin-top: 10px;
        }

        button:hover {
            background: #162d5a;
            transform: translateY(-2px);
        }

        .footer-text {
            margin-top: 25px;
            text-align: center;
            font-size: 0.85rem;
            color: #94a3b8;
        }

        /* Responsive */
        @media (max-width: 850px) {
            .login-brand { display: none; }
            .login-container { width: 400px; }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-brand">
        <i class="fa-solid fa-train-subway" style="font-size: 4rem; margin-bottom: 20px;"></i>
        <h1>Railway System</h1>
        <p>Centralized Staff Management & Concurrency Control Portal</p>
    </div>

    <div class="login-form-section">
        <div class="form-header">
            <h2>Staff Login</h2>
            <p>Welcome back! Please enter your details.</p>
        </div>

        <form method="post" action="login_process.php">
            <div class="input-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="username" placeholder="Username or Employee ID" required>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit">
                Sign In <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i>
            </button>
        </form>

        <div class="footer-text">
            &copy; 2026 National Railway Corporation <br>
            Secure 2PL Transaction Environment
        </div>
    </div>
</div>

</body>
</html>