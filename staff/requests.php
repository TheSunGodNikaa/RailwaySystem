<?php
require_once "auth_guard.php";
include "../db.php";
require_once __DIR__ . "/../TwoPL/train_data.php";

railwayEnsureCancellationRequestsTable($conn);
$sql = "
    SELECT
        r.request_id,
        r.user_id,
        r.passenger_name,
        r.transaction_id,
        r.reason,
        r.request_status,
        r.created_at,
        h.train_id,
        h.compartment,
        h.train_name,
        h.train_number,
        h.seats
    FROM BOOKING_CANCELLATION_REQUESTS r
    LEFT JOIN (
        SELECT h1.*
        FROM PASSENGER_BOOKING_HISTORY h1
        INNER JOIN (
            SELECT transaction_id, MAX(booked_at) AS latest_booked_at
            FROM PASSENGER_BOOKING_HISTORY
            GROUP BY transaction_id
        ) latest
            ON latest.transaction_id = h1.transaction_id
           AND latest.latest_booked_at = h1.booked_at
    ) h
        ON h.transaction_id = r.transaction_id
    WHERE r.request_status = 'PENDING'
    ORDER BY r.created_at DESC
";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);
$requests = [];
while ($row = oci_fetch_assoc($stmt)) {
    $requests[] = $row;
}

if (isset($_GET['json']) && $_GET['json'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'requests' => $requests,
        'count' => count($requests),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancellation Requests | RailOps Clerk</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#f1f5f9;--card:#fff;--text:#0f172a;--muted:#64748b;--line:#e2e8f0;--accent:#2563eb}
        *{box-sizing:border-box} body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);margin:0;padding:32px;color:var(--text)} .wrap{max-width:1200px;margin:0 auto}
        .top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:22px}.top h1{margin:0 0 8px;font-size:2rem}.meta{color:var(--muted);font-size:.88rem;line-height:1.6}.back{text-decoration:none;background:#0f172a;color:#fff;padding:12px 16px;border-radius:14px;font-weight:800}
        .card{background:var(--card);border:1px solid var(--line);border-radius:24px;box-shadow:0 18px 30px rgba(15,23,42,.04);padding:24px}.table-wrap{overflow:auto} table{width:100%;border-collapse:collapse} th{text-align:left;padding:12px 10px;color:var(--muted);font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--line)} td{padding:14px 10px;border-bottom:1px solid #f1f5f9;vertical-align:top;font-size:.92rem}
        .badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:.72rem;font-weight:800}.pending{background:#fef3c7;color:#92400e}.resolved{background:#dcfce7;color:#166534}
        .meta{color:var(--muted);font-size:.84rem;line-height:1.6}.seat-link{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border-radius:12px;background:var(--accent);color:#fff;text-decoration:none;font-size:.82rem;font-weight:800}.pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#eff6ff;color:var(--accent);font-size:.8rem;font-weight:800}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Cancellation Requests</h1>
            <div class="meta">Pending passenger cancellation requests submitted from the Help Center. Once the clerk resolves the booking, the row disappears automatically.</div>
        </div>
        <a href="clerk.php" class="back">Back to Dashboard</a>
    </div>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px;">
            <div class="meta">Direct seat access is available here now, so you can jump straight into releasing the booked seats.</div>
            <div class="pill">Pending: <span id="pendingCount"><?php echo count($requests); ?></span></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Passenger</th>
                        <th>Transaction ID</th>
                        <th>Train</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Requested At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="requestsBody">
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="meta">No pending cancellation requests right now.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr data-request-id="<?php echo htmlspecialchars((string) $request['REQUEST_ID']); ?>">
                                <td><strong><?php echo htmlspecialchars($request['PASSENGER_NAME'] ?: 'Unknown Passenger'); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($request['TRANSACTION_ID']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars(($request['TRAIN_NUMBER'] ?: $request['TRAIN_ID'] ?: '--') . ' - ' . ($request['TRAIN_NAME'] ?: 'Unknown Train')); ?></strong><br>
                                    <span class="meta">Class: <?php echo htmlspecialchars($request['COMPARTMENT'] ?: '--'); ?><br>Seats: <?php echo htmlspecialchars($request['SEATS'] ?: '--'); ?></span>
                                </td>
                                <td class="meta"><?php echo htmlspecialchars($request['REASON'] ?: '--'); ?></td>
                                <td><span class="badge <?php echo $request['REQUEST_STATUS'] === 'RESOLVED' ? 'resolved' : 'pending'; ?>"><?php echo htmlspecialchars($request['REQUEST_STATUS']); ?></span></td>
                                <td class="meta"><?php echo htmlspecialchars((string) $request['CREATED_AT']); ?></td>
                                <td>
                                    <?php if (!empty($request['TRAIN_ID']) && !empty($request['COMPARTMENT'])): ?>
                                        <a class="seat-link" href="seat_view.php?train_id=<?php echo urlencode($request['TRAIN_ID']); ?>&compartment=<?php echo urlencode($request['COMPARTMENT']); ?>">
                                            View Seating
                                        </a>
                                    <?php else: ?>
                                        <span class="meta">No seating data</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
const requestsJsonUrl = 'requests.php?json=1';

function renderRequests(rows) {
    const body = document.getElementById('requestsBody');
    document.getElementById('pendingCount').textContent = rows.length;

    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="7" class="meta">No pending cancellation requests right now.</td></tr>';
        return;
    }

    body.innerHTML = rows.map((row) => {
        const trainLabel = `${row.TRAIN_NUMBER || row.TRAIN_ID || '--'} - ${row.TRAIN_NAME || 'Unknown Train'}`;
        const action = (row.TRAIN_ID && row.COMPARTMENT)
            ? `<a class="seat-link" href="seat_view.php?train_id=${encodeURIComponent(row.TRAIN_ID)}&compartment=${encodeURIComponent(row.COMPARTMENT)}">View Seating</a>`
            : '<span class="meta">No seating data</span>';

        return `
            <tr data-request-id="${row.REQUEST_ID}">
                <td><strong>${escapeHtml(row.PASSENGER_NAME || 'Unknown Passenger')}</strong></td>
                <td><strong>${escapeHtml(row.TRANSACTION_ID || '--')}</strong></td>
                <td>
                    <strong>${escapeHtml(trainLabel)}</strong><br>
                    <span class="meta">Class: ${escapeHtml(row.COMPARTMENT || '--')}<br>Seats: ${escapeHtml(row.SEATS || '--')}</span>
                </td>
                <td class="meta">${escapeHtml(row.REASON || '--')}</td>
                <td><span class="badge pending">${escapeHtml(row.REQUEST_STATUS || 'PENDING')}</span></td>
                <td class="meta">${escapeHtml(row.CREATED_AT || '--')}</td>
                <td>${action}</td>
            </tr>
        `;
    }).join('');
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

async function refreshRequests() {
    try {
        const response = await fetch(requestsJsonUrl, { cache: 'no-store' });
        const data = await response.json();
        renderRequests(data.requests || []);
    } catch (error) {
        console.error('Unable to refresh requests', error);
    }
}

setInterval(refreshRequests, 4000);
</script>
</body>
</html>
