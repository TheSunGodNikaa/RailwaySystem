<?php
session_start();
include "../db.php";
require_once __DIR__ . "/../train_catalog.php";

$train_id = (int) ($_REQUEST['train_id'] ?? ($_SESSION['active_booking']['train_id'] ?? 101));
$journeyDate = $_REQUEST['journey_date'] ?? ($_SESSION['active_booking']['journey_date'] ?? date('Y-m-d'));
$train = getTrainById($train_id);

$query = "
    SELECT
        compartment,
        COUNT(*) AS total_seats,
        SUM(CASE WHEN status = 'AVAILABLE' THEN 1 ELSE 0 END) AS available_seats
    FROM train_seats
    WHERE train_id = :train_id
    GROUP BY compartment
";

$stid = oci_parse($conn, $query);
oci_bind_by_name($stid, ":train_id", $train_id);
oci_execute($stid);

$availability = [];
while ($row = oci_fetch_assoc($stid)) {
    $availability[$row['COMPARTMENT']] = [
        'total' => $row['TOTAL_SEATS'],
        'available' => $row['AVAILABLE_SEATS']
    ];
}

$compartments = [
    'SL' => ['name' => 'Sleeper', 'price' => 500, 'icon' => 'SL'],
    '3A' => ['name' => 'AC 3 Tier', 'price' => 1200, 'icon' => '3A'],
    '2A' => ['name' => 'AC 2 Tier', 'price' => 1800, 'icon' => '2A'],
    'GN' => ['name' => 'General', 'price' => 300, 'icon' => 'GN'],
    'CC' => ['name' => 'Chair Car', 'price' => 850, 'icon' => 'CC'],
    'EC' => ['name' => 'Executive Chair Car', 'price' => 1450, 'icon' => 'EC']
];

if ($train && !empty($train['classes'])) {
    foreach ($train['classes'] as $code => $classInfo) {
        if (isset($compartments[$code])) {
            $compartments[$code]['price'] = $classInfo['price'];
            $compartments[$code]['name'] = $classInfo['label'];
        } else {
            $compartments[$code] = [
                'name' => $classInfo['label'],
                'price' => $classInfo['price'],
                'icon' => $code
            ];
        }
    }

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Class | Indian Railways</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        header {
            width: 100%;
            padding: 60px 20px 40px;
            text-align: center;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            margin-bottom: 40px;
        }

        .train-badge {
            background: #dcfce7;
            color: #166534;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        h1 { font-size: 2.5rem; margin: 15px 0 5px; color: #0f172a; }
        .subtitle { color: var(--text-muted); font-size: 1.05rem; max-width: 760px; margin: 0 auto; }
        .stepper { display: flex; gap: 40px; margin-bottom: 30px; }
        .step { display: flex; align-items: center; gap: 8px; font-weight: 600; color: var(--text-muted); }
        .step.active { color: var(--primary); }
        .step.active .circle { background: var(--primary); color: white; }
        .circle { width: 24px; height: 24px; border-radius: 50%; background: #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; max-width: 1200px; width: 90%; padding-bottom: 60px; }
        .compartment-card { background: var(--card-bg); border: 1px solid #e2e8f0; border-radius: 24px; padding: 30px; text-align: left; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; }
        .compartment-card:hover { transform: translateY(-10px); border-color: var(--primary); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .icon-box { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 800; color: var(--primary); background: #dbeafe; margin-bottom: 20px; }
        .compartment-card h3 { margin: 0; font-size: 1.4rem; font-weight: 700; }
        .price-tag { font-size: 1.8rem; font-weight: 800; color: var(--primary); margin: 15px 0; }
        .avail-info { background: #f1f5f9; padding: 12px 16px; border-radius: 12px; font-size: 0.95rem; color: var(--text-muted); }
        .avail-count { color: #059669; font-weight: 700; }
        .select-btn { margin-top: 20px; width: 100%; padding: 12px; border-radius: 12px; border: none; background: #f1f5f9; color: var(--text-main); font-weight: 700; transition: 0.2s; }
        .compartment-card:hover .select-btn { background: var(--primary); color: white; }
        #bookingForm { display: none; }
    </style>
</head>
<body>
<header>
    <span class="train-badge">Train #<?php echo $train_id; ?></span>
    <h1>Select Class</h1>
    <p class="subtitle">
        <?php if ($train): ?>
            <?php echo htmlspecialchars($train['train_name'] . ' • ' . $train['from'] . ' to ' . $train['to'] . ' • ' . date('D, d M Y', strtotime($journeyDate))); ?>
        <?php else: ?>
            Choose your preferred comfort for the journey
        <?php endif; ?>
    </p>
</header>

<div class="stepper">
    <div class="step active"><span class="circle">1</span> Select Class</div>
    <div class="step"><span class="circle">2</span> Choose Seats</div>
    <div class="step"><span class="circle">3</span> Confirmed</div>
</div>

<div class="container">
    <?php foreach ($compartments as $code => $info): ?>
        <?php if (!isset($availability[$code])) continue; ?>
        <div class="compartment-card" onclick="submitSelection('<?php echo $code; ?>')">
            <div class="icon-box"><?php echo htmlspecialchars($info['icon']); ?></div>
            <h3><?php echo htmlspecialchars($info['name']); ?> (<?php echo htmlspecialchars($code); ?>)</h3>
            <div class="price-tag">Rs. <?php echo number_format($info['price']); ?></div>
            <div class="avail-info">
                Available:
                <span class="avail-count"><?php echo $availability[$code]['available'] ?? 0; ?></span>
                / <?php echo $availability[$code]['total'] ?? 0; ?>
            </div>
            <button class="select-btn" type="button">Select Class</button>
        </div>
    <?php endforeach; ?>
</div>

<form id="bookingForm" action="seat_selection.php" method="POST">
    <input type="hidden" name="train_id" value="<?php echo $train_id; ?>">
    <input type="hidden" name="journey_date" value="<?php echo htmlspecialchars($journeyDate); ?>">
    <input type="hidden" name="compartment" id="hidden_comp">
</form>

<script>
function submitSelection(compCode) {
    document.getElementById('hidden_comp').value = compCode;
    document.getElementById('bookingForm').submit();
}
</script>
</body>
</html>
