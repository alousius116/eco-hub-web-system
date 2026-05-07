<?php
require_once __DIR__ . "/../config/db_connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$BASE = "/RWDD2408/eco_hub";
$PLACEHOLDER = $BASE . "/assets/img/placeholder.png";

if (empty($_SESSION['user_id'])) {
  header("Location: $BASE/auth/login.php");
  exit();
}
$user_id = (int)$_SESSION['user_id'];

function build_img_url($img, $BASE, $PLACEHOLDER){
  $img = trim((string)$img);
  if ($img === '') return $PLACEHOLDER;
  if (strpos($img, '/') !== false) return $BASE . "/uploads/" . ltrim($img, '/');
  return $BASE . "/uploads/items/" . $img;
}

/* ===== Load wishlist items (safer: prepared) ===== */
$items = [];
$sql = "
SELECT i.item_id, i.item_name, i.category, i.condition_status, i.availability_status, i.image
FROM wishlist w
JOIN items i ON i.item_id = w.item_id
WHERE w.user_id = ?
ORDER BY w.item_id DESC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) {
  while ($row = mysqli_fetch_assoc($res)) $items[] = $row;
}
mysqli_stmt_close($stmt);

$wish_count = count($items);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Saved</title>
<link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css?v=FINAL_WISH_AJAX_1">
<style>
  .wishBtn.loading{ opacity:.6; pointer-events:none; }
</style>
</head>
<body>

<?php @include __DIR__ . "/../partials/mobile_appbar.php"; ?>
<?php @include __DIR__ . "/../partials/mobile_drawer.php"; ?>

<div class="section" style="padding-top:14px;">

  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
    <a href="<?= $BASE ?>/index.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:12px;border:1px solid #e5e7eb;background:#fff;font-weight:800;font-size:13px;text-decoration:none;color:#111;">
      ← Back
    </a>

    <div style="text-align:right;">
      <div style="font-weight:950; font-size:18px;">
        Saved <span id="jsWishCount" style="color:#ef4444;">(<?= (int)$wish_count ?>)</span>
      </div>
      <div class="meta" style="margin-top:4px;">Your wishlist items</div>
    </div>
  </div>

</div>

<div class="grid" id="jsWishGrid">

<?php if(count($items)===0): ?>
  <div class="card" style="grid-column:1/-1;">
    <div class="cardBody">
      <div class="itemTitle">No saved items yet</div>
      <div class="meta">Go to Top picks and tap ♥ to save.</div>
      <div style="margin-top:10px;">
        <a class="tab active" href="<?= $BASE ?>/index.php" style="display:inline-block;">Back to Explore</a>
      </div>
    </div>
  </div>
<?php else: ?>

  <?php foreach($items as $it): ?>
    <?php
      $item_id = (int)$it['item_id'];
      $img_url = build_img_url($it['image'] ?? '', $BASE, $PLACEHOLDER);
      $detail  = $BASE . "/items/item_detail.php?id=" . $item_id;
    ?>

    <a class="card jsWishCard" data-item-id="<?= $item_id ?>" href="<?= h($detail) ?>" style="text-decoration:none;color:inherit;">
      <div class="thumb">
        <img src="<?= h($img_url) ?>" loading="lazy"
             onerror="this.onerror=null;this.src='<?= h($PLACEHOLDER) ?>';"
             alt="<?= h($it['item_name']) ?>">

        <!-- ✅ IMPORTANT: type=button, no normal submit -->
        <form class="wishForm js-wishForm" method="post" action="<?= $BASE ?>/wishlist/toggle.php">
          <input type="hidden" name="item_id" value="<?= $item_id ?>">
          <button class="wishBtn js-wishBtn active" type="button" aria-label="Remove from wishlist">♥</button>
        </form>
      </div>

      <div class="cardBody">
        <div class="itemTitle"><?= h($it['item_name']) ?></div>
        <div class="meta"><?= h($it['condition_status']) ?> · <?= h($it['availability_status']) ?></div>
      </div>
    </a>

  <?php endforeach; ?>

<?php endif; ?>

</div>

<nav class="bottomnav">
  <a href="<?= $BASE ?>/index.php"><span>🔍</span>Explore</a>
  <a href="<?= $BASE ?>/items/item_list.php"><span>📋</span>Browse</a>
  <a class="mid" href="<?= $BASE ?>/items/add_item.php"><span>＋</span>Rent</a>
  <a class="active" href="<?= $BASE ?>/wishlist/wishlist.php"><span>❤️</span>Saved</a>
  <a href="<?= $BASE ?>/profile.php"><span>👤</span>Me</a>
</nav>

<script src="<?= $BASE ?>/assets/js/mobile_drawer.js"></script>

<script>
(function(){
  function setCount(n){
    const el = document.getElementById('jsWishCount');
    if (el) el.textContent = '(' + n + ')';
  }

  async function toggleWish(form){
    const btn = form.querySelector('.js-wishBtn');
    if (!btn) return;

    btn.classList.add('loading');

    try{
      const fd = new FormData(form);
      const res = await fetch(form.action, {
        method: 'POST',
        body: fd,
        headers: {
          'X-Requested-With': 'fetch',
          'Accept': 'application/json'
        }
      });

      const text = await res.text();
      let data = null;
      try { data = JSON.parse(text); } catch(e){ data = null; }

      if (!data || !data.ok){
        alert((data && data.error) ? data.error : 'Wishlist failed.');
        return;
      }

      // data.liked false = removed
      const liked = !!data.liked;

      // ✅ In wishlist page: if removed => remove card from grid
      if (!liked){
        const card = form.closest('.jsWishCard');
        if (card) card.remove();
      } else {
        // normally wishlist page only has liked items, but just in case:
        btn.classList.toggle('active', liked);
        btn.textContent = liked ? '♥' : '♡';
      }

      // ✅ update count (use server count if returned)
      if (typeof data.count !== 'undefined'){
        setCount(parseInt(data.count, 10) || 0);
      } else {
        // fallback count by DOM
        const n = document.querySelectorAll('.jsWishCard').length;
        setCount(n);
      }

      // ✅ If empty now, show empty state
      const remaining = document.querySelectorAll('.jsWishCard').length;
      if (remaining === 0){
        const grid = document.getElementById('jsWishGrid');
        if (grid){
          grid.innerHTML = `
            <div class="card" style="grid-column:1/-1;">
              <div class="cardBody">
                <div class="itemTitle">No saved items yet</div>
                <div class="meta">Go to Top picks and tap ♥ to save.</div>
                <div style="margin-top:10px;">
                  <a class="tab active" href="<?= $BASE ?>/index.php" style="display:inline-block;">Back to Explore</a>
                </div>
              </div>
            </div>
          `;
        }
      }

    }catch(err){
      console.error(err);
      alert('Wishlist failed. Please try again.');
    }finally{
      btn.classList.remove('loading');
    }
  }

  // ✅ 捕获阶段：点 ♥ 不要跳 item_detail
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-wishBtn');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    const form = btn.closest('.js-wishForm');
    if (form) toggleWish(form);
  }, true);
})();
</script>

</body>
</html>

