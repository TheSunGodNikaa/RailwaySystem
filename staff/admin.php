<?php
$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
if (!$embed) {
    require_once "admin_guard.php";
} else {
    session_start();
}
include "../db.php";
require_once __DIR__ . "/../TwoPL/train_data.php";

$clerkStatuses = railwayGetClerkStatuses($conn);
$stats = [
    'TOTAL_CLERKS' => count($clerkStatuses),
    'LOGGED_IN_CLERKS' => count(array_filter($clerkStatuses, static function ($clerk) {
        return (int) ($clerk['IS_LOGGED_IN'] ?? 0) === 1;
    })),
];

if (isset($_GET['json']) && $_GET['json'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'total_clerks' => (int) ($stats['TOTAL_CLERKS'] ?? 0),
        'logged_in_clerks' => (int) ($stats['LOGGED_IN_CLERKS'] ?? 0),
        'clerks' => $clerkStatuses,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staffs Overview | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#f1f5f9; --card:#fff; --text:#0f172a; --muted:#64748b; --line:#e2e8f0; --accent:#2563eb; }
        *{box-sizing:border-box} body{font-family:'Plus Jakarta Sans',sans-serif;background:<?php echo $embed ? '#0b0f1a' : 'var(--bg)'; ?>;margin:0;color:<?php echo $embed ? '#f8fafc' : 'var(--text)'; ?>;padding:<?php echo $embed ? '20px' : '32px'; ?>}
        .wrap{max-width:1240px;margin:0 auto}.top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:22px}.top h1{margin:0 0 8px;font-size:2rem}.meta{color:<?php echo $embed ? '#94a3b8' : 'var(--muted)'; ?>;font-size:.9rem;line-height:1.6}
        .logout{text-decoration:none;background:#0f172a;color:#fff;padding:12px 16px;border-radius:14px;font-weight:800}
        .stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-bottom:22px}.stat,.card{background:<?php echo $embed ? '#1e293b' : 'var(--card)'; ?>;border:1px solid <?php echo $embed ? 'rgba(255,255,255,.08)' : 'var(--line)'; ?>;border-radius:24px;box-shadow:0 18px 30px rgba(15,23,42,.04)}.stat{padding:22px}.label{color:<?php echo $embed ? '#94a3b8' : 'var(--muted)'; ?>;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.value{font-size:2rem;font-weight:800;margin-top:10px}
        .card{padding:22px}.table-wrap{overflow:auto} table{width:100%;border-collapse:collapse} th{text-align:left;padding:12px 10px;color:<?php echo $embed ? '#94a3b8' : 'var(--muted)'; ?>;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid <?php echo $embed ? 'rgba(255,255,255,.08)' : 'var(--line)'; ?>} td{padding:14px 10px;border-bottom:1px solid <?php echo $embed ? 'rgba(255,255,255,.05)' : '#f1f5f9'; ?>;vertical-align:top;font-size:.92rem}
        .sub{color:<?php echo $embed ? '#94a3b8' : 'var(--muted)'; ?>;font-size:.84rem;line-height:1.6}.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:.72rem;font-weight:800}.on{background:#dcfce7;color:#166534}.off{background:#fee2e2;color:#991b1b}
        @media (max-width:900px){body{padding:18px}.stats{grid-template-columns:1fr}.top{flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Staffs Overview</h1>
            <div class="meta">Created clerk accounts, login status, station posting, and last activity.</div>
        </div>
        <?php if (!$embed): ?>
            <a href="logout.php" class="logout">Logout</a>
        <?php endif; ?>
    </div>

    <div class="stats">
        <div class="stat"><div class="label">Clerk Accounts Created</div><div class="value"><?php echo number_format((float) ($stats['TOTAL_CLERKS'] ?? 0)); ?></div></div>
        <div class="stat"><div class="label">Clerks Logged In</div><div class="value"><?php echo number_format((float) ($stats['LOGGED_IN_CLERKS'] ?? 0)); ?></div></div>
    </div>

    <div class="card">
        <div style="margin-bottom:16px;">
            <h3 style="margin:0 0 6px;">Clerk Details</h3>
            <div class="sub">Operational status of every created clerk account.</div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Clerk</th>
                        <th>Username</th>
                        <th>Designation</th>
                        <th>Station</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clerkStatuses)): ?>
                        <tr><td colspan="5" class="sub">No clerk accounts found yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($clerkStatuses as $clerk): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($clerk['FULL_NAME']); ?></strong><br><span class="sub"><?php echo htmlspecialchars($clerk['EMAIL']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($clerk['USERNAME']); ?></strong><br><span class="sub">EMP: <?php echo htmlspecialchars($clerk['EMP_ID']); ?></span></td>
                                <td><?php echo htmlspecialchars($clerk['DESIGNATION']); ?><br><span class="sub"><?php echo htmlspecialchars($clerk['DEPARTMENT']); ?></span></td>
                                <td><?php echo htmlspecialchars($clerk['STATION_CODE']); ?><br><span class="sub">Created: <?php echo htmlspecialchars((string) ($clerk['CREATED_AT'] ?? '--')); ?></span></td>
                                <td>
                                    <span class="badge <?php echo ((int) ($clerk['IS_LOGGED_IN'] ?? 0)) === 1 ? 'on' : 'off'; ?>">
                                        <?php echo ((int) ($clerk['IS_LOGGED_IN'] ?? 0)) === 1 ? 'Logged In' : 'Logged Out'; ?>
                                    </span><br>
                                    <span class="sub">Last login: <?php echo htmlspecialchars((string) ($clerk['LAST_LOGIN_AT'] ?? '--')); ?><br>Last logout: <?php echo htmlspecialchars((string) ($clerk['LAST_LOGOUT_AT'] ?? '--')); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if ($embed): ?>
<script>
setInterval(() => {
    window.location.reload();
}, 8000);
</script>
<?php endif; ?>
</body>
</html>
