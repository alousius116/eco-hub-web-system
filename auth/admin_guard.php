<?php
require_once __DIR__ . "/../config/db_connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$BASE = $BASE ?? "/RWDD2408/eco_hub";

// must login
if (empty($_SESSION['user_id'])) {
  header("Location: $BASE/auth/login.php");
  exit();
}

$user_id = (int)$_SESSION['user_id'];

// check admin role
$stmt = mysqli_prepare($conn, "SELECT role FROM users WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row || strtolower(trim($row['role'])) !== 'admin') {
  header("Location: $BASE/index.php");
  exit();
}


