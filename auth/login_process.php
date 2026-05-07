<?php
require_once __DIR__ . "/../config/db_connect.php";
session_start();

$BASE = "/RWDD2408/eco_hub";

if (!isset($_POST['login'])) {
  header("Location: $BASE/auth/login.php");
  exit();
}

$tp = strtoupper(trim($_POST['tp_number'] ?? ''));
$password = $_POST['password'] ?? '';

if ($tp === '' || $password === '') {
  header("Location: $BASE/auth/login.php?error=" . urlencode("Please enter your TP Number and password."));
  exit();
}

if (!preg_match('/^TP\d{6}$/', $tp)) {
  header("Location: $BASE/auth/login.php?error=" . urlencode("Invalid TP Number format. Example: TP071222"));
  exit();
}

$sql = "SELECT * FROM users WHERE tp_number = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $tp);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);

if (!$user) {
  header("Location: $BASE/auth/login.php?error=" . urlencode("TP Number not found."));
  exit();
}

if (!password_verify($password, $user['password'])) {
  header("Location: $BASE/auth/login.php?error=" . urlencode("Incorrect password."));
  exit();
}

/* login success */
$_SESSION['user_id']   = (int)$user['user_id'];
$_SESSION['tp_number'] = $user['tp_number'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'] ?? 'user';

$role = strtolower(trim($_SESSION['role']));

if ($role === 'admin') {
  header("Location: $BASE/admin/dashboard.php?ok=" . urlencode("Welcome, Admin."));
  exit();
}

header("Location: $BASE/index.php?ok=" . urlencode("Welcome back!"));
exit();


