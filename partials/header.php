<?php
require_once __DIR__ . "/../config/db_connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($BASE) || $BASE === '') {
  $BASE = "/RWDD2408/eco_hub";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>EcoHub</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= htmlspecialchars($BASE) ?>/assets/css/style.css">
</head>
<body>

<div class="topbar">
  <div class="brand">EcoHub APU</div>

  <div class="toplinks">

<?php if (!empty($IS_ADMIN_PAGE)): ?>

  <a href="javascript:history.back()"
     style="margin-right:12px;">
    ← Back
  </a>

  <a href="<?= $BASE ?>/auth/logout.php">Logout</a>

<?php else: ?>

  <a href="<?= $BASE ?>/index.php">Home</a>
  <a href="<?= $BASE ?>/items/item_list.php">Browse</a>

  <?php if (!empty($_SESSION['user_id'])): ?>
    <a href="<?= $BASE ?>/borrow/my_borrow.php">My Borrow</a>
    <a href="<?= $BASE ?>/auth/logout.php">Logout</a>
  <?php else: ?>
    <a href="<?= $BASE ?>/auth/login.php">Login</a>
  <?php endif; ?>

<?php endif; ?>

</div>


</div>

<div class="wrap">

