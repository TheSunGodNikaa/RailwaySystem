<?php
require_once "auth_guard.php";
include "../db.php";
require_once __DIR__ . "/../TwoPL/train_data.php";

railwayEnsureBookingHistoryTable($conn);

$query = "
    SELECT
        h.TRANSACTION_ID,
        h.TRAIN_ID,
        h.TRAIN_NUMBER,
        h.TRAIN_NAME,
        h.SOURCE_STATION,
        h.DESTINATION_STATION,
        h.BOARD_TIME,
        h.DROP_TIME,
        h.COMPARTMENT,
        h.SEATS,
        h.SEAT_COUNT,
        h.FARE_PER_SEAT,
        h.TOTAL_AMOUNT,
        h.JOURNEY_DATE,
        h.BOOKED_AT,
        h.BOOKING_STATUS,
        u.FULL_NAME AS PASSENGER_NAME,
        u.EMAIL,
        u.PHONE
    FROM PASSENGER_BOOKING_HISTORY h
    LEFT JOIN USERS u
        ON u.USER_ID = h.USER_ID
    ORDER BY h.BOOKED_AT DESC
";
$stid = oci_parse($conn, $query);
oci_execute($stid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Entry | RailOps Clerk</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg:#f1f5f9; --card:#fff; --text:#0f172a; --muted:#64748b; --line:#e2e8f0; --accent:#2563eb; }
        *{box-sizing:border-box} body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);margin:0;padding:36px;color:var(--text)} .wrap{max-width:1320px;margin:0 auto}
        .top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px}.top h1{margin:0 0 8px;font-size:2rem}.top p{margin:0;color:var(--muted)}.back{display:inline-flex;align-items:center;gap:8px;text-decoration:none;background:#0f172a;color:#fff;padding:12px 16px;border-radius:14px;font-weight:800}
        .card{background:var(--card);border:1px solid var(--line);border-radius:24px;box-shadow:0 18px 30px rgba(15,23,42,.04);padding:24px}.table-wrap{overflow:auto} table{width:100%;border-collapse:collapse} th{text-align:left;padding:12px 10px;color:var(--muted);font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--line)} td{padding:14px 10px;border-bottom:1px solid #f1f5f9;vertical-align:top;font-size:.92rem}
        .meta{color:var(--muted);font-size:.84rem;line-height:1.6}.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:.72rem;font-weight:800;background:#dcfce7;color:#166534}.seat-link{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border-radius:12px;background:var(--accent);color:#fff;text-decoration:none;font-size:.82rem;font-weight:800}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Booking Entry</h1>
            <p>Full clerk-facing booking register with transaction ID, passenger details, route, class, seats, and revenue.</p>
        </div>
        <a href="clerk.php" class="back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Transaction</th>
                        <th>Passenger</th>
                        <th>Train</th>
                        <th>Journey</th>
                        <th>Seats</th>
                        <th>Fare</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $hasRows = false; ?>
                    <?php while ($row = oci_fetch_assoc($stid)): ?>
                        <?php $hasRows = true; ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['TRANSACTION_ID']); ?></strong><br>
                                <span class="meta"><?php echo htmlspecialchars((string) $row['BOOKED_AT']); ?></span><br>
                                <span class="badge"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($row['BOOKING_STATUS']); ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['PASSENGER_NAME'] ?: 'Unknown Passenger'); ?></strong><br>
                                <span class="meta"><?php echo htmlspecialchars($row['EMAIL'] ?: '--'); ?><br><?php echo htmlspecialchars($row['PHONE'] ?: '--'); ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars(($row['TRAIN_NUMBER'] ?: $row['TRAIN_ID']) . ' - ' . $row['TRAIN_NAME']); ?></strong><br>
                                <span class="meta">Class: <?php echo htmlspecialchars($row['COMPARTMENT']); ?></span>
                            </td>
                            <td>
                                <span class="meta">
                                    <?php echo htmlspecialchars($row['SOURCE_STATION'] . ' to ' . $row['DESTINATION_STATION']); ?><br>
                                    Journey Date: <?php echo htmlspecialchars($row['JOURNEY_DATE']); ?><br>
                                    Timing: <?php echo htmlspecialchars(($row['BOARD_TIME'] ?: '--') . ' / ' . ($row['DROP_TIME'] ?: '--')); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['SEATS']); ?></strong><br>
                                <span class="meta"><?php echo htmlspecialchars((string) $row['SEAT_COUNT']); ?> seat(s)</span>
                            </td>
                            <td>
                                <strong>Rs. <?php echo htmlspecialchars(number_format((float) ($row['TOTAL_AMOUNT'] ?? 0), 2)); ?></strong><br>
                                <span class="meta">Per seat: Rs. <?php echo htmlspecialchars(number_format((float) ($row['FARE_PER_SEAT'] ?? 0), 2)); ?></span>
                            </td>
                            <td>
                                <a class="seat-link" href="seat_view.php?train_id=<?php echo urlencode($row['TRAIN_ID']); ?>&compartment=<?php echo urlencode($row['COMPARTMENT']); ?>">
                                    <i class="fa-solid fa-eye"></i> View Seating
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if (!$hasRows): ?>
                        <tr><td colspan="7" class="meta">No booking entries found yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
