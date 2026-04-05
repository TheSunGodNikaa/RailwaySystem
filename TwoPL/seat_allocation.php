<?php
function allocateSeat($conn, $train_id, $seat_no, $compartment = null) {
    if ($compartment !== null && $compartment !== '') {
        $sql = "UPDATE TRAIN_SEATS
                SET STATUS='BOOKED'
                WHERE TRAIN_ID=:train AND SEAT_NO=:seat AND COMPARTMENT=:compartment";
    } else {
        $sql = "UPDATE TRAIN_SEATS
                SET STATUS='BOOKED'
                WHERE TRAIN_ID=:train AND SEAT_NO=:seat";
    }

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":train", $train_id);
    oci_bind_by_name($stmt, ":seat", $seat_no);
    if ($compartment !== null && $compartment !== '') {
        oci_bind_by_name($stmt, ":compartment", $compartment);
    }
    oci_execute($stmt, OCI_NO_AUTO_COMMIT);
}
?>
