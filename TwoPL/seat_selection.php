<?php
session_start();
include_once "../db.php";
require_once __DIR__ . "/train_data.php";

$train_id = trim($_REQUEST['train_id'] ?? '');
$compartment = trim($_REQUEST['compartment'] ?? 'SL');
$journeyDate = trim($_REQUEST['journey_date'] ?? ($_SESSION['active_booking']['journey_date'] ?? date('Y-m-d')));
$selectedSource = trim($_REQUEST['source'] ?? ($_SESSION['active_booking']['source'] ?? ''));
$selectedDestination = trim($_REQUEST['destination'] ?? ($_SESSION['active_booking']['destination'] ?? ''));
$passengerManifest = railwayNormalizePassengerManifest($_REQUEST['passengers'] ?? ($_SESSION['active_booking']['passengers'] ?? []));

if ($train_id === '' || empty($passengerManifest)) {
    header("Location: index.php");
    exit();
}

$train = railwayGetTrainById($conn, $train_id);
if (!$train) {
    header("Location: index.php");
    exit();
}

$classCatalog = railwayGetClassCatalog((float) ($train['PRICE'] ?? 0));
$seatPrice = (float) ($classCatalog[$compartment]['price'] ?? railwayRoundFare((float) ($train['PRICE'] ?? 0)));
$classLabel = $classCatalog[$compartment]['label'] ?? $compartment;
$pricing = railwayCalculateManifestPricing($seatPrice, $passengerManifest);
$passengerCount = count($pricing['passengers']);

$_SESSION['active_booking'] = [
    'train_id' => $train['TRAIN_ID'],
    'train_number' => $train['TRAIN_NUMBER'],
    'train_name' => $train['TRAIN_NAME'],
    'source' => $selectedSource !== '' ? $selectedSource : $train['SOURCE_STATION'],
    'destination' => $selectedDestination !== '' ? $selectedDestination : $train['DESTINATION_STATION'],
    'full_source' => $train['SOURCE_STATION'],
    'full_destination' => $train['DESTINATION_STATION'],
    'departure' => $train['DEPARTURE_TIME'],
    'arrival' => $train['ARRIVAL_TIME'],
    'duration' => $train['DURATION'],
    'journey_date' => $journeyDate,
    'passengers' => $pricing['passengers'],
    'compartment' => $compartment,
    'base_fare' => $seatPrice,
    'pricing' => $pricing,
];

$sql = "SELECT SEAT_NO, STATUS FROM TRAIN_SEATS
        WHERE TRAIN_ID = :train_id AND COMPARTMENT = :compartment
        ORDER BY TO_NUMBER(REGEXP_REPLACE(SEAT_NO, '[^0-9]', '')) ASC";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":train_id", $train_id);
oci_bind_by_name($stmt, ":compartment", $compartment);
oci_execute($stmt);

$seats = [];
while ($row = oci_fetch_assoc($stmt)) {
    $seats[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coach Layout | <?php echo htmlspecialchars($train['TRAIN_NAME']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eef3f8; --card:#fff; --line:#e2e8f0; --text:#0f172a; --muted:#64748b; --accent:#2563eb; --selected:#10b981; --booked:#cbd5e1; --warn:#dc2626; }
        *{box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif}
        body{margin:0;background:var(--bg);padding:40px}
        .wrap{display:flex;gap:30px;max-width:1240px;margin:0 auto}
        .coach,.side{background:var(--card);border-radius:24px;box-shadow:0 10px 30px rgba(15,23,42,.08)}
        .coach{flex:1;padding:30px}
        .side{width:390px;padding:28px;height:fit-content;position:sticky;top:30px}
        .coachbox{display:flex;flex-direction:column;gap:18px;padding:18px;background:#f8fafc;border:2px solid var(--line);border-radius:18px;max-height:72vh;overflow-y:auto}
        .bay{display:grid;grid-template-columns:repeat(3,1fr) 40px 1fr;gap:10px;padding-bottom:18px;border-bottom:1px solid var(--line)}
        .main{display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:1fr 1fr;gap:8px}
        .sidecol{display:grid;grid-template-rows:1fr 1fr;gap:8px}
        .aisle{background:#e5e7eb;border-radius:6px}
        .seat{height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;cursor:pointer;border:1px solid #d1d5db;background:#fff;color:#475569}
        .seat.booked{background:var(--booked);color:#94a3b8;cursor:not-allowed;border:none}
        .seat.selected{background:var(--selected);color:#fff;border-color:var(--selected);box-shadow:0 0 12px rgba(16,185,129,.35)}
        .pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#dbeafe;color:var(--accent);font-size:.8rem;font-weight:800;margin-bottom:16px}
        .muted{color:var(--muted)}
        .price{font-size:2rem;font-weight:800;color:var(--accent);margin:16px 0}
        .btn{width:100%;padding:16px;border:none;border-radius:14px;background:var(--accent);color:#fff;font-weight:800;cursor:pointer}
        .warn{margin-top:12px;color:var(--warn);font-weight:700;line-height:1.5}
        .fare-lines,.passenger-lines{display:grid;gap:10px;margin-top:16px}
        .fare-line,.passenger-line{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
        .passenger-line{padding:10px 0;border-top:1px solid var(--line)}
        .passenger-line:first-child{border-top:none;padding-top:0}
        @media (max-width:980px){body{padding:20px}.wrap{flex-direction:column}.side{width:100%;position:static}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="coach">
        <div class="pill"><?php echo htmlspecialchars($train['TRAIN_NUMBER']); ?> | <?php echo htmlspecialchars($classLabel); ?></div>
        <h2 style="margin:0 0 6px 0"><?php echo htmlspecialchars($train['TRAIN_NAME']); ?></h2>
        <p class="muted" style="margin:0 0 24px 0">
            <?php echo htmlspecialchars(($selectedSource !== '' ? $selectedSource : $train['SOURCE_STATION']) . ' to ' . ($selectedDestination !== '' ? $selectedDestination : $train['DESTINATION_STATION']) . ' | ' . date('D, d M Y', strtotime($journeyDate))); ?>
        </p>
        <p class="muted" style="margin:0 0 18px 0">Select exactly <?php echo htmlspecialchars((string) $passengerCount); ?> seat<?php echo $passengerCount > 1 ? 's' : ''; ?> for this booking.</p>
        <div class="coachbox">
            <?php foreach (array_chunk($seats, 8) as $bay_seats): ?>
                <div class="bay">
                    <div class="main">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <?php if (isset($bay_seats[$i])): $s = $bay_seats[$i]; $cls = ($s['STATUS'] === 'BOOKED') ? 'booked' : 'available'; ?>
                                <div class="seat <?php echo $cls; ?>" onclick="toggleSeat('<?php echo htmlspecialchars($s['SEAT_NO'], ENT_QUOTES); ?>', this)"><?php echo htmlspecialchars($s['SEAT_NO']); ?></div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div class="aisle"></div>
                    <div class="sidecol">
                        <?php for ($i = 6; $i < 8; $i++): ?>
                            <?php if (isset($bay_seats[$i])): $s = $bay_seats[$i]; $cls = ($s['STATUS'] === 'BOOKED') ? 'booked' : 'available'; ?>
                                <div class="seat <?php echo $cls; ?>" onclick="toggleSeat('<?php echo htmlspecialchars($s['SEAT_NO'], ENT_QUOTES); ?>', this)"><?php echo htmlspecialchars($s['SEAT_NO']); ?></div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="side">
        <h3 style="margin-top:0">Reservation Summary</h3>
        <form action="booking_2pl.php" method="POST">
            <input type="hidden" name="train_id" value="<?php echo htmlspecialchars((string) $train['TRAIN_ID']); ?>">
            <input type="hidden" name="compartment" value="<?php echo htmlspecialchars($compartment); ?>">
            <input type="hidden" name="journey_date" value="<?php echo htmlspecialchars($journeyDate); ?>">
            <input type="hidden" name="source" value="<?php echo htmlspecialchars($selectedSource !== '' ? $selectedSource : $train['SOURCE_STATION']); ?>">
            <input type="hidden" name="destination" value="<?php echo htmlspecialchars($selectedDestination !== '' ? $selectedDestination : $train['DESTINATION_STATION']); ?>">
            <input type="hidden" name="seat_no" id="seat_input">
            <input type="hidden" name="passengers" value="<?php echo htmlspecialchars(railwayEncodePassengerManifest($pricing['passengers'])); ?>">

            <div class="muted" style="display:flex;justify-content:space-between"><span>Train</span><strong style="color:var(--text)"><?php echo htmlspecialchars($train['TRAIN_NAME']); ?></strong></div>
            <div class="muted" style="display:flex;justify-content:space-between;margin-top:10px"><span>Class</span><strong style="color:var(--text)"><?php echo htmlspecialchars($classLabel . ' (' . $compartment . ')'); ?></strong></div>
            <div class="muted" style="display:flex;justify-content:space-between;margin-top:10px"><span>Passengers</span><strong style="color:var(--text)"><?php echo htmlspecialchars((string) $passengerCount); ?></strong></div>
            <div class="muted" style="display:flex;justify-content:space-between;margin-top:10px"><span>Seats Selected</span><strong id="seat_list" style="color:var(--text)">None</strong></div>

            <div class="fare-lines">
                <div class="fare-line"><span>Base fare per passenger</span><strong style="color:var(--text)">Rs. <?php echo htmlspecialchars(number_format($seatPrice, 2)); ?></strong></div>
                <div class="fare-line"><span>Total concession savings</span><strong style="color:var(--text)">Rs. <?php echo htmlspecialchars(number_format((float) $pricing['discount_total'], 2)); ?></strong></div>
            </div>

            <div class="passenger-lines">
                <?php foreach ($pricing['passengers'] as $passenger): ?>
                    <div class="passenger-line">
                        <span class="muted"><?php echo htmlspecialchars($passenger['name'] . ' | Age ' . $passenger['age']); ?></span>
                        <strong style="color:var(--text)"><?php echo htmlspecialchars($passenger['discount_label'] . ' | Rs. ' . number_format((float) $passenger['final_fare'], 2)); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="price"><small style="display:block;font-size:14px;color:var(--muted);font-weight:400">Total Payable</small>Rs. <span id="total_price">0.00</span></div>
            <div class="warn" id="seat_limit_message">Select <?php echo htmlspecialchars((string) $passengerCount); ?> seat<?php echo $passengerCount > 1 ? 's' : ''; ?> to continue.</div>
            <button type="submit" class="btn">Confirm Seats</button>
        </form>
    </div>
</div>
<script>
let selected = [];
const bookingTotal = <?php echo json_encode((float) $pricing['final_total']); ?>;
const passengerCount = <?php echo json_encode($passengerCount); ?>;
const seatInput = document.getElementById('seat_input');
const seatList = document.getElementById('seat_list');
const totalPrice = document.getElementById('total_price');
const seatLimitMessage = document.getElementById('seat_limit_message');
const bookingForm = document.querySelector('form[action="booking_2pl.php"]');

function updateSeatSummary() {
    seatList.innerText = selected.length ? selected.join(', ') : 'None';
    totalPrice.innerText = selected.length === passengerCount
        ? bookingTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : '0.00';
    seatInput.value = selected.join(',');

    if (selected.length < passengerCount) {
        const remaining = passengerCount - selected.length;
        seatLimitMessage.textContent = `Select ${remaining} more seat${remaining === 1 ? '' : 's'} to continue.`;
    } else if (selected.length === passengerCount) {
        seatLimitMessage.textContent = 'Seat count matched. You can confirm the booking now.';
    } else {
        seatLimitMessage.textContent = `Only ${passengerCount} seat${passengerCount === 1 ? ' is' : 's are'} allowed for this booking.`;
    }
}

function toggleSeat(id, el) {
    if (el.classList.contains('booked')) {
        return;
    }

    if (selected.includes(id)) {
        selected = selected.filter((seat) => seat !== id);
        el.classList.remove('selected');
    } else {
        if (selected.length >= passengerCount) {
            seatLimitMessage.textContent = `Only ${passengerCount} seat${passengerCount === 1 ? ' is' : 's are'} allowed for this booking.`;
            return;
        }

        selected.push(id);
        el.classList.add('selected');
    }

    updateSeatSummary();
}

bookingForm.addEventListener('submit', function (event) {
    if (selected.length !== passengerCount) {
        event.preventDefault();
        seatLimitMessage.textContent = `Please select exactly ${passengerCount} seat${passengerCount === 1 ? '' : 's'} before confirming.`;
    }
});

updateSeatSummary();
</script>
</body>
</html>
