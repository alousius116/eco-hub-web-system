<?php
require_once __DIR__ . "/config/db_connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$BASE = "/RWDD2408/eco_hub";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['user_id'])) {
  header("Location: $BASE/auth/login.php?error=" . urlencode("Please login first."));
  exit();
}

$uid = (int)$_SESSION['user_id'];

$display_name = trim($_POST['display_name'] ?? '');
$current_pw   = $_POST['current_password'] ?? '';
$new_pw       = $_POST['new_password'] ?? '';
$confirm_pw   = $_POST['confirm_password'] ?? '';

if ($display_name === '') {
  header("Location: $BASE/edit_profile.php?error=" . urlencode("Display name cannot be empty."));
  exit();
}

/* 1) Update display_name */
$stmt = mysqli_prepare($conn, "UPDATE users SET display_name=? WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "si", $display_name, $uid);
$ok1 = mysqli_stmt_execute($stmt);

/* 2) Optional: change password (only if any new password field is filled) */
$changing_pw = ($current_pw !== '' || $new_pw !== '' || $confirm_pw !== '');
if ($changing_pw) {

  if ($current_pw === '' || $new_pw === '' || $confirm_pw === '') {
    header("Location: $BASE/edit_profile.php?error=" . urlencode("To change password, please fill all password fields."));
    exit();
  }

  if ($new_pw !== $confirm_pw) {
    header("Location: $BASE/edit_profile.php?error=" . urlencode("New password does not match confirmation."));
    exit();
  }

  if (strlen($new_pw) < 6) {
    header("Location: $BASE/edit_profile.php?error=" . urlencode("Password must be at least 6 characters."));
    exit();
  }

  // get current hash
  $stmt2 = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id=? LIMIT 1");
  mysqli_stmt_bind_param($stmt2, "i", $uid);
  mysqli_stmt_execute($stmt2);
  $rs = mysqli_stmt_get_result($stmt2);
  $row = $rs ? mysqli_fetch_assoc($rs) : null;

  if (!$row) {
    header("Location: $BASE/edit_profile.php?error=" . urlencode("User not found."));
    exit();
  }

  if (!password_verify($current_pw, $row['password'])) {
    header("Location: $BASE/edit_profile.php?error=" . urlencode("Current password is incorrect."));
    exit();
  }

  $hash = password_hash($new_pw, PASSWORD_DEFAULT);
  $stmt3 = mysqli_prepare($conn, "UPDATE users SET password=? WHERE user_id=? LIMIT 1");
  mysqli_stmt_bind_param($stmt3, "si", $hash, $uid);
  $ok2 = mysqli_stmt_execute($stmt3);

  if (!$ok2) {
    header("Location: $BASE/edit_profile.php?error=" . urlencode("Failed to update password."));
    exit();
  }
}

/* 3) Sync session so profile.php shows updated name immediately */
$_SESSION['display_name'] = $display_name;

header("Location: $BASE/profile.php?ok=" . urlencode("Profile updated"));
exit();




