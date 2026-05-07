<?php
session_start();
require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";

function go($url){
  header("Location: " . $url);
  exit();
}

$password = (string)($_POST['password'] ?? '');
$confirm  = (string)($_POST['confirm_password'] ?? '');

if ($password === '' || $confirm === '') {
  go("$BASE/auth/reset_password.php?error=" . urlencode("Please fill in all fields."));
}

if ($password !== $confirm) {
  go("$BASE/auth/reset_password.php?error=" . urlencode("Passwords do not match."));
}

if (strlen($password) < 8) {
  go("$BASE/auth/reset_password.php?error=" . urlencode("Password must be at least 8 characters."));
}

/* ✅ DEMO MODE */
if (!empty($_POST['demo'])) {
  // presentation only
  go("$BASE/auth/login.php?ok=" . urlencode("Demo: Password updated (not really saved)."));
}

/* ✅ REAL MODE */
$reset_id = (int)($_POST['reset_id'] ?? 0);
$token    = trim((string)($_POST['token'] ?? ''));

if ($reset_id <= 0 || $token === '') {
  go("$BASE/auth/forgot_password.php?error=" . urlencode("Reset link is invalid. Please request again."));
}

$sql = "SELECT pr.id, pr.user_id, pr.token_hash, pr.expires_at, pr.used_at
        FROM password_resets pr
        WHERE pr.id = ?
        LIMIT 1"; 
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $reset_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;

if (!$row) {
  go("$BASE/auth/forgot_password.php?error=" . urlencode("Reset link not found. Please request again."));
}

if (!empty($row['used_at'])) {
  go("$BASE/auth/forgot_password.php?error=" . urlencode("Reset link already used. Please request a new one."));
}

$now = date('Y-m-d H:i:s');
if ($row['expires_at'] <= $now) {
  go("$BASE/auth/forgot_password.php?error=" . urlencode("Reset link expired. Please request a new one."));
}

if (!password_verify($token, $row['token_hash'])) {
  go("$BASE/auth/forgot_password.php?error=" . urlencode("Reset link invalid. Please request again."));
}

$user_id = (int)$row['user_id'];
$new_hash = password_hash($password, PASSWORD_DEFAULT);

$up = "UPDATE users SET password = ? WHERE user_id = ? LIMIT 1";
$stmt2 = mysqli_prepare($conn, $up);
mysqli_stmt_bind_param($stmt2, "si", $new_hash, $user_id);
mysqli_stmt_execute($stmt2);

$mark = "UPDATE password_resets SET used_at = NOW() WHERE id = ? LIMIT 1";
$stmt3 = mysqli_prepare($conn, $mark);
mysqli_stmt_bind_param($stmt3, "i", $reset_id);
mysqli_stmt_execute($stmt3);

go("$BASE/auth/login.php?ok=" . urlencode("Password updated. Please login."));
