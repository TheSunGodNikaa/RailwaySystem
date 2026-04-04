<?php
function insertBooking($conn, $train_id, $seat_no) {

    $time = time();

    $sql = "INSERT INTO BOOKINGS (TRAIN_ID, SEAT_NO, BOOKING_TIME)
            VALUES (:train, :seat, :time)";

    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":train", $train_id);
    oci_bind_by_name($stmt, ":seat", $seat_no);
    oci_bind_by_name($stmt, ":time", $time);

    oci_execute($stmt);
    oci_commit($conn);
}
?>