<?php 
include_once "lock_manager.php"; 
function acquireLock($conn, $request) { 
    if (isLockAvailable($conn, $request['DATA_ITEM'], $request['LOCK_TYPE'])) { 
        grantLock($conn, 
                  $request['DATA_ITEM'], 
                  $request['TID'], 
                  $request['LOCK_TYPE']); 
        return true; 
    } 
    return false; 
} 
?> 
