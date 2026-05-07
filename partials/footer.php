</div> <!-- wrap end -->

<div class="bottomnav">
  <a href="/RWDD2408/eco_hub/index.php" class="bn-item">Home</a>
  <a href="/RWDD2408/eco_hub/items/item_list.php" class="bn-item">Browse</a>
  <?php if (!empty($_SESSION['user_id'])): ?>
    <a href="/RWDD2408/eco_hub/borrow/my_borrow.php" class="bn-item">Borrow</a>
    <a href="/RWDD2408/eco_hub/profile.php" class="bn-item">Profile</a>
  <?php else: ?>
    <a href="/RWDD2408/eco_hub/auth/login.php" class="bn-item">Login</a>
    <a href="/RWDD2408/eco_hub/auth/register.php" class="bn-item">Register</a>
  <?php endif; ?>
</div>

</body>
</html>
