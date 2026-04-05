<?php
function insertBooking($conn, $train_id, $seat_no, $compartment = null) {

    $time = time();
    $hasCompartmentColumn = false;

    if ($compartment !== null && $compartment !== '') {
        $columnCheck = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM USER_TAB_COLUMNS WHERE TABLE_NAME = 'BOOKINGS' AND COLUMN_NAME = 'COMPARTMENT'");
        oci_execute($columnCheck);
        $columnRow = oci_fetch_assoc($columnCheck);
        $hasCompartmentColumn = ((int) ($columnRow['CNT'] ?? 0)) > 0;
    }

    if ($compartment !== null && $compartment !== '' && $hasCompartmentColumn) {
        $sql = "INSERT INTO BOOKINGS (TRAIN_ID, SEAT_NO, BOOKING_TIME, COMPARTMENT)
                VALUES (:train, :seat, :time, :compartment)";
    } else {
        $sql = "INSERT INTO BOOKINGS (TRAIN_ID, SEAT_NO, BOOKING_TIME)
                VALUES (:train, :seat, :time)";
    }

    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":train", $train_id);
    oci_bind_by_name($stmt, ":seat", $seat_no);
    oci_bind_by_name($stmt, ":time", $time);
    if ($compartment !== null && $compartment !== '' && $hasCompartmentColumn) {
        oci_bind_by_name($stmt, ":compartment", $compartment);
    }

    oci_execute($stmt, OCI_NO_AUTO_COMMIT);
}
?>
