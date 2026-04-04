<?php
session_start();
include_once __DIR__ . "/lock_manager.php";
include "../db.php";
require_once __DIR__ . "/../train_catalog.php";
include_once "identify_data.php";
include_once "lock_type.php";
include_once "lock_request.php";
include_once "acquire_lock.php";
include_once "seat_allocation.php";
include_once "booking_record.php";
include_once "transaction_logger.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!$conn) {
    die("Database connection failed.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $train_id = $_POST['train_id'];
    $compartment = $_POST['compartment'];
    $journeyDate = $_POST['journey_date'] ?? ($_SESSION['active_booking']['journey_date'] ?? date('Y-m-d'));
    $seat_no_raw = $_POST['seat_no'] ?? '';
    $train = getTrainById($train_id);

    if (empty($seat_no_raw)) {
        header("Location: index.php");
        exit;
    }

    $seat_list = explode(",", $seat_no_raw);
    $tid = "TXN" . rand(100000, 999999);
    $allLocked = true;

    foreach ($seat_list as $seat_no) {
        $data_item = identifyDataItem($train_id, $seat_no);
        $lock_type = determineLockType("WRITE");
        $request = generateLockRequest($tid, $data_item, $lock_type);

        if (!acquireLock($conn, $request)) {
            $allLocked = false;
            break;
        }
    }

    if (!$allLocked) {
        releaseLocks($conn, $tid);
        logTransaction($conn, $tid, "ABORTED");
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Transaction Failed | IRCTC Clone</title>
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
            <style>
                body { font-family: 'Plus Jakarta Sans', sans-serif; background: #fef2f2; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .error-card { background: white; padding: 40px; border-radius: 24px; box-shadow: 0 20px 25px -5px rgba(220, 38, 38, 0.1); text-align: center; max-width: 450px; border: 1px solid #fee2e2; }
                .icon-circle { width: 80px; height: 80px; background: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 20px; }
                h2 { color: #991b1b; margin: 0 0 10px; }
                p { color: #7f1d1d; line-height: 1.6; }
                .retry-btn { margin-top: 25px; width: 100%; padding: 14px; border-radius: 12px; border: none; background: #dc2626; color: white; font-weight: 700; cursor: pointer; transition: 0.2s; }
            </style>
        </head>
        <body>
            <div class="error-card">
                <div class="icon-circle">X</div>
                <h2>Transaction Conflict</h2>
                <p>Another passenger is currently attempting to book these seats (Seat: <?php echo htmlspecialchars($seat_no); ?>). The 2PL manager aborted this request to prevent double-booking.</p>
                <button class="retry-btn" onclick="window.location.href='index.php?train_id=<?php echo urlencode((string) $train_id); ?>&journey_date=<?php echo urlencode($journeyDate); ?>'">Try Different Seats</button>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    foreach ($seat_list as $seat_no) {
        allocateSeat($conn, $train_id, $seat_no);
        insertBooking($conn, $train_id, $seat_no);
    }
    logTransaction($conn, $tid, "COMMITTED");
    releaseLocks($conn, $tid);
    unset($_SESSION['pending_booking']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Booking Confirmed | E-Ticket</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            :root { --primary: #2563eb; --success: #059669; --bg: #f8fafc; }
            body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); margin: 0; padding: 40px; display: flex; flex-direction: column; align-items: center; }
            .stepper { display: flex; gap: 40px; margin-bottom: 40px; opacity: 0.6; }
            .step { display: flex; align-items: center; gap: 8px; font-weight: 600; color: #64748b; }
            .step.done { color: var(--success); }
            .ticket { background: white; width: 100%; max-width: 700px; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.08); position: relative; }
            .ticket-header { background: var(--primary); color: white; padding: 30px; display: flex; justify-content: space-between; align-items: center; }
            .ticket-body { padding: 40px; }
            .pnr-section { display: flex; border-bottom: 2px dashed #e2e8f0; padding-bottom: 30px; margin-bottom: 30px; justify-content: space-between; }
            .label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; display: block; margin-bottom: 5px; }
            .value { font-size: 18px; font-weight: 700; color: #1e293b; }
            .journey-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px; }
            .seat-badge-container { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
            .seat-badge { background: #dcfce7; color: #166534; padding: 6px 14px; border-radius: 8px; font-weight: 700; border: 1px solid #bbf7d0; }
            .footer-actions { margin-top: 30px; display: flex; gap: 15px; width: 100%; max-width: 700px; }
            .btn { flex: 1; padding: 16px; border-radius: 12px; font-weight: 700; cursor: pointer; border: none; transition: 0.2s; text-decoration: none; text-align: center; }
            .btn-primary { background: var(--primary); color: white; }
            .btn-secondary { background: white; color: var(--primary); border: 2px solid var(--primary); }
            .ticket::before, .ticket::after { content: ''; position: absolute; width: 40px; height: 40px; background: var(--bg); border-radius: 50%; top: 215px; }
            .ticket::before { left: -20px; }
            .ticket::after { right: -20px; }
        </style>
    </head>
    <body>
        <div class="stepper">
            <div class="step done">OK Class</div>
            <div class="step done">OK Seats</div>
            <div class="step done" style="opacity:1">OK Confirmed</div>
        </div>

        <div class="ticket">
            <div class="ticket-header">
                <div>
                    <h3 style="margin:0">Electronic Reservation Slip</h3>
                    <small style="opacity:0.8">Indian Railways - IRCTC Authorized</small>
                </div>
                <div style="text-align:right">
                    <span style="background:rgba(255,255,255,0.2); padding:5px 12px; border-radius:20px; font-size:12px;">CONCURRENCY: 2PL ENABLED</span>
                </div>
            </div>

            <div class="ticket-body">
                <div class="pnr-section">
                    <div>
                        <span class="label">Transaction ID</span>
                        <span class="value" style="color:var(--primary)"><?php echo htmlspecialchars($tid); ?></span>
                    </div>
                    <div style="text-align:right">
                        <span class="label">Booking Status</span>
                        <span class="value" style="color:var(--success)">CONFIRMED (CNF)</span>
                    </div>
                </div>

                <div class="journey-grid">
                    <div>
                        <span class="label">Train No and Name</span>
                        <span class="value"><?php echo htmlspecialchars($train ? ($train_id . ' - ' . $train['train_name']) : ($train_id . ' - Express')); ?></span>
                    </div>
                    <div>
                        <span class="label">Class</span>
                        <span class="value"><?php echo htmlspecialchars($compartment); ?></span>
                    </div>
                    <div>
                        <span class="label">Date of Journey</span>
                        <span class="value"><?php echo htmlspecialchars(date('d M, Y', strtotime($journeyDate))); ?></span>
                    </div>
                </div>

                <?php if ($train): ?>
                    <div class="journey-grid">
                        <div>
                            <span class="label">Route</span>
                            <span class="value"><?php echo htmlspecialchars($train['from_code'] . ' to ' . $train['to_code']); ?></span>
                        </div>
                        <div>
                            <span class="label">Departure</span>
                            <span class="value"><?php echo htmlspecialchars($train['departure']); ?></span>
                        </div>
                        <div>
                            <span class="label">Arrival</span>
                            <span class="value"><?php echo htmlspecialchars($train['arrival']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="background: #f8fafc; padding: 20px; border-radius: 16px;">
                    <span class="label">Passenger Berth Details</span>
                    <div class="seat-badge-container">
                        <?php foreach ($seat_list as $s): ?>
                            <div class="seat-badge">Seat <?php echo htmlspecialchars($s); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top:30px; font-size: 11px; color: #94a3b8; text-align: center;">
                    This is a computer generated document. Locks released successfully for Transaction <?php echo htmlspecialchars($tid); ?>.
                </div>
            </div>
        </div>

        <div class="footer-actions">
            <a href="../home.php" class="btn btn-secondary">Back to Home</a>
            <button onclick="window.print()" class="btn btn-primary">Print E-Ticket</button>
        </div>
    </body>
    </html>
    <?php
}
?>
