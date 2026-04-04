<?php 
function allocateSeat($conn, $train_id, $seat_no) { 
    $sql = "UPDATE TRAIN_SEATS SET STATUS='BOOKED' WHERE TRAIN_ID=:train AND SEAT_NO=:seat"; 
    $stmt = oci_parse($conn, $sql); 
    oci_bind_by_name($stmt, ":train", $train_id); 
    oci_bind_by_name($stmt, ":seat", $seat_no); 
    oci_execute($stmt); 
    oci_commit($conn); 
} 
?> 
