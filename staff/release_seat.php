<?php
require_once "auth_guard.php";
include "../db.php";
require_once __DIR__ . "/../TwoPL/train_data.php";

$items_raw = $_GET['items'] ?? '';
$train_id = $_GET['train_id'] ?? '';
$compartment = $_GET['compartment'] ?? '';

if ($items_raw === '' || $train_id === '' || $compartment === '') {
    echo "Error: Missing required parameters.";
    exit;
}

$items = explode(',', $items_raw);
$columnCheck = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM USER_TAB_COLUMNS WHERE TABLE_NAME = 'BOOKINGS' AND COLUMN_NAME = 'COMPARTMENT'");
oci_execute($columnCheck);
$columnRow = oci_fetch_assoc($columnCheck);
$bookingsHasCompartment = ((int) ($columnRow['CNT'] ?? 0)) > 0;

$transactionQuery = oci_parse($conn, "
    SELECT DISTINCT transaction_id
    FROM PASSENGER_BOOKING_HISTORY
    WHERE train_id = :train_id
      AND compartment = :compartment
      AND booking_status = 'CONFIRMED'
");
oci_bind_by_name($transactionQuery, ":train_id", $train_id);
oci_bind_by_name($transactionQuery, ":compartment", $compartment);
@oci_execute($transactionQuery);
$relatedTransactions = [];
while ($row = oci_fetch_assoc($transactionQuery)) {
    $relatedTransactions[] = $row['TRANSACTION_ID'];
}

foreach ($items as $data_item) {
    $data_item = trim($data_item);
    $parts = explode("_", $data_item);
    if (count($parts) < 3) {
        continue;
    }

    $seat_no = $parts[2];

    $sql1 = "UPDATE TRAIN_SEATS
             SET STATUS = 'AVAILABLE'
             WHERE TRAIN_ID = :tid AND SEAT_NO = :seat AND COMPARTMENT = :compartment";
    $stid1 = oci_parse($conn, $sql1);
    oci_bind_by_name($stid1, ":tid", $train_id);
    oci_bind_by_name($stid1, ":seat", $seat_no);
    oci_bind_by_name($stid1, ":compartment", $compartment);
    oci_execute($stid1, OCI_NO_AUTO_COMMIT);

    if ($bookingsHasCompartment) {
        $sql2 = "DELETE FROM BOOKINGS
                 WHERE TRAIN_ID = :tid AND SEAT_NO = :seat AND COMPARTMENT = :compartment";
    } else {
        $sql2 = "DELETE FROM BOOKINGS
                 WHERE TRAIN_ID = :tid AND SEAT_NO = :seat";
    }
    $stid2 = oci_parse($conn, $sql2);
    oci_bind_by_name($stid2, ":tid", $train_id);
    oci_bind_by_name($stid2, ":seat", $seat_no);
    if ($bookingsHasCompartment) {
        oci_bind_by_name($stid2, ":compartment", $compartment);
    }
    oci_execute($stid2, OCI_NO_AUTO_COMMIT);

    $sql3 = "DELETE FROM LOCK_TABLE WHERE DATA_ITEM = :item";
    $stid3 = oci_parse($conn, $sql3);
    oci_bind_by_name($stid3, ":item", $data_item);
    oci_execute($stid3, OCI_NO_AUTO_COMMIT);

    $sql4 = "
        UPDATE PASSENGER_BOOKING_HISTORY
        SET booking_status = 'CANCELLED'
        WHERE train_id = :tid
          AND compartment = :compartment
          AND booking_status = 'CONFIRMED'
          AND (
                seats = :seat_exact
                OR seats LIKE :seat_start
                OR seats LIKE :seat_middle
                OR seats LIKE :seat_end
              )
    ";
    $seatExact = $seat_no;
    $seatStart = $seat_no . ',%';
    $seatMiddle = '%,' . $seat_no . ',%';
    $seatEnd = '%,' . $seat_no;
    $stid4 = oci_parse($conn, $sql4);
    oci_bind_by_name($stid4, ":tid", $train_id);
    oci_bind_by_name($stid4, ":compartment", $compartment);
    oci_bind_by_name($stid4, ":seat_exact", $seatExact);
    oci_bind_by_name($stid4, ":seat_start", $seatStart);
    oci_bind_by_name($stid4, ":seat_middle", $seatMiddle);
    oci_bind_by_name($stid4, ":seat_end", $seatEnd);
    oci_execute($stid4, OCI_NO_AUTO_COMMIT);
}

foreach ($relatedTransactions as $transactionId) {
    railwayResolveCancellationRequest($conn, $transactionId);
}

if (oci_commit($conn)) {
    foreach ($relatedTransactions as $transactionId) {
        railwayAppendMiqsmEvent('booking_cancelled', [
            'transaction_id' => $transactionId,
            'train_id' => $train_id,
            'compartment' => $compartment,
            'seat_items' => $items,
        ]);
    }
    header("Location: seat_view.php?train_id=" . urlencode($train_id) . "&compartment=" . urlencode($compartment) . "&msg=released");
    exit;
}

$e = oci_error($conn);
echo "Transaction Error: " . $e['message'];
?>
