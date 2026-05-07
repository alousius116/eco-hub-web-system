<?php
$DRAWER_CATEGORY_ONLY = true;

require_once __DIR__ . "/../config/db_connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$BASE = "/RWDD2408/eco_hub";

/* ✅ optional: appbar back (usually Browse no back) */
$SHOW_BACK = $SHOW_BACK ?? false;
$BACK_HREF = $BACK_HREF ?? ($BASE . "/index.php");

/* ✅ no-404 placeholder */
$PLACEHOLDER = "data:image/svg+xml;utf8," . rawurlencode('
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600">
<rect width="100%" height="100%" fill="#f1f5f9"/>
<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
 fill="#64748b" font-family="Arial" font-size="28">No Image</text>
</svg>');

/* ✅ wishlist count (optional, if you show it somewhere) */
$wishlist_count = 0;
if (!empty($_SESSION['user_id'])) {
  $uid = (int)$_SESSION['user_id'];
  $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM wishlist WHERE user_id=?");
  mysqli_stmt_bind_param($stmt, "i", $uid);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $wishlist_count);
  mysqli_stmt_fetch($stmt);
  mysqli_stmt_close($stmt);
}

/* query params */
$q    = trim($_GET['q'] ?? '');
$cat  = trim($_GET['cat'] ?? '');
$sort = trim($_GET['sort'] ?? '');
$is_new = ($sort === 'new');

/* ✅ always only approved items */
$where = [];
$where[] = "i.status = 'approved'";

/* keyword search */
if ($q !== '') {
  $q_safe = mysqli_real_escape_string($conn, $q);
  $where[] = "(i.item_name LIKE '%$q_safe%' OR i.description LIKE '%$q_safe%' OR i.category LIKE '%$q_safe%')";
}

/* New filter: last 7 days */
if ($is_new) {
  $where[] = "i.created_at >= (NOW() - INTERVAL 7 DAY)";
}

/* ✅ Category filter (DB-aligned) */
if ($cat !== '') {
  $cat_norm = strtolower(trim($cat));
  $cat_norm = str_replace(['&', '-', '_', "'"], ' ', $cat_norm);
  $cat_norm = preg_replace('/\s+/', ' ', $cat_norm);

  $CAT_DB_MAP = [
    'mobile phones' => ['Mobile Phones'],
    'women'         => ['Women', 'Women Fashion'],
    'men'           => ['Men Accessories'],
    'computers'     => ['Laptops', 'Computers & Tech', 'Computer Accessories'],
    'gaming'        => ['Gaming', 'Video Gaming', 'Gaming Accessories'],
    'audio'         => ['Audio', 'Audio Equipment'],
    'luxury'        => ['Luxury'],
    'following'     => [], // no filter
  ];

  if (isset($CAT_DB_MAP[$cat_norm]) && count($CAT_DB_MAP[$cat_norm]) > 0) {
    $conds = [];
    foreach ($CAT_DB_MAP[$cat_norm] as $dbCat) {
      $dbCatSafe = mysqli_real_escape_string($conn, $dbCat);
      $conds[] = "TRIM(i.category) = '$dbCatSafe'";
    }
    $where[] = '(' . implode(' OR ', $conds) . ')';
  }
}

$where_sql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

/* image helper */
function build_img_url($img, $BASE, $PLACEHOLDER){
  $img = trim((string)$img);
  if ($img === '') return $PLACEHOLDER;
  if (strpos($img, '/') !== false) return $BASE . "/uploads/" . ltrim($img, '/');
  return $BASE . "/uploads/items/" . $img;
}

$items = [];
$sql = "
SELECT 
  i.item_id,
  i.item_name,
  i.category,
  i.condition_status,
  i.availability_status,
  i.image,
  i.created_at,
  i.listing_type,
  i.price,
  i.rental_price_per_day
FROM items i
$where_sql
ORDER BY i.created_at DESC
";
$res = mysqli_query($conn, $sql);
if(!$res){
  die("SQL Error: " . mysqli_error($conn));
}
while($row = mysqli_fetch_assoc($res)){
  $items[] = $row;
}

if ($is_new && count($items) === 0) {
  $items = [];
  $sql2 = "
    SELECT i.item_id, i.item_name, i.category, i.condition_status, i.availability_status, i.image, i.created_at,
           i.listing_type, i.price, i.rental_price_per_day
    FROM items i
    WHERE i.status = 'approved'
    ORDER BY i.created_at DESC
    LIMIT 20
  ";
  $res2 = mysqli_query($conn, $sql2);
  if($res2){
    while($row = mysqli_fetch_assoc($res2)) $items[] = $row;
  }
}

$wish_set = [];
if (!empty($_SESSION['user_id'])) {
  $uid = (int)$_SESSION['user_id'];
  $stmt = mysqli_prepare($conn, "SELECT item_id FROM wishlist WHERE user_id=?");
  mysqli_stmt_bind_param($stmt, "i", $uid);
  mysqli_stmt_execute($stmt);
  $wr = mysqli_stmt_get_result($stmt);
  if ($wr) {
    while($w = mysqli_fetch_assoc($wr)) $wish_set[(int)$w['item_id']] = true;
  }
  mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Browse - EcoSwap</title>
<link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css?v=BROWSE_AJAX_WISH_FIX_1">

<style>
  .typePill{
    display:inline-flex; align-items:center; gap:6px;
    font-weight:900; font-size:12px;
    padding:5px 10px; border-radius:999px;
    border:1px solid #e6eef0; background:#f8fafc;
  }
  .typePill.sell{ background:#fff7ed; border-color:#fed7aa; }
  .typePill.rent{ background:#ecfeff; border-color:#a5f3fc; }
  .wishBtn.loading{ opacity:.6; pointer-events:none; }
</style>
</head>
<body>

<?php @include __DIR__ . "/../partials/mobile_appbar.php"; ?>
<?php @include __DIR__ . "/../partials/mobile_drawer.php"; ?>

<div class="categoryRow carousellCats">
  <a class="catIcon" href="<?= $BASE ?>/wishlist/wishlist.php">
    <div class="bubble">❤️</div>
    <span>Likes</span>
  </a>

  <a class="catIcon" href="<?= $BASE ?>/items/item_list.php?cat=Mobile%20Phones">
    <div class="bubble">📱</div>
    <span>Mobile Phones &amp; Gadgets</span>
  </a>

  <a class="catIcon" href="<?= $BASE ?>/items/item_list.php?cat=Women">
    <div class="bubble">👗</div>
    <span>Women's Fashion</span>
  </a>

  <a class="catIcon" href="<?= $BASE ?>/items/item_list.php?cat=Men">
    <div class="bubble">👔</div>
    <span>Men's Fashion</span>
  </a>

  <a class="catIcon" href="<?= $BASE ?>/items/item_list.php?cat=Computers">
    <div class="bubble">💻</div>
    <span>Computers &amp; Tech</span>
  </a>

  <a class="catIcon" href="<?= $BASE ?>/items/item_list.php?cat=Following">
    <div class="bubble">⭐</div>
    <span>Following</span>
  </a>

  <a class="catIcon" href="<?= $BASE ?>/items/item_list.php?cat=Luxury">
    <div class="bubble">💎</div>
    <span>Luxury</span>
  </a>

  <a class="catIcon" href="<?= $BASE ?>/items/item_list.php?cat=Gaming">
    <div class="bubble">🎮</div>
    <span>Video Gaming</span>
  </a>

  <a class="catIcon" href="<?= $BASE ?>/items/item_list.php?cat=Audio">
    <div class="bubble">🎧</div>
    <span>Audio</span>
  </a>
</div>

<div class="tabs">
  <a class="tab" href="<?= $BASE ?>/index.php">Top picks</a>
  <a class="tab <?= (!$is_new) ? 'active' : '' ?>" href="<?= $BASE ?>/items/item_list.php">Browse</a>
  <a class="tab <?= $is_new ? 'active' : '' ?>" href="<?= $BASE ?>/items/item_list.php?sort=new">New</a>
</div>

<div class="grid">
  <?php if(count($items) === 0): ?>
    <div class="card" style="grid-column:1/-1;">
      <div class="cardBody">
        <div class="itemTitle">No results</div>
        <div class="meta">Try another keyword, category, or filter.</div>
      </div>
    </div>
  <?php else: ?>

    <?php foreach($items as $it): ?>
      <?php
        $item_id = (int)$it['item_id'];
        $img_url = build_img_url($it['image'] ?? '', $BASE, $PLACEHOLDER);
        $detail  = $BASE . "/items/item_detail.php?id=" . $item_id;
        $is_wish = !empty($wish_set[$item_id]);

        $is_recent = false;
        if (!empty($it['created_at'])) {
          $is_recent = (strtotime($it['created_at']) >= strtotime('-7 days'));
        }

        $lt = strtolower($it['listing_type'] ?? 'rent');
        $is_sell = ($lt === 'sell');
        $price = $it['price'] ?? null;
      ?>

      <a class="card" href="<?= h($detail) ?>" style="text-decoration:none;color:inherit;">
        <div class="thumb">
          <img src="<?= h($img_url) ?>" loading="lazy"
               onerror="this.onerror=null;this.src='<?= h($PLACEHOLDER) ?>';"
               alt="<?= h($it['item_name']) ?>">

          <form class="wishForm js-wishForm" method="post" action="<?= $BASE ?>/wishlist/toggle.php"
                data-item="<?= (int)$item_id ?>">
            <input type="hidden" name="item_id" value="<?= (int)$item_id ?>">
            <button class="wishBtn js-wishBtn <?= $is_wish ? 'active' : '' ?>" type="button"
                    aria-label="Toggle wishlist"><?= $is_wish ? '♥' : '♡' ?></button>
          </form>
        </div>

        <div class="cardBody">
          <div class="cardTopRow" style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
            <?php if ($is_recent): ?>
              <span class="badgeMini">NEW</span>
            <?php else: ?>
              <span class="badgeMini"><?= h($it['category'] ?? 'Item') ?></span>
            <?php endif; ?>

            <span class="typePill <?= $is_sell ? 'sell' : 'rent' ?>">
              <?= $is_sell ? 'SELL' : 'RENT' ?>
            </span>
          </div>

          <div class="itemTitle"><?= h($it['item_name']) ?></div>

          <?php if ($is_sell): ?>
            <div class="meta" style="font-weight:900;">
              RM <?= h(number_format((float)$price, 2)) ?>
            </div>
          <?php else: ?>
            <div class="meta">
              <?php if (!is_null($it['rental_price_per_day'])): ?>
                RM <?= h(number_format((float)$it['rental_price_per_day'], 2)) ?>/day ·
              <?php endif; ?>
              <?= h($it['condition_status']) ?> · <?= h($it['availability_status']) ?>
            </div>
          <?php endif; ?>
        </div>
      </a>

    <?php endforeach; ?>

  <?php endif; ?>
</div>

<nav class="bottomnav">
  <a href="<?= $BASE ?>/index.php"><span>🔍</span>Explore</a>
  <a class="active" href="<?= $BASE ?>/items/item_list.php"><span>📋</span>Browse</a>
  <a class="mid" href="<?= $BASE ?>/items/add_item.php"><span>＋</span>Rent</a>
  <a href="<?= $BASE ?>/wishlist/wishlist.php"><span>❤️</span>Saved</a>
  <a href="<?= $BASE ?>/profile.php"><span>👤</span>Me</a>
</nav>

<script>

(function(){
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
        alert(data && data.error ? data.error : 'Wishlist failed. Please login and try again.');
        return;
      }

      const liked = !!data.liked;
      btn.classList.toggle('active', liked);
      btn.textContent = liked ? '♥' : '♡';

      const badge = document.getElementById('jsSavedBadge');
      if (badge && typeof data.count !== 'undefined'){
        const c = parseInt(data.count, 10) || 0;
        if (c > 0){ badge.textContent = c; badge.style.display = 'inline-flex'; }
        else { badge.style.display = 'none'; }
      }

    }catch(err){
      console.error(err);
      alert('Wishlist failed. Please try again.');
    }finally{
      btn.classList.remove('loading');
    }
  }


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

<script src="<?= $BASE ?>/assets/js/mobile_drawer.js?v=1"></script>
</body>
</html>

