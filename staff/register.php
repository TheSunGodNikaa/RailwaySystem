<?php if(isset($_GET['success'])): ?>
    <div class="success-banner">
        Clerk account created successfully!
    </div>
<?php endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration | RailOps Internal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0f172a; /* Slate Dark */
            --accent: #38bdf8;  /* Administrative Blue */
            --border: #e2e8f0;
            --radius: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }

        body {
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            /* Engineering Grid Background */
            background-image: linear-gradient(#e2e8f0 1.5px, transparent 1.5px), linear-gradient(90deg, #e2e8f0 1.5px, transparent 1.5px);
            background-size: 40px 40px;
        }

        .success-banner {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #22c55e;
            color: white;
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: bold;
            z-index: 999;
        }

        .staff-wrapper {
            width: 100%;
            max-width: 900px;
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            border: 1px solid #cbd5e1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Official Header */
        .admin-header {
            background: var(--primary);
            color: white;
            padding: 30px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-header h2 { font-size: 1.5rem; letter-spacing: 0.5px; }
        .admin-header span { font-size: 0.8rem; color: var(--accent); font-weight: 800; text-transform: uppercase; }

        /* Form Area */
        .card { padding: 50px; }

        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .section-title {
            grid-column: span 2;
            font-size: 0.85rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 10px;
            margin-top: 10px;
        }

        .input-group { position: relative; }
        .input-group i {
            position: absolute; left: 16px; top: 50%;
            transform: translateY(-50%); color: #94a3b8;
        }

        input, select {
            width: 100%; padding: 14px 14px 14px 48px;
            border: 1.5px solid var(--border);
            border-radius: 10px; font-size: 0.95rem;
            transition: 0.2s; color: var(--primary);
        }

        input:focus, select:focus {
            outline: none; border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.1);
        }

        .full-width { grid-column: span 2; }

        button {
            grid-column: span 2; background: var(--primary);
            color: white; padding: 18px; border: none;
            border-radius: 10px; font-size: 1rem;
            font-weight: 700; cursor: pointer; transition: 0.3s;
            margin-top: 20px;
            text-transform: uppercase; letter-spacing: 1px;
        }

        button:hover { background: #1e293b; letter-spacing: 2px; }

        .footer-note {
            grid-column: span 2; text-align: center;
            margin-top: 20px; font-size: 0.85rem; color: #94a3b8;
        }

        @media (max-width: 700px) {
            form { grid-template-columns: 1fr; }
            .full-width, .section-title, button { grid-column: span 1; }
            .admin-header { padding: 20px; flex-direction: column; text-align: center; gap: 10px; }
        }
    </style>
</head>
<body>

<div class="staff-wrapper">
    <div class="admin-header">
        <div>
            <h2>Clerk Account Creation</h2>
            <p style="font-size: 0.8rem; opacity: 0.7;">Official Staff Registration Module</p>
        </div>
        <span>Internal Access Only</span>
    </div>

    <div class="card">
        <form method="post" action="register_action.php">
            
            <div class="section-title">Employment Details</div>

            <div class="input-group">
                <i class="fa-solid fa-id-badge"></i>
                <input type="text" name="emp_id" placeholder="Official Employee ID" required>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-briefcase"></i>
                <select name="designation" required>
                    <option value="" disabled selected>Select Designation</option>
                    <option value="junior_clerk">Junior Clerk</option>
                    <option value="senior_clerk">Senior Clerk</option>
                    <option value="station_master">Station Master</option>
                    <option value="admin">System Administrator</option>
                </select>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-map-location-dot"></i>
                <select name="station_code" required>
                    <option value="" disabled selected>Assigned Station Code</option>
                    <option value="NDLS">NDLS - New Delhi</option>
                    <option value="CSMT">CSMT - Mumbai Terminus</option>
                    <option value="HWH">HWH - Howrah (Kolkata)</option>
                    <option value="MAS">MAS - Chennai Central</option>
                    <option value="SBC">SBC - KSR Bengaluru</option>
                    <option value="HYB">HYB - Hyderabad Decan</option>
                    <option value="ADI">ADI - Ahmedabad</option>
                    <option value="PNBE">PNBE - Patna Junction</option>
                    <option value="LKO">LKO - Lucknow Charbagh</option>
                    <option value="JP">JP - Jaipur Junction</option>
                    <option value="BCT">BCT - Mumbai Central</option>
                    <option value="BSB">BSB - Varanasi Junction</option>
                    <option value="MAS">MAS - Chennai Central</option>
                    <option value="ST">ST - Surat</option>
                    <option value="PUNE">PUNE - Pune Junction</option>
                    <option value="CNB">CNB - Kanpur Central</option>
                    <option value="NGP">NGP - Nagpur Junction</option>
                    <option value="BPL">BPL - Bhopal Junction</option>
                    <option value="MGS">DDU - Pt. Deen Dayal Upadhyaya</option>
                    <option value="GHY">GHY - Guwahati</option>
                </select>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-building"></i>
                <select name="department" required>
                    <option value="" disabled selected>Department</option>
                    <option value="commercial">Commercial</option>
                    <option value="operations">Operations</option>
                    <option value="technical">Technical</option>
                </select>
            </div>

            <div class="section-title">Personal Information</div>

            <div class="input-group full-width">
                <i class="fa-solid fa-user-tie"></i>
                <input type="text" name="full_name" placeholder="Legal Full Name" required>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="Official Email Address" required>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-calendar-day"></i>
                <input type="text" onfocus="(this.type='date')" name="joining_date" placeholder="Date of Joining" required>
            </div>

            <div class="section-title">System Credentials</div>

            <div class="input-group">
                <i class="fa-solid fa-user-lock"></i>
                <input type="text" name="username" placeholder="Login Username" required>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-key"></i>
                <input type="password" name="password" placeholder="System Password" required>
            </div>

            <button type="submit">Initialize Clerk Account</button>
        </form>
        
        <div class="footer-note">
            <i class="fa-solid fa-circle-info"></i> All registrations are logged and subject to audit by the Division Manager.
        </div>
    </div>
</div>

</body>
</html>