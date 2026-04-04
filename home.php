<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home | Indian Railways Reservation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #0f172a;
            --blue: #1d4ed8;
            --amber: #f59e0b;
            --text: #1f2937;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(180deg, #eff6ff 0%, #f8fafc 100%);
            color: var(--text);
        }

        .hero {
            min-height: 100vh;
            background:
                linear-gradient(rgba(15, 23, 42, 0.82), rgba(30, 64, 175, 0.72)),
                url("./assets/photo-1474487548417-781cb71495f3.jpg") center/cover;
            color: white;
            padding: 28px 24px 60px;
            display: flex;
            flex-direction: column;
        }

        .nav, .content, .bottom-cta {
            max-width: 1180px;
            width: 100%;
            margin: 0 auto;
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 64px;
        }

        .brand {
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: white;
            padding: 12px 18px;
            border-radius: 999px;
            font-weight: 700;
            border: 1px solid rgba(255, 255, 255, 0.28);
        }

        .nav-links a.primary {
            background: var(--amber);
            border-color: var(--amber);
            color: #111827;
        }

        .content {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 32px;
            align-items: center;
            flex: 1;
        }

        .intro h1 {
            font-size: clamp(2.5rem, 5vw, 4.6rem);
            line-height: 1.02;
            margin-bottom: 18px;
        }

        .intro p {
            font-size: 1.05rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.88);
            max-width: 640px;
        }

        .info-panel {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 28px;
            padding: 28px;
            backdrop-filter: blur(10px);
        }

        .info-panel h2 {
            font-size: 1.4rem;
            margin-bottom: 18px;
        }

        .info-list {
            display: grid;
            gap: 14px;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 16px 18px;
        }

        .info-item strong {
            display: block;
            margin-bottom: 6px;
            font-size: 1rem;
        }

        .info-item span {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .bottom-cta {
            margin-top: 32px;
            text-align: center;
        }

        .bottom-cta a {
            display: inline-block;
            text-decoration: none;
            background: white;
            color: var(--blue);
            font-weight: 800;
            padding: 16px 28px;
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.22);
        }

        @media (max-width: 900px) {
            .content {
                grid-template-columns: 1fr;
            }

            .nav {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <section class="hero">
        <div class="nav">
            <div class="brand"><i class="fa-solid fa-train"></i> Indian Railways</div>
            <div class="nav-links">
                <a href="login.php">Login</a>
                <a href="register.php" class="primary">Sign Up</a>
            </div>
        </div>

        <div class="content">
            <div class="intro">
                <h1>Travel across India with a faster railway booking experience.</h1>
                <p>
                    Indian Railways connects major cities, regional hubs, pilgrim routes, and business corridors across the country.
                    This system lets passengers learn about the railway service, sign in securely, and continue into the booking module for seat reservation.
                </p>
            </div>

            <div class="info-panel">
                <h2>About The Railway System</h2>
                <div class="info-list">
                    <div class="info-item">
                        <strong>Nationwide Connectivity</strong>
                        <span>Rail routes link metro cities, state capitals, industrial centers, and tourist destinations across India.</span>
                    </div>
                    <div class="info-item">
                        <strong>Passenger-Friendly Booking</strong>
                        <span>Users can register, log in, choose a train class, and move into the seat selection flow from the booking module.</span>
                    </div>
                    <div class="info-item">
                        <strong>Secure Seat Handling</strong>
                        <span>The app uses controlled booking logic to reduce booking conflicts and protect seat availability.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bottom-cta">
            <a href="login.php">Book Ticket</a>
        </div>
    </section>
</body>
</html>
