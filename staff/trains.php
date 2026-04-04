<?php
include "../db.php";

$sql = "
    SELECT ts.train_id, t.train_name, ts.compartment
    FROM train_seats ts
    JOIN trains t ON ts.train_id = t.train_id
    GROUP BY ts.train_id, t.train_name, ts.compartment
    ORDER BY ts.train_id
";
$stid = oci_parse($conn, $sql);
oci_execute($stid);

/* Group by train */
$trains = [];
while ($row = oci_fetch_assoc($stid)) {
    $trainId = $row['TRAIN_ID'];

    if (!isset($trains[$trainId])) {
        $trains[$trainId] = [
            'name' => $row['TRAIN_NAME'],
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
    <title>Fleet Management | RailOps Clerk</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f1f5f9;
            --primary: #0f172a;
            --accent: #2563eb;
            --card: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 50px;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 30px 30px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 40px;
            border-left: 5px solid var(--accent);
            padding-left: 20px;
        }

        .header h1 {
            font-weight: 800;
            font-size: 2.2rem;
            margin: 0;
            letter-spacing: -1px;
        }

        .header p {
            color: var(--text-muted);
            margin: 5px 0 0 0;
        }

        /* --- Fleet Grid --- */
        .fleet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .train-card {
            background: var(--card);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .train-card:hover {
            transform: translateY(-8px);
            border-color: var(--accent);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .train-id-badge {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .train-icon {
            background: #eff6ff;
            color: var(--accent);
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .train-card h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .compartment-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .comp-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .comp-btn {
            flex: 1;
            min-width: 70px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 12px 10px;
            border-radius: 10px;
            color: var(--text-main);
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .comp-btn:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .status-indicator {
            margin-top: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: #10b981;
            font-weight: 600;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Fleet Console</h1>
        <p>Select a train unit and coach to begin seat management.</p>
    </div>

    <div class="fleet-grid">
        <?php foreach ($trains as $train_id => $train): ?>
            
            <div class="train-card">
                <div class="train-id-badge">
                    <div class="train-icon">
                        <i class="fa-solid fa-train-subway"></i>
                    </div>
                    <div>
                        <h3><?php echo htmlspecialchars($train['name']); ?></h3>
                        <div style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600; margin-top: 4px;">
                            Train ID: <?php echo htmlspecialchars($train_id); ?>
                        </div>
                        <div class="status-indicator">
                            <div class="status-dot"></div> Systems Active
                        </div>
                    </div>
                </div>

                <div style="height: 1px; background: #f1f5f9; width: 100%;"></div>

                <div>
                    <div class="compartment-label">Manage Coach</div>
                    <div class="comp-group">
                        <?php foreach ($train['compartments'] as $comp): ?>
                            <form action="seat_view.php" method="GET" style="margin: 0; flex: 1;">
                                <input type="hidden" name="train_id" value="<?php echo $train_id; ?>">
                                <input type="hidden" name="compartment" value="<?php echo $comp; ?>">
                                <button type="submit" class="comp-btn">
                                    <?php echo $comp; ?>
                                </button>
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
