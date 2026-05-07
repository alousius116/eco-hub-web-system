<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();
require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";
$owner_id = (int)($_SESSION['user_id'] ?? 0);

function redirect_manage($BASE, $type, $msg){
  header("Location: $BASE/borrow/manage_requests.php?$type=" . urlencode($msg));
  exit();
}

function redirect_my_rentals($BASE, $okMsg = "Request approved successfully."){
  header("Location: $BASE/borrow/my_borrow.php?ok=" . urlencode($okMsg));
  exit();
}

/* ===== helper: columns exist ===== */
function get_columns($conn, $table){
  $cols = [];
  $table = mysqli_real_escape_string($conn, $table);
  $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
  if($res){
    while($r = mysqli_fetch_assoc($res)){
      $cols[] = $r['Field'];
    }
  }
  return $cols;
}
function has_col($cols, $name){ return in_array($name, $cols, true); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_manage($BASE, "error", "Invalid request method.");
}

$request_id = (int)($_POST['request_id'] ?? 0);
if ($request_id <= 0) redirect_manage($BASE, "error", "Invalid request.");

$br_cols = get_columns($conn, "borrow_requests");

/* =========================
   Transaction (防 race condition)
========================= */
mysqli_begin_transaction($conn);

try {

  /* ===== fetch request + item + verify owner (LOCK row) ===== */
  $sql = "
    SELECT
      br.request_id,
      br.request_status,
      br.borrow_days,
      br.borrower_id,
      br.item_id,
      " . (has_col($br_cols, "final_price") ? "br.final_price," : "NULL AS final_price,") . "
      " . (has_col($br_cols, "total_price") ? "br.total_price," : "NULL AS total_price,") . "

      i.user_id AS item_owner_id,
      i.availability_status,
      i.rental_price_per_day
    FROM borrow_requests br
    JOIN items i ON i.item_id = br.item_id
    WHERE br.request_id = ?
    LIMIT 1
    FOR UPDATE
  ";

  $stmt = mysqli_prepare($conn, $sql);
  if(!$stmt) throw new Exception("DB error (fetch).");

  mysqli_stmt_bind_param($stmt, "i", $request_id);
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);
  $row = $rs ? mysqli_fetch_assoc($rs) : null;

  if(!$row) throw new Exception("Request not found.");

  if ((int)$row['item_owner_id'] !== $owner_id) {
    throw new Exception("You cannot approve this request.");
  }

  $status = strtolower(trim((string)$row['request_status']));
  if ($status !== 'pending') {
    throw new Exception("Only pending requests can be approved.");
  }

  /* item must be available (prevent double-approve) */
  $itemAvail = strtolower(trim((string)($row['availability_status'] ?? '')));
  if ($itemAvail !== 'available') {
    throw new Exception("Item is not available now.");
  }

  $days = (int)($row['borrow_days'] ?? 0);
  if ($days <= 0) throw new Exception("Borrow days not set.");

  $ppd = $row['rental_price_per_day'];
  if ($ppd === null || $ppd === '' || !is_numeric($ppd)) {
    throw new Exception("Item rental price/day not set.");
  }

  $ppd = (float)$ppd;
  $base_total = $ppd * $days;

  /* ✅ 最终金额：优先用 borrower 已经算好的 final_price（折扣后）
     如果没有 final_price，就 fallback 用 base_total */
  $final_price = null;
  if (has_col($br_cols, "final_price") && $row['final_price'] !== null && $row['final_price'] !== '' && is_numeric($row['final_price'])) {
    $final_price = (float)$row['final_price'];
  }
  $payable = ($final_price !== null) ? $final_price : $base_total;
  $payable = round((float)$payable, 2);

  /* ===== build update borrow_requests ===== */
  $updates = [];
  $params  = [];
  $types   = "";

  $updates[] = "request_status='approved'";

  // ✅ total_cost/total_amount 代表 owner earned / payable（折扣后）
  if (has_col($br_cols, "total_cost")) {
    $updates[] = "total_cost=?";
    $types .= "d"; $params[] = $payable;
  }
  if (has_col($br_cols, "total_amount")) {
    $updates[] = "total_amount=?";
    $types .= "d"; $params[] = $payable;
  }

  // ✅ total_price（原价）若为空才补上 base_total
  if (has_col($br_cols, "total_price")) {
    $updates[] = "total_price=COALESCE(total_price, ?)";
    $types .= "d"; $params[] = round((float)$base_total, 2);
  }

  // ✅ final_price 若为空才补 base_total（你原本逻辑OK）
  if (has_col($br_cols, "final_price")) {
    $updates[] = "final_price=COALESCE(final_price, ?)";
    $types .= "d"; $params[] = round((float)$base_total, 2);
  }

  // borrow period (optional)
  if (has_col($br_cols, "borrow_start_date")) {
    $updates[] = "borrow_start_date=COALESCE(borrow_start_date, CURDATE())";
  }
  if (has_col($br_cols, "borrow_end_date")) {
    // ✅ 借 1 day -> end date = start date（所以 interval 用 days-1）
    $intervalDays = max(0, $days - 1);

    if (has_col($br_cols, "borrow_start_date")) {
      $updates[] = "borrow_end_date=COALESCE(borrow_end_date, DATE_ADD(COALESCE(borrow_start_date, CURDATE()), INTERVAL ? DAY))";
      $types .= "i"; $params[] = $intervalDays;
    } else {
      $updates[] = "borrow_end_date=COALESCE(borrow_end_date, DATE_ADD(CURDATE(), INTERVAL ? DAY))";
      $types .= "i"; $params[] = $intervalDays;
    }
  }

  if (empty($updates)) throw new Exception("No updatable columns found.");

  $sqlUp = "UPDATE borrow_requests SET " . implode(", ", $updates) . " WHERE request_id=? AND request_status='pending'";
  $types2  = $types . "i";
  $params2 = array_merge($params, [$request_id]);

  $stUp = mysqli_prepare($conn, $sqlUp);
  if(!$stUp) throw new Exception("DB error (approve).");

  mysqli_stmt_bind_param($stUp, $types2, ...$params2);
  mysqli_stmt_execute($stUp);

  if (mysqli_stmt_affected_rows($stUp) <= 0) {
    throw new Exception("Approve failed. Please refresh and try again.");
  }

  /* ===== update item availability => borrowed ===== */
  $stItem = mysqli_prepare($conn, "
    UPDATE items
    SET availability_status='borrowed'
    WHERE item_id=? AND user_id=? AND availability_status='available'
  ");
  if(!$stItem) throw new Exception("DB error (item).");

  mysqli_stmt_bind_param($stItem, "ii", $row['item_id'], $owner_id);
  mysqli_stmt_execute($stItem);

  if (mysqli_stmt_affected_rows($stItem) <= 0) {
    throw new Exception("Item status update failed (already borrowed?).");
  }

  mysqli_commit($conn);

  // ✅ 成功：你要去哪一页？
  // 选 A：回 Manage Requests（推荐）
  redirect_manage($BASE, "ok", "Approved! Earned RM " . number_format($payable, 2));

  // 选 B：去 My Rentals（如果你坚持这样，用这一行替换上面那行）
  // redirect_my_rentals($BASE, "Approved! Earned RM " . number_format($payable, 2));

} catch (Exception $e) {
  mysqli_rollback($conn);
  redirect_manage($BASE, "error", $e->getMessage());
}





