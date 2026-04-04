<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | RailOps Gateway</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3a8a; 
            --accent: #f59e0b;
            --text-dark: #111827;
            --bg-body: #142f66;;
            --radius-lg: 32px;
            --radius-sm: 14px;
        }
        

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        body {
            background-color: var(--bg-body);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 30px 30px;
        }

        .register-wrapper {
            display: flex;
            width: 1100px;
            height: 750px;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.4);
        }

        /* --- Immersive Left Side --- */
        .image-section {
            flex: 1;
            position: relative;
            background: linear-gradient(to top, rgba(15, 23, 42, 0.9), rgba(15, 23, 42, 0.3)),
            url("./assets/pexels-satvikpandurangi-10662882.jpg");
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 60px;
            color: white;
        }

        .image-section h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 15px; }
        .image-section p { font-size: 1rem; opacity: 0.8; line-height: 1.6; max-width: 300px; }

        /* --- Registration Form Side --- */
        .card {
            flex: 1.4;
            padding: 50px 70px;
            overflow-y: auto;
            background: #fff;
        }

        h2 { font-size: 2.2rem; color: var(--text-dark); margin-bottom: 5px; font-weight: 700; }
        .subtitle { color: #6b7280; margin-bottom: 30px; font-size: 0.95rem; }

        form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }

        .input-group { position: relative; }
        .input-group i {
            position: absolute; left: 18px; top: 50%;
            transform: translateY(-50%); color: #94a3b8;
        }

        input, select {
            width: 100%; padding: 14px 14px 14px 50px;
            border: 2px solid #f3f4f6; background: #f9fafb;
            border-radius: var(--radius-sm); font-size: 0.95rem;
            transition: all 0.2s; color: var(--text-dark);
        }

        input:focus, select:focus {
            outline: none; border-color: var(--primary);
            background: #fff; box-shadow: 0 0 0 4px rgba(30, 58, 138, 0.1);
        }

        /* Specialized Styling for Select to match Inputs */
        select { appearance: none; cursor: pointer; }

        button {
            grid-column: span 2; background: var(--primary);
            color: white; padding: 16px; border: none;
            border-radius: var(--radius-sm); font-size: 1.1rem;
            font-weight: 600; cursor: pointer; transition: 0.2s;
            margin-top: 10px;
        }

        button:hover { background: #172554; transform: translateY(-1px); }

        .link {
            grid-column: span 2; margin-top: 25px;
            text-align: center; font-size: 0.95rem; color: #4b5563;
        }

        .link a { color: var(--primary); text-decoration: none; font-weight: 700; }

        /* Responsive */
        @media (max-width: 1000px) {
            .image-section { display: none; }
            .register-wrapper { width: 550px; height: auto; }
            form { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            button, .link { grid-column: span 1; }
        }
    </style>
</head>
<body>

<div class="register-wrapper">
    <div class="image-section">
        <div style="background: var(--accent); width: 60px; height: 6px; border-radius: 10px; margin-bottom: 20px;"></div>
        <h1>Join the <br>Network.</h1>
        <p>Register your official passenger profile for fast bookings and real-time journey tracking.</p>
    </div>

    <div class="card">
        <h2>Create Account</h2>
        <p class="subtitle">Complete the form to set up your digital rail identity.</p>

        <form method="post" action="register_action.php">
            <div class="input-group full-width">
                <i class="fa-solid fa-id-card"></i>
                <input type="text" name="full_name" placeholder="Full Name (As per ID)" required>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="Email Address" required>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-phone"></i>
                <input type="tel" name="phone" placeholder="Phone Number" required>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-venus-mars"></i>
                <select name="gender" required>
                    <option value="" disabled selected>Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="username" placeholder="Choose Username" required>
            </div>

            <div class="input-group full-width">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Create Password" required>
            </div>

            <button type="submit">Complete Registration</button>
        </form>

        <div class="link">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>
</div>

</body>
</html>