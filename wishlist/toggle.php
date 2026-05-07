<?php
// wishlist/toggle.php

// ✅ 强制不要把 PHP warning/notice 输出成 HTML
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// ✅ 把任何意外输出先缓存起来（避免污染 JSON）
ob_start();

require_once __DIR__ . "/../config/db_connect.php";

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=UTF-8');

function out($arr, $code = 200){
  // ✅ 清掉之前所有输出（BOM/空格/warning/echo）
  if (ob_get_length()) { ob_clean(); }
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit();
}

if (empty($_SESSION['user_id'])) {
  out(["ok"=>false, "error"=>"Please login first."], 401);
}

$user_id = (int)$_SESSION['user_id'];
$item_id = (int)($_POST['item_id'] ?? 0);

if ($item_id <= 0) {
  out(["ok"=>false, "error"=>"Invalid item id."], 400);
}

/* ensure item exists */
$stItem = mysqli_prepare($conn, "SELECT 1 FROM items WHERE item_id=? LIMIT 1");
if(!$stItem) out(["ok"=>false,"error"=>"DB error (item)."], 500);
mysqli_stmt_bind_param($stItem, "i", $item_id);
mysqli_stmt_execute($stItem);
$rsItem = mysqli_stmt_get_result($stItem);

if (!$rsItem || !mysqli_fetch_row($rsItem)) {
  out(["ok"=>false, "error"=>"Item not found."], 404);
}

mysqli_begin_transaction($conn);

try {
  // check existing
  $stChk = mysqli_prepare($conn, "SELECT 1 FROM wishlist WHERE user_id=? AND item_id=? LIMIT 1");
  if(!$stChk) throw new Exception("DB error (check).");
  mysqli_stmt_bind_param($stChk, "ii", $user_id, $item_id);
  mysqli_stmt_execute($stChk);
  $rsChk = mysqli_stmt_get_result($stChk);
  $exists = ($rsChk && mysqli_fetch_row($rsChk)) ? true : false;

  $liked = false;

  if ($exists) {
    $stDel = mysqli_prepare($conn, "DELETE FROM wishlist WHERE user_id=? AND item_id=? LIMIT 1");
    if(!$stDel) throw new Exception("DB error (delete).");
    mysqli_stmt_bind_param($stDel, "ii", $user_id, $item_id);
    mysqli_stmt_execute($stDel);
    $liked = false;
  } else {
    $stIns = mysqli_prepare($conn, "INSERT INTO wishlist (user_id, item_id) VALUES (?, ?)");
    if(!$stIns) throw new Exception("DB error (insert).");
    mysqli_stmt_bind_param($stIns, "ii", $user_id, $item_id);
    mysqli_stmt_execute($stIns);
    $liked = true;
  }

  // count
  $stCnt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM wishlist WHERE user_id=?");
  if(!$stCnt) throw new Exception("DB error (count).");
  mysqli_stmt_bind_param($stCnt, "i", $user_id);
  mysqli_stmt_execute($stCnt);
  $rsCnt = mysqli_stmt_get_result($stCnt);
  $count = 0;
  if($rsCnt && ($row = mysqli_fetch_assoc($rsCnt))){
    $count = (int)$row['c'];
  }

  mysqli_commit($conn);

  out(["ok"=>true, "liked"=>$liked, "count"=>$count], 200);

} catch (Exception $e) {
  mysqli_rollback($conn);
  out(["ok"=>false, "error"=>$e->getMessage()], 500);
}





