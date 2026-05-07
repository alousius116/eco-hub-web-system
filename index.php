<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== DEV BYPASS (remove before submission) ===== */
$DEV_MODE = true; // 开发时 true，交作业前改 false

if (!$DEV_MODE && !isset($_SESSION['seen_onboarding'])) {
    header("Location: onboarding.php");
    exit;
}
/* =============================================== */

$DRAWER_CATEGORY_ONLY = false;
require_once __DIR__ . "/config/db_connect.php";



$BASE = "/RWDD2408/eco_hub";
$PLACEHOLDER = $BASE . "/assets/img/placeholder.png";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* wishlist count (optional badge usage) */
$wishlist_count = 0;
if (!empty($_SESSION['user_id'])) {
  $uid = (int)$_SESSION['user_id'];
  $rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM wishlist WHERE user_id = $uid");
  if ($rs && ($row = mysqli_fetch_assoc($rs))) {
    $wishlist_count = (int)$row['c'];
  }
}

/* items */
$items = [];
$sql = "
SELECT i.item_id, i.item_name, i.category,
       i.condition_status, i.availability_status,
       i.image, i.status
FROM items i
WHERE i.status='approved'
ORDER BY i.created_at DESC
LIMIT 20
";
$res = mysqli_query($conn, $sql);
if ($res) {
  while ($row = mysqli_fetch_assoc($res)) {
    $items[] = $row;
  }
}

/* wishlist set for heart active (for card hearts) */
$wish_set = [];
if (!empty($_SESSION['user_id'])) {
  $uid = (int)$_SESSION['user_id'];
  $wr = mysqli_query($conn, "SELECT item_id FROM wishlist WHERE user_id=$uid");
  if ($wr) {
    while ($w = mysqli_fetch_assoc($wr)) {
      $wish_set[(int)$w['item_id']] = true;
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>EcoSwap</title>
<link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css?v=CAROUSELL_MOBILE_2">
<style>
/* ================= Base ================= */
:root{
  --green-600:#16a34a;
  --green-700:#15803d;
  --gray-900:#111827;
  --gray-700:#374151;
  --gray-500:#6b7280;
  --gray-200:#e5e7eb;
  --card:#ffffff;
}

.wishBtn.loading{
  opacity:.6;
  pointer-events:none;
}

/* ================= HERO ================= */
.howBanner{
  position:relative;
  margin:16px 14px 10px;
  border-radius:20px;
  overflow:hidden;
  background:#fff;
  box-shadow:
    0 12px 30px rgba(0,0,0,.10),
    inset 0 0 0 1px rgba(255,255,255,.6);
}

.howBannerImg{
  width:100%;
  display:block;
}

.howBannerBtn{
  position:absolute;
  bottom:12px;
  right:12px;
  background:rgba(255,255,255,.96);
  border-radius:999px;
  padding:6px 14px;
  font-size:12px;
  font-weight:700;
  color:var(--gray-900);
  box-shadow:0 6px 16px rgba(0,0,0,.14);
  backdrop-filter: blur(6px);
}

/* ================= INTRO ================= */
/* EcoHub = 品牌标签，不是标题 */
.home-intro{
  padding:8px 14px 4px;
}

.home-intro h1{
  margin:0 0 2px;
  font-size:11px;
  font-weight:700;
  letter-spacing:.12em;
  text-transform:uppercase;
  color:var(--green-600);
}

/* 这句 = 价值主张 */
.home-tagline{
  margin:2px 0 0;
  font-size:15px;
  font-weight:700;
  line-height:1.45;
  color:var(--gray-900);
  max-width:92%;
}

/* ================= ECO IMPACT (UPGRADED) ================= */
.eco-stats{
  display:flex;
  gap:10px;
  padding:10px 14px 6px;
  margin-top:6px;
}

.eco-stats div{
  position:relative;
  flex:1;
  background:
    linear-gradient(180deg,#f0fdf4 0%, #ffffff 65%);
  border-radius:16px;
  padding:10px 8px 8px;
  text-align:center;
  font-size:11px;
  font-weight:600;
  color:var(--gray-700);
  box-shadow:
    inset 0 0 0 1px #dcfce7,
    0 6px 14px rgba(0,0,0,.08);
}

/* 数字成为视觉重点 */
.eco-stats div b{
  display:block;
  font-size:16px;
  font-weight:900;
  color:var(--green-700);
  margin:2px 0 2px;
}

/* 轻装饰点，增加“成就感” */
.eco-stats div::before{
  content:"";
  position:absolute;
  top:10px;
  left:10px;
  width:6px;
  height:6px;
  background:var(--green-600);
  border-radius:50%;
  opacity:.5;
}

/* ================= CATEGORIES ================= */
.categoryRow{
  margin-top:12px;
}

.catIcon{
  opacity:.9;
  transition:opacity .15s ease, transform .15s ease;
}

.catIcon:active{
  transform:scale(.96);
}

.catIcon span{
  font-size:11px;
  color:var(--gray-500);
}

/* ================= ITEM STATUS ================= */
.status{
  margin-left:6px;
  padding:2px 8px;
  border-radius:999px;
  font-size:11px;
  font-weight:800;
}

.status.available{
  background:#dcfce7;
  color:#166534;
}

.status.borrowed{
  background:#fee2e2;
  color:#991b1b;
}

.status.unavailable{
  background:#e5e7eb;
  color:#374151;
}

/* ================= EMPTY ================= */
.empty-state{
  grid-column:1/-1;
  text-align:center;
  padding:40px 12px;
  color:var(--gray-500);
}

/* ================= GLOBAL RHYTHM ================= */
/* 给页面呼吸感，不挤 */
.section > * + *{
  margin-top:10px;
}




</style>
</head>

<body>

<?php
@include __DIR__ . "/partials/mobile_appbar.php";
@include __DIR__ . "/partials/mobile_drawer.php";
?>

<section class="section">
  <div class="banner howBanner">
    <img
      src="<?= $BASE ?>/assets/how_ecoswap_works.png"
      alt="How EcoSwap Works"
      class="howBannerImg"
    >
    <button type="button" class="howBannerBtn" onclick="openGuideModal()">
      View guide →
    </button>
  </div>
  <!-- 🔹 Platform intro -->
<div class="home-intro">
  <h1>EcoHub</h1>
  <p class="home-tagline">
    A peer-to-peer rental platform where users share eco-friendly items
    instead of buying new ones.
  </p>
</div>
  <!-- 🔹 Eco impact (UI-only) -->
<div class="eco-stats">
  <div>🌱 CO₂ saved<br><b>124kg</b></div>
  <div>♻ Items reused<br><b>58</b></div>
  <div>💚 Eco points<br><b>320</b></div>
</div>

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
</section>

<!-- Tabs -->
<div class="tabs">
  <a class="tab active" href="<?= $BASE ?>/index.php">Top picks</a>
  <a class="tab" href="<?= $BASE ?>/items/item_list.php">Browse</a>
  <a class="tab" href="<?= $BASE ?>/items/item_list.php?sort=new">New</a>
</div>

<div class="grid">

<?php foreach($items as $it): ?>
  <?php
    $img = trim((string)($it['image'] ?? ''));
    if ($img === '') {
      $img_url = $PLACEHOLDER;
    } else {
      if (strpos($img, '/') !== false) {
        $img_url = $BASE . "/uploads/" . ltrim($img, '/');
      } else {
        $img_url = $BASE . "/uploads/items/" . $img;
      }
    }

    $detail = $BASE . "/items/item_detail.php?id=" . (int)$it['item_id'];
    $is_wish = !empty($wish_set[(int)$it['item_id']]);

    $a = strtolower(trim((string)($it['availability_status'] ?? 'available')));
    $availLabel = 'Available';
    if ($a === 'borrowed') $availLabel = 'Borrowed';
    else if ($a === 'unavailable') $availLabel = 'Unavailable';
  ?>

  <a class="card" href="<?= h($detail) ?>" style="text-decoration:none;color:inherit;">
    <div class="thumb">
      <img
        src="<?= h($img_url) ?>"
        alt="<?= h($it['item_name']) ?>"
        loading="lazy"
        onerror="this.onerror=null;this.src='<?= h($PLACEHOLDER) ?>';"
      >

      <!-- ✅ FIX: type=button (same as item_list) -->
      <form class="wishForm js-wishForm" method="post" action="<?= $BASE ?>/wishlist/toggle.php">
        <input type="hidden" name="item_id" value="<?= (int)$it['item_id'] ?>">
        <button class="wishBtn js-wishBtn <?= $is_wish ? 'active' : '' ?>"
                type="button"
                aria-label="Toggle wishlist"><?= $is_wish ? '♥' : '♡' ?></button>
      </form>

    </div>

    <div class="cardBody">
      <div class="cardTopRow">
        <span class="badgeMini"><?= h($it['category'] ?? 'Item') ?></span>
      </div>

      <div class="itemTitle"><?= h($it['item_name']) ?></div>

      <div class="meta">
        <?= h($it['condition_status'] ?? 'Good') ?> · <?= h($availLabel) ?>
      </div>
    </div>
  </a>

<?php endforeach; ?>

</div>

<nav class="bottomnav">
  <a class="active" href="<?= $BASE ?>/index.php"><span>🔍</span>Explore</a>
  <a href="<?= $BASE ?>/items/item_list.php"><span>📋</span>Browse</a>
  <a class="mid" href="<?= $BASE ?>/items/add_item.php"><span>＋</span>Rent</a>
  <a href="<?= $BASE ?>/wishlist/wishlist.php"><span>❤️</span>Saved</a>
  <a href="<?= $BASE ?>/profile.php"><span>👤</span>Me</a>
</nav>

<script src="<?= $BASE ?>/assets/js/mobile_drawer.js?v=1"></script>

<!-- ===== Guide Modal ===== -->
<div id="guideOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;"></div>

<div id="guideSheet" style="display:none;position:fixed;left:50%;bottom:0;transform:translateX(-50%);
  width:min(520px, calc(100vw - 18px));
  background:#fff;border-radius:18px 18px 0 0;z-index:100000;
  padding:14px 14px calc(14px + env(safe-area-inset-bottom));
  box-shadow:0 -18px 40px rgba(0,0,0,.25);">

  <div style="width:44px;height:5px;background:#e5e7eb;border-radius:99px;margin:8px auto 10px;"></div>
  <div style="font-weight:950;font-size:16px;margin-bottom:8px;">EcoSwap Quick Guide</div>

  <div style="display:grid;gap:10px;">
    <div style="border:1px solid #e5e7eb;border-radius:14px;padding:12px;">
      <b>📦 Step 1: List unused items</b>
      <div style="color:#6b7280;font-weight:800;font-size:12px;margin-top:4px;">
        Post items you don’t use. Set rental price per day.
      </div>
    </div>

    <div style="border:1px solid #e5e7eb;border-radius:14px;padding:12px;">
      <b>🤝 Step 2: Borrow or share</b>
      <div style="color:#6b7280;font-weight:800;font-size:12px;margin-top:4px;">
        Request to rent, chat with owner, and get approval.
      </div>
    </div>

    <div style="border:1px solid #e5e7eb;border-radius:14px;padding:12px;">
      <b>🌍 Step 3: Reduce waste</b>
      <div style="color:#6b7280;font-weight:800;font-size:12px;margin-top:4px;">
        Earn Eco Points, unlock discounts, and track CO₂ saved.
      </div>
    </div>
  </div>

  <button type="button" onclick="closeGuideModal()"
    style="margin-top:12px;width:100%;border:none;border-radius:14px;padding:12px 14px;
    background:#10b981;color:#052016;font-weight:950;font-size:16px;cursor:pointer;">
    Got it
  </button>
</div>

<script>
function openGuideModal(){
  document.getElementById('guideOverlay').style.display = 'block';
  document.getElementById('guideSheet').style.display = 'block';
  document.body.style.overflow = 'hidden';
}
function closeGuideModal(){
  document.getElementById('guideOverlay').style.display = 'none';
  document.getElementById('guideSheet').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('click', (e)=>{
  if(e.target && e.target.id === 'guideOverlay') closeGuideModal();
});
document.addEventListener('keydown', (e)=>{
  if(e.key === 'Escape') closeGuideModal();
});
</script>

<!-- ✅ Wishlist JS (same pattern as item_list) -->
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

</body>
</html>




