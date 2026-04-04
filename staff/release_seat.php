<?php
include "../db.php";

// The UI now sends 'items' (plural) as a comma-separated string
$items_raw = $_GET['items'] ?? '';
$train_id = $_GET['train_id'] ?? '';
$compartment = $_GET['compartment'] ?? '';

if (!empty($items_raw) && !empty($train_id)) {
    // 1. Convert the string "SEAT_101_1,SEAT_101_2" into an array
    $items = explode(',', $items_raw);

    foreach ($items as $data_item) {
        // 2. Extract seat_no (e.g., from "SEAT_101_S1" get "S1")
        $parts = explode("_", $data_item);
        if (count($parts) < 3) continue; // Safety check
        $seat_no = $parts[2]; 

        // 3. Make seat AVAILABLE in the master seat table
        $sql1 = "UPDATE TRAIN_SEATS 
                 SET STATUS = 'AVAILABLE'
                 WHERE TRAIN_ID = :tid AND SEAT_NO = :seat";

        $stid1 = oci_parse($conn, $sql1);
        oci_bind_by_name($stid1, ":tid", $train_id);
        oci_bind_by_name($stid1, ":seat", $seat_no);
        oci_execute($stid1, OCI_DEFAULT); // Do not auto-commit yet

        // 4. Remove the record from BOOKINGS table
        $sql2 = "DELETE FROM BOOKINGS 
                 WHERE TRAIN_ID = :tid AND SEAT_NO = :seat";

        $stid2 = oci_parse($conn, $sql2);
        oci_bind_by_name($stid2, ":tid", $train_id);
        oci_bind_by_name($stid2, ":seat", $seat_no);
        oci_execute($stid2, OCI_DEFAULT);

        // 5. Remove any leftover locks from the LOCK_TABLE (Concurrency Control)
        $sql3 = "DELETE FROM LOCK_TABLE WHERE DATA_ITEM = :item";
        
        $stid3 = oci_parse($conn, $sql3);
        oci_bind_by_name($stid3, ":item", $data_item);
        oci_execute($stid3, OCI_DEFAULT);
    }

    // 6. CRITICAL: Commit all changes at once for database integrity
    $committed = oci_commit($conn);

    if ($committed) {
        // Redirect back to your layout page (ensure the filename matches your file)
        header("Location: seat_view.php?train_id=$train_id&compartment=$compartment&msg=released");
        exit;
    } else {
        $e = oci_error($conn);
        echo "Transaction Error: " . $e['message'];
    }
} else {
    echo "Error: Missing required parameters.";
}
?>