<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}


/* 清空所有 session 变量 */
$_SESSION = [];

/* 如果有 session cookie，也一起删掉（更干净） */
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"],
    $params["domain"],
    $params["secure"],
    $params["httponly"]
  );
}

/* 销毁 session */
session_destroy();

/* 回到首页 */
header("Location: /RWDD2408/eco_hub/index.php");
exit();

