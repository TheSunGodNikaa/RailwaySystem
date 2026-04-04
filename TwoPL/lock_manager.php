<?php

function isLockAvailable($conn, $data_item, $lock_type) {

    $sql = "SELECT LOCK_TYPE FROM LOCK_TABLE WHERE DATA_ITEM = :data_item";
    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":data_item", $data_item);
    oci_execute($stmt);

    while ($row = oci_fetch_assoc($stmt)) {

        // If any exclusive lock exists OR
        // If requesting exclusive while any lock exists → reject
        if ($row['LOCK_TYPE'] == 'EXCLUSIVE' || $lock_type == 'EXCLUSIVE') {
            return false;
        }
    }

    return true;
}


function grantLock($conn, $data_item, $tid, $lock_type) {

    $time = time();

    $sql = "INSERT INTO LOCK_TABLE 
            (DATA_ITEM, TID, LOCK_TYPE, REQUEST_TIME)
            VALUES (:data_item, :tid, :lock_type, :time)";

    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":data_item", $data_item);
    oci_bind_by_name($stmt, ":tid", $tid);
    oci_bind_by_name($stmt, ":lock_type", $lock_type);
    oci_bind_by_name($stmt, ":time", $time);

    oci_execute($stmt);
}


function releaseLocks($conn, $tid) {

    $sql = "DELETE FROM LOCK_TABLE WHERE TID = :tid";
    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":tid", $tid);
    oci_execute($stmt);
}
    
function clearExpiredLocks($conn, $timeout_seconds = 10) {

    $current_time = time();

    $sql = "SELECT DATA_ITEM, TID, REQUEST_TIME FROM LOCK_TABLE";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);

    while ($row = oci_fetch_assoc($stmt)) {

        $lock_time = $row['REQUEST_TIME'];

        if (($current_time - $lock_time) > $timeout_seconds) {

            $delete_sql = "DELETE FROM LOCK_TABLE WHERE TID = :tid";
            $del_stmt = oci_parse($conn, $delete_sql);

            oci_bind_by_name($del_stmt, ":tid", $row['TID']);
            oci_execute($del_stmt);

            oci_commit($conn);  // 🔥 THIS WAS MISSING
        }
    }
}

?>
