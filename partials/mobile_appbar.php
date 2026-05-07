<?php
require_once __DIR__ . "/../config/db_connect.php";

/* helpers */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
}

/* base path */
$BASE = "/RWDD2408/eco_hub";

/* back button support */
$SHOW_BACK = !empty($SHOW_BACK);
$BACK_HREF = $BACK_HREF ?? ($BASE . "/index.php");

$unread_count = 0;

if (!empty($_SESSION['user_id'])) {
  $uid = (int)$_SESSION['user_id'];


  // unread messages
  $rs2 = mysqli_query($conn, "SELECT COUNT(*) AS c FROM messages WHERE receiver_id=$uid AND is_read=0");
  if ($rs2 && ($r2 = mysqli_fetch_assoc($rs2))) $unread_count = (int)($r2['c'] ?? 0);
}

$search_action = $BASE . "/items/item_list.php";
$search_value  = $_GET['q'] ?? '';
?>

<div class="topbarMobile">

  <?php if ($SHOW_BACK): ?>
    <a class="menuBtn" href="<?= h($BACK_HREF) ?>" aria-label="Back">←</a>
  <?php else: ?>
    <button class="menuBtn mdrawer-openbtn" type="button" aria-label="Menu">☰</button>
  <?php endif; ?>

  <!-- Search pill -->
  <form class="searchBox" action="<?= h($search_action) ?>" method="get">
    <input type="search" name="q" placeholder="Search EcoHub items" value="<?= h($search_value) ?>">
  </form>

  <div class="rightIcons">

    <a class="profileLink" href="<?= h($BASE) ?>/chat/inbox.php" aria-label="Messages">
      💬
      <?php if ($unread_count > 0): ?>
        <span class="mbadge"><?= (int)$unread_count ?></span>
      <?php endif; ?>
    </a>
  </div>

</div>


