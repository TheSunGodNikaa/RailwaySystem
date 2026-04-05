<?php
session_start();
include "db.php";

// Check if user is logged in to show profile
$isLoggedIn = isset($_SESSION['user_id']);
$userName = "Profile";

if ($isLoggedIn) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT FULL_NAME FROM USERS WHERE USER_ID = :user_id";
    $stid = oci_parse($conn, $query);
    oci_bind_by_name($stid, ":user_id", $user_id);
    oci_execute($stid);
    $user = oci_fetch_assoc($stid);
    if ($user) {
        $userName = explode(' ', $user['FULL_NAME'])[0];
    }
}

$source = trim($_GET['source'] ?? '');
$destination = trim($_GET['destination'] ?? '');
$journeyDate = trim($_GET['journey_date'] ?? '');
$searchResults = [];
$searchError = '';

$stationsSql = "
    SELECT station_name
    FROM (
        SELECT source_station AS station_name FROM trains
        UNION
        SELECT destination_station AS station_name FROM trains
        UNION
        SELECT station_name FROM train_station
    )
    ORDER BY station_name
";
$stationsStmt = oci_parse($conn, $stationsSql);
oci_execute($stationsStmt);

$stations = [];
while ($station = oci_fetch_assoc($stationsStmt)) {
    $stations[] = $station['STATION_NAME'];
}

if ($source !== '' && $destination !== '') {
    if (strcasecmp($source, $destination) === 0) {
        $searchError = 'Source and destination must be different stations.';
    } else {
        $searchSql = "
            SELECT *
            FROM (
                SELECT
                    t.train_id,
                    t.train_number,
                    t.train_name,
                    t.source_station,
                    t.destination_station,
                    t.departure_time,
                    t.arrival_time,
                    t.duration,
                    t.train_type,
                    t.price,
                    NVL(src.stop_number, 0) AS src_stop_number,
                    CASE
                        WHEN UPPER(t.destination_station) = UPPER(:destination)
                            THEN 999999
                        ELSE NVL(dst.stop_number, -1)
                    END AS dst_stop_number,
                    CASE
                        WHEN UPPER(t.source_station) = UPPER(:source)
                            THEN t.departure_time
                        ELSE src.departure_time
                    END AS board_time,
                    CASE
                        WHEN UPPER(t.destination_station) = UPPER(:destination)
                            THEN t.arrival_time
                        ELSE dst.arrival_time
                    END AS drop_time
                FROM trains t
                LEFT JOIN train_station src
                    ON t.train_id = src.train_id
                    AND UPPER(src.station_name) = UPPER(:source)
                LEFT JOIN train_station dst
                    ON t.train_id = dst.train_id
                    AND UPPER(dst.station_name) = UPPER(:destination)
                WHERE
                    (UPPER(t.source_station) = UPPER(:source) OR src.station_name IS NOT NULL)
                    AND
                    (UPPER(t.destination_station) = UPPER(:destination) OR dst.station_name IS NOT NULL)
            )
            WHERE src_stop_number < dst_stop_number
            ORDER BY train_id
        ";

        $searchStmt = oci_parse($conn, $searchSql);
        oci_bind_by_name($searchStmt, ":source", $source);
        oci_bind_by_name($searchStmt, ":destination", $destination);
        oci_execute($searchStmt);

        while ($row = oci_fetch_assoc($searchStmt)) {
            $searchResults[] = $row;
        }
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'source' => $source,
        'destination' => $destination,
        'journey_date' => $journeyDate,
        'is_logged_in' => $isLoggedIn,
        'error' => $searchError,
        'results' => $searchResults,
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RailOps | Premium Railway Experience</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Default Dark Mode Variables */
            --bg: #0f172a;
            --navy: #0f172a;
            --blue: #2563eb;
            --amber: #f59e0b;
            --text-main: #f1f5f9;
            --text-muted: #cbd5e1;
            --card-bg: #1e293b;
            --glass: rgba(15, 23, 42, 0.8);
            --nav-border: rgba(255, 255, 255, 0.1);
            --footer-bg: #020617; /* Deepest black-navy for contrast */
            --card-shadow: 0 20px 45px rgba(2, 6, 23, 0.35);
        }

        body.light-theme {
            --bg: radial-gradient(circle at top left, #dbeafe 0%, #eff6ff 35%, #e0f2fe 68%, #f8fbff 100%);
            --navy: #0f172a;
            --footer-bg: #0f172a;
            --text-main: #334155;
            --text-muted: #64748b;
            --card-bg: #ffffff;
            --glass: rgba(239, 246, 255, 0.78);
            --nav-border: rgba(37, 99, 235, 0.12);
            --card-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            scroll-behavior: smooth;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        body {
            background: var(--bg);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* --- Scroll Reveal Animation --- */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease-out;
        }
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* --- Navigation --- */
        nav {
            position: fixed;
            top: 0;
            width: 100%;
            height: 80px;
            background: var(--glass);
            backdrop-filter: blur(12px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 5%;
            z-index: 1000;
            border-bottom: 1px solid var(--nav-border);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i { color: var(--blue); }

        .nav-menu {
            display: flex;
            gap: 40px;
            list-style: none;
        }

        .nav-menu a {
            text-decoration: none;
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.95rem;
            transition: 0.3s;
        }

        .nav-menu a:hover { color: var(--blue); }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .theme-toggle {
            cursor: pointer;
            font-size: 1.2rem;
            color: var(--text-main);
            padding: 10px;
            border-radius: 50%;
            transition: 0.3s;
        }

        .theme-toggle:hover { background: rgba(128, 128, 128, 0.1); }

        .nav-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            background: var(--blue);
            color: white;
            padding: 5px 15px 5px 5px;
            border-radius: 50px;
            font-weight: 700;
            transition: 0.3s;
        }

        .nav-profile .avatar-circle {
            width: 35px;
            height: 35px;
            background: white;
            color: var(--blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-profile:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(37, 99, 235, 0.4); }

        /* --- Hero Section --- */
        .hero {
            min-height: 100vh;
            padding: 180px 5% 100px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            gap: 50px;
            background: linear-gradient(rgba(15, 23, 42, 0.7), rgba(15, 23, 42, 0.7)), 
                        url('assets/photo-1474487548417-781cb71495f3.jpg') center/cover;
            position: relative;
        }

        .hero-content h1 {
            font-size: clamp(3rem, 5vw, 4.5rem);
            font-weight: 800;
            line-height: 1.1;
            color: #ffffff;
            margin-bottom: 25px;
            animation: fadeInUp 0.8s ease forwards;
        }

        .hero-content p {
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 35px;
            color: var(--text-muted);
            animation: fadeInUp 0.8s ease 0.2s forwards;
            opacity: 0;
        }

        .hero-image-wrapper {
            position: relative;
            animation: float 6s ease-in-out infinite;
        }

        .hero-image {
            width: 110%;
            height: 500px;
            position: relative;
            background: url('assets/pexels-satvikpandurangi-10662882.jpg') center/cover;
            border-radius: 30px;
            box-shadow: 20px 40px 80px rgba(15, 23, 42, 0.15);
            transform: perspective(1000px) rotateY(-10deg) rotateX(5deg);
            border: 8px solid var(--card-bg);
        }

        .hero-badge {
            position: absolute;
            bottom: -30px;
            left: -30px;
            background: var(--card-bg);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            z-index: 2;
            border: 1px solid var(--nav-border);
        }

        /* --- Features Section --- */
        .features {
            padding: 120px 5%;
            background: transparent;
        }

        .route-search-section {
            padding: 0 5% 120px;
        }

        .route-search-shell {
            background:
                linear-gradient(135deg, rgba(37, 99, 235, 0.18), rgba(245, 158, 11, 0.1)),
                var(--card-bg);
            border: 1px solid var(--nav-border);
            border-radius: 32px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            position: relative;
        }

        .route-search-head {
            display: grid;
            grid-template-columns: minmax(0, 980px);
            justify-content: center;
            gap: 30px;
            padding: 52px 38px 38px;
            align-items: center;
            text-align: center;
        }

        .route-search-copy h2 {
            font-size: clamp(2rem, 3vw, 2.8rem);
            margin-bottom: 12px;
        }

        .route-search-copy p {
            color: var(--text-muted);
            line-height: 1.7;
            max-width: 720px;
            margin: 0 auto;
        }

        .route-search-chip-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
            justify-content: center;
        }

        .route-search-chip {
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.12);
            color: var(--text-main);
            font-weight: 700;
            font-size: 0.85rem;
            border: 1px solid rgba(37, 99, 235, 0.18);
        }

        .route-search-form {
            background: rgba(15, 23, 42, 0.16);
            border: 1px solid var(--nav-border);
            border-radius: 26px;
            padding: 28px;
            backdrop-filter: blur(12px);
            max-width: 980px;
            margin: 0 auto;
        }

        .route-field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 18px;
            align-items: end;
        }

        .route-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
        }

        .route-field input {
            width: 100%;
            border: 1px solid var(--nav-border);
            background: rgba(255, 255, 255, 0.88);
            color: #0f172a;
            border-radius: 16px;
            padding: 16px 18px;
            font-size: 0.95rem;
            font-weight: 600;
            outline: none;
        }

        .route-field input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.14);
        }

        .search-submit-btn {
            border: none;
            border-radius: 16px;
            padding: 16px 26px;
            background: var(--blue);
            color: white;
            font-weight: 800;
            cursor: pointer;
            min-width: 160px;
        }

        .search-submit-btn[disabled] {
            opacity: 0.75;
            cursor: wait;
        }

        .search-feedback {
            margin: 0 38px 24px;
            padding: 16px 18px;
            border-radius: 18px;
            font-weight: 700;
            max-width: 980px;
            margin-left: auto;
            margin-right: auto;
        }

        .search-feedback.error {
            background: rgba(239, 68, 68, 0.14);
            color: #fecaca;
            border: 1px solid rgba(239, 68, 68, 0.24);
        }

        .search-feedback.empty {
            background: rgba(148, 163, 184, 0.12);
            color: var(--text-muted);
            border: 1px solid var(--nav-border);
        }

        .home-results-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
            padding: 0 38px 38px;
            max-width: 1240px;
            margin: 0 auto;
        }

        .search-results-panel {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transform: translateY(-20px);
            transition: max-height 0.5s ease, opacity 0.35s ease, transform 0.45s ease;
        }

        .search-results-panel.visible {
            max-height: 2400px;
            opacity: 1;
            transform: translateY(0);
        }

        .search-results-status {
            padding: 0 38px 28px;
            max-width: 980px;
            margin: 0 auto;
        }

        .search-results-title {
            font-size: 1.35rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .search-results-subtitle {
            color: var(--text-muted);
            font-weight: 600;
            line-height: 1.6;
        }

        .home-train-card {
            background: rgba(15, 23, 42, 0.24);
            border: 1px solid var(--nav-border);
            border-radius: 26px;
            padding: 26px;
            box-shadow: 0 20px 40px rgba(2, 6, 23, 0.16);
        }

        .home-train-top {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 14px;
        }

        .home-train-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.14);
            color: var(--text-main);
            font-weight: 800;
            font-size: 0.82rem;
        }

        .home-train-id {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 700;
        }

        .home-train-card h3 {
            font-size: 1.45rem;
            margin-bottom: 8px;
        }

        .home-train-type {
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 24px;
        }

        .home-route {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .home-route::before {
            content: '';
            position: absolute;
            left: 56px;
            right: 56px;
            top: 50%;
            border-top: 2px dashed rgba(148, 163, 184, 0.4);
            transform: translateY(-50%);
        }

        .home-route-stop,
        .home-route-icon {
            position: relative;
            z-index: 1;
            background: var(--card-bg);
            padding: 0 12px;
        }

        .home-route-stop strong {
            display: block;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }

        .home-route-stop span {
            color: var(--text-muted);
            font-size: 0.83rem;
            font-weight: 700;
        }

        .home-route-icon {
            color: var(--amber);
            font-size: 1.1rem;
        }

        .home-train-meta {
            color: var(--text-muted);
            line-height: 1.7;
            font-size: 0.9rem;
            margin-bottom: 22px;
            font-weight: 600;
        }

        .home-train-actions {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }

        .home-price {
            font-size: 1.4rem;
            font-weight: 800;
        }

        .home-book-btn {
            text-decoration: none;
            padding: 13px 22px;
            border-radius: 14px;
            background: var(--blue);
            color: white;
            font-weight: 800;
            white-space: nowrap;
        }

        .section-header {
            text-align: center;
            margin-bottom: 80px;
        }

        .section-header h2,
        .section-header h3 {
            color: var(--text-main);
        }

        .tilted-grid {
            display: grid;
            gap: 40px;
        }

        .feature-card {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 60px;
            background: var(--card-bg);
            padding: 40px;
            border-radius: 30px;
            align-items: center;
            border: 1px solid var(--nav-border);
            box-shadow: var(--card-shadow);
            transform: rotate(-1.3deg);
            transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .feature-card.reverse {
            grid-template-columns: 1.2fr 1fr;
            transform: rotate(1.3deg);
        }

        .feature-card:hover {
            transform: translateY(-10px) rotate(0deg);
            box-shadow: 0 28px 60px rgba(2, 6, 23, 0.3);
        }

        .feat-img {
            height: 350px;
            border-radius: 20px;
            background-size: cover;
            background-position: center;
            box-shadow: 0 18px 28px rgba(0,0,0,0.18);
        }

        .feat-text h3 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: var(--text-main);
            font-weight: 800;
        }

        .feat-text p {
            line-height: 1.7;
            color: var(--text-muted);
        }

        .hero-actions {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .primary-btn,
        .secondary-btn {
            text-decoration: none;
            padding: 18px 35px;
            border-radius: 15px;
            font-weight: 700;
            display: inline-block;
        }

        .primary-btn {
            background: var(--blue);
            color: white;
        }

        .secondary-btn {
            border: 2px solid var(--blue);
            color: var(--blue);
        }

        /* --- Sliding Sections (Reviews & Partners) --- */
        .slider-container {
            padding: 80px 0;
            background: rgba(128, 128, 128, 0.05);
            overflow: hidden;
            white-space: nowrap;
            position: relative;
        }

        .slider-track {
            display: inline-flex;
            animation: scrollLeft 40s linear infinite;
            gap: 40px;
        }

        .slider-track.reverse {
            animation: scrollRight 40s linear infinite;
        }

        @keyframes scrollLeft {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        @keyframes scrollRight {
            0% { transform: translateX(-50%); }
            100% { transform: translateX(0); }
        }

        .review-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 20px;
            min-width: 350px;
            box-shadow: var(--card-shadow);
            display: inline-block;
            vertical-align: top;
            white-space: normal;
            border: 1px solid var(--nav-border);
        }

        .review-quote {
            font-style: italic;
            color: var(--text-muted);
            margin-bottom: 15px;
        }

        .review-author {
            color: var(--text-main);
        }

        .partner-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 40px;
        }

        /* --- Simple Modal for Footer --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: var(--card-bg);
            color: var(--text-main);
            margin: 15% auto;
            padding: 40px;
            border-radius: 24px;
            width: 90%;
            max-width: 500px;
            position: relative;
            animation: fadeInUp 0.4s ease;
        }

        .modal-title {
            color: var(--text-main);
            margin-bottom: 15px;
        }

        .modal-body {
            line-height: 1.6;
            color: var(--text-muted);
            white-space: pre-line;
        }

        /* --- Footer --- */
        footer {
            background: #000000;
            color: white;
            padding: 72px 5% 32px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: minmax(260px, 1.3fr) repeat(3, minmax(180px, 1fr));
            gap: 36px;
            align-items: start;
        }

        .footer-col {
            text-align: left;
        }

        .footer-col h4 {
            font-size: 1.2rem;
            margin-bottom: 22px;
            position: relative;
        }

        .footer-col h4::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 36px;
            height: 2px;
            background: var(--blue);
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 15px;
        }

        .footer-col ul li a {
            color: #94a3b8;
            text-decoration: none;
            transition: 0.3s;
        }

        .footer-col ul li a:hover { color: white; }

        .footer-brand {
            max-width: 320px;
        }

        .footer-brand .logo {
            color: white;
            margin-bottom: 20px;
        }

        .footer-copy {
            color: #94a3b8;
            line-height: 1.7;
        }

        .footer-contact p {
            color: #94a3b8;
            margin-bottom: 15px;
        }

        .footer-contact i {
            width: 18px;
            margin-right: 10px;
            color: var(--amber);
        }

        .footer-issue-link {
            color: var(--amber);
            font-weight: 700;
            text-decoration: none;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 40px;
            padding-top: 24px;
            text-align: center;
            color: #94a3b8;
            font-size: 0.9rem;
        }

        /* --- Animations --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotateY(-10deg); }
            50% { transform: translateY(-20px) rotateY(-5deg); }
        }

        @media (max-width: 968px) {
            .hero, .feature-card, .feature-card.reverse {
                grid-template-columns: 1fr;
            }
            .route-search-head,
            .route-field-grid,
            .home-results-grid {
                grid-template-columns: 1fr;
            }
            .hero-image { width: 100%; height: 300px; }
            .nav-menu { display: none; }
            .feature-card,
            .feature-card.reverse,
            .feature-card:hover {
                transform: none;
            }
            .footer-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .hero-badge {
                position: static;
                margin-top: 20px;
            }
        }

        @media (max-width: 640px) {
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 28px;
            }
        }
    </style>
</head>
<body class="dark-theme">
    <nav>
        <a href="#" class="logo"><i class="fa-solid fa-train-subway"></i> RailOps</a>
        <ul class="nav-menu">
            <li><a href="#">Home</a></li>
            <li><a href="#features">Features</a></li>
            <li><a href="#contact">Contact Us</a></li>
        </ul>
        <div class="nav-right">
            <i class="fa-solid fa-moon theme-toggle" id="themeBtn" onclick="toggleTheme()"></i>
            <a href="<?php echo $isLoggedIn ? 'passenger.php' : 'login.php'; ?>" class="nav-profile">
                <div class="avatar-circle"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($userName); ?></span>
            </a>
        </div>
    </nav>

    <section class="hero reveal">
        <div class="hero-content">
            <h1>Next-Gen Rail Booking Is Here.</h1>
            <p>Experience India's most advanced railway reservation system. High-speed seat allocation, real-time availability, and encrypted transactions all in one place.</p>
            <div class="hero-actions">
                <a href="login.php" class="primary-btn">Book Tickets</a>
                <a href="register.php" class="secondary-btn">Join Now</a>
            </div>
        </div>
        <div class="hero-image-wrapper">
            <div class="hero-image"></div>
            <div class="hero-badge">
                <div style="color:var(--blue); font-weight:800; font-size:1.5rem;">99.9%</div>
                <div style="font-size:0.8rem; font-weight:700; color:var(--text-main);">Booking Success</div>
            </div>
        </div>
    </section>

    <section class="route-search-section reveal">
        <div class="route-search-shell">
            <div class="route-search-head">
                <div class="route-search-copy">
                    <h2>Search Real Routes Before You Book</h2>
                    <p>Pick a source and destination to see live trains from your database. We match both full-route trains and routes where your stations appear as intermediate stops.</p>
                    <div class="route-search-chip-row">
                        <span class="route-search-chip">Live station lookup</span>
                        <span class="route-search-chip">Route-aware stop order</span>
                        <span class="route-search-chip">Passenger-ready booking handoff</span>
                    </div>
                </div>

                <form method="GET" class="route-search-form">
                    <div class="route-field-grid">
                        <div class="route-field">
                            <label for="source">Source</label>
                            <input list="station-list" id="source" name="source" value="<?php echo htmlspecialchars($source); ?>" placeholder="Enter source station" required>
                        </div>
                        <div class="route-field">
                            <label for="destination">Destination</label>
                            <input list="station-list" id="destination" name="destination" value="<?php echo htmlspecialchars($destination); ?>" placeholder="Enter destination station" required>
                        </div>
                        <div class="route-field">
                            <label for="journey_date">Journey Date</label>
                            <input type="date" id="journey_date" name="journey_date" value="<?php echo htmlspecialchars($journeyDate); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" class="search-submit-btn" id="routeSearchBtn">Search Trains</button>
                    </div>
                </form>
            </div>

            <datalist id="station-list">
                <?php foreach ($stations as $stationName): ?>
                    <option value="<?php echo htmlspecialchars($stationName); ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <div id="searchFeedback"></div>

            <div id="searchResultsPanel" class="search-results-panel<?php echo !empty($searchResults) ? ' visible' : ''; ?>">
                <div class="search-results-status" id="searchResultsStatus">
                    <?php if (!empty($searchResults)): ?>
                        <div class="search-results-title">Available trains</div>
                        <div class="search-results-subtitle">
                            Showing <?php echo count($searchResults); ?> result(s) for <?php echo htmlspecialchars($source); ?> to <?php echo htmlspecialchars($destination); ?><?php if ($journeyDate !== ''): ?> on <?php echo htmlspecialchars($journeyDate); ?><?php endif; ?>.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="home-results-grid" id="searchResultsGrid">
                    <?php foreach ($searchResults as $train): ?>
                        <?php
                        $selectedPassengerUrl = 'passenger.php?source=' . urlencode($source)
                            . '&destination=' . urlencode($destination)
                            . '&journey_date=' . urlencode($journeyDate)
                            . '&train_id=' . urlencode($train['TRAIN_ID']);
                        $bookUrl = $isLoggedIn
                            ? $selectedPassengerUrl
                            : 'login.php?redirect=' . urlencode($selectedPassengerUrl);
                        ?>
                        <div class="home-train-card">
                            <div class="home-train-top">
                                <span class="home-train-badge">
                                    <i class="fa-solid fa-train-subway"></i>
                                    <?php echo htmlspecialchars($train['TRAIN_NUMBER']); ?>
                                </span>
                                <span class="home-train-id">Train ID: <?php echo htmlspecialchars($train['TRAIN_ID']); ?></span>
                            </div>

                            <h3><?php echo htmlspecialchars($train['TRAIN_NAME']); ?></h3>
                            <div class="home-train-type"><?php echo htmlspecialchars($train['TRAIN_TYPE'] ?: 'Standard Service'); ?></div>

                            <div class="home-route">
                                <div class="home-route-stop">
                                    <strong><?php echo htmlspecialchars($source); ?></strong>
                                    <span><?php echo htmlspecialchars($train['BOARD_TIME'] ?: '--'); ?></span>
                                </div>
                                <div class="home-route-icon"><i class="fa-solid fa-route"></i></div>
                                <div class="home-route-stop" style="text-align:right;">
                                    <strong><?php echo htmlspecialchars($destination); ?></strong>
                                    <span><?php echo htmlspecialchars($train['DROP_TIME'] ?: '--'); ?></span>
                                </div>
                            </div>

                            <div class="home-train-meta">
                                Full route: <?php echo htmlspecialchars($train['SOURCE_STATION']); ?> to <?php echo htmlspecialchars($train['DESTINATION_STATION']); ?><br>
                                Duration: <?php echo htmlspecialchars($train['DURATION'] ?: '--'); ?><br>
                                Journey date: <?php echo htmlspecialchars($journeyDate ?: 'Not selected'); ?>
                            </div>

                            <div class="home-train-actions">
                                <div class="home-price">Rs. <?php echo htmlspecialchars(number_format((float) ($train['PRICE'] ?? 0), 2)); ?></div>
                                <a href="<?php echo htmlspecialchars($bookUrl); ?>" class="home-book-btn">Book</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="section-header">
            <h2 style="font-size: 2.5rem; color:var(--blue);">Why Choose RailOps?</h2>
            <p style="color:var(--text-muted); margin-top:10px;">Engineering speed and reliability into every journey.</p>
        </div>

        <div class="tilted-grid">
            <!-- Feature 1 -->
            <div class="feature-card reveal">
                <div class="feat-img" style="background-image: url('assets/images.jpg')"></div>
                <div class="feat-text">
                    <h3>Quantum Seat Allocation</h3>
                    <p>Our proprietary Two-Phase Locking (2PL) algorithm ensures that seat conflicts are a thing of the past. When you select a seat, it's yours. No overlaps, no errors.</p>
                </div>
            </div>

            <!-- Feature 2 -->
            <div class="feature-card reverse reveal">
                <div class="feat-text">
                    <h3>Real-time Traffic Control</h3>
                    <p>We process thousands of requests per second using advanced queueing theory. This means even during peak hours, your experience remains smooth and responsive.</p>
                </div>
                <div class="feat-img" style="background-image: url('assets/360_F_614228326_oNI45dDAFTzWlWIVFGpzmGjaotf331U6.jpg')"></div>
            </div>

            <!-- Feature 3 -->
            <div class="feature-card reveal">
                <div class="feat-img" style="background-image: url('assets/pexels-satvikpandurangi-10662882.jpg')"></div>
                <div class="feat-text">
                    <h3>Modern Seat Visualizer</h3>
                    <p>Don't just book a seat, see it. Our interactive coach layouts let you choose exactly where you want to sit, with clear indicators for lower and upper berths.</p>
                </div>
            </div>

            <!-- Feature 4 -->
            <div class="feature-card reverse reveal">
                <div class="feat-text">
                    <h3>Secure Transaction Layer</h3>
                    <p>Your security is our priority. Every transaction is wrapped in enterprise-grade encryption with automatic rollback mechanisms to protect your money.</p>
                </div>
                <div class="feat-img" style="background-image: url('assets/photo-1474487548417-781cb71495f3.jpg')"></div>
            </div>
        </div>
    </section>

    <!-- Review Section -->
    <div class="slider-container">
        <div class="section-header"><h3>What Our Travelers Say</h3></div>
        <div class="slider-track">
            <?php 
            $reviews = [
                ["Arjun S.", "The seat locking system actually works! No more 'payment failed' issues.", 5],
                ["Priya R.", "Cleanest UI I've ever used for booking trains. Super intuitive.", 4.5],
                ["Vikram K.", "Booked a ticket in seconds. The queuing really makes a difference.", 5],
                ["Sarah L.", "Love the transparency. I can see exactly where my seat is.", 4],
                ["Rahul M.", "Customer support solved my issue in under 5 minutes. Amazing!", 4.5]
            ];
            // Duplicate for smooth loop
            $full_reviews = array_merge($reviews, $reviews);
            foreach($full_reviews as $rev): ?>
            <div class="review-card">
                <div style="color:var(--amber); margin-bottom:10px;">
                    <?php 
                        for($i=1; $i<=5; $i++){
                            if($i <= floor($rev[2])) echo '<i class="fa-solid fa-star"></i>';
                            elseif($rev[2] > floor($rev[2]) && $i == ceil($rev[2])) echo '<i class="fa-solid fa-star-half-stroke"></i>';
                            else echo '<i class="fa-regular fa-star"></i>';
                        }
                    ?>
                </div>
                <p class="review-quote">"<?php echo $rev[1]; ?>"</p>
                <strong class="review-author"><?php echo $rev[0]; ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Collaborations Section -->
    <div class="slider-container" style="background: transparent; border-top: 1px solid var(--nav-border);">
        <div class="slider-track reverse">
            <?php 
            $partners = [
                ["IRCTC", "fa-train"],
                ["Oracle Cloud", "fa-cloud"],
                ["Google Cloud", "fa-google"],
                ["Microsoft Azure", "fa-microsoft"],
                ["Amazon AWS", "fa-amazon"],
                ["Stripe", "fa-stripe"]
            ];
            $full_partners = array_merge($partners, $partners, $partners);
            foreach($full_partners as $p): ?>
            <div class="partner-logo">
                <i class="fa-brands <?php echo $p[1]; ?>"></i> <?php echo $p[0]; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

        <div id="infoModal" class="modal">
        <div class="modal-content">
            <i class="fa-solid fa-xmark" style="position:absolute; top:20px; right:20px; cursor:pointer;" onclick="closeModal()"></i>
            <h2 id="modalTitle" class="modal-title"></h2>
            <p id="modalBody" class="modal-body"></p>
        </div>
    </div>

    <footer id="contact">
        <div class="footer-grid">
            <div class="footer-col footer-brand">
                <div class="logo"><i class="fa-solid fa-train"></i> RailOps</div>
                <p class="footer-copy">The next generation of Indian Railways reservation systems. Built for speed, efficiency, and reliability.</p>
            </div>
            <div class="footer-col">
                <h4>Platform</h4>
                <ul>
                    <li><a href="login.php">Book Tickets</a></li>
                    <li><a href="#">Train Schedule</a></li>
                    <li><a href="#">Station List</a></li>
                    <li><a href="#">Refund Status</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Company</h4>
                <ul>
                    <li><a href="javascript:void(0)" onclick="showModal('About RailOps', 'RailOps is a fictional railway technology company created for this demo project. Founded in 2024 in Kathmandu, RailOps designs reservation software, passenger dashboards, ticket validation tools, and station management systems for modern rail networks.\n\nOur sample team includes 42 engineers, 8 support specialists, and 5 operations planners working across booking reliability, fraud prevention, and customer experience.\n\nMission: make rail travel feel fast, transparent, and dependable for every passenger.\nVision: become the most trusted digital platform for regional railway reservations across South Asia.')">About Us</a></li>
                    <li><a href="javascript:void(0)" onclick="showModal('Join Our Team', 'RailOps is always looking for talented people to join our fictional demo team. Current sample openings include Frontend Developer, PHP Backend Engineer, Oracle Database Administrator, QA Analyst, UI Designer, and Customer Support Associate.\n\nWork model: hybrid, 3 days in office and 2 days remote.\nLocation: Kathmandu, Pokhara, and Biratnagar.\nSalary range: NPR 45,000 to NPR 180,000 per month depending on role and experience.\n\nPerks include medical coverage, annual learning budget, free railway travel credits, flexible hours, and monthly team workshops.\n\nTo apply, send your sample CV and portfolio to careers@railops-demo.com.')">Careers</a></li>
                    <li><a href="javascript:void(0)" onclick="showModal('Terms of Service', 'These Terms of Service are placeholder content for demonstration purposes only.\n\n1. Users must provide accurate registration details when creating an account.\n2. Tickets generated through the platform are non-transferable unless a station officer approves the request.\n3. Refund requests must be submitted at least 6 hours before scheduled departure for full consideration.\n4. RailOps may suspend accounts involved in suspicious booking activity, payment abuse, or repeated cancellation fraud.\n5. Platform schedules, seat availability, and fares shown in this demo may change without notice.\n\nBy continuing to use this sample system, users agree to these fictional terms and acknowledge that the content is not legally binding.')">Terms of Service</a></li>
                    <li><a href="javascript:void(0)" onclick="showModal('Privacy Policy', 'This Privacy Policy is fictional sample text for the project interface.\n\nRailOps may collect user name, email address, phone number, travel history, seat preferences, payment status, and device metadata to improve booking performance and customer support.\n\nSample data retention policy:\n- Account details: stored until account deletion request is approved.\n- Booking history: stored for 18 months.\n- Payment logs: stored for 24 months for audit review.\n- Support conversations: stored for 12 months.\n\nWe do not sell personal information in this demo scenario. Data may be shared with fictional payment processors, railway authorities, and fraud monitoring partners only for service delivery, verification, and reporting.\n\nUsers may request account review, profile correction, or data deletion by emailing privacy@railops-demo.com.')">Privacy Policy</a></li>
                </ul>
            </div>
            <div class="footer-col footer-contact">
                <h4>Contact Support</h4>
                <p><i class="fa-solid fa-envelope"></i>support@railops.com</p>
                <p><i class="fa-solid fa-phone"></i>1800-RAIL-OPS</p>
                <a href="#" class="footer-issue-link">Report an issue <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; 2024 RailOps Reservation Systems. An Indian Railways Authorized Project.
        </div>
    </footer>

    <script>
        // Theme Toggle Logic
        function toggleTheme() {
            const body = document.body;
            const btn = document.getElementById('themeBtn');
            if(body.classList.contains('dark-theme')) {
                body.classList.replace('dark-theme', 'light-theme');
                btn.classList.replace('fa-moon', 'fa-sun');
            } else {
                body.classList.replace('light-theme', 'dark-theme');
                btn.classList.replace('fa-sun', 'fa-moon');
            }
        }

        // Scroll Reveal Logic
        function reveal() {
            var reveals = document.querySelectorAll(".reveal");
            for (var i = 0; i < reveals.length; i++) {
                var windowHeight = window.innerHeight;
                var elementTop = reveals[i].getBoundingClientRect().top;
                var elementVisible = 150;
                if (elementTop < windowHeight - elementVisible) {
                    reveals[i].classList.add("active");
                }
            }
        }
        window.addEventListener("scroll", reveal);
        reveal(); // Initial check

        // Modal Logic
        function showModal(title, body) {
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalBody').innerText = body;
            document.getElementById('infoModal').style.display = "block";
        }

        function closeModal() {
            document.getElementById('infoModal').style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('infoModal')) {
                closeModal();
            }
        }

        const routeSearchForm = document.querySelector('.route-search-form');
        const routeSearchBtn = document.getElementById('routeSearchBtn');
        const feedbackContainer = document.getElementById('searchFeedback');
        const resultsPanel = document.getElementById('searchResultsPanel');
        const resultsStatus = document.getElementById('searchResultsStatus');
        const resultsGrid = document.getElementById('searchResultsGrid');
        const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderFeedback(type, message) {
            if (!message) {
                feedbackContainer.innerHTML = '';
                return;
            }

            feedbackContainer.innerHTML = `<div class="search-feedback ${type}">${escapeHtml(message)}</div>`;
        }

        function buildResultsMarkup(data) {
            const source = data.source || '';
            const destination = data.destination || '';
            const journeyDate = data.journey_date || '';

            resultsStatus.innerHTML = `
                <div class="search-results-title">Available trains</div>
                <div class="search-results-subtitle">
                    Showing ${data.results.length} result(s) for ${escapeHtml(source)} to ${escapeHtml(destination)}${journeyDate ? ` on ${escapeHtml(journeyDate)}` : ''}.
                </div>
            `;

            resultsGrid.innerHTML = data.results.map((train) => {
                const selectedPassengerUrl = `passenger.php?source=${encodeURIComponent(source)}&destination=${encodeURIComponent(destination)}&journey_date=${encodeURIComponent(journeyDate)}&train_id=${encodeURIComponent(train.TRAIN_ID)}`;
                const bookUrl = isLoggedIn
                    ? selectedPassengerUrl
                    : `login.php?redirect=${encodeURIComponent(selectedPassengerUrl)}`;

                const price = Number(train.PRICE || 0).toFixed(2);
                const trainType = train.TRAIN_TYPE || 'Standard Service';
                const duration = train.DURATION || '--';
                const boardTime = train.BOARD_TIME || '--';
                const dropTime = train.DROP_TIME || '--';

                return `
                    <div class="home-train-card">
                        <div class="home-train-top">
                            <span class="home-train-badge">
                                <i class="fa-solid fa-train-subway"></i>
                                ${escapeHtml(train.TRAIN_NUMBER)}
                            </span>
                            <span class="home-train-id">Train ID: ${escapeHtml(train.TRAIN_ID)}</span>
                        </div>
                        <h3>${escapeHtml(train.TRAIN_NAME)}</h3>
                        <div class="home-train-type">${escapeHtml(trainType)}</div>
                        <div class="home-route">
                            <div class="home-route-stop">
                                <strong>${escapeHtml(source)}</strong>
                                <span>${escapeHtml(boardTime)}</span>
                            </div>
                            <div class="home-route-icon"><i class="fa-solid fa-route"></i></div>
                            <div class="home-route-stop" style="text-align:right;">
                                <strong>${escapeHtml(destination)}</strong>
                                <span>${escapeHtml(dropTime)}</span>
                            </div>
                        </div>
                        <div class="home-train-meta">
                            Full route: ${escapeHtml(train.SOURCE_STATION)} to ${escapeHtml(train.DESTINATION_STATION)}<br>
                            Duration: ${escapeHtml(duration)}<br>
                            Journey date: ${escapeHtml(journeyDate || 'Not selected')}
                        </div>
                        <div class="home-train-actions">
                            <div class="home-price">Rs. ${escapeHtml(price)}</div>
                            <a href="${bookUrl}" class="home-book-btn">Book</a>
                        </div>
                    </div>
                `;
            }).join('');

            resultsPanel.classList.add('visible');
        }

        async function handleRouteSearch(event) {
            event.preventDefault();

            const formData = new FormData(routeSearchForm);
            const params = new URLSearchParams(formData);
            params.set('ajax', '1');

            routeSearchBtn.disabled = true;
            routeSearchBtn.textContent = 'Searching...';
            renderFeedback('', '');

            try {
                const response = await fetch(`home.php?${params.toString()}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error('Search request failed.');
                }

                const data = await response.json();

                if (data.error) {
                    resultsPanel.classList.remove('visible');
                    resultsStatus.innerHTML = '';
                    resultsGrid.innerHTML = '';
                    renderFeedback('error', data.error);
                    return;
                }

                if (!data.results || data.results.length === 0) {
                    resultsPanel.classList.remove('visible');
                    resultsStatus.innerHTML = '';
                    resultsGrid.innerHTML = '';
                    renderFeedback('empty', `No trains found for ${data.source} to ${data.destination}.`);
                    return;
                }

                buildResultsMarkup(data);
                renderFeedback('', '');
                resultsPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (error) {
                resultsPanel.classList.remove('visible');
                resultsStatus.innerHTML = '';
                resultsGrid.innerHTML = '';
                renderFeedback('error', 'Something went wrong while searching. Please try again.');
            } finally {
                routeSearchBtn.disabled = false;
                routeSearchBtn.textContent = 'Search Trains';
            }
        }

        routeSearchForm.addEventListener('submit', handleRouteSearch);
    </script>
</body>
</html>
