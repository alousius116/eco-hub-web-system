<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();

require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";

$request_id  = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$borrower_id = (int)($_SESSION['user_id'] ?? 0);

if (!$request_id || $borrower_id <= 0) {
    header("Location: $BASE/borrow/my_borrow.php");
    exit();
}

$br_cols = [];
$rs = mysqli_query($conn, "SHOW COLUMNS FROM `borrow_requests`");
if ($rs) {
    while ($r = mysqli_fetch_assoc($rs)) {
        $br_cols[] = $r['Field'];
    }
}
$has = fn($c) => in_array($c, $br_cols, true);

mysqli_autocommit($conn, false);

try {
    $sql = "SELECT item_id
            FROM borrow_requests
            WHERE request_id=? 
              AND borrower_id=? 
              AND LOWER(request_status)='approved'
            LIMIT 1 FOR UPDATE";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Prepare failed (select lock).");
    }

    mysqli_stmt_bind_param($stmt, "ii", $request_id, $borrower_id);
    mysqli_stmt_execute($stmt);

    $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$data) {
        throw new Exception("Invalid request or not approved.");
    }

    $item_id = (int)$data['item_id'];


    $updates = [];
    $types   = "";
    $params  = [];

    $updates[] = "request_status=?";
    $types .= "s";
    $params[] = "completed";

    if ($has("borrow_end_date")) $updates[] = "borrow_end_date=CURDATE()";
    if ($has("completed_at"))    $updates[] = "completed_at=NOW()";
    if ($has("updated_at"))      $updates[] = "updated_at=NOW()";

    $sql1 = "UPDATE borrow_requests
             SET " . implode(", ", $updates) . "
             WHERE request_id=? AND borrower_id=? AND LOWER(request_status)='approved'";

    $params2 = array_merge($params, [$request_id, $borrower_id]);
    $types2  = $types . "ii";

    $st1 = mysqli_prepare($conn, $sql1);
    if (!$st1) {
        throw new Exception("Prepare failed (update request).");
    }

    mysqli_stmt_bind_param($st1, $types2, ...$params2);
    mysqli_stmt_execute($st1);
    mysqli_stmt_close($st1);

    $sql2 = "UPDATE items
             SET availability_status='available'
             WHERE item_id=?";

    $st2 = mysqli_prepare($conn, $sql2);
    if (!$st2) {
        throw new Exception("Prepare failed (update item).");
    }

    mysqli_stmt_bind_param($st2, "i", $item_id);
    mysqli_stmt_execute($st2);
    mysqli_stmt_close($st2);
    mysqli_commit($conn);

    header("Location: $BASE/profile.php?ok=" . urlencode("Item returned successfully."));
    exit();

} catch (Exception $e) {

    mysqli_rollback($conn);

    /* internal logging – Top Distinction practice */
    error_log("Return Item Error: " . $e->getMessage());

    header("Location: $BASE/borrow/my_borrow.php?error=" . urlencode("Return failed. Please try again."));
    exit();
}


