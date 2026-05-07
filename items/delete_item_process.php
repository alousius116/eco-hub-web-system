<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();

require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";
$user_id = (int)($_SESSION['user_id'] ?? 0);

/* =========================================================
   Request Validation
========================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $BASE/items/my_items.php?error=" . urlencode("Invalid request method."));
    exit();
}

$item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);

if (!$item_id || $item_id <= 0) {
    header("Location: $BASE/items/my_items.php?error=" . urlencode("Missing or invalid item."));
    exit();
}

/* =========================================================
   Ownership Verification
========================================================= */
$stmt = mysqli_prepare($conn, "SELECT user_id FROM items WHERE item_id=? LIMIT 1");

if (!$stmt) {
    error_log("Prepare failed: " . mysqli_error($conn));
    header("Location: $BASE/items/my_items.php?error=" . urlencode("Database error."));
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $item_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$res || mysqli_num_rows($res) === 0) {
    mysqli_stmt_close($stmt);
    header("Location: $BASE/items/my_items.php?error=" . urlencode("Item not found."));
    exit();
}

$row = mysqli_fetch_assoc($res);
$owner_id = (int)$row['user_id'];
mysqli_stmt_close($stmt);

if ($owner_id !== $user_id) {
    header("Location: $BASE/items/my_items.php?error=" . urlencode("Permission denied."));
    exit();
}

/* =========================================================
   Transactional Delete Process
   - Ensures database consistency
   - Prevents partial deletion (ACID principle)
========================================================= */
mysqli_autocommit($conn, false);

try {

    /* -------- Delete wishlist dependencies -------- */
    $d1 = mysqli_prepare($conn, "DELETE FROM wishlist WHERE item_id=?");
    if (!$d1) {
        throw new Exception("Prepare wishlist delete failed.");
    }

    mysqli_stmt_bind_param($d1, "i", $item_id);
    mysqli_stmt_execute($d1);
    mysqli_stmt_close($d1);

    /* -------- Delete borrow request dependencies -------- */
    $d2 = mysqli_prepare($conn, "DELETE FROM borrow_requests WHERE item_id=?");
    if (!$d2) {
        throw new Exception("Prepare borrow_requests delete failed.");
    }

    mysqli_stmt_bind_param($d2, "i", $item_id);
    mysqli_stmt_execute($d2);
    mysqli_stmt_close($d2);

    /* -------- Delete main item -------- */
    $d3 = mysqli_prepare($conn, "DELETE FROM items WHERE item_id=? AND user_id=?");
    if (!$d3) {
        throw new Exception("Prepare item delete failed.");
    }

    mysqli_stmt_bind_param($d3, "ii", $item_id, $user_id);
    mysqli_stmt_execute($d3);

    if (mysqli_stmt_affected_rows($d3) <= 0) {
        mysqli_stmt_close($d3);
        throw new Exception("Delete operation failed or unauthorized.");
    }

    mysqli_stmt_close($d3);

    /* -------- Commit transaction -------- */
    mysqli_commit($conn);

    header("Location: $BASE/items/my_items.php?ok=" . urlencode("Item deleted successfully."));
    exit();

} catch (Exception $e) {

    /* -------- Rollback on failure -------- */
    mysqli_rollback($conn);

    // Log internal error for debugging (not shown to user)
    error_log("Delete Item Error: " . $e->getMessage());

    header("Location: $BASE/items/my_items.php?error=" . urlencode("Unable to delete item. Please try again."));
    exit();
}


