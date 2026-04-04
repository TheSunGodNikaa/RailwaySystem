<?php
session_start();
include_once "../db.php";
require_once __DIR__ . "/../train_catalog.php";

$train_id = $_REQUEST['train_id'] ?? 'N/A';
$compartment = $_REQUEST['compartment'] ?? 'SL';
$journeyDate = $_REQUEST['journey_date'] ?? ($_SESSION['active_booking']['journey_date'] ?? date('Y-m-d'));
$train = getTrainById($train_id);

$price_map = ["SL" => 500, "3A" => 1200, "2A" => 1800, "GN" => 300, "CC" => 850, "EC" => 1450];
$seat_price = $price_map[$compartment] ?? 500;

if ($train && isset($train['classes'][$compartment]['price'])) {
    $seat_price = $train['classes'][$compartment]['price'];
    $_SESSION['active_booking'] = [
        'train_id' => $train['train_id'],
        'train_name' => $train['train_name'],
        'from' => $train['from'],
        'to' => $train['to'],
        'from_code' => $train['from_code'],
        'to_code' => $train['to_code'],
        'departure' => $train['departure'],
        'arrival' => $train['arrival'],
        'duration' => $train['duration'],
        'journey_date' => $journeyDate,
    ];
}

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
    <title>Coach Layout | Train <?php echo htmlspecialchars((string) $train_id); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f0f2f5; --coach-body: #ffffff; --aisle: #e5e7eb; --primary: #2563eb; --selected: #10b981; --booked: #cbd5e1; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); margin: 0; display: flex; justify-content: center; padding: 40px; }
        .main-container { display: flex; gap: 30px; max-width: 1100px; width: 100%; }
        .coach-view { flex: 1; background: var(--coach-body); border-radius: 20px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .coach-container { display: flex; flex-direction: column; gap: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 2px solid #e2e8f0; max-height: 70vh; overflow-y: auto; }
        .bay { display: grid; grid-template-columns: repeat(3, 1fr) 40px 1fr; gap: 10px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
        .main-seats { display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: 1fr 1fr; gap: 8px; }
        .aisle-space { background: var(--aisle); border-radius: 4px; height: 100%; }
        .side-seats { display: grid; grid-template-rows: 1fr 1fr; gap: 8px; }
        .seat { height: 45px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; border: 1px solid #d1d5db; background: white; color: #475569; }
        .seat.booked { background: var(--booked); color: #94a3b8; cursor: not-allowed; border: none; }
        .seat.selected { background: var(--selected); color: white; border-color: var(--selected); box-shadow: 0 0 10px rgba(16, 185, 129, 0.4); }
        .sidebar { width: 350px; background: white; padding: 25px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); height: fit-content; position: sticky; top: 40px; }
        .price-tag { font-size: 32px; font-weight: 700; color: var(--primary); margin: 15px 0; }
        .btn-book { width: 100%; background: var(--primary); color: white; border: none; padding: 16px; border-radius: 12px; font-weight: 700; font-size: 16px; cursor: pointer; margin-top: 20px; }
    </style>
</head>
<body>
<div class="main-container">
    <div class="coach-view">
        <h2 style="margin:0 0 5px 0">
            <?php echo $train ? htmlspecialchars($train['train_name']) : 'Train ' . htmlspecialchars((string) $train_id); ?>
        </h2>
        <p style="color: #64748b; margin-bottom: 25px;">
            <?php
            if ($train) {
                echo htmlspecialchars($train['from'] . ' to ' . $train['to'] . ' • ' . date('D, d M Y', strtotime($journeyDate)) . ' • ' . $compartment);
            } else {
                echo 'Compartment: ' . htmlspecialchars($compartment) . ' Layout';
            }
            ?>
        </p>

        <div class="coach-container">
            <?php
            $chunks = array_chunk($seats, 8);
            foreach ($chunks as $bay_seats) {
                echo '<div class="bay">';
                echo '<div class="main-seats">';
                for ($i = 0; $i < 6; $i++) {
                    if (isset($bay_seats[$i])) {
                        $s = $bay_seats[$i];
                        $cls = ($s['STATUS'] == 'BOOKED') ? 'booked' : 'available';
                        echo "<div class='seat $cls' onclick=\"toggleSeat('{$s['SEAT_NO']}', this)\">{$s['SEAT_NO']}</div>";
                    }
                }
                echo '</div>';
                echo '<div class="aisle-space"></div>';
                echo '<div class="side-seats">';
                for ($i = 6; $i < 8; $i++) {
                    if (isset($bay_seats[$i])) {
                        $s = $bay_seats[$i];
                        $cls = ($s['STATUS'] == 'BOOKED') ? 'booked' : 'available';
                        echo "<div class='seat $cls' onclick=\"toggleSeat('{$s['SEAT_NO']}', this)\">{$s['SEAT_NO']}</div>";
                    }
                }
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <div class="sidebar">
        <h3 style="margin-top:0">Reservation Summary</h3>
        <form action="booking_2pl.php" method="POST">
            <input type="hidden" name="train_id" value="<?php echo htmlspecialchars((string) $train_id); ?>">
            <input type="hidden" name="compartment" value="<?php echo htmlspecialchars($compartment); ?>">
            <input type="hidden" name="journey_date" value="<?php echo htmlspecialchars($journeyDate); ?>">
            <input type="hidden" name="seat_no" id="seat_input">

            <div style="display:flex; justify-content: space-between; color: #64748b;">
                <span>Seats Selected</span>
                <span id="seat_list" style="font-weight:600; color: #1e293b;">None</span>
            </div>

            <div class="price-tag">
                <small style="font-size: 14px; color: #64748b; display:block; font-weight:400">Total Payable</small>
                Rs. <span id="total_price">0</span>
            </div>

            <button type="submit" class="btn-book">Confirm Seats</button>
        </form>
    </div>
</div>

<script>
let selected = [];
const price = <?php echo $seat_price; ?>;

function toggleSeat(id, el) {
    if (el.classList.contains('booked')) return;

    if (selected.includes(id)) {
        selected = selected.filter(s => s !== id);
        el.classList.remove('selected');
    } else {
        selected.push(id);
        el.classList.add('selected');
    }

    document.getElementById('seat_list').innerText = selected.length > 0 ? selected.join(', ') : 'None';
    document.getElementById('total_price').innerText = (selected.length * price).toLocaleString('en-IN');
    document.getElementById('seat_input').value = selected.join(',');
}
</script>
</body>
</html>
