<?php

function isLockAvailable($conn, $data_item, $lock_type, $tid = null) {

    $sql = "SELECT LOCK_TYPE, TID FROM LOCK_TABLE WHERE DATA_ITEM = :data_item";
    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":data_item", $data_item);
    oci_execute($stmt);

    while ($row = oci_fetch_assoc($stmt)) {
        if ($tid !== null && (string) $row['TID'] === (string) $tid) {
            continue;
        }

        if ($row['LOCK_TYPE'] === 'EXCLUSIVE' || $lock_type === 'EXCLUSIVE') {
            return false;
        }
    }

    return true;
}

function grantLock($conn, $data_item, $tid, $lock_type) {

    $time = time();
    $existingSql = "SELECT COUNT(*) AS CNT FROM LOCK_TABLE WHERE DATA_ITEM = :data_item AND TID = :tid";
    $existingStmt = oci_parse($conn, $existingSql);
    oci_bind_by_name($existingStmt, ":data_item", $data_item);
    oci_bind_by_name($existingStmt, ":tid", $tid);
    oci_execute($existingStmt);
    $existing = oci_fetch_assoc($existingStmt);

    if ((int) ($existing['CNT'] ?? 0) > 0) {
        $updateSql = "UPDATE LOCK_TABLE
                      SET LOCK_TYPE = :lock_type,
                          REQUEST_TIME = :time
                      WHERE DATA_ITEM = :data_item
                        AND TID = :tid";
        $updateStmt = oci_parse($conn, $updateSql);
        oci_bind_by_name($updateStmt, ":lock_type", $lock_type);
        oci_bind_by_name($updateStmt, ":time", $time);
        oci_bind_by_name($updateStmt, ":data_item", $data_item);
        oci_bind_by_name($updateStmt, ":tid", $tid);
        oci_execute($updateStmt, OCI_NO_AUTO_COMMIT);
        return;
    }

    $sql = "INSERT INTO LOCK_TABLE
            (DATA_ITEM, TID, LOCK_TYPE, REQUEST_TIME)
            VALUES (:data_item, :tid, :lock_type, :time)";

    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":data_item", $data_item);
    oci_bind_by_name($stmt, ":tid", $tid);
    oci_bind_by_name($stmt, ":lock_type", $lock_type);
    oci_bind_by_name($stmt, ":time", $time);

    oci_execute($stmt, OCI_NO_AUTO_COMMIT);
}

function releaseLocks($conn, $tid) {

    $sql = "DELETE FROM LOCK_TABLE WHERE TID = :tid";
    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":tid", $tid);
    oci_execute($stmt, OCI_NO_AUTO_COMMIT);
}

function releaseLockItems($conn, $tid, array $items) {

    if (empty($items)) {
        return;
    }

    foreach ($items as $item) {
        $sql = "DELETE FROM LOCK_TABLE WHERE TID = :tid AND DATA_ITEM = :data_item";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":tid", $tid);
        oci_bind_by_name($stmt, ":data_item", $item);
        oci_execute($stmt, OCI_NO_AUTO_COMMIT);
    }
}

function updateLockHeartbeat($conn, $tid, array $items = []) {

    $time = time();

    if (empty($items)) {
        $sql = "UPDATE LOCK_TABLE SET REQUEST_TIME = :time WHERE TID = :tid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":time", $time);
        oci_bind_by_name($stmt, ":tid", $tid);
        oci_execute($stmt, OCI_NO_AUTO_COMMIT);
        return;
    }

    foreach ($items as $item) {
        $sql = "UPDATE LOCK_TABLE
                SET REQUEST_TIME = :time
                WHERE TID = :tid
                  AND DATA_ITEM = :data_item";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":time", $time);
        oci_bind_by_name($stmt, ":tid", $tid);
        oci_bind_by_name($stmt, ":data_item", $item);
        oci_execute($stmt, OCI_NO_AUTO_COMMIT);
    }
}

function clearExpiredLocks($conn, $timeout_seconds = 10) {

    $current_time = time();

    $sql = "SELECT DATA_ITEM, TID, REQUEST_TIME FROM LOCK_TABLE";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);

    while ($row = oci_fetch_assoc($stmt)) {

        $lock_time = (int) ($row['REQUEST_TIME'] ?? 0);

        if (($current_time - $lock_time) > $timeout_seconds) {

            $delete_sql = "DELETE FROM LOCK_TABLE WHERE TID = :tid";
            $del_stmt = oci_parse($conn, $delete_sql);

            oci_bind_by_name($del_stmt, ":tid", $row['TID']);
            oci_execute($del_stmt, OCI_NO_AUTO_COMMIT);

            oci_commit($conn);
        }
    }
}

?>
