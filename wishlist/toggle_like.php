<?php
require_once __DIR__ . "/../config/db_connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

$BASE = "/RWDD2408/eco_hub";

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Please login first.']);
  exit();
}

$user_id = (int)$_SESSION['user_id'];
$item_id = (int)($_POST['item_id'] ?? 0);

if ($item_id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Invalid item.']);
  exit();
}

// ✅ check existing
$chk = mysqli_prepare($conn, "SELECT 1 FROM wishlist WHERE user_id=? AND item_id=? LIMIT 1");
mysqli_stmt_bind_param($chk, "ii", $user_id, $item_id);
mysqli_stmt_execute($chk);
$r = mysqli_stmt_get_result($chk);

$liked = false;

if ($r && mysqli_num_rows($r) > 0) {
  // ✅ unlike
  $del = mysqli_prepare($conn, "DELETE FROM wishlist WHERE user_id=? AND item_id=? LIMIT 1");
  mysqli_stmt_bind_param($del, "ii", $user_id, $item_id);
  mysqli_stmt_execute($del);
  $liked = false;
} else {
  // ✅ like
  $ins = mysqli_prepare($conn, "INSERT INTO wishlist (user_id, item_id, created_at) VALUES (?, ?, NOW())");
  mysqli_stmt_bind_param($ins, "ii", $user_id, $item_id);
  mysqli_stmt_execute($ins);
  $liked = true;
}

// ✅ badge count (optional)
$cnt = 0;
$cq = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM wishlist WHERE user_id=?");
mysqli_stmt_bind_param($cq, "i", $user_id);
mysqli_stmt_execute($cq);
$cr = mysqli_stmt_get_result($cq);
if ($cr) {
  $row = mysqli_fetch_assoc($cr);
  $cnt = (int)($row['c'] ?? 0);
}

echo json_encode([
  'ok' => true,
  'liked' => $liked,
  'count' => $cnt
]);
