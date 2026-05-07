<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($BASE) || !$BASE) $BASE = "/RWDD2408/eco_hub";
$DRAWER_CATEGORY_ONLY = $DRAWER_CATEGORY_ONLY ?? false;
?>



<!-- overlay -->
<div class="mdrawer-overlay"></div>

<!-- drawer -->
<aside id="mdrawer" class="mdrawer" aria-label="Menu">
  <div class="mdrawer-head">
    <div class="mdrawer-title">EcoSwap</div>
    <button class="mdrawer-close" type="button" data-mdrawer-close aria-label="Close">✕</button>
  </div>

  <div class="mdrawer-section">
    <div class="mdrawer-label">Browse by Category</div>

    <a class="mdrawer-link" href="<?= $BASE ?>/items/item_list.php?cat=Mobile%20Phones">📱 Mobile Phones &amp; Gadgets</a>
    <a class="mdrawer-link" href="<?= $BASE ?>/items/item_list.php?cat=Women">👗 Women&#39;s Fashion</a>
    <a class="mdrawer-link" href="<?= $BASE ?>/items/item_list.php?cat=Men">👔 Men&#39;s Fashion</a>
    <a class="mdrawer-link" href="<?= $BASE ?>/items/item_list.php?cat=Computers">💻 Computers &amp; Tech</a>
    <a class="mdrawer-link" href="<?= $BASE ?>/items/item_list.php?cat=Luxury">💎 Luxury</a>
    <a class="mdrawer-link" href="<?= $BASE ?>/items/item_list.php?cat=Gaming">🎮 Video Gaming</a>
    <a class="mdrawer-link" href="<?= $BASE ?>/items/item_list.php?cat=Audio">🎧 Audio</a>
    <a class="mdrawer-link" href="<?= $BASE ?>/items/item_list.php?cat=Following">⭐ Following</a>
  </div>

<?php if (!$DRAWER_CATEGORY_ONLY): ?>
<div class="mdrawer-section">
  <div class="mdrawer-label">Quick Links</div>

  <a class="mdrawer-link" href="<?= $BASE ?>/items/item_list.php">🔎 Browse All</a>
  <a class="mdrawer-link" href="<?= $BASE ?>/wishlist/wishlist.php">❤️ Saved (Wishlist)</a>
  <a class="mdrawer-link" href="<?= $BASE ?>/items/add_item.php">＋ List for Rent</a>
</div>
<?php endif; ?>


<?php if (!$DRAWER_CATEGORY_ONLY): ?>
<div class="mdrawer-section">
  <div class="mdrawer-label">Account</div>

  <?php if (!empty($_SESSION['user_id'])): ?>
    <a class="mdrawer-link" href="<?= $BASE ?>/profile.php">👤 My Profile</a>
    <a class="mdrawer-link" href="<?= $BASE ?>/borrow/my_borrow.php">📦 My Rentals</a>
    <form action="<?= $BASE ?>/auth/logout.php" method="post">
      <button type="submit">Logout</button>
    </form>
  <?php else: ?>
    <a class="mdrawer-link" href="<?= $BASE ?>/auth/login.php">🔐 Login</a>
    <a class="mdrawer-link" href="<?= $BASE ?>/auth/register.php">📝 Register</a>
  <?php endif; ?>
</div>
<?php endif; ?>

</aside>


