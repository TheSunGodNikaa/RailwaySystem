<?php
require_once "auth_guard.php";
include_once "../db.php";

$train_id = $_REQUEST['train_id'] ?? 101;
$compartment = $_REQUEST['compartment'] ?? 'SL';

$train_sql = "SELECT TRAIN_NAME FROM TRAINS WHERE TRAIN_ID = :train_id";
$train_stmt = oci_parse($conn, $train_sql);
oci_bind_by_name($train_stmt, ":train_id", $train_id);
oci_execute($train_stmt);
$train = oci_fetch_assoc($train_stmt);
$train_name = $train['TRAIN_NAME'] ?? 'Unknown Train';

$sql = "SELECT SEAT_NO, STATUS FROM TRAIN_SEATS
        WHERE TRAIN_ID = :train_id AND COMPARTMENT = :compartment
        ORDER BY TO_NUMBER(REGEXP_REPLACE(SEAT_NO, '[^0-9]', '')) ASC";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":train_id", $train_id);
oci_bind_by_name($stmt, ":compartment", $compartment);
oci_execute($stmt);

$seats = [];
$bookedCount = 0;
while ($row = oci_fetch_assoc($stmt)) {
    if (($row['STATUS'] ?? '') === 'BOOKED') {
        $bookedCount++;
    }
    $seats[] = $row;
}

$lock_sql = "SELECT DATA_ITEM FROM LOCK_TABLE";
$lock_stid = oci_parse($conn, $lock_sql);
oci_execute($lock_stid);
$locked = [];
while ($row = oci_fetch_assoc($lock_stid)) {
    $locked[] = trim($row['DATA_ITEM']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Console | Bay View Control</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg:#f8fafc; --primary:#1e293b; --accent:#2563eb; --booked:#ef4444; --locked:#f59e0b; --available:#22c55e; --selected:#6366f1; }
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);margin:0;padding:40px}.topbar{max-width:1240px;margin:0 auto 18px;display:flex;justify-content:space-between;align-items:center;gap:16px}.back{display:inline-flex;align-items:center;gap:8px;text-decoration:none;background:var(--primary);color:#fff;padding:12px 16px;border-radius:14px;font-weight:800}
        .pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#dbeafe;color:var(--accent);font-size:.8rem;font-weight:800}
        .main{display:flex;gap:30px;max-width:1240px;margin:0 auto}.coach{flex:1;background:#fff;border-radius:24px;padding:35px;box-shadow:0 10px 25px rgba(0,0,0,.05)}.coachbox{background:#f1f5f9;border-radius:16px;padding:20px;max-height:75vh;overflow-y:auto;border:1px solid #e2e8f0}
        .bay{display:grid;grid-template-columns:repeat(3,1fr) 45px 1fr;gap:12px;padding:20px 0;border-bottom:2px dashed #cbd5e1}.main-cabin{display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:1fr 1fr;gap:8px}.side-berths{display:grid;grid-template-rows:1fr 1fr;gap:8px}.aisle{background:#cbd5e1;border-radius:10px;opacity:.5}
        .seat{height:50px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;cursor:pointer;transition:.2s;border:2px solid transparent;background:#fff;color:#64748b}.seat:hover:not(.available){transform:translateY(-2px);box-shadow:0 5px 10px rgba(0,0,0,.1)}.seat.booked{color:var(--booked);border-color:rgba(239,68,68,.2);background:rgba(239,68,68,.05)}.seat.locked{color:var(--locked);border-color:rgba(245,158,11,.2);background:rgba(245,158,11,.05)}.seat.available{color:#94a3b8;background:#f8fafc;cursor:default;border-style:dashed}.seat.selected{background:var(--selected)!important;color:#fff!important;border-color:var(--selected);box-shadow:0 0 15px rgba(99,102,241,.4)}
        .side{width:390px;background:#fff;padding:30px;border-radius:24px;box-shadow:0 10px 30px rgba(0,0,0,.08);height:fit-content;position:sticky;top:40px}.pill-container{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0;min-height:50px}.seat-pill{background:#f1f5f9;padding:6px 14px;border-radius:50px;font-size:13px;font-weight:700;color:var(--primary);border:1px solid #e2e8f0}.btn{width:100%;background:var(--primary);color:#fff;border:none;padding:18px;border-radius:14px;font-weight:700;cursor:pointer;display:none}
        .legend{display:flex;gap:15px;margin-top:15px;font-size:12px;flex-wrap:wrap}.legend-item{display:flex;align-items:center;gap:5px;font-weight:600}.dot{width:10px;height:10px;border-radius:50%}.meta{color:#64748b;font-size:.92rem;line-height:1.7}
        @media (max-width:1040px){body{padding:20px}.main{flex-direction:column}.side{width:100%;position:static}}
    </style>
</head>
<body>
<div class="topbar">
    <div class="pill"><i class="fa-solid fa-train"></i> <?php echo htmlspecialchars($train_name); ?> • <?php echo htmlspecialchars($compartment); ?> • Booked seats: <?php echo htmlspecialchars((string) $bookedCount); ?></div>
    <a href="trains.php" class="back"><i class="fa-solid fa-arrow-left"></i> Back to Fleet</a>
</div>
<div class="main">
    <div class="coach">
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:25px;gap:16px;">
            <div>
                <h2 style="margin:0"><?php echo htmlspecialchars($train_name); ?> (ID: <?php echo htmlspecialchars($train_id); ?>)</h2>
                <div class="legend">
                    <div class="legend-item"><span class="dot" style="background:var(--booked)"></span> Booked</div>
                    <div class="legend-item"><span class="dot" style="background:var(--locked)"></span> Locked</div>
                    <div class="legend-item"><span class="dot" style="background:#cbd5e1"></span> Available</div>
                </div>
            </div>
            <div class="meta">Only booked or locked seats can be selected for release.</div>
        </div>
        <div class="coachbox">
            <?php foreach (array_chunk($seats, 8) as $bay_seats): ?>
                <div class="bay">
                    <div class="main-cabin">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <?php if (isset($bay_seats[$i])): $s = $bay_seats[$i]; $data_item = "SEAT_" . $train_id . "_" . $s['SEAT_NO']; $status = ($s['STATUS'] === 'BOOKED') ? 'booked' : (in_array($data_item, $locked, true) ? 'locked' : 'available'); ?>
                                <div class="seat <?php echo $status; ?>" id="s-<?php echo htmlspecialchars($s['SEAT_NO']); ?>" onclick="handleSelect('<?php echo htmlspecialchars($s['SEAT_NO'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($data_item, ENT_QUOTES); ?>','<?php echo $status; ?>')"><?php echo htmlspecialchars($s['SEAT_NO']); ?></div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div class="aisle"></div>
                    <div class="side-berths">
                        <?php for ($i = 6; $i < 8; $i++): ?>
                            <?php if (isset($bay_seats[$i])): $s = $bay_seats[$i]; $data_item = "SEAT_" . $train_id . "_" . $s['SEAT_NO']; $status = ($s['STATUS'] === 'BOOKED') ? 'booked' : (in_array($data_item, $locked, true) ? 'locked' : 'available'); ?>
                                <div class="seat <?php echo $status; ?>" id="s-<?php echo htmlspecialchars($s['SEAT_NO']); ?>" onclick="handleSelect('<?php echo htmlspecialchars($s['SEAT_NO'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($data_item, ENT_QUOTES); ?>','<?php echo $status; ?>')"><?php echo htmlspecialchars($s['SEAT_NO']); ?></div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="side">
        <h3 style="margin-top:0">Clerk Control Panel</h3>
        <p class="meta">Select booked or currently locked seats, then release them back to available status for this exact train and class.</p>
        <div class="pill-container" id="selection-list"><span style="color:#cbd5e1;font-style:italic;">No seats selected...</span></div>
        <button id="releaseBtn" class="btn" onclick="submitRelease()">Release Selected Seats</button>
    </div>
</div>
<script>
let selected=[];
function handleSelect(num,dataId,status){if(status==='available')return;const idx=selected.findIndex(item=>item.id===dataId);const el=document.getElementById('s-'+num);if(idx>-1){selected.splice(idx,1);el.classList.remove('selected')}else{selected.push({num,id:dataId});el.classList.add('selected')}updatePanel()}
function updatePanel(){const list=document.getElementById('selection-list');const btn=document.getElementById('releaseBtn');if(selected.length===0){list.innerHTML='<span style="color:#cbd5e1;font-style:italic;">No seats selected...</span>';btn.style.display='none'}else{list.innerHTML=selected.map(s=>`<span class="seat-pill">Seat ${s.num}</span>`).join('');btn.style.display='block'}}
function submitRelease(){const ids=selected.map(s=>s.id).join(',');if(confirm(`ADMIN ACTION: Release ${selected.length} seats?`)){window.location.href="release_seat.php?items="+encodeURIComponent(ids)+"&train_id=<?php echo urlencode((string) $train_id); ?>&compartment=<?php echo urlencode($compartment); ?>"}}
</script>
</body>
</html>
