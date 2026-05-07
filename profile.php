<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$BASE = "/RWDD2408/eco_hub";

$DRAWER_CATEGORY_ONLY = false;

require_once __DIR__ . "/config/db_connect.php";
require_once __DIR__ . "/includes/reward_helper.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// must login
if (empty($_SESSION['user_id'])) {
  header("Location: $BASE/auth/login.php?error=" . urlencode("Please login first."));
  exit();
}

$user_id = (int)$_SESSION['user_id'];

/* ========= Appbar needs wishlist count (badge) ========= */
$wishlist_count = 0;
$rsW = mysqli_query($conn, "SELECT COUNT(*) AS c FROM wishlist WHERE user_id = $user_id");
if ($rsW && ($rowW = mysqli_fetch_assoc($rsW))) {
  $wishlist_count = (int)$rowW['c'];
}

/* ========= Owner check (has listed items?) ========= */
$has_items = false;
$rs_owner = mysqli_query($conn, "SELECT 1 FROM items WHERE user_id = $user_id LIMIT 1");
if ($rs_owner && mysqli_fetch_row($rs_owner)) {
  $has_items = true;
}

/* ========= helper: table & columns ========= */
function table_exists($conn, $name){
  $name = mysqli_real_escape_string($conn, $name);
  $res = mysqli_query($conn, "SHOW TABLES LIKE '$name'");
  return $res && mysqli_num_rows($res) > 0;
}
function get_columns($conn, $table){
  $cols = [];
  $table = mysqli_real_escape_string($conn, $table);
  $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
  if($res){
    while($r = mysqli_fetch_assoc($res)){
      $cols[] = $r['Field'];
    }
  }
  return $cols;
}

/* ========= Reward ========= */
$sus = getUserSustainability($conn, $user_id);
$benefits = getLevelBenefits($sus['eco_level'] ?? '');

$eco_points = (int)($sus['eco_points'] ?? 0);
$total_co2  = (float)($sus['total_co2'] ?? 0);
$eco_level  = (string)($sus['eco_level'] ?? 'New User');

/* progress to next level (Shopee style) */
$nextPts = (int)($benefits['next_points'] ?? 0);
$progressPct = ($nextPts > 0) ? min(100, ($eco_points / $nextPts) * 100) : 100;
$remainPts = ($nextPts > 0) ? max(0, $nextPts - $eco_points) : 0;

/* profile info */
$name  = $_SESSION['display_name'] ?? $_SESSION['full_name'] ?? "User";
$tp    = $_SESSION['tp_number'] ?? "";
$email = $tp ? (strtolower($tp) . "@mail.apu.edu.my") : "";

/* ========= Rent History (Borrower side) + Owner Rentals ========= */
$rent_history  = [];
$owner_history = [];

$price_col = null;
$time_col  = null;
$id_col    = null;

if (table_exists($conn, "borrow_requests") && table_exists($conn, "items")) {

  $br_cols = get_columns($conn, "borrow_requests");

  // ✅ find total amount column in borrow_requests (if you have)
  $total_col = null;
  foreach (['total_amount','total_price','total_cost','final_total','amount','total'] as $c){
    if (in_array($c, $br_cols)) { $total_col = $c; break; }
  }
  $sel_total = $total_col ? "br.`$total_col` AS total_amount" : "NULL AS total_amount";

  $it_cols = get_columns($conn, "items");

  // find time column
  foreach (['updated_at','created_at','request_date','requested_at','date_created'] as $c){
    if (in_array($c, $br_cols)) { $time_col = $c; break; }
  }
  // find pk column fallback
  foreach (['request_id','borrow_id','id'] as $c){
    if (in_array($c, $br_cols)) { $id_col = $c; break; }
  }
  // find rental price/day column
  foreach (['rental_price_per_day','rental_price','price_per_day','price'] as $c){
    if (in_array($c, $it_cols)) { $price_col = $c; break; }
  }

  $sel_time  = $time_col  ? "br.`$time_col` AS br_time" : "NULL AS br_time";
  $sel_id    = $id_col    ? "br.`$id_col` AS br_pk"     : "NULL AS br_pk";
  $sel_price = $price_col ? "i.`$price_col` AS rent_day" : "NULL AS rent_day";

  $order_by = $time_col ? "br.`$time_col` DESC" : ($id_col ? "br.`$id_col` DESC" : "br.item_id DESC");

  // ✅ Borrower history (only completed/cancelled)
  $sqlBorrower = "
    SELECT
      $sel_time,
      $sel_id,
      $sel_total,
      br.item_id,
      br.borrow_days,
      br.request_status,
      i.item_name,
      i.image,
      i.category,
      i.condition_status,
      $sel_price
    FROM borrow_requests br
    JOIN items i ON i.item_id = br.item_id
    WHERE br.borrower_id = ?
      AND LOWER(br.request_status) IN ('completed','cancelled')
    ORDER BY $order_by
    LIMIT 12
  ";

  $stmt = mysqli_prepare($conn, $sqlBorrower);
  if ($stmt){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    if($rs){
      while($row = mysqli_fetch_assoc($rs)){
        $rent_history[] = $row;
      }
    }
  }

  // ✅ Owner rentals: show approved + completed ONLY (no cancelled)
  if (table_exists($conn, "users")) {
    $sqlOwner = "
      SELECT
        $sel_time,
        $sel_id,
        $sel_total,
        br.item_id,
        br.borrow_days,
        br.request_status,
        u.full_name AS borrower_name,
        i.item_name,
        i.image,
        i.category,
        $sel_price
      FROM borrow_requests br
      JOIN items i ON i.item_id = br.item_id
      JOIN users u ON u.user_id = br.borrower_id
      WHERE i.user_id = ?
        AND LOWER(br.request_status) IN ('approved','completed')
      ORDER BY $order_by
      LIMIT 12
    ";

    $stmt2 = mysqli_prepare($conn, $sqlOwner);
    if ($stmt2){
      mysqli_stmt_bind_param($stmt2, "i", $user_id);
      mysqli_stmt_execute($stmt2);
      $rs2 = mysqli_stmt_get_result($stmt2);
      if($rs2){
        while($row = mysqli_fetch_assoc($rs2)){
          $owner_history[] = $row;
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Profile - EcoSwap</title>
<link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css">

<style>
.profileWrap{ padding:12px; padding-bottom:90px; }
.pCard{ background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:16px; }
.pTop{ display:flex; gap:14px; align-items:center; margin-bottom:12px; }
.avatar{ width:64px; height:64px; border-radius:999px; background:#e5e7eb; display:grid; place-items:center; font-size:28px; }
.pName{ font-weight:900; font-size:18px; }
.pMeta{ font-size:13px; color:#6b7280; margin-top:3px; }

.pActions{ display:flex; gap:10px; margin-top:12px; flex-wrap:wrap; }
.pBtn{ flex:1; padding:10px 12px; border-radius:12px; border:1px solid #e5e7eb; background:#fff; font-weight:900; text-align:center; text-decoration:none; color:#111827; }
.pBtn.primary{ background:#111827; color:#fff; }

.rwCard{
  margin-top:14px;
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:16px;
  padding:14px;
}
.rwTop{ display:flex; justify-content:space-between; align-items:center; gap:12px; }
.rwLeft{ display:flex; gap:12px; align-items:center; }
.rwBadge{
  width:44px; height:44px;
  border-radius:14px;
  display:grid; place-items:center;
  font-size:22px; font-weight:900;
  background:#f8fafc;
  border:1px solid #e5e7eb;
}
.rwTitle{ font-weight:950; font-size:15px; }
.rwSub{ font-size:12px; color:#6b7280; font-weight:800; margin-top:3px; }
.rwBtn{
  background:#111827;
  color:#fff;
  border:0;
  padding:10px 12px;
  border-radius:12px;
  font-weight:900;
  cursor:pointer;
}
.rwProgress{ margin-top:12px; }
.rwProgressText{
  display:flex; justify-content:space-between;
  font-size:12px; font-weight:800; color:#6b7280;
  margin-bottom:6px;
}
.rwBar{ height:12px; background:#e5e7eb; border-radius:999px; overflow:hidden; }
.rwFill{ height:100%; background:#10b981; border-radius:999px; }

/* ===== Bottom Sheet ===== */
.rwModal{ display:none; position:fixed; inset:0; z-index:9999; }
.rwModal.open{ display:block; }
.rwOverlay{ position:absolute; inset:0; background:rgba(0,0,0,.35); }

.rwSheet{
  position:absolute; left:0; right:0; bottom:0;
  background:#fff;
  border-radius:18px 18px 0 0;
  padding:16px;
  max-height:80vh;
  overflow:auto;
}
.rwSheetTitle{ font-weight:950; font-size:16px; }
.rwSheetSub{ font-size:12px; color:#6b7280; margin-top:4px; }
.rwList{ margin-top:14px; display:flex; flex-direction:column; gap:10px; }
.rwItem{ display:flex; gap:10px; font-size:13px; font-weight:800; }
.rwDot{ color:#10b981; font-weight:900; }
.rwClose{
  margin-top:14px;
  width:100%;
  background:#111827;
  color:#fff;
  border:0;
  padding:12px;
  border-radius:14px;
  font-weight:950;
}

/* ===== Rent History UI ===== */
.rhList{ margin-top:12px; display:flex; flex-direction:column; gap:10px; }

.rhRow{
  display:flex; gap:10px; align-items:center;
  text-decoration:none; color:inherit;
  border:1px solid #e5e7eb;
  border-radius:14px;
  padding:10px;
  background:#fff;
}
.rhThumb{
  width:54px; height:54px;
  border-radius:12px;
  object-fit:cover;
  background:#f3f4f6;
  border:1px solid #eef2f7;
  flex:0 0 auto;
}
.rhMid{ flex:1; min-width:0; }
.rhTitleLine{ font-weight:950; font-size:13px; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.rhMetaLine{ margin-top:3px; font-size:12px; color:#6b7280; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.rhPriceLine{ margin-top:6px; font-size:12px; font-weight:900; color:#111827; }

/* Status pill base */
.rhSt{
  font-size:12px;
  font-weight:950;
  padding:6px 10px;
  border-radius:999px;
  border:1px solid #e5e7eb;
  background:#f9fafb;
  color:#111827;
  white-space:nowrap;
  flex:0 0 auto;
}
/* 🟢 Completed */
.rhSt.ok{
  background:#ecfdf5;
  border-color:#a7f3d0;
  color:#065f46;
}
/* 🔵 Info (Approved/Returned) */
.rhSt.info{
  background:#eff6ff;
  border-color:#bfdbfe;
  color:#1d4ed8;
}
/* 🔴 Cancelled */
.rhSt.bad{
  background:#fff1f2;
  border-color:#fecdd3;
  color:#9f1239;
}
</style>
</head>

<body>

<?php
@include __DIR__ . "/partials/mobile_appbar.php";
@include __DIR__ . "/partials/mobile_drawer.php";
?>

<div class="profileWrap">

  <div class="pCard">
    <div class="pTop">
      <div class="avatar">👤</div>
      <div>
        <div class="pName"><?= h($name) ?></div>
        <?php if ($email): ?><div class="pMeta"><?= h($email) ?></div><?php endif; ?>
        <?php if ($tp): ?><div class="pMeta">TP: <b><?= h($tp) ?></b></div><?php endif; ?>
      </div>
    </div>

    <div class="pActions">
      <a class="pBtn primary" href="<?= $BASE ?>/edit_profile.php">Edit Profile</a>
      <a class="pBtn" href="<?= $BASE ?>/items/my_items.php">My Items</a>
      <a class="pBtn" href="<?= $BASE ?>/borrow/my_borrow.php">My Rentals</a>

      <?php if ($has_items): ?>
        <a class="pBtn" href="<?= $BASE ?>/borrow/manage_requests.php">Manage Requests</a>
      <?php endif; ?>

      <form action="<?= $BASE ?>/auth/logout.php" method="post" style="flex:1;margin:0;">
        <button class="pBtn" type="submit" style="width:100%;cursor:pointer;">Logout</button>
      </form>
    </div>

    <div class="rwCard">
      <div class="rwTop">
        <div class="rwLeft">
          <div class="rwBadge"><?= h($benefits['badge'] ?? '🌱') ?></div>
          <div>
            <div class="rwTitle"><?= h($eco_level) ?></div>
            <div class="rwSub">
              <?= (int)$eco_points ?> pts · CO₂ <?= number_format((float)$total_co2,1) ?>kg
            </div>
          </div>
        </div>
        <button class="rwBtn" id="btnBenefits">View Benefits</button>
      </div>

      <div class="rwProgress">
        <?php if ($nextPts > 0): ?>
          <div class="rwProgressText">
            <span>Next: <?= h($benefits['next_level'] ?? 'Next Level') ?></span>
            <span><?= (int)$remainPts ?> pts to go</span>
          </div>
          <div class="rwBar"><div class="rwFill" style="width:<?= (float)$progressPct ?>%"></div></div>
        <?php else: ?>
          <div class="rwProgressText">
            <span><b>Max Tier</b></span><span>Keep it up 🌱</span>
          </div>
          <div class="rwBar"><div class="rwFill" style="width:100%"></div></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ===== Rent History (Borrower) ===== -->
  <div class="rwCard" style="margin-top:12px;">
    <div class="rwTop">
      <div class="rwLeft">
        <div class="rwBadge">🧾</div>
        <div>
          <div class="rwTitle">Rent History</div>
          <div class="rwSub">Only Completed / Cancelled</div>
        </div>
      </div>
    </div>

    <?php if (empty($rent_history)): ?>
      <div style="margin-top:12px;color:#6b7280;font-weight:800;font-size:13px;">
        No completed / cancelled rent history yet.
      </div>
    <?php else: ?>
      <div class="rhList">
        <?php foreach($rent_history as $rh): ?>
          <?php
            $img = trim((string)($rh['image'] ?? ''));
            $img_url = $img
              ? ((strpos($img,'/')!==false) ? ($BASE."/uploads/".ltrim($img,'/')) : ($BASE."/uploads/items/".$img))
              : ($BASE."/assets/img/placeholder.png");

            $days = (int)($rh['borrow_days'] ?? 0);
            $time = $rh['br_time'] ?? null;

            $rent_day = isset($rh['rent_day']) ? $rh['rent_day'] : null;
            $rent_day = ($rent_day === null) ? null : (float)$rent_day;
            $total = ($rent_day !== null && $days > 0) ? ($rent_day * $days) : null;

            $status = strtolower((string)($rh['request_status'] ?? ''));
            $stClass = "rhSt";
            $stLabel = ucfirst($status);

            if ($status === 'completed') { $stClass .= " ok"; $stLabel = "Completed"; }
            elseif ($status === 'cancelled' || $status === 'canceled') { $stClass .= " bad"; $stLabel = "Cancelled"; }
            else { $stLabel = "Closed"; }
          ?>

          <a class="rhRow" href="<?= $BASE ?>/items/item_detail.php?id=<?= (int)$rh['item_id'] ?>">
            <img class="rhThumb" src="<?= h($img_url) ?>"
                 onerror="this.onerror=null;this.src='<?= h($BASE) ?>/assets/img/placeholder.png';" alt="">

            <div class="rhMid">
              <div class="rhTitleLine"><?= h($rh['item_name'] ?? 'Item') ?></div>
              <div class="rhMetaLine">
                <?= h($rh['category'] ?? '-') ?> · <?= (int)$days ?> day(s)
                <?php if (!empty($time)): ?> · <?= h($time) ?><?php endif; ?>
              </div>

              <?php if ($total !== null): ?>
                <div class="rhPriceLine">Est. Total: <b>RM<?= number_format((float)$total, 2) ?></b></div>
              <?php else: ?>
                <div class="rhPriceLine" style="opacity:.75;">Est. Total: <b>—</b></div>
              <?php endif; ?>
            </div>

            <div class="<?= h($stClass) ?>"><?= h($stLabel) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ===== Owner Rentals (Approved + Completed) ===== -->
  <div class="rwCard" style="margin-top:12px;">
    <div class="rwTop">
      <div class="rwLeft">
        <div class="rwBadge">💰</div>
        <div>
          <div class="rwTitle">My Rentals</div>
          <div class="rwSub">Approved / Completed</div>
        </div>
      </div>
    </div>

    <?php if (empty($owner_history)): ?>
      <div style="margin-top:12px;color:#6b7280;font-weight:800;font-size:13px;">
        No approved / completed rental records yet.
      </div>
    <?php else: ?>
      <div class="rhList">
        <?php foreach($owner_history as $oh): ?>
          <?php
            $img = trim((string)($oh['image'] ?? ''));
            $img_url = $img
              ? ((strpos($img,'/')!==false) ? ($BASE."/uploads/".ltrim($img,'/')) : ($BASE."/uploads/items/".$img))
              : ($BASE."/assets/img/placeholder.png");

            $days = (int)($oh['borrow_days'] ?? 0);
            $time = $oh['br_time'] ?? null;

            $rent_day = isset($oh['rent_day']) ? $oh['rent_day'] : null;
            $rent_day = ($rent_day === null) ? null : (float)$rent_day;

            $real_total = isset($oh['total_amount']) ? $oh['total_amount'] : null;
            $real_total = ($real_total === null) ? null : (float)$real_total;

            $income = ($real_total !== null && $real_total > 0)
              ? $real_total
              : (($rent_day !== null && $days > 0) ? ($rent_day * $days) : null);

            $status = strtolower((string)($oh['request_status'] ?? ''));

            // ✅ defensive: owner view only approved/completed
            if (!in_array($status, ['approved','completed'], true)) {
              continue;
            }

            $stClass = "rhSt";
            $stLabel = ucfirst($status);

            if ($status === 'completed') { $stClass .= " ok"; $stLabel = "Completed"; }
            elseif ($status === 'approved') { $stClass .= " info"; $stLabel = "Approved"; }
          ?>

          <a class="rhRow" href="<?= $BASE ?>/items/item_detail.php?id=<?= (int)$oh['item_id'] ?>">
            <img class="rhThumb" src="<?= h($img_url) ?>"
                 onerror="this.onerror=null;this.src='<?= h($BASE) ?>/assets/img/placeholder.png';" alt="">

            <div class="rhMid">
              <div class="rhTitleLine"><?= h($oh['item_name'] ?? 'Item') ?></div>
              <div class="rhMetaLine">
                Borrower: <?= h($oh['borrower_name'] ?? '-') ?> · <?= (int)$days ?> day(s)
                <?php if (!empty($time)): ?> · <?= h($time) ?><?php endif; ?>
              </div>

              <?php if ($income !== null): ?>
                <div class="rhPriceLine">
                  Earned: <b>RM<?= number_format((float)$income, 2) ?></b>
                  <?php if ($status === 'completed' && !empty($real_total) && $real_total > 0): ?>
                    <span style="font-size:11px;color:#6b7280;font-weight:800;">(Final)</span>
                  <?php else: ?>
                    <span style="font-size:11px;color:#6b7280;font-weight:800;">(Est.)</span>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <div class="rhPriceLine" style="opacity:.75;">Earned: <b>—</b></div>
              <?php endif; ?>
            </div>

            <div class="<?= h($stClass) ?>"><?= h($stLabel) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- Bottom Sheet -->
<div class="rwModal" id="rwModal">
  <div class="rwOverlay" data-close="1"></div>
  <div class="rwSheet">
    <div class="rwSheetTitle"><?= h($eco_level) ?> Benefits</div>
    <div class="rwSheetSub">Unlocked by your eco points</div>

    <div class="rwList">
      <?php foreach (($benefits['benefits_list'] ?? []) as $b): ?>
        <div class="rwItem"><span class="rwDot">✓</span><?= h($b) ?></div>
      <?php endforeach; ?>
    </div>

    <button class="rwClose" data-close="1">Got it</button>
  </div>
</div>

<nav class="bottomnav">
  <a href="<?= $BASE ?>/index.php"><span>🔍</span>Explore</a>
  <a href="<?= $BASE ?>/items/item_list.php"><span>📋</span>Browse</a>
  <a class="mid" href="<?= $BASE ?>/items/add_item.php"><span>＋</span>Rent</a>
  <a href="<?= $BASE ?>/wishlist/wishlist.php"><span>❤️</span>Saved</a>
  <a class="active" href="<?= $BASE ?>/profile.php"><span>👤</span>Me</a>
</nav>

<script>
(function(){
  const modal = document.getElementById('rwModal');
  const btn = document.getElementById('btnBenefits');
  btn?.addEventListener('click', ()=> modal.classList.add('open'));
  modal?.addEventListener('click', e=>{
    if(e.target.dataset.close) modal.classList.remove('open');
  });
})();
</script>

<script src="<?= $BASE ?>/assets/js/mobile_drawer.js?v=1"></script>

</body>
</html>
