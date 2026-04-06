<?php
session_start();
include_once "../db.php";
require_once __DIR__ . "/train_data.php";
require_once __DIR__ . "/lock_manager.php";
require_once __DIR__ . "/identify_data.php";

clearExpiredLocks($conn);

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

$bookingToken = trim($_REQUEST['booking_token'] ?? ($_SESSION['active_booking']['booking_token'] ?? ''));
if ($bookingToken === '') {
    $bookingToken = railwayGenerateBookingToken();
}

$_SESSION['active_booking'] = [
    'booking_token' => $bookingToken,
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

function railwayGetSeatSelectionState($conn, $trainId, $compartment, $bookingToken): array
{
    $sql = "SELECT SEAT_NO, STATUS FROM TRAIN_SEATS
            WHERE TRAIN_ID = :train_id AND COMPARTMENT = :compartment
            ORDER BY TO_NUMBER(REGEXP_REPLACE(SEAT_NO, '[^0-9]', '')) ASC";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":train_id", $trainId);
    oci_bind_by_name($stmt, ":compartment", $compartment);
    oci_execute($stmt);

    $seats = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $seats[(string) $row['SEAT_NO']] = [
            'seat_no' => (string) $row['SEAT_NO'],
            'db_status' => (string) $row['STATUS'],
            'state' => ((string) $row['STATUS'] === 'BOOKED') ? 'booked' : 'available',
        ];
    }

    $lockSql = "SELECT DATA_ITEM, TID
                FROM LOCK_TABLE
                WHERE DATA_ITEM LIKE :seat_pattern";
    $lockStmt = oci_parse($conn, $lockSql);
    $seatPattern = 'SEAT_' . $trainId . '_%';
    oci_bind_by_name($lockStmt, ":seat_pattern", $seatPattern);
    oci_execute($lockStmt);

    while ($row = oci_fetch_assoc($lockStmt)) {
        $dataItem = (string) ($row['DATA_ITEM'] ?? '');
        $parts = explode('_', $dataItem, 3);
        $seatNo = $parts[2] ?? '';
        if ($seatNo === '' || !isset($seats[$seatNo]) || $seats[$seatNo]['state'] === 'booked') {
            continue;
        }

        $seats[$seatNo]['state'] = ((string) ($row['TID'] ?? '') === (string) $bookingToken) ? 'held_self' : 'held_other';
    }

    return array_values($seats);
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'status') {
    header('Content-Type: application/json');
    echo json_encode([
        'seats' => railwayGetSeatSelectionState($conn, $train_id, $compartment, $bookingToken),
        'booking_token' => $bookingToken,
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $ajaxAction = trim((string) $_POST['ajax']);
    $seatNo = trim((string) ($_POST['seat_no'] ?? ''));
    $items = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['items'] ?? '')))));

    if ($ajaxAction === 'reserve' && $seatNo !== '') {
        $item = identifyDataItem($train_id, $seatNo);
        $seatCheck = oci_parse($conn, "SELECT STATUS FROM TRAIN_SEATS WHERE TRAIN_ID = :train_id AND COMPARTMENT = :compartment AND SEAT_NO = :seat_no");
        oci_bind_by_name($seatCheck, ":train_id", $train_id);
        oci_bind_by_name($seatCheck, ":compartment", $compartment);
        oci_bind_by_name($seatCheck, ":seat_no", $seatNo);
        oci_execute($seatCheck);
        $seatRow = oci_fetch_assoc($seatCheck);

        if (!$seatRow || (string) ($seatRow['STATUS'] ?? '') === 'BOOKED') {
            echo json_encode(['ok' => false, 'message' => 'That seat is already booked.', 'seats' => railwayGetSeatSelectionState($conn, $train_id, $compartment, $bookingToken)]);
            exit();
        }

        if (!isLockAvailable($conn, $item, 'EXCLUSIVE', $bookingToken)) {
            echo json_encode(['ok' => false, 'message' => 'Another user is already holding that seat.', 'seats' => railwayGetSeatSelectionState($conn, $train_id, $compartment, $bookingToken)]);
            exit();
        }

        grantLock($conn, $item, $bookingToken, 'EXCLUSIVE');
        oci_commit($conn);

        echo json_encode(['ok' => true, 'seats' => railwayGetSeatSelectionState($conn, $train_id, $compartment, $bookingToken)]);
        exit();
    }

    if ($ajaxAction === 'release' && $seatNo !== '') {
        releaseLockItems($conn, $bookingToken, [identifyDataItem($train_id, $seatNo)]);
        oci_commit($conn);
        echo json_encode(['ok' => true, 'seats' => railwayGetSeatSelectionState($conn, $train_id, $compartment, $bookingToken)]);
        exit();
    }

    if ($ajaxAction === 'heartbeat') {
        $lockItems = [];
        foreach ($items as $itemSeatNo) {
            $lockItems[] = identifyDataItem($train_id, $itemSeatNo);
        }
        updateLockHeartbeat($conn, $bookingToken, $lockItems);
        oci_commit($conn);
        echo json_encode(['ok' => true]);
        exit();
    }

    if ($ajaxAction === 'release_all') {
        releaseLocks($conn, $bookingToken);
        oci_commit($conn);
        echo json_encode(['ok' => true]);
        exit();
    }

    echo json_encode(['ok' => false, 'message' => 'Unsupported seat action.']);
    exit();
}

$seats = railwayGetSeatSelectionState($conn, $train_id, $compartment, $bookingToken);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coach Layout | <?php echo htmlspecialchars($train['TRAIN_NAME']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eef3f8; --card:#fff; --line:#e2e8f0; --text:#0f172a; --muted:#64748b; --accent:#2563eb; --selected:#10b981; --booked:#cbd5e1; --held:#f59e0b; --warn:#dc2626; }
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
        .seat{height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;cursor:pointer;border:1px solid #d1d5db;background:#fff;color:#475569;transition:.2s}
        .seat.booked{background:var(--booked);color:#94a3b8;cursor:not-allowed;border:none}
        .seat.held-other{background:#fde68a;color:#92400e;cursor:not-allowed;border:none}
        .seat.selected,.seat.held-self{background:var(--selected);color:#fff;border-color:var(--selected);box-shadow:0 0 12px rgba(16,185,129,.35)}
        .pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#dbeafe;color:var(--accent);font-size:.8rem;font-weight:800;margin-bottom:16px}
        .legend{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;color:var(--muted);font-size:.85rem;font-weight:700}
        .legend span{display:inline-flex;align-items:center;gap:8px}
        .dot{width:12px;height:12px;border-radius:999px;display:inline-block}
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
        <p class="muted" style="margin:0 0 10px 0">
            <?php echo htmlspecialchars(($selectedSource !== '' ? $selectedSource : $train['SOURCE_STATION']) . ' to ' . ($selectedDestination !== '' ? $selectedDestination : $train['DESTINATION_STATION']) . ' | ' . date('D, d M Y', strtotime($journeyDate))); ?>
        </p>
        <p class="muted" style="margin:0 0 18px 0">Select exactly <?php echo htmlspecialchars((string) $passengerCount); ?> seat<?php echo $passengerCount > 1 ? 's' : ''; ?> for this booking. Other users will immediately see your held seats as unavailable until you confirm or leave.</p>
        <div class="legend">
            <span><i class="dot" style="background:#cbd5e1"></i> Already booked</span>
            <span><i class="dot" style="background:#fde68a"></i> Held by another user</span>
            <span><i class="dot" style="background:#10b981"></i> Your held seats</span>
        </div>
        <div class="coachbox" id="coachbox">
            <?php foreach (array_chunk($seats, 8) as $bay_seats): ?>
                <div class="bay">
                    <div class="main">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <?php if (isset($bay_seats[$i])): $s = $bay_seats[$i]; ?>
                                <div class="seat <?php echo htmlspecialchars(str_replace('_', '-', $s['state'])); ?>" data-seat-no="<?php echo htmlspecialchars($s['seat_no']); ?>" onclick="toggleSeat('<?php echo htmlspecialchars($s['seat_no'], ENT_QUOTES); ?>')"><?php echo htmlspecialchars($s['seat_no']); ?></div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div class="aisle"></div>
                    <div class="sidecol">
                        <?php for ($i = 6; $i < 8; $i++): ?>
                            <?php if (isset($bay_seats[$i])): $s = $bay_seats[$i]; ?>
                                <div class="seat <?php echo htmlspecialchars(str_replace('_', '-', $s['state'])); ?>" data-seat-no="<?php echo htmlspecialchars($s['seat_no']); ?>" onclick="toggleSeat('<?php echo htmlspecialchars($s['seat_no'], ENT_QUOTES); ?>')"><?php echo htmlspecialchars($s['seat_no']); ?></div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="side">
        <h3 style="margin-top:0">Reservation Summary</h3>
        <form action="booking_2pl.php" method="POST" id="bookingForm">
            <input type="hidden" name="train_id" value="<?php echo htmlspecialchars((string) $train['TRAIN_ID']); ?>">
            <input type="hidden" name="compartment" value="<?php echo htmlspecialchars($compartment); ?>">
            <input type="hidden" name="journey_date" value="<?php echo htmlspecialchars($journeyDate); ?>">
            <input type="hidden" name="source" value="<?php echo htmlspecialchars($selectedSource !== '' ? $selectedSource : $train['SOURCE_STATION']); ?>">
            <input type="hidden" name="destination" value="<?php echo htmlspecialchars($selectedDestination !== '' ? $selectedDestination : $train['DESTINATION_STATION']); ?>">
            <input type="hidden" name="booking_token" value="<?php echo htmlspecialchars($bookingToken); ?>">
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
const bookingToken = <?php echo json_encode($bookingToken); ?>;
const bookingTotal = <?php echo json_encode((float) $pricing['final_total']); ?>;
const passengerCount = <?php echo json_encode($passengerCount); ?>;
const ajaxBaseUrl = `seat_selection.php?train_id=${encodeURIComponent(<?php echo json_encode((string) $train['TRAIN_ID']); ?>)}&compartment=${encodeURIComponent(<?php echo json_encode($compartment); ?>)}&journey_date=${encodeURIComponent(<?php echo json_encode($journeyDate); ?>)}&source=${encodeURIComponent(<?php echo json_encode($selectedSource !== '' ? $selectedSource : $train['SOURCE_STATION']); ?>)}&destination=${encodeURIComponent(<?php echo json_encode($selectedDestination !== '' ? $selectedDestination : $train['DESTINATION_STATION']); ?>)}&booking_token=${encodeURIComponent(bookingToken)}`;
const seatInput = document.getElementById('seat_input');
const seatList = document.getElementById('seat_list');
const totalPrice = document.getElementById('total_price');
const seatLimitMessage = document.getElementById('seat_limit_message');
const bookingForm = document.getElementById('bookingForm');
let selected = [];

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

function renderSeatStates(seats) {
    const stateMap = new Map(seats.map((seat) => [seat.seat_no, seat.state]));
    document.querySelectorAll('.seat[data-seat-no]').forEach((seatNode) => {
        const seatNo = seatNode.dataset.seatNo;
        const state = stateMap.get(seatNo) || 'available';
        seatNode.className = `seat ${state.replaceAll('_', '-')}`;
    });

    selected = selected.filter((seatNo) => stateMap.get(seatNo) === 'held_self');
    updateSeatSummary();
}

async function postSeatAction(action, extra = {}) {
    const payload = new URLSearchParams({ ajax: action, ...extra });
    const response = await fetch(ajaxBaseUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: payload.toString(),
        keepalive: action === 'release_all'
    });
    return response.json();
}

async function refreshSeatStatus() {
    try {
        const response = await fetch(`${ajaxBaseUrl}&ajax=status`, { cache: 'no-store' });
        const data = await response.json();
        renderSeatStates(data.seats || []);
    } catch (error) {
        console.error('Unable to refresh seat status', error);
    }
}

async function toggleSeat(seatNo) {
    const seatNode = document.querySelector(`.seat[data-seat-no="${CSS.escape(seatNo)}"]`);
    if (!seatNode || seatNode.classList.contains('booked') || seatNode.classList.contains('held-other')) {
        return;
    }

    if (seatNode.classList.contains('held-self')) {
        const data = await postSeatAction('release', { seat_no: seatNo });
        renderSeatStates(data.seats || []);
        return;
    }

    if (selected.length >= passengerCount) {
        seatLimitMessage.textContent = `Only ${passengerCount} seat${passengerCount === 1 ? ' is' : 's are'} allowed for this booking.`;
        return;
    }

    const data = await postSeatAction('reserve', { seat_no: seatNo });
    if (!data.ok && data.message) {
        seatLimitMessage.textContent = data.message;
    }
    renderSeatStates(data.seats || []);
}

async function sendHeartbeat() {
    if (!selected.length) {
        return;
    }

    try {
        await postSeatAction('heartbeat', { items: selected.join(',') });
    } catch (error) {
        console.error('Unable to refresh seat lock heartbeat', error);
    }
}

bookingForm.addEventListener('submit', function (event) {
    if (selected.length !== passengerCount) {
        event.preventDefault();
        seatLimitMessage.textContent = `Please select exactly ${passengerCount} seat${passengerCount === 1 ? '' : 's'} before confirming.`;
    }
});

window.addEventListener('beforeunload', function () {
    if (selected.length) {
        navigator.sendBeacon(ajaxBaseUrl, new URLSearchParams({ ajax: 'release_all' }));
    }
});

setInterval(refreshSeatStatus, 2000);
setInterval(sendHeartbeat, 4000);
refreshSeatStatus();
updateSeatSummary();
</script>
</body>
</html>
