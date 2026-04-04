<?php
function generateLockRequest($tid, $data_item, $lock_type) {

    return array(
        "TID" => $tid,
        "DATA_ITEM" => $data_item,
        "LOCK_TYPE" => $lock_type,
        "TIME" => time()
    );

}
?>