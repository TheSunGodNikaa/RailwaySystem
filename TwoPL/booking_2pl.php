<?php
session_start();
include_once __DIR__ . "/lock_manager.php";
include "../db.php";
require_once __DIR__ . "/train_data.php";
include_once "identify_data.php";
include_once "lock_type.php";
include_once "lock_request.php";
include_once "acquire_lock.php";
include_once "seat_allocation.php";
include_once "booking_record.php";
include_once "transaction_logger.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!$conn || $_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Database connection failed.");
}

$train_id = trim($_POST['train_id'] ?? '');
$compartment = trim($_POST['compartment'] ?? 'SL');
$journeyDate = trim($_POST['journey_date'] ?? ($_SESSION['active_booking']['journey_date'] ?? date('Y-m-d')));
$seat_no_raw = trim($_POST['seat_no'] ?? '');
$selectedSource = trim($_POST['source'] ?? ($_SESSION['active_booking']['source'] ?? ''));
$selectedDestination = trim($_POST['destination'] ?? ($_SESSION['active_booking']['destination'] ?? ''));
$seatPrice = (float) ($_POST['seat_price'] ?? 0);
$train = railwayGetTrainById($conn, $train_id);
$classCatalog = railwayGetClassCatalog((float) ($train['PRICE'] ?? 0));
$classLabel = $classCatalog[$compartment]['label'] ?? $compartment;
$seatPrice = $seatPrice > 0 ? $seatPrice : ($classCatalog[$compartment]['price'] ?? 0);

if ($train_id === '' || empty($seat_no_raw) || !$train) {
    header("Location: index.php");
    exit;
}

$seat_list = array_values(array_filter(array_map('trim', explode(",", $seat_no_raw))));
$tid = "TXN" . rand(100000, 999999);
$allLocked = true;

foreach ($seat_list as $seat_no) {
    $data_item = identifyDataItem($train_id, $seat_no);
    $request = generateLockRequest($tid, $data_item, determineLockType("WRITE"));
    if (!acquireLock($conn, $request)) {
        $allLocked = false;
        break;
    }
}

if (!$allLocked) {
    releaseLocks($conn, $tid);
    logTransaction($conn, $tid, "ABORTED");
    ?>
    <!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Transaction Failed</title><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet"><style>body{font-family:'Plus Jakarta Sans',sans-serif;background:#fef2f2;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}.card{background:#fff;padding:40px;border-radius:24px;box-shadow:0 20px 25px -5px rgba(220,38,38,.1);text-align:center;max-width:460px;border:1px solid #fee2e2}.btn{margin-top:25px;width:100%;padding:14px;border:none;border-radius:12px;background:#dc2626;color:#fff;font-weight:700;cursor:pointer}</style></head><body><div class="card"><h2 style="color:#991b1b">Transaction Conflict</h2><p style="color:#7f1d1d;line-height:1.6">Another passenger is currently attempting to book one of these seats. The 2PL manager aborted this request to prevent double-booking.</p><button class="btn" onclick="window.location.href='index.php?train_id=<?php echo urlencode((string) $train_id); ?>&journey_date=<?php echo urlencode($journeyDate); ?>&source=<?php echo urlencode($selectedSource); ?>&destination=<?php echo urlencode($selectedDestination); ?>'">Try Different Seats</button></div></body></html>
    <?php
    exit;
}

foreach ($seat_list as $seat_no) {
    allocateSeat($conn, $train_id, $seat_no, $compartment);
    insertBooking($conn, $train_id, $seat_no, $compartment);
}

logTransaction($conn, $tid, "COMMITTED");

$historyPayload = [
    'user_id' => $_SESSION['user_id'],
    'transaction_id' => $tid,
    'train_id' => $train['TRAIN_ID'],
    'train_number' => $train['TRAIN_NUMBER'],
    'train_name' => $train['TRAIN_NAME'],
    'source_station' => $selectedSource !== '' ? $selectedSource : $train['SOURCE_STATION'],
    'destination_station' => $selectedDestination !== '' ? $selectedDestination : $train['DESTINATION_STATION'],
    'board_time' => $train['DEPARTURE_TIME'],
    'drop_time' => $train['ARRIVAL_TIME'],
    'compartment' => $compartment,
    'seats' => implode(', ', $seat_list),
    'seat_count' => count($seat_list),
    'fare_per_seat' => $seatPrice,
    'total_amount' => $seatPrice * count($seat_list),
    'journey_date' => $journeyDate,
    'booking_status' => 'CONFIRMED',
];
railwayInsertPassengerBookingHistory($conn, $historyPayload);

oci_commit($conn);
releaseLocks($conn, $tid);
unset($_SESSION['pending_booking']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Confirmed | E-Ticket</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#2563eb;--success:#059669;--bg:#f8fafc}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);margin:0;padding:40px;display:flex;flex-direction:column;align-items:center}
        .stepper{display:flex;gap:40px;margin-bottom:40px;opacity:.7}.ticket{background:#fff;width:100%;max-width:760px;border-radius:24px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.08)}.header{background:var(--primary);color:#fff;padding:30px;display:flex;justify-content:space-between;align-items:center}.body{padding:40px}
        .row{display:flex;justify-content:space-between;gap:20px}.pnr,.grid{display:grid;gap:20px}.pnr{grid-template-columns:1fr 1fr;border-bottom:2px dashed #e2e8f0;padding-bottom:28px;margin-bottom:28px}.grid{grid-template-columns:1fr 1fr 1fr;margin-bottom:28px}.label{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:1px;font-weight:700;display:block;margin-bottom:5px}.value{font-size:18px;font-weight:700;color:#1e293b}.seats{background:#f8fafc;padding:20px;border-radius:16px}.seatbadges{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}.seat{background:#dcfce7;color:#166534;padding:6px 14px;border-radius:8px;font-weight:700;border:1px solid #bbf7d0}.actions{margin-top:30px;display:flex;gap:15px;width:100%;max-width:760px}.btn{flex:1;padding:16px;border-radius:12px;font-weight:700;text-decoration:none;text-align:center;border:none;cursor:pointer}.secondary{background:#fff;color:var(--primary);border:2px solid var(--primary)}.primary{background:var(--primary);color:#fff}
    </style>
</head>
<body>
    <div class="stepper"><div>OK Class</div><div>OK Seats</div><div style="color:var(--success)">OK Confirmed</div></div>
    <div class="ticket">
        <div class="header">
            <div><h3 style="margin:0">Electronic Reservation Slip</h3><small style="opacity:.85">Indian Railways - IRCTC Authorized</small></div>
            <div><span style="background:rgba(255,255,255,.2);padding:5px 12px;border-radius:20px;font-size:12px;">2PL ENABLED</span></div>
        </div>
        <div class="body">
            <div class="pnr">
                <div><span class="label">Transaction ID</span><span class="value" style="color:var(--primary)"><?php echo htmlspecialchars($tid); ?></span></div>
                <div style="text-align:right"><span class="label">Booking Status</span><span class="value" style="color:var(--success)">CONFIRMED (CNF)</span></div>
            </div>
            <div class="grid">
                <div><span class="label">Train</span><span class="value"><?php echo htmlspecialchars($train['TRAIN_NUMBER'] . ' - ' . $train['TRAIN_NAME']); ?></span></div>
                <div><span class="label">Class</span><span class="value"><?php echo htmlspecialchars($classLabel . ' (' . $compartment . ')'); ?></span></div>
                <div><span class="label">Date of Journey</span><span class="value"><?php echo htmlspecialchars(date('d M, Y', strtotime($journeyDate))); ?></span></div>
            </div>
            <div class="grid">
                <div><span class="label">Route</span><span class="value"><?php echo htmlspecialchars(($selectedSource !== '' ? $selectedSource : $train['SOURCE_STATION']) . ' to ' . ($selectedDestination !== '' ? $selectedDestination : $train['DESTINATION_STATION'])); ?></span></div>
                <div><span class="label">Departure</span><span class="value"><?php echo htmlspecialchars($train['DEPARTURE_TIME']); ?></span></div>
                <div><span class="label">Arrival</span><span class="value"><?php echo htmlspecialchars($train['ARRIVAL_TIME']); ?></span></div>
            </div>
            <div class="grid">
                <div><span class="label">Seats</span><span class="value"><?php echo htmlspecialchars(implode(', ', $seat_list)); ?></span></div>
                <div><span class="label">Fare Per Seat</span><span class="value">Rs. <?php echo htmlspecialchars(number_format($seatPrice, 2)); ?></span></div>
                <div><span class="label">Total Paid</span><span class="value">Rs. <?php echo htmlspecialchars(number_format($seatPrice * count($seat_list), 2)); ?></span></div>
            </div>
            <div class="seats">
                <span class="label">Passenger Berth Details</span>
                <div class="seatbadges"><?php foreach ($seat_list as $s): ?><div class="seat">Seat <?php echo htmlspecialchars($s); ?></div><?php endforeach; ?></div>
            </div>
        </div>
    </div>
    <div class="actions">
        <a href="../passenger.php#history" class="btn secondary">View Booking History</a>
        <button onclick="window.print()" class="btn primary">Print E-Ticket</button>
    </div>
</body>
</html>
