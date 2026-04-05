<?php
require_once "auth_guard.php";
include "../db.php";
require_once __DIR__ . "/../TwoPL/train_data.php";

railwayEnsureBookingHistoryTable($conn);

$sql = "
    SELECT
        ts.train_id,
        t.train_name,
        ts.compartment,
        NVL(booked.booked_count, 0) AS booked_count
    FROM train_seats ts
    JOIN trains t
        ON ts.train_id = t.train_id
    LEFT JOIN (
        SELECT train_id, COUNT(*) AS booked_count
        FROM train_seats
        WHERE status = 'BOOKED'
        GROUP BY train_id
    ) booked
        ON booked.train_id = ts.train_id
    GROUP BY ts.train_id, t.train_name, ts.compartment, booked.booked_count
    ORDER BY booked_count DESC, ts.train_id ASC, ts.compartment ASC
";
$stid = oci_parse($conn, $sql);
oci_execute($stid);

$trains = [];
while ($row = oci_fetch_assoc($stid)) {
    $trainId = $row['TRAIN_ID'];
    if (!isset($trains[$trainId])) {
        $trains[$trainId] = [
            'name' => $row['TRAIN_NAME'],
            'booked_count' => (int) ($row['BOOKED_COUNT'] ?? 0),
            'compartments' => [],
        ];
    }
    $trains[$trainId]['compartments'][] = $row['COMPARTMENT'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Management | RailOps Clerk</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg:#f1f5f9; --primary:#0f172a; --accent:#2563eb; --card:#fff; --text:#1e293b; --muted:#64748b; --line:#e2e8f0; }
        *{box-sizing:border-box} body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);margin:0;padding:46px;background-image:radial-gradient(#cbd5e1 1px,transparent 1px);background-size:30px 30px}
        .container{max-width:1140px;margin:0 auto}.header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:34px}.header h1{font-size:2.2rem;margin:0;letter-spacing:-1px}.header p{color:var(--muted);margin:8px 0 0}.back{display:inline-flex;align-items:center;gap:8px;text-decoration:none;background:var(--primary);color:#fff;padding:12px 16px;border-radius:14px;font-weight:800}
        .fleet-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px}.card{background:var(--card);border-radius:24px;padding:28px;border:1px solid transparent;box-shadow:0 8px 24px rgba(15,23,42,.06);display:flex;flex-direction:column;gap:18px;transition:.2s}.card:hover{transform:translateY(-6px);border-color:var(--accent)}
        .top{display:flex;align-items:flex-start;gap:14px}.icon{background:#eff6ff;color:var(--accent);width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.2rem}
        .rank{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#dbeafe;color:var(--accent);font-size:.78rem;font-weight:800;width:max-content}
        .booked{color:var(--muted);font-size:.9rem;font-weight:700}.label{font-size:.75rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);font-weight:800}
        .group{display:flex;gap:10px;flex-wrap:wrap}.btn{flex:1;min-width:78px;background:#f8fafc;border:1px solid var(--line);padding:12px 10px;border-radius:12px;color:var(--text);font-weight:800;cursor:pointer;transition:.2s}.btn:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Fleet Console</h1>
            <p>Trains with the highest number of currently booked seats appear first so clerks can jump to the busiest coaches faster.</p>
        </div>
        <a href="clerk.php" class="back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="fleet-grid">
        <?php foreach ($trains as $train_id => $train): ?>
            <div class="card">
                <div class="rank"><i class="fa-solid fa-arrow-trend-up"></i> Sorted by current booked seats</div>
                <div class="top">
                    <div class="icon"><i class="fa-solid fa-train-subway"></i></div>
                    <div>
                        <h3 style="margin:0; font-size:1.32rem;"><?php echo htmlspecialchars($train['name']); ?></h3>
                        <div class="booked">Train ID: <?php echo htmlspecialchars($train_id); ?></div>
                        <div class="booked" style="margin-top:4px;">Booked seats now: <?php echo htmlspecialchars((string) $train['booked_count']); ?></div>
                    </div>
                </div>
                <div>
                    <div class="label">View Seating By Class</div>
                    <div class="group" style="margin-top:10px;">
                        <?php foreach ($train['compartments'] as $comp): ?>
                            <form action="seat_view.php" method="GET" style="margin:0; flex:1;">
                                <input type="hidden" name="train_id" value="<?php echo htmlspecialchars((string) $train_id); ?>">
                                <input type="hidden" name="compartment" value="<?php echo htmlspecialchars($comp); ?>">
                                <button type="submit" class="btn"><?php echo htmlspecialchars($comp); ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
