<?php
session_start();
include "../db.php";
require_once __DIR__ . "/train_data.php";

$source = trim($_GET['source'] ?? ($_SESSION['active_booking']['source'] ?? ''));
$destination = trim($_GET['destination'] ?? ($_SESSION['active_booking']['destination'] ?? ''));
$journeyDate = trim($_GET['journey_date'] ?? ($_SESSION['active_booking']['journey_date'] ?? date('Y-m-d')));
$selectedTrainId = trim($_GET['train_id'] ?? ($_SESSION['active_booking']['train_id'] ?? ''));

$stations = railwayGetStations($conn);
$initialResults = [];
$selectedTrain = null;
$selectedClasses = [];

if ($source !== '' && $destination !== '' && strcasecmp($source, $destination) !== 0) {
    $initialResults = railwaySearchTrains($conn, $source, $destination);
}

if ($selectedTrainId !== '') {
    $selectedTrain = railwayGetTrainById($conn, $selectedTrainId);
    $selectedClasses = railwayGetTrainClassAvailability($conn, $selectedTrainId);
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
    header('Content-Type: application/json');
    if ($source === '' || $destination === '') {
        echo json_encode(['error' => 'Please enter both source and destination stations.']);
        exit();
    }
    if (strcasecmp($source, $destination) === 0) {
        echo json_encode(['error' => 'Source and destination must be different stations.']);
        exit();
    }
    echo json_encode([
        'error' => '',
        'source' => $source,
        'destination' => $destination,
        'journey_date' => $journeyDate,
        'results' => railwaySearchTrains($conn, $source, $destination),
    ]);
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'class') {
    header('Content-Type: application/json');
    if ($selectedTrainId === '') {
        echo json_encode(['error' => 'Please select a train first.']);
        exit();
    }
    $train = railwayGetTrainById($conn, $selectedTrainId);
    if (!$train) {
        echo json_encode(['error' => 'The selected train could not be found.']);
        exit();
    }

    $_SESSION['active_booking'] = [
        'train_id' => $train['TRAIN_ID'],
        'train_number' => $train['TRAIN_NUMBER'],
        'train_name' => $train['TRAIN_NAME'],
        'source' => $source !== '' ? $source : $train['SOURCE_STATION'],
        'destination' => $destination !== '' ? $destination : $train['DESTINATION_STATION'],
        'full_source' => $train['SOURCE_STATION'],
        'full_destination' => $train['DESTINATION_STATION'],
        'departure' => $train['DEPARTURE_TIME'],
        'arrival' => $train['ARRIVAL_TIME'],
        'duration' => $train['DURATION'],
        'journey_date' => $journeyDate,
    ];

    echo json_encode([
        'error' => '',
        'journey_date' => $journeyDate,
        'source' => $source !== '' ? $source : $train['SOURCE_STATION'],
        'destination' => $destination !== '' ? $destination : $train['DESTINATION_STATION'],
        'train' => $train,
        'classes' => railwayGetTrainClassAvailability($conn, $selectedTrainId),
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Ticket | RailOps 2PL</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--bg:#081120;--surface:#0f1c33;--card:#f8fbff;--line:rgba(148,163,184,.18);--text:#e2e8f0;--muted:#94a3b8;--accent:#2563eb;--amber:#f59e0b;--shadow:0 28px 60px rgba(2,6,23,.32)}
        *{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif}
        body{background:linear-gradient(180deg,#081120 0%,#0b1527 100%);color:var(--text);min-height:100vh}
        .shell{width:min(1240px,calc(100% - 40px));margin:0 auto;padding:32px 0 56px}
        .topbar,.row,.actions,.head{display:flex;justify-content:space-between;align-items:center;gap:16px}
        .topbar{margin-bottom:24px}.brand,.back{text-decoration:none}.brand{color:#fff;font-weight:800;font-size:1.2rem;display:flex;align-items:center;gap:12px}.brand i{color:var(--accent)}
        .back{color:var(--text);padding:12px 18px;border:1px solid var(--line);border-radius:999px;background:rgba(15,28,51,.78);font-weight:700}
        .panel,.extras{background:rgba(15,28,51,.92);border:1px solid var(--line);border-radius:30px;box-shadow:var(--shadow)}
        .panel{padding:36px}.intro{text-align:center;max-width:760px;margin:0 auto 24px}.intro h1{font-size:clamp(2.2rem,5vw,3.6rem);line-height:1.05;margin-bottom:12px}.intro p,.status p,.extra p{color:var(--muted);line-height:1.7}
        .searchbox{max-width:1040px;margin:0 auto;background:linear-gradient(135deg,rgba(37,99,235,.16),rgba(255,255,255,.03));border:1px solid var(--line);padding:22px;border-radius:24px}
        .grid4,.results,.classes,.extras-grid{display:grid;gap:18px}.grid4{grid-template-columns:1fr 1fr 1fr auto;align-items:end}.results,.classes,.extras-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        label{display:block;margin-bottom:8px;color:var(--muted);font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em}
        input{width:100%;border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(255,255,255,.92);color:#0f172a;padding:16px 18px;font-size:.96rem;font-weight:600;outline:none}
        button{border:none;cursor:pointer}.search-btn,.book-btn,.class-btn{border-radius:14px;padding:15px 22px;background:var(--accent);color:#fff;font-weight:800}
        .chips{display:flex;justify-content:center;flex-wrap:wrap;gap:12px;margin-top:16px}.chip{padding:10px 14px;border-radius:999px;background:rgba(255,255,255,.04);border:1px solid var(--line);font-size:.85rem;font-weight:700}
        .feedback{margin:18px auto 0;max-width:1040px;padding:14px 18px;border-radius:18px;font-weight:700}.feedback.error{background:rgba(239,68,68,.16);color:#fecaca;border:1px solid rgba(239,68,68,.28)}.feedback.empty{background:rgba(148,163,184,.12);color:var(--muted);border:1px solid var(--line)}
        .slide{max-height:0;opacity:0;overflow:hidden;transform:translateY(-18px);transition:max-height .55s ease,opacity .35s ease,transform .45s ease}.slide.visible{max-height:2600px;opacity:1;transform:translateY(0)}
        .status{padding:26px 6px 18px}.status h2{font-size:1.35rem;margin-bottom:8px}
        .card,.class-card{background:var(--card);color:#0f172a;padding:26px;border-radius:26px;box-shadow:var(--shadow)}.badge,.pill{display:inline-flex;align-items:center;gap:10px;padding:8px 12px;border-radius:999px;font-size:.82rem;font-weight:800}.badge{background:#dbeafe;color:var(--accent)}.pill{background:#eff6ff;color:#1d4ed8}.muted{color:#64748b;font-size:.84rem;font-weight:700}
        .card h3,.class-card h3{font-size:1.42rem;margin:8px 0}.type,.meta,.class-card p{color:#64748b;line-height:1.6;font-weight:600}
        .route{display:flex;justify-content:space-between;align-items:center;position:relative;margin:22px 0}.route:before{content:'';position:absolute;top:50%;left:56px;right:56px;border-top:2px dashed rgba(148,163,184,.55);transform:translateY(-50%)}.stop,.icon{position:relative;z-index:1;background:var(--card);padding:0 12px}.stop strong{display:block;font-size:1.08rem;margin-bottom:4px}.stop span{color:#64748b;font-size:.82rem;font-weight:700}.icon{color:var(--amber);font-size:1.1rem}
        .price,.class-price{font-size:1.45rem;font-weight:800}.class-price{color:var(--accent);margin:14px 0}.banner{background:rgba(16,185,129,.14);border:1px solid rgba(16,185,129,.28);color:#bbf7d0;padding:14px 18px;border-radius:18px;margin:0 6px 18px;font-weight:700}.disabled{opacity:.55;pointer-events:none}
        .extras{margin-top:28px;padding:32px}.extras-head{text-align:center;max-width:760px;margin:0 auto 24px}.extras-head h2{font-size:2rem;margin-bottom:10px}.extra{background:rgba(255,255,255,.04);border:1px solid var(--line);padding:24px;border-radius:24px}.extra .ico{width:52px;height:52px;border-radius:16px;background:rgba(37,99,235,.14);color:#93c5fd;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:14px}.extra h3{margin-bottom:10px}
        @media (max-width:980px){.grid4,.results,.classes,.extras-grid{grid-template-columns:1fr}.panel{padding:26px}}
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <a href="../passenger.php" class="brand"><i class="fa-solid fa-train-subway"></i> RailOps 2PL Booking</a>
        <a href="../passenger.php" class="back">Back to Passenger Dashboard</a>
    </div>

    <section class="panel">
        <div class="intro">
            <h1>Search Trains, Then Unlock Class Selection</h1>
            <p>Start with a real route search, review live trains from the database, and only then continue to class and seat selection.</p>
        </div>
        <form id="searchForm" class="searchbox">
            <div class="grid4">
                <div><label for="source">Source</label><input list="station-list" id="source" name="source" value="<?php echo htmlspecialchars($source); ?>" placeholder="Enter source station" required></div>
                <div><label for="destination">Destination</label><input list="station-list" id="destination" name="destination" value="<?php echo htmlspecialchars($destination); ?>" placeholder="Enter destination station" required></div>
                <div><label for="journey_date">Journey Date</label><input type="date" id="journey_date" name="journey_date" value="<?php echo htmlspecialchars($journeyDate); ?>" min="<?php echo date('Y-m-d'); ?>" required></div>
                <button type="submit" class="search-btn" id="searchBtn">Search Trains</button>
            </div>
            <div class="chips">
                <span class="chip">Live route search</span>
                <span class="chip">3A is the base fare</span>
                <span class="chip">Class selection unlocks after choosing a train</span>
            </div>
        </form>
        <datalist id="station-list">
            <?php foreach ($stations as $station): ?><option value="<?php echo htmlspecialchars($station); ?>"></option><?php endforeach; ?>
        </datalist>
        <div id="feedbackHost"></div>

        <div id="resultsPanel" class="slide<?php echo !empty($initialResults) ? ' visible' : ''; ?>">
            <div class="status" id="resultsStatus"><?php if (!empty($initialResults)): ?><h2>Matching trains</h2><p>Showing <?php echo count($initialResults); ?> train(s) for <?php echo htmlspecialchars($source); ?> to <?php echo htmlspecialchars($destination); ?> on <?php echo htmlspecialchars($journeyDate); ?>.</p><?php endif; ?></div>
            <div class="results" id="resultsGrid">
                <?php foreach ($initialResults as $train): ?>
                    <div class="card">
                        <div class="head"><span class="badge"><i class="fa-solid fa-train-subway"></i><?php echo htmlspecialchars($train['TRAIN_NUMBER']); ?></span><span class="muted">Train ID: <?php echo htmlspecialchars($train['TRAIN_ID']); ?></span></div>
                        <h3><?php echo htmlspecialchars($train['TRAIN_NAME']); ?></h3>
                        <div class="type"><?php echo htmlspecialchars($train['TRAIN_TYPE'] ?: 'Standard Service'); ?></div>
                        <div class="route">
                            <div class="stop"><strong><?php echo htmlspecialchars($source); ?></strong><span><?php echo htmlspecialchars($train['BOARD_TIME'] ?: '--'); ?></span></div>
                            <div class="icon"><i class="fa-solid fa-route"></i></div>
                            <div class="stop" style="text-align:right;"><strong><?php echo htmlspecialchars($destination); ?></strong><span><?php echo htmlspecialchars($train['DROP_TIME'] ?: '--'); ?></span></div>
                        </div>
                        <div class="meta">Full route: <?php echo htmlspecialchars($train['SOURCE_STATION']); ?> to <?php echo htmlspecialchars($train['DESTINATION_STATION']); ?><br>Duration: <?php echo htmlspecialchars($train['DURATION'] ?: '--'); ?></div>
                        <div class="actions" style="margin-top:22px"><div class="price">Rs. <?php echo htmlspecialchars(number_format((float) ($train['PRICE'] ?? 0), 2)); ?></div><button type="button" class="book-btn" onclick="chooseTrain('<?php echo htmlspecialchars($train['TRAIN_ID'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($source, ENT_QUOTES); ?>','<?php echo htmlspecialchars($destination, ENT_QUOTES); ?>','<?php echo htmlspecialchars($journeyDate, ENT_QUOTES); ?>')">Book</button></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="classPanel" class="slide<?php echo $selectedTrain ? ' visible' : ''; ?>">
            <div style="padding-top:24px">
                <div id="selectedTrainBanner" class="banner" style="<?php echo $selectedTrain ? '' : 'display:none;'; ?>"><?php if ($selectedTrain): ?>Class selection unlocked for <?php echo htmlspecialchars($selectedTrain['TRAIN_NAME']); ?> (<?php echo htmlspecialchars($selectedTrain['TRAIN_NUMBER']); ?>).<?php endif; ?></div>
                <div class="status" id="classStatus"><?php if ($selectedTrain): ?><h2><?php echo htmlspecialchars($selectedTrain['TRAIN_NAME']); ?></h2><p><?php echo htmlspecialchars(($source !== '' ? $source : $selectedTrain['SOURCE_STATION']) . ' to ' . ($destination !== '' ? $destination : $selectedTrain['DESTINATION_STATION'])); ?> on <?php echo htmlspecialchars($journeyDate); ?>. Base fare shown in search is the 3A fare.</p><?php else: ?><h2>Class Selection</h2><p>Search and click `Book` on a train to unlock class selection.</p><?php endif; ?></div>
                <div class="classes" id="classGrid">
                    <?php if ($selectedTrain): ?>
                        <?php foreach ($selectedClasses as $class): ?>
                            <div class="class-card">
                                <div class="head"><span class="badge"><?php echo htmlspecialchars($class['icon']); ?></span><span class="pill"><?php echo htmlspecialchars((string) $class['available_seats']); ?> of <?php echo htmlspecialchars((string) $class['total_seats']); ?> seats free</span></div>
                                <h3><?php echo htmlspecialchars($class['label']); ?></h3>
                                <p>Fare aligned from the selected train's base 3A price for consistent totals.</p>
                                <div class="class-price">Rs. <?php echo htmlspecialchars(number_format((float) $class['price'], 2)); ?></div>
                                <div class="actions"><div class="muted">Code: <?php echo htmlspecialchars($class['code']); ?></div><button type="button" class="class-btn" onclick="submitClassSelection('<?php echo htmlspecialchars($class['code'], ENT_QUOTES); ?>')" <?php echo ($class['available_seats'] ?? 0) < 1 ? 'disabled' : ''; ?>>Select Class</button></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="class-card disabled"><div class="head"><span class="badge">3A</span><span class="pill">Search required</span></div><h3>Class selection locked</h3><p>Your class options will appear here after you search for trains and choose one to book.</p><div class="class-price">Rs. --</div><div class="actions"><div class="muted">Select a train first</div><button type="button" class="class-btn" disabled>Select Class</button></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="extras">
        <div class="extras-head"><h2>Useful Booking Notes</h2><p>The lower section now has some helpful context so the page feels active and polished instead of ending abruptly.</p></div>
        <div class="extras-grid">
            <div class="extra"><div class="ico"><i class="fa-solid fa-shield-halved"></i></div><h3>2PL Protection</h3><p>Seats are committed only after the locking flow succeeds, reducing double-booking conflicts.</p></div>
            <div class="extra"><div class="ico"><i class="fa-solid fa-ticket"></i></div><h3>Three-Step Booking</h3><p>Search a route, pick a train, unlock classes, then continue to seat selection.</p></div>
            <div class="extra"><div class="ico"><i class="fa-solid fa-indian-rupee-sign"></i></div><h3>Aligned Pricing</h3><p>Search shows the 3A base fare, and other classes now derive from that same base price.</p></div>
            <div class="extra"><div class="ico"><i class="fa-solid fa-clock-rotate-left"></i></div><h3>History Ready</h3><p>Confirmed bookings can feed the passenger history section so travelers can revisit their trips.</p></div>
        </div>
    </section>

    <form id="classForm" action="seat_selection.php" method="POST" style="display:none;">
        <input type="hidden" name="train_id" id="selected_train_id" value="<?php echo htmlspecialchars($selectedTrain['TRAIN_ID'] ?? ''); ?>">
        <input type="hidden" name="train_number" id="selected_train_number" value="<?php echo htmlspecialchars($selectedTrain['TRAIN_NUMBER'] ?? ''); ?>">
        <input type="hidden" name="train_name" id="selected_train_name" value="<?php echo htmlspecialchars($selectedTrain['TRAIN_NAME'] ?? ''); ?>">
        <input type="hidden" name="source" id="selected_source" value="<?php echo htmlspecialchars($source); ?>">
        <input type="hidden" name="destination" id="selected_destination" value="<?php echo htmlspecialchars($destination); ?>">
        <input type="hidden" name="journey_date" id="selected_journey_date" value="<?php echo htmlspecialchars($journeyDate); ?>">
        <input type="hidden" name="compartment" id="selected_compartment">
    </form>

    <script>
        const searchForm=document.getElementById('searchForm'),searchBtn=document.getElementById('searchBtn'),feedbackHost=document.getElementById('feedbackHost'),resultsPanel=document.getElementById('resultsPanel'),resultsStatus=document.getElementById('resultsStatus'),resultsGrid=document.getElementById('resultsGrid'),classPanel=document.getElementById('classPanel'),classStatus=document.getElementById('classStatus'),classGrid=document.getElementById('classGrid'),selectedTrainBanner=document.getElementById('selectedTrainBanner'),classForm=document.getElementById('classForm');
        function escapeHtml(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}
        function setFeedback(type,msg){feedbackHost.innerHTML=msg?`<div class="feedback ${type}">${escapeHtml(msg)}</div>`:''}
        function renderResults(data){resultsStatus.innerHTML=`<h2>Matching trains</h2><p>Showing ${data.results.length} train(s) for ${escapeHtml(data.source)} to ${escapeHtml(data.destination)} on ${escapeHtml(data.journey_date)}.</p>`;resultsGrid.innerHTML=data.results.map(train=>`<div class="card"><div class="head"><span class="badge"><i class="fa-solid fa-train-subway"></i>${escapeHtml(train.TRAIN_NUMBER)}</span><span class="muted">Train ID: ${escapeHtml(train.TRAIN_ID)}</span></div><h3>${escapeHtml(train.TRAIN_NAME)}</h3><div class="type">${escapeHtml(train.TRAIN_TYPE||'Standard Service')}</div><div class="route"><div class="stop"><strong>${escapeHtml(data.source)}</strong><span>${escapeHtml(train.BOARD_TIME||'--')}</span></div><div class="icon"><i class="fa-solid fa-route"></i></div><div class="stop" style="text-align:right;"><strong>${escapeHtml(data.destination)}</strong><span>${escapeHtml(train.DROP_TIME||'--')}</span></div></div><div class="meta">Full route: ${escapeHtml(train.SOURCE_STATION)} to ${escapeHtml(train.DESTINATION_STATION)}<br>Duration: ${escapeHtml(train.DURATION||'--')}</div><div class="actions" style="margin-top:22px"><div class="price">Rs. ${escapeHtml(Number(train.PRICE||0).toFixed(2))}</div><button type="button" class="book-btn" onclick="chooseTrain('${escapeHtml(train.TRAIN_ID)}','${escapeHtml(data.source)}','${escapeHtml(data.destination)}','${escapeHtml(data.journey_date)}')">Book</button></div></div>`).join('');resultsPanel.classList.add('visible')}
        function renderClasses(data){document.getElementById('selected_train_id').value=data.train.TRAIN_ID;document.getElementById('selected_train_number').value=data.train.TRAIN_NUMBER;document.getElementById('selected_train_name').value=data.train.TRAIN_NAME;document.getElementById('selected_source').value=data.source;document.getElementById('selected_destination').value=data.destination;document.getElementById('selected_journey_date').value=data.journey_date;selectedTrainBanner.style.display='block';selectedTrainBanner.textContent=`Class selection unlocked for ${data.train.TRAIN_NAME} (${data.train.TRAIN_NUMBER}).`;classStatus.innerHTML=`<h2>${escapeHtml(data.train.TRAIN_NAME)}</h2><p>${escapeHtml(data.source)} to ${escapeHtml(data.destination)} on ${escapeHtml(data.journey_date)}. Base fare shown in search is the 3A fare.</p>`;classGrid.innerHTML=data.classes.map(item=>`<div class="class-card"><div class="head"><span class="badge">${escapeHtml(item.icon)}</span><span class="pill">${escapeHtml(item.available_seats)} of ${escapeHtml(item.total_seats)} seats free</span></div><h3>${escapeHtml(item.label)}</h3><p>Fare aligned from the selected train's base 3A price for consistent totals.</p><div class="class-price">Rs. ${escapeHtml(Number(item.price||0).toFixed(2))}</div><div class="actions"><div class="muted">Code: ${escapeHtml(item.code)}</div><button type="button" class="class-btn" onclick="submitClassSelection('${escapeHtml(item.code)}')" ${Number(item.available_seats)<1?'disabled':''}>Select Class</button></div></div>`).join('');classPanel.classList.add('visible');classPanel.scrollIntoView({behavior:'smooth',block:'start'})}
        async function chooseTrain(trainId,source,destination,journeyDate){setFeedback('','');const params=new URLSearchParams({ajax:'class',train_id:trainId,source,destination,journey_date:journeyDate});const response=await fetch(`index.php?${params.toString()}`,{headers:{'X-Requested-With':'XMLHttpRequest'}});const data=await response.json();if(data.error){setFeedback('error',data.error);return}renderClasses(data)}
        function submitClassSelection(code){document.getElementById('selected_compartment').value=code;classForm.submit()}
        searchForm.addEventListener('submit',async function(e){e.preventDefault();const params=new URLSearchParams(new FormData(searchForm));params.set('ajax','search');searchBtn.disabled=true;searchBtn.textContent='Searching...';setFeedback('','');try{const response=await fetch(`index.php?${params.toString()}`,{headers:{'X-Requested-With':'XMLHttpRequest'}});const data=await response.json();if(data.error){resultsPanel.classList.remove('visible');classPanel.classList.remove('visible');setFeedback('error',data.error);return}if(!data.results||data.results.length===0){resultsPanel.classList.remove('visible');classPanel.classList.remove('visible');setFeedback('empty',`No trains found for ${data.source} to ${data.destination}.`);return}renderResults(data);classPanel.classList.remove('visible');selectedTrainBanner.style.display='none';classStatus.innerHTML='<h2>Class Selection</h2><p>Search and click `Book` on a train to unlock class selection.</p>';classGrid.innerHTML='<div class="class-card disabled"><div class="head"><span class="badge">3A</span><span class="pill">Search required</span></div><h3>Class selection locked</h3><p>Your class options will appear here after you search for trains and choose one to book.</p><div class="class-price">Rs. --</div><div class="actions"><div class="muted">Select a train first</div><button type="button" class="class-btn" disabled>Select Class</button></div></div>';resultsPanel.scrollIntoView({behavior:'smooth',block:'start'})}catch(err){setFeedback('error','Something went wrong while searching. Please try again.')}finally{searchBtn.disabled=false;searchBtn.textContent='Search Trains'}})
    </script>
</div>
</body>
</html>
