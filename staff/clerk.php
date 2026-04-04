<?php
include "../db.php";

// Fetch Summary Stats
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM TRAIN_SEATS WHERE STATUS = 'BOOKED') as total_bookings,
        (SELECT COUNT(*) FROM LOCK_TABLE) as active_locks,
        (SELECT COUNT(*) FROM TRANSACTION_LOG WHERE STATUS = 'COMMITTED') as total_revenue
    FROM DUAL";
$stid_stats = oci_parse($conn, $stats_query);
oci_execute($stid_stats);
$stats = oci_fetch_assoc($stid_stats);

// Fetch Recent Transactions (2PL Status)
$trans_query = "
SELECT * FROM (
    SELECT TID, STATUS, LOG_TIME 
    FROM TRANSACTION_LOG 
    ORDER BY LOG_TIME DESC
) WHERE ROWNUM <= 10";
$stid_trans = oci_parse($conn, $trans_query);
oci_execute($stid_trans);

// Fetch Active Locks
$lock_query = "SELECT TID, DATA_ITEM, LOCK_TYPE FROM LOCK_TABLE";
$stid_locks = oci_parse($conn, $lock_query);
oci_execute($stid_locks);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clerk Dashboard | RailSystem</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --primary: #2563eb;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            margin: 0;
            display: flex;
        }

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: #0f172a;
            color: white;
            position: fixed;
            padding: 20px;
        }

        .sidebar h2 { font-size: 1.5rem; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        .nav-item { 
            padding: 12px 15px; 
            border-radius: 10px; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            color: #94a3b8; 
            text-decoration: none; 
            margin-bottom: 8px;
            transition: 0.2s;
        }
        .nav-item:hover, .nav-item.active { background: #1e293b; color: white; }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 40px;
        }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: var(--card); padding: 25px; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .stat-card small { color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 12px; }
        .stat-card h3 { font-size: 2rem; margin: 10px 0 0; color: var(--text-dark); }

        /* Table Section */
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        .section-card { background: var(--card); border-radius: 20px; padding: 25px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { text-align: left; padding: 12px; color: var(--text-muted); font-size: 13px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 15px 12px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }

        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2><i class="fa-solid fa-train-subway"></i> RailSystem</h2>
    <a href="#" class="nav-item active"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
    <a href="index.php" class="nav-item"><i class="fa-solid fa-ticket"></i> Booking Entry</a>
    <a href="#" class="nav-item"><i class="fa-solid fa-users"></i> Passengers</a>
    <a href="#" class="nav-item"><i class="fa-solid fa-gear"></i> System Config</a>
    <a href="trains.php" class="nav-item"><i class="fa-solid fa-train"></i> View Trains</a>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h1 style="margin:0">Dashboard Overview</h1>
            <p style="color:var(--text-muted)">Welcome back, <?php echo $_SESSION['username'] ?? 'Clerk'; ?></p>
        </div>
        <div style="text-align:right">
            <span class="badge badge-success">System Online</span>
            <p style="font-size:12px; margin-top:5px; color:var(--text-muted)"><?php echo date('D, d M Y'); ?></p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <small>Confirmed Bookings</small>
            <h3><?php echo number_format($stats['TOTAL_BOOKINGS'] ?? 0); ?></h3>
        </div>
        <div class="stat-card">
            <small>Active 2PL Locks</small>
            <h3><?php echo $stats['ACTIVE_LOCKS'] ?? 0; ?></h3>
        </div>
        <div class="stat-card">
            <small>Total Revenue</small>
            <h3>₹<?php echo number_format($stats['TOTAL_REVENUE'] ?? 0); ?></h3>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="section-card">
            <h4 style="margin:0">Live Transaction Monitor (2PL)</h4>
            <table>
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Status</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = oci_fetch_assoc($stid_trans)): 
                        $bClass = ($row['STATUS'] == 'COMMITTED') ? 'badge-success' : (($row['STATUS'] == 'ABORTED') ? 'badge-danger' : 'badge-warning');
                    ?>
                    <tr>
                        <td><strong>#<?php echo $row['TID']; ?></strong></td>
                        <td><span class="badge <?php echo $bClass; ?>"><?php echo $row['STATUS']; ?></span></td>
                        <td style="color:var(--text-muted)"><?php echo $row['LOG_TIME']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="section-card">
            <h4 style="margin:0">Active Resource Locks</h4>
            
            <table>
                <thead>
                    <tr>
                        <th>TID</th>
                        <th>Item</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = oci_fetch_assoc($stid_locks)): ?>
                    <tr>
                        <td>#<?php echo $row['TID']; ?></td>
                        <td><?php echo $row['DATA_ITEM']; ?></td>
                        <td><i class="fa-solid fa-lock" style="font-size:10px; color:var(--primary)"></i> <?php echo $row['LOCK_TYPE']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>