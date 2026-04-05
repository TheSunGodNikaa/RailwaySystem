<?php
session_start();
include "db.php";
require_once __DIR__ . "/TwoPL/train_data.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$query = "SELECT * FROM USERS WHERE USER_ID = :user_id";
$stid = oci_parse($conn, $query);
oci_bind_by_name($stid, ":user_id", $user_id);

if (!oci_execute($stid)) {
    $e = oci_error($stid);
    die("Query failed: " . $e['message']);
}

$user = oci_fetch_assoc($stid);

if (!$user) {
    die("User not found.");
}

railwayEnsureCancellationRequestsTable($conn);
$helpMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_cancellation') {
    $transactionId = trim($_POST['transaction_id'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($transactionId === '' || $reason === '') {
        $helpMessage = 'Please provide both the transaction ID and a short reason.';
    } else {
        railwayInsertCancellationRequest($conn, [
            'user_id' => $user_id,
            'passenger_name' => $user['FULL_NAME'],
            'transaction_id' => $transactionId,
            'reason' => $reason,
            'request_status' => 'PENDING',
        ]);
        @oci_commit($conn);
        $helpMessage = 'Cancellation request submitted. The clerk team can review it now.';
    }
}

$bookingHistory = railwayGetPassengerBookingHistory($conn, $user_id);

$source = trim($_GET['source'] ?? '');
$destination = trim($_GET['destination'] ?? '');
$journeyDate = trim($_GET['journey_date'] ?? '');
$selectedTrainId = trim($_GET['train_id'] ?? '');
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
                AND (:selected_train_id IS NULL OR TO_CHAR(train_id) = :selected_train_id)
            ORDER BY train_id
        ";

        $searchStmt = oci_parse($conn, $searchSql);
        oci_bind_by_name($searchStmt, ":source", $source);
        oci_bind_by_name($searchStmt, ":destination", $destination);
        $selectedTrainBind = $selectedTrainId !== '' ? $selectedTrainId : null;
        oci_bind_by_name($searchStmt, ":selected_train_id", $selectedTrainBind);

        if (!oci_execute($searchStmt)) {
            $e = oci_error($searchStmt);
            die("Search failed: " . $e['message']);
        }

        while ($row = oci_fetch_assoc($searchStmt)) {
            $searchResults[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | RailOps Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f8fafc;
            --sidebar: #0f172a;
            --accent: #2563eb;
            --white: #ffffff;
            --text-main: #1e293b;
            --text-dim: #64748b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg); display: flex; min-height: 100vh; overflow-x: hidden; }

        nav { width: 280px; background: var(--sidebar); padding: 40px 20px; display: flex; flex-direction: column; position: fixed; height: 100vh; color: white; z-index: 1000; }
        .logo { font-size: 1.5rem; font-weight: 800; margin-bottom: 50px; padding-left: 10px; letter-spacing: -1px; }
        .nav-links { list-style: none; flex-grow: 1; }
        .nav-links li { margin-bottom: 10px; }
        .nav-links a { display: flex; align-items: center; gap: 12px; padding: 14px 18px; text-decoration: none; color: #94a3b8; font-weight: 600; border-radius: 12px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.1); color: white; }

        main { flex: 1; margin-left: 280px; padding: 40px; position: relative; }
        .top-bar { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 40px; position: relative; }
        .profile-container { position: relative; z-index: 2000; margin-left: auto; }

        .profile-capsule {
            background: white;
            border-radius: 50px;
            padding: 5px 18px 5px 5px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            min-width: 220px;
            justify-content: flex-start;
        }

        .avatar {
            width: 38px;
            height: 38px;
            background: var(--accent);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
        }

        .profile-overlay {
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            background: white;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid #e2e8f0;
            display: none;
            z-index: 2100;
        }

        .profile-container.active .profile-overlay { display: block; }
        .profile-container.active .profile-capsule { opacity: 0; pointer-events: none; }

        .overlay-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .close-profile { cursor: pointer; color: var(--text-dim); font-size: 1.2rem; padding: 5px; }
        .info-group { margin-bottom: 15px; }
        .info-group label { display: block; font-size: 0.65rem; text-transform: uppercase; font-weight: 800; color: var(--text-dim); letter-spacing: 0.8px; }
        .info-group p { font-size: 0.9rem; font-weight: 600; color: var(--text-main); margin-top: 2px; }

        .search-hero { background: var(--accent); padding: 40px; border-radius: 24px; color: white; margin-bottom: 40px; }
        .search-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 16px;
            margin-top: 24px;
            align-items: end;
        }
        .field-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            margin-bottom: 8px;
            opacity: 0.9;
        }
        .field-group input {
            width: 100%;
            border: none;
            border-radius: 14px;
            padding: 14px 16px;
            font-size: 0.95rem;
            color: var(--text-main);
        }
        .search-btn {
            border: none;
            background: #0f172a;
            color: white;
            border-radius: 14px;
            padding: 14px 24px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .search-btn:hover { transform: translateY(-2px); }

        .results-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 40px;
        }
        .train-card { background: white; border-radius: 24px; padding: 30px; border: 1px solid #e2e8f0; transition: 0.3s; }
        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 10px;
        }
        .meta-badge {
            font-weight: 800;
            color: var(--accent);
            font-size: 0.72rem;
            background: #eff6ff;
            padding: 4px 10px;
            border-radius: 50px;
        }
        .subtext { color: var(--text-dim); font-size: 0.85rem; font-weight: 600; }

        .route-path {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0;
            position: relative;
        }
        .route-line {
            position: absolute;
            left: 60px;
            right: 60px;
            top: 50%;
            height: 2px;
            border-top: 2px dashed #e2e8f0;
            transform: translateY(-50%);
            z-index: 1;
        }
        .station { position: relative; z-index: 5; background: white; padding: 0 15px; }
        .train-icon-mid { position: relative; z-index: 5; background: white; padding: 0 15px; color: #cbd5e1; }
        .station h4 { font-size: 1.25rem; font-weight: 800; }
        .station p { font-size: 0.8rem; color: var(--text-dim); font-weight: 600; }

        .book-btn { background: var(--accent); color: white; padding: 12px 28px; border-radius: 14px; text-decoration: none; font-weight: 700; transition: 0.3s; display: inline-block; }
        .book-btn:hover { background: #1d4ed8; transform: translateY(-2px); }

        .empty-state {
            background: white;
            border: 1px dashed #cbd5e1;
            border-radius: 24px;
            padding: 30px;
            color: var(--text-dim);
            margin-bottom: 40px;
            font-weight: 600;
        }

        .error-banner {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px 18px;
            border-radius: 14px;
            margin-top: 18px;
            font-weight: 700;
        }

        .selected-train-note {
            background: #dbeafe;
            color: #1d4ed8;
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 24px;
            font-weight: 700;
        }

        .history-section { background: white; border-radius: 24px; padding: 32px; border: 1px solid #e2e8f0; margin-bottom: 40px; }
        .history-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .history-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; }
        .history-card h4 { margin: 10px 0 8px; font-size: 1.2rem; }
        .history-meta { color: var(--text-dim); font-size: 0.86rem; font-weight: 600; line-height: 1.7; }
        .history-chip { display: inline-flex; align-items: center; gap: 8px; background: #dbeafe; color: var(--accent); border-radius: 999px; padding: 8px 12px; font-size: 0.78rem; font-weight: 800; }
        .history-total { font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin-top: 14px; }
        .help-section { background: #0f172a; border-radius: 24px; padding: 40px; color: white; margin-top: 40px; }
        .help-form { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 24px 0 30px; }
        .help-form textarea, .help-form input { width: 100%; border: none; border-radius: 14px; padding: 14px 16px; font-size: 0.95rem; color: var(--text-main); }
        .help-form textarea { min-height: 120px; resize: vertical; }
        .help-full { grid-column: span 2; }
        .help-banner { background: rgba(255,255,255,0.12); color: #bfdbfe; padding: 14px 18px; border-radius: 14px; margin-bottom: 18px; font-weight: 700; }
        .faq-section { background: transparent; border-radius: 0; padding: 0; color: white; margin-top: 0; }
        .faq-item { border-bottom: 1px solid rgba(255,255,255,0.1); padding: 22px 0; cursor: pointer; }
        .faq-item:last-child { border: none; }
        .faq-question { display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 1rem; }
        .faq-answer { max-height: 0; overflow: hidden; transition: all 0.4s ease; color: #94a3b8; font-size: 0.85rem; margin-top: 0; line-height: 1.6; }
        .faq-item.active .faq-answer { max-height: 200px; margin-top: 15px; }
        .plus-icon { transition: transform 0.3s; font-size: 1rem; }
        .faq-item.active .plus-icon { transform: rotate(45deg); color: var(--accent); }

        @media (max-width: 900px) {
            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .profile-container {
                margin-left: 0;
            }
            .search-form,
            .results-grid,
            .history-grid,
            .help-form {
                grid-template-columns: 1fr;
            }
            .help-full { grid-column: span 1; }
        }
    </style>
</head>
<body>

<nav>
    <div class="logo"><i class="fa-solid fa-train"></i> RailOps</div>
    <ul class="nav-links">
        <li><a href="#" class="active"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li><a href="TwoPL/index.php"><i class="fa-solid fa-ticket"></i> Book Ticket</a></li>
        <li><a href="#history"><i class="fa-solid fa-clock-rotate-left"></i> History</a></li>
        <li><a href="#help-center"><i class="fa-solid fa-circle-question"></i> Help Center</a></li>
    </ul>
    <a href="login.php" style="margin-top:auto; color: #fda4af; text-decoration: none; font-weight: 700; padding: 15px; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-power-off"></i> Logout
    </a>
</nav>

<main>
    <div class="top-bar">
        <h2 style="font-weight: 800; letter-spacing: -1px; color: var(--text-main);">Passenger Dashboard</h2>

        <div class="profile-container" id="profileCont">
            <div class="profile-capsule" onclick="event.stopPropagation(); document.getElementById('profileCont').classList.add('active')">
                <div class="avatar"><?php echo strtoupper(substr($user['FULL_NAME'], 0, 1)); ?></div>
                <span style="font-weight:700; font-size:0.85rem; color: var(--text-main);"><?php echo htmlspecialchars($user['FULL_NAME']); ?></span>
            </div>

            <div class="profile-overlay" id="profileOverlay" onclick="event.stopPropagation();">
                <div class="overlay-header">
                    <div class="avatar" style="width:45px; height:45px; font-size:1.1rem;"><?php echo strtoupper(substr($user['FULL_NAME'], 0, 1)); ?></div>
                    <i class="fa-solid fa-xmark close-profile" onclick="document.getElementById('profileCont').classList.remove('active')"></i>
                </div>

                <div class="info-group">
                    <label>Full Name</label>
                    <p><?php echo htmlspecialchars($user['FULL_NAME']); ?></p>
                </div>
                <div class="info-group">
                    <label>Email Address</label>
                    <p><?php echo htmlspecialchars($user['EMAIL']); ?></p>
                </div>
                <div class="info-group">
                    <label>Phone Number</label>
                    <p><?php echo htmlspecialchars($user['PHONE']); ?></p>
                </div>
                <div class="info-group">
                    <label>Gender</label>
                    <p><?php echo htmlspecialchars($user['GENDER']); ?></p>
                </div>

                <hr style="border:0; border-top:1px solid #f1f5f9; margin:20px 0;">
                <a href="login.php" style="color:red; text-decoration:none; font-weight:700; font-size:0.85rem; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                </a>
            </div>
        </div>
    </div>

    <section class="search-hero">
        <h1 style="font-weight: 800; margin-bottom: 8px;">Welcome, <?php echo htmlspecialchars(explode(' ', $user['FULL_NAME'])[0]); ?></h1>
        <p style="opacity: 0.9;">Search trains by source and destination using the routes saved in the database.</p>

        <form method="GET" class="search-form">
            <div class="field-group">
                <label for="source">Source Station</label>
                <input list="station-list" id="source" name="source" value="<?php echo htmlspecialchars($source); ?>" placeholder="Enter source station" required>
            </div>
            <div class="field-group">
                <label for="destination">Destination Station</label>
                <input list="station-list" id="destination" name="destination" value="<?php echo htmlspecialchars($destination); ?>" placeholder="Enter destination station" required>
            </div>
            <input type="hidden" name="journey_date" value="<?php echo htmlspecialchars($journeyDate); ?>">
            <?php if ($selectedTrainId !== ''): ?>
                <input type="hidden" name="train_id" value="<?php echo htmlspecialchars($selectedTrainId); ?>">
            <?php endif; ?>
            <button type="submit" class="search-btn">Search Trains</button>
        </form>

        <datalist id="station-list">
            <?php foreach ($stations as $stationName): ?>
                <option value="<?php echo htmlspecialchars($stationName); ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <?php if ($searchError !== ''): ?>
            <div class="error-banner"><?php echo htmlspecialchars($searchError); ?></div>
        <?php endif; ?>
    </section>

    <?php if ($source === '' || $destination === ''): ?>
        <div class="empty-state">
            Enter a source and destination above to find matching trains, including routes where your stations are intermediate stops.
        </div>
    <?php elseif (empty($searchResults) && $searchError === ''): ?>
        <div class="empty-state">
            No trains were found for <?php echo htmlspecialchars($source); ?> to <?php echo htmlspecialchars($destination); ?>.
        </div>
    <?php else: ?>
        <?php if ($selectedTrainId !== ''): ?>
            <div class="selected-train-note">
                Showing the train you selected from home. Train ID: <?php echo htmlspecialchars($selectedTrainId); ?>
                <?php if ($journeyDate !== ''): ?>
                    | Journey date: <?php echo htmlspecialchars($journeyDate); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="results-grid">
            <?php foreach ($searchResults as $train): ?>
                <div class="train-card">
                    <div class="meta-row">
                        <span class="meta-badge"><?php echo htmlspecialchars($train['TRAIN_NUMBER']); ?></span>
                        <span class="subtext">Train ID: <?php echo htmlspecialchars($train['TRAIN_ID']); ?></span>
                    </div>

                    <h3 style="font-size: 1.35rem; margin-bottom: 8px; color: var(--text-main);">
                        <?php echo htmlspecialchars($train['TRAIN_NAME']); ?>
                    </h3>
                    <p class="subtext" style="margin-bottom: 20px;">
                        <?php echo htmlspecialchars($train['TRAIN_TYPE'] ?: 'Standard Service'); ?>
                    </p>

                    <div class="route-path">
                        <div class="route-line"></div>
                        <div class="station">
                            <h4><?php echo htmlspecialchars($source); ?></h4>
                            <p><?php echo htmlspecialchars($train['BOARD_TIME'] ?: '--'); ?></p>
                        </div>
                        <div class="train-icon-mid"><i class="fa-solid fa-train"></i></div>
                        <div class="station" style="text-align:right;">
                            <h4><?php echo htmlspecialchars($destination); ?></h4>
                            <p><?php echo htmlspecialchars($train['DROP_TIME'] ?: '--'); ?></p>
                        </div>
                    </div>

                    <div class="subtext" style="margin-bottom: 20px;">
                        Full route: <?php echo htmlspecialchars($train['SOURCE_STATION']); ?> to <?php echo htmlspecialchars($train['DESTINATION_STATION']); ?>
                        <?php if (!empty($train['DURATION'])): ?>
                            | Duration: <?php echo htmlspecialchars($train['DURATION']); ?>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div style="font-weight:800; font-size:1.4rem; color: var(--text-main);">
                            Rs. <?php echo htmlspecialchars(number_format((float) ($train['PRICE'] ?? 0), 2)); ?>
                        </div>
                        <a href="TwoPL/index.php?train_id=<?php echo urlencode($train['TRAIN_ID']); ?>&source=<?php echo urlencode($source); ?>&destination=<?php echo urlencode($destination); ?>&journey_date=<?php echo urlencode($journeyDate); ?>" class="book-btn">Book Now</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="history-section" id="history">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:20px; margin-bottom:24px;">
            <div>
                <h3 style="margin:0; font-size:1.5rem;">Booking History</h3>
                <p class="subtext" style="margin-top:6px;">Every confirmed train you book through the 2PL flow appears here with route, seats, fare, and transaction details.</p>
            </div>
            <a href="TwoPL/index.php" class="book-btn" style="white-space:nowrap;">Book Another Trip</a>
        </div>

        <?php if (empty($bookingHistory)): ?>
            <div class="empty-state" style="margin-bottom:0;">
                No bookings yet. Search a train and complete one booking to start building your passenger history.
            </div>
        <?php else: ?>
            <div class="history-grid">
                <?php foreach ($bookingHistory as $item): ?>
                    <div class="history-card">
                        <span class="history-chip">
                            <i class="fa-solid fa-receipt"></i>
                            <?php echo htmlspecialchars($item['TRANSACTION_ID'] ?: 'CONFIRMED'); ?>
                        </span>
                        <h4><?php echo htmlspecialchars(($item['TRAIN_NUMBER'] ?: $item['TRAIN_ID']) . ' - ' . $item['TRAIN_NAME']); ?></h4>
                        <div class="history-meta">
                            Route: <?php echo htmlspecialchars($item['SOURCE_STATION']); ?> to <?php echo htmlspecialchars($item['DESTINATION_STATION']); ?><br>
                            Journey Date: <?php echo htmlspecialchars($item['JOURNEY_DATE']); ?><br>
                            Boarding / Arrival: <?php echo htmlspecialchars($item['BOARD_TIME'] ?: '--'); ?> / <?php echo htmlspecialchars($item['DROP_TIME'] ?: '--'); ?><br>
                            Class: <?php echo htmlspecialchars($item['COMPARTMENT']); ?><br>
                            Seats: <?php echo htmlspecialchars($item['SEATS']); ?><br>
                            Seats Count: <?php echo htmlspecialchars($item['SEAT_COUNT']); ?><br>
                            Base Fare: Rs. <?php echo htmlspecialchars(number_format((float) ($item['BASE_FARE'] ?? 0), 2)); ?><br>
                            Fare Per Seat: Rs. <?php echo htmlspecialchars(number_format((float) ($item['FARE_PER_SEAT'] ?? 0), 2)); ?><br>
                            <?php if (!empty($item['PASSENGER_SUMMARY'])): ?>
                                Passenger Summary:<br><?php echo nl2br(htmlspecialchars($item['PASSENGER_SUMMARY'])); ?><br>
                            <?php endif; ?>
                            Booked At: <?php echo htmlspecialchars($item['BOOKED_AT']); ?><br>
                            Status: <?php echo htmlspecialchars($item['BOOKING_STATUS']); ?>
                            <?php if (($item['BOOKING_STATUS'] ?? '') === 'CANCELLED'): ?>
                                <br><strong style="color:#dc2626;">Booking cancelled by clerk action.</strong>
                            <?php endif; ?>
                        </div>
                        <div class="history-total">Total: Rs. <?php echo htmlspecialchars(number_format((float) ($item['TOTAL_AMOUNT'] ?? 0), 2)); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="help-section" id="help-center">
        <h3 style="margin-bottom: 10px; font-weight: 800; font-size: 1.5rem;">Help Center</h3>
        <p style="color:#cbd5e1; line-height:1.7;">Need a cancellation? Send the clerk team a request with your transaction ID and a short reason. They can review it directly from the staff dashboard.</p>

        <?php if ($helpMessage !== ''): ?>
            <div class="help-banner"><?php echo htmlspecialchars($helpMessage); ?></div>
        <?php endif; ?>

        <form method="POST" class="help-form">
            <input type="hidden" name="action" value="request_cancellation">
            <div>
                <label style="display:block; margin-bottom:8px; font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:#cbd5e1;">Transaction ID</label>
                <input type="text" name="transaction_id" placeholder="Enter your booking transaction ID" required>
            </div>
            <div>
                <label style="display:block; margin-bottom:8px; font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:#cbd5e1;">Passenger</label>
                <input type="text" value="<?php echo htmlspecialchars($user['FULL_NAME']); ?>" disabled>
            </div>
            <div class="help-full">
                <label style="display:block; margin-bottom:8px; font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:#cbd5e1;">Short Reason</label>
                <textarea name="reason" placeholder="Example: Journey postponed due to personal reason." required></textarea>
            </div>
            <div class="help-full">
                <button type="submit" class="book-btn">Request Booking Cancellation</button>
            </div>
        </form>

        <div class="faq-section">
        <h3 style="margin-bottom: 25px; font-weight: 800; font-size: 1.4rem;">System Architecture</h3>
        <div class="faq-item" onclick="this.classList.toggle('active')">
            <div class="faq-question">
                How does the seat locking system work?
                <span class="plus-icon"><i class="fa-solid fa-plus"></i></span>
            </div>
            <div class="faq-answer">
                We implement Two-Phase Locking (2PL). When you select a seat, it enters a locked state in our database, preventing any other user from acquiring that same seat until your transaction is either committed or rolled back.
            </div>
        </div>
        <div class="faq-item" onclick="this.classList.toggle('active')">
            <div class="faq-question">
                Is my payment data secure?
                <span class="plus-icon"><i class="fa-solid fa-plus"></i></span>
            </div>
            <div class="faq-answer">
                Absolutely. Our system uses enterprise-grade encryption and session-based authentication to ensure that your personal and financial details remain private.
            </div>
        </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('click', function() {
    const container = document.getElementById('profileCont');
    if (container.classList.contains('active')) {
        container.classList.remove('active');
    }
});
</script>

</body>
</html>
