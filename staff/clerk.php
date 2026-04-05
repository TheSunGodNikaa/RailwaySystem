<?php
require_once "auth_guard.php";
include "../db.php";
require_once __DIR__ . "/../TwoPL/train_data.php";
require_once __DIR__ . "/../TwoPL/lock_manager.php";

clearExpiredLocks($conn);
railwayEnsureBookingHistoryTable($conn);

$username = $_SESSION['username'];
$profileStmt = oci_parse($conn, "SELECT FULL_NAME, EMAIL, DESIGNATION, DEPARTMENT, STATION_CODE FROM CLERK WHERE USERNAME = :username");
oci_bind_by_name($profileStmt, ":username", $username);
oci_execute($profileStmt);
$profile = oci_fetch_assoc($profileStmt) ?: ['FULL_NAME' => $username, 'EMAIL' => '--', 'DESIGNATION' => 'Clerk', 'DEPARTMENT' => '--', 'STATION_CODE' => '--'];

$stats_query = "
    SELECT
        (SELECT COUNT(*) FROM PASSENGER_BOOKING_HISTORY WHERE BOOKING_STATUS = 'CONFIRMED') AS total_bookings,
        (SELECT NVL(SUM(TOTAL_AMOUNT), 0) FROM PASSENGER_BOOKING_HISTORY WHERE BOOKING_STATUS = 'CONFIRMED') AS total_revenue,
        (SELECT COUNT(*) FROM LOCK_TABLE) AS active_locks,
        (SELECT COUNT(*) FROM USERS) AS total_users
    FROM DUAL
";
$stid_stats = oci_parse($conn, $stats_query);
oci_execute($stid_stats);
$stats = oci_fetch_assoc($stid_stats);

$trans_query = "
    SELECT *
    FROM (
        SELECT
            t.TID,
            t.STATUS,
            t.LOG_TIME,
            h.TRAIN_ID,
            h.TRAIN_NUMBER,
            h.TRAIN_NAME,
            h.SOURCE_STATION,
            h.DESTINATION_STATION,
            h.COMPARTMENT,
            h.SEATS,
            h.TOTAL_AMOUNT,
            h.USER_ID,
            u.FULL_NAME AS PASSENGER_NAME
        FROM TRANSACTION_LOG t
        LEFT JOIN PASSENGER_BOOKING_HISTORY h
            ON h.TRANSACTION_ID = t.TID
           AND h.BOOKING_STATUS = 'CONFIRMED'
        LEFT JOIN USERS u
            ON u.USER_ID = h.USER_ID
        WHERE t.STATUS <> 'COMMITTED' OR h.TRANSACTION_ID IS NOT NULL
        ORDER BY t.LOG_TIME DESC
    )
    WHERE ROWNUM <= 12
";
$stid_trans = oci_parse($conn, $trans_query);
oci_execute($stid_trans);

$bookings_query = "
    SELECT *
    FROM (
        SELECT
            h.TRANSACTION_ID,
            h.TRAIN_NAME,
            h.SOURCE_STATION,
            h.DESTINATION_STATION,
            h.COMPARTMENT,
            h.SEATS,
            h.TOTAL_AMOUNT,
            h.BOOKED_AT,
            h.BOOKING_STATUS,
            u.FULL_NAME AS PASSENGER_NAME
        FROM PASSENGER_BOOKING_HISTORY h
        LEFT JOIN USERS u
            ON u.USER_ID = h.USER_ID
        ORDER BY h.BOOKED_AT DESC
    )
    WHERE ROWNUM <= 12
";
$stid_bookings = oci_parse($conn, $bookings_query);
oci_execute($stid_bookings);

$lock_query = "SELECT TID, DATA_ITEM, LOCK_TYPE, REQUEST_TIME FROM LOCK_TABLE ORDER BY REQUEST_TIME DESC";
$stid_locks = oci_parse($conn, $lock_query);
oci_execute($stid_locks);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clerk Dashboard | RailOps Staff</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--sidebar-width:270px;--primary:#2563eb;--bg:#f1f5f9;--card:#fff;--text:#0f172a;--muted:#64748b;--line:#e2e8f0}
        *{box-sizing:border-box} body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);margin:0;color:var(--text);display:flex}
        .sidebar{width:var(--sidebar-width);min-height:100vh;background:linear-gradient(180deg,#0f172a 0%,#111827 100%);color:#fff;position:fixed;inset:0 auto 0 0;padding:24px 18px;display:flex;flex-direction:column}
        .brand{display:flex;align-items:center;gap:12px;font-size:1.4rem;font-weight:800;margin-bottom:28px}.brand i{color:#60a5fa}.nav{display:grid;gap:8px}.nav a{text-decoration:none;color:#94a3b8;padding:13px 15px;border-radius:14px;font-weight:700;display:flex;align-items:center;gap:12px}.nav a.active,.nav a:hover{background:rgba(255,255,255,.08);color:#fff}
        .sidefoot{margin-top:auto;display:grid;gap:12px;padding-top:18px;border-top:1px solid rgba(255,255,255,.08)}.logout{text-decoration:none;color:#fecaca;font-weight:700;padding:12px 14px;border-radius:12px;background:rgba(220,38,38,.08);display:flex;align-items:center;justify-content:center;gap:10px}
        .main{margin-left:var(--sidebar-width);width:calc(100% - var(--sidebar-width));padding:32px}.header{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;margin-bottom:24px}.header h1{margin:0 0 8px;font-size:2rem;letter-spacing:-.04em}.meta{color:var(--muted);font-size:.86rem;line-height:1.6}
        .header-right{display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;justify-content:flex-end}.top-badges{display:flex;gap:10px;flex-wrap:wrap}.pill{padding:8px 12px;border-radius:999px;font-size:.78rem;font-weight:800}.online{background:#dcfce7;color:#166534}.date{background:#fff;border:1px solid var(--line);color:var(--muted)}
        .profile-container{position:relative;z-index:15}.profile-capsule{background:#fff;border:1px solid var(--line);border-radius:999px;padding:6px 16px 6px 6px;display:flex;align-items:center;gap:12px;cursor:pointer;box-shadow:0 18px 30px rgba(15,23,42,.04)}.avatar{width:42px;height:42px;border-radius:50%;background:#dbeafe;color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:800}.profile-overlay{position:absolute;top:0;right:0;width:320px;background:#fff;border:1px solid var(--line);border-radius:24px;padding:24px;box-shadow:0 24px 40px rgba(15,23,42,.16);display:none}.profile-container.active .profile-overlay{display:block}.profile-container.active .profile-capsule{opacity:0;pointer-events:none}.row{margin-bottom:14px}.row small{display:block;color:var(--muted);font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.row div{margin-top:4px;font-weight:700}
        .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;margin-bottom:24px}.stat,.card{background:var(--card);border:1px solid var(--line);border-radius:24px;box-shadow:0 18px 30px rgba(15,23,42,.04)}.stat{padding:22px}.label{color:var(--muted);font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.value{font-size:1.5rem;font-weight:800;margin-top:10px}
        .card{padding:22px;margin-bottom:18px}.head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:14px}.head h3{margin:0;font-size:1.15rem}
        .table-wrap{overflow:auto} table{width:100%;border-collapse:collapse} th{text-align:left;padding:11px 10px;color:var(--muted);font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--line)} td{padding:14px 10px;font-size:.92rem;border-bottom:1px solid #f1f5f9;vertical-align:top}
        .badge{padding:6px 10px;border-radius:999px;font-size:.72rem;font-weight:800;display:inline-flex;align-items:center;gap:6px}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.danger{background:#fee2e2;color:#991b1b}.neutral{background:#eff6ff;color:#1d4ed8}
        .linkbtn{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border-radius:12px;background:var(--primary);color:#fff;text-decoration:none;font-size:.82rem;font-weight:800}.empty{padding:16px;border-radius:16px;background:#f8fafc;border:1px dashed var(--line);color:var(--muted);font-weight:600}
        @media (max-width:1180px){.stats{grid-template-columns:1fr 1fr}.header{flex-direction:column}.header-right{width:100%;justify-content:space-between}}
        @media (max-width:860px){body{flex-direction:column}.sidebar{position:static;width:100%;min-height:auto}.main{margin-left:0;width:100%;padding:20px}.stats{grid-template-columns:1fr}}
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="brand"><i class="fa-solid fa-train-subway"></i> RailOps Clerk</div>
    <nav class="nav">
        <a href="clerk.php" class="active"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
        <a href="booking_entry.php"><i class="fa-solid fa-ticket"></i> Booking Entry</a>
        <a href="requests.php"><i class="fa-solid fa-envelope-open-text"></i> Requests</a>
        <a href="trains.php"><i class="fa-solid fa-train"></i> Fleet Console</a>
    </nav>
    <div class="sidefoot">
        <div style="color:#94a3b8;font-size:.86rem;line-height:1.6">Logged in as <strong style="color:#fff"><?php echo htmlspecialchars($username); ?></strong><br>Role: Clerk Operations</div>
        <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</aside>
<main class="main">
    <div class="header">
        <div>
            <h1>Clerk Dashboard</h1>
            <div class="meta">Monitor active confirmed transactions, current lock activity, and booking volume with a cleaner clerk workspace.</div>
        </div>
        <div class="header-right">
            <div class="top-badges">
                <span class="pill online">System Online</span>
                <span class="pill date"><?php echo date('D, d M Y'); ?></span>
            </div>
            <div class="profile-container" id="clerkProfile">
                <div class="profile-capsule" onclick="event.stopPropagation(); document.getElementById('clerkProfile').classList.add('active')">
                    <div class="avatar"><?php echo htmlspecialchars(strtoupper(substr($profile['FULL_NAME'],0,1))); ?></div>
                    <div>
                        <div style="font-weight:800"><?php echo htmlspecialchars($profile['FULL_NAME']); ?></div>
                        <div class="meta"><?php echo htmlspecialchars($profile['DESIGNATION']); ?></div>
                    </div>
                </div>
                <div class="profile-overlay" onclick="event.stopPropagation();">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:18px;">
                        <div class="avatar"><?php echo htmlspecialchars(strtoupper(substr($profile['FULL_NAME'],0,1))); ?></div>
                        <button type="button" onclick="document.getElementById('clerkProfile').classList.remove('active')" style="border:none;background:none;font-size:1.2rem;color:#64748b;cursor:pointer;">×</button>
                    </div>
                    <div class="row"><small>Full Name</small><div><?php echo htmlspecialchars($profile['FULL_NAME']); ?></div></div>
                    <div class="row"><small>Email</small><div><?php echo htmlspecialchars($profile['EMAIL']); ?></div></div>
                    <div class="row"><small>Designation</small><div><?php echo htmlspecialchars($profile['DESIGNATION']); ?></div></div>
                    <div class="row"><small>Department</small><div><?php echo htmlspecialchars($profile['DEPARTMENT']); ?></div></div>
                    <div class="row"><small>Station</small><div><?php echo htmlspecialchars($profile['STATION_CODE']); ?></div></div>
                </div>
            </div>
        </div>
    </div>

    <section class="stats">
        <div class="stat"><div class="label">Confirmed Bookings</div><div class="value"><?php echo number_format((float)($stats['TOTAL_BOOKINGS'] ?? 0)); ?></div></div>
        <div class="stat"><div class="label">Total Revenue</div><div class="value">Rs. <?php echo number_format((float)($stats['TOTAL_REVENUE'] ?? 0),2); ?></div></div>
        <div class="stat"><div class="label">Active Locks</div><div class="value"><?php echo number_format((float)($stats['ACTIVE_LOCKS'] ?? 0)); ?></div></div>
        <div class="stat"><div class="label">Registered Users</div><div class="value"><?php echo number_format((float)($stats['TOTAL_USERS'] ?? 0)); ?></div></div>
    </section>

    <section class="card">
        <div class="head"><div><h3>Live Monitoring (2PL)</h3><div class="meta">Only active confirmed bookings remain here. Cancelled bookings are removed automatically after release.</div></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Transaction</th><th>Passenger</th><th>Train & Class</th><th>Seats</th><th>Amount</th><th>Action</th></tr></thead>
                <tbody>
                <?php $hasTrans=false; while($row=oci_fetch_assoc($stid_trans)): $hasTrans=true; $badge=($row['STATUS']==='COMMITTED')?'success':(($row['STATUS']==='ABORTED')?'danger':'warning'); ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($row['TID']); ?></strong><br><span class="meta"><?php echo htmlspecialchars((string)$row['LOG_TIME']); ?></span><br><span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($row['STATUS']); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($row['PASSENGER_NAME'] ?: 'Pending / Not linked'); ?></strong><br><span class="meta">User ID: <?php echo htmlspecialchars((string)($row['USER_ID'] ?? '--')); ?></span></td>
                        <td><strong><?php echo htmlspecialchars(($row['TRAIN_NUMBER'] ?: $row['TRAIN_ID']) . ' - ' . ($row['TRAIN_NAME'] ?: 'Unknown Train')); ?></strong><br><span class="meta"><?php echo htmlspecialchars(($row['SOURCE_STATION'] ?: '--').' to '.($row['DESTINATION_STATION'] ?: '--')); ?><br>Class: <?php echo htmlspecialchars($row['COMPARTMENT'] ?: '--'); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($row['SEATS'] ?: '--'); ?></strong></td>
                        <td>Rs. <?php echo htmlspecialchars(number_format((float)($row['TOTAL_AMOUNT'] ?? 0),2)); ?></td>
                        <td><?php if(!empty($row['TRAIN_ID']) && !empty($row['COMPARTMENT'])): ?><a class="linkbtn" href="seat_view.php?train_id=<?php echo urlencode($row['TRAIN_ID']); ?>&compartment=<?php echo urlencode($row['COMPARTMENT']); ?>"><i class="fa-solid fa-eye"></i> View Seating</a><?php else: ?><span class="meta">No seating data</span><?php endif; ?></td>
                    </tr>
                <?php endwhile; if(!$hasTrans): ?><tr><td colspan="6"><div class="empty">No live transaction activity yet.</div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div style="height:18px"></div>
        <div class="head" style="margin-bottom:10px"><div><h3 style="font-size:1rem">Active Resource Locks</h3><div class="meta">Lock entries sit under live monitoring for easier 2PL tracing.</div></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>TID</th><th>Item</th><th>Type</th></tr></thead>
                <tbody>
                <?php $hasLocks=false; while($row=oci_fetch_assoc($stid_locks)): $hasLocks=true; ?>
                    <tr><td><strong>#<?php echo htmlspecialchars($row['TID']); ?></strong></td><td class="meta"><?php echo htmlspecialchars($row['DATA_ITEM']); ?></td><td><span class="badge neutral"><i class="fa-solid fa-lock"></i> <?php echo htmlspecialchars($row['LOCK_TYPE']); ?></span></td></tr>
                <?php endwhile; if(!$hasLocks): ?><tr><td colspan="3"><div class="empty">No active locks right now.</div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="head">
            <div><h3>Recent Booking Entries</h3><div class="meta">Latest booking details placed below live monitoring for a more spacious layout.</div></div>
            <a href="booking_entry.php" class="linkbtn"><i class="fa-solid fa-list"></i> Full Entry</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Transaction</th><th>Passenger</th><th>Fare</th></tr></thead>
                <tbody>
                <?php $hasBookings=false; while($row=oci_fetch_assoc($stid_bookings)): $hasBookings=true; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['TRANSACTION_ID'] ?: '--'); ?></strong><br><span class="meta"><?php echo htmlspecialchars(($row['TRAIN_NAME'] ?? 'Unknown').' • '.($row['COMPARTMENT'] ?? '--')); ?><br><?php echo htmlspecialchars((string)$row['BOOKED_AT']); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($row['PASSENGER_NAME'] ?: 'Unknown Passenger'); ?></strong><br><span class="meta"><?php echo htmlspecialchars($row['SOURCE_STATION'].' to '.$row['DESTINATION_STATION']); ?><br>Seats: <?php echo htmlspecialchars($row['SEATS']); ?></span></td>
                        <td><strong>Rs. <?php echo htmlspecialchars(number_format((float)($row['TOTAL_AMOUNT'] ?? 0),2)); ?></strong><br><span class="badge <?php echo ($row['BOOKING_STATUS'] === 'CONFIRMED') ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($row['BOOKING_STATUS']); ?></span></td>
                    </tr>
                <?php endwhile; if(!$hasBookings): ?><tr><td colspan="3"><div class="empty">No booking entries found yet.</div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script>
document.addEventListener('click', function() {
    const profile = document.getElementById('clerkProfile');
    if (profile && profile.classList.contains('active')) profile.classList.remove('active');
});
</script>
</body>
</html>
