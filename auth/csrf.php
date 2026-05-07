<?php
// /RWDD2408/eco_hub/auth/csrf.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_field(): string {
  $t = htmlspecialchars(csrf_token(), ENT_QUOTES);
  return '<input type="hidden" name="csrf_token" value="'.$t.'">';
}

function csrf_verify_or_die(): void {
  $sent = $_POST['csrf_token'] ?? '';
  $ok = is_string($sent) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
  if (!$ok) {
    http_response_code(403);
    die("Forbidden: invalid CSRF token");
  }
}
