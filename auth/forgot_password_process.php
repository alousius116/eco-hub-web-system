<?php
session_start();
require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";

$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header("Location: $BASE/auth/forgot_password.php?error=" . urlencode("Please enter a valid APU email."));
  exit();
}

$sql = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = $res ? mysqli_fetch_assoc($res) : null;

$okMsg = "If the email exists, a reset link will be provided.";

if ($user) {
  $user_id = (int)$user['user_id'];

  $token = bin2hex(random_bytes(32)); 
  $token_hash = password_hash($token, PASSWORD_DEFAULT); 
  $expires_at = date('Y-m-d H:i:s', time() + 30 * 60);
  mysqli_query($conn, "DELETE FROM password_resets WHERE user_id = $user_id AND used_at IS NULL");

  $ins = "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)";
  $stmt2 = mysqli_prepare($conn, $ins);
  mysqli_stmt_bind_param($stmt2, "iss", $user_id, $token_hash, $expires_at);
  mysqli_stmt_execute($stmt2);

  $reset_link = $BASE . "/auth/reset_password.php?token=" . urlencode($token);

  $_SESSION['reset_link'] = $reset_link;
}

header("Location: $BASE/auth/forgot_password.php?ok=" . urlencode($okMsg));
exit();

