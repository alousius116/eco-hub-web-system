<?php
// /RWDD2408/eco_hub/auth/auth_guard.php
if (session_status() === PHP_SESSION_NONE) session_start();

$BASE = "/RWDD2408/eco_hub";

function go(string $path): void {
  global $BASE;
  if ($path === '' || $path[0] !== '/') $path = '/' . $path;
  header("Location: " . $BASE . $path);
  exit();
}

function require_login(): void {
  global $BASE;
  if (empty($_SESSION['user_id'])) {
    header("Location: $BASE/auth/login.php?error=" . urlencode("Please login first."));
    exit();
  }
}


function require_role(string $role): void {
  if (empty($_SESSION['role']) || strtolower($_SESSION['role']) !== strtolower($role)) {
    go("/index.php");
  }
}
