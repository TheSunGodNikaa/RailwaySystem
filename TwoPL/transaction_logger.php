<?php 
function logTransaction($conn, $tid, $status) { 
    $time = time(); 
    $sql = "INSERT INTO TRANSACTION_LOG (TID, STATUS, LOG_TIME) VALUES (:tid, :status, :time)"; 
    $stmt = oci_parse($conn, $sql); 
    oci_bind_by_name($stmt, ":tid", $tid); 
    oci_bind_by_name($stmt, ":status", $status); 
    oci_bind_by_name($stmt, ":time", $time); 
    oci_execute($stmt); 
    oci_commit($conn); 
} 
?> 
