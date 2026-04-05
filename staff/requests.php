<?php
require_once "auth_guard.php";
include "../db.php";
require_once __DIR__ . "/../TwoPL/train_data.php";

railwayEnsureCancellationRequestsTable($conn);
$requests = railwayGetCancellationRequests($conn);
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
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Cancellation Requests</h1>
            <div class="meta">Passenger cancellation requests submitted from the Help Center. Review transaction ID and reason before releasing the seats.</div>
        </div>
        <a href="clerk.php" class="back">Back to Dashboard</a>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Passenger</th>
                        <th>Transaction ID</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Requested At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="5" class="meta">No cancellation requests yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($request['PASSENGER_NAME'] ?: 'Unknown Passenger'); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($request['TRANSACTION_ID']); ?></strong></td>
                                <td class="meta"><?php echo htmlspecialchars($request['REASON'] ?: '--'); ?></td>
                                <td><span class="badge <?php echo $request['REQUEST_STATUS'] === 'RESOLVED' ? 'resolved' : 'pending'; ?>"><?php echo htmlspecialchars($request['REQUEST_STATUS']); ?></span></td>
                                <td class="meta"><?php echo htmlspecialchars((string) $request['CREATED_AT']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
