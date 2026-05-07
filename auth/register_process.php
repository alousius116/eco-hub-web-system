<?php
require_once __DIR__ . "/../config/db_connect.php";
session_start();

$BASE = "/RWDD2408/eco_hub";

function go($url){
  header("Location: " . $url);
  exit();
}

/* must come from register form */
if (!isset($_POST['register'])) {
  go("$BASE/auth/register.php");
}

$full_name = trim((string)($_POST['full_name'] ?? ''));
$tp = strtoupper(trim((string)($_POST['tp_number'] ?? '')));
$pw = (string)($_POST['password'] ?? '');
$cpw = (string)($_POST['confirm_password'] ?? '');

/* basic validation */
if ($full_name === '' || $tp === '' || $pw === '' || $cpw === '') {
  go("$BASE/auth/register.php?error=" . urlencode("Please fill in all fields."));
}

if (!preg_match('/^TP\d{6}$/', $tp)) {
  go("$BASE/auth/register.php?error=" . urlencode("Invalid TP Number format. Example: TP071222"));
}

if ($pw !== $cpw) {
  go("$BASE/auth/register.php?error=" . urlencode("Passwords do not match."));
}

if (strlen($pw) < 6) {
  go("$BASE/auth/register.php?error=" . urlencode("Password must be at least 6 characters."));
}
$email = strtolower($tp) . "@mail.apu.edu.my";
/* check existing TP */
$chk = "SELECT user_id FROM users WHERE tp_number = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $chk);
mysqli_stmt_bind_param($stmt, "s", $tp);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res && mysqli_fetch_assoc($res)) {
  go("$BASE/auth/register.php?error=" . urlencode("TP Number already registered. Please login instead."));
}

/* detect user table columns (more robust) */
$cols = [];
$rs = mysqli_query($conn, "SHOW COLUMNS FROM `users`");
if ($rs) {
  while ($r = mysqli_fetch_assoc($rs)) $cols[] = $r['Field'];
}
$has = fn($c) => in_array($c, $cols, true);

$fields = [];
$placeholders = [];
$types = "";
$values = [];

$fields[] = "full_name";
$placeholders[] = "?";
$types .= "s";
$values[] = $full_name;

/* optional display_name */
if ($has("display_name")) {
  $fields[] = "display_name";
  $placeholders[] = "?";
  $types .= "s";
  $values[] = $full_name; // default same as full_name
}

$fields[] = "tp_number";
$placeholders[] = "?";
$types .= "s";
$values[] = $tp;

$fields[] = "password";
$placeholders[] = "?";
$types .= "s";
$values[] = password_hash($pw, PASSWORD_DEFAULT);

if ($has("role")) {
  $fields[] = "role";
  $placeholders[] = "?";
  $types .= "s";
  $values[] = "user";
}

if ($has("email")) {
  $fields[] = "email";
  $placeholders[] = "?";
  $types .= "s";
  $values[] = $email;
}

/* optional created_at */
if ($has("created_at")) {
  $fields[] = "created_at";
  $placeholders[] = "NOW()";
}

/* build insert */
$sql = "INSERT INTO users (" . implode(",", $fields) . ") VALUES (" . implode(",", $placeholders) . ")";
$stmt2 = mysqli_prepare($conn, $sql);
if (!$stmt2) {
  go("$BASE/auth/register.php?error=" . urlencode("Register failed (prepare error)."));
}

/* bind only if we have ? placeholders */
if (strpos($sql, '?') !== false) {
  mysqli_stmt_bind_param($stmt2, $types, ...$values);
}

if (!mysqli_stmt_execute($stmt2)) {
  go("$BASE/auth/register.php?error=" . urlencode("Register failed. Please try again."));
}

/* success -> go login */
go("$BASE/auth/login.php?ok=" . urlencode("Account created! Please login."));

