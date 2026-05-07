<?php
require_once __DIR__ . "/../config/db_connect.php";

require_once __DIR__ . "/../auth/csrf.php";

$BASE = "/RWDD2408/eco_hub";

// ✅ 给 appbar 用：显示 back 回 Browse
$SHOW_BACK = true;
$BACK_HREF = $BASE . "/items/item_list.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

$is_login = !empty($_SESSION['user_id']);
$item_id = (int)($_GET['id'] ?? 0);

if ($item_id <= 0) {
  header("Location: $BASE/items/item_list.php");
  exit();
}

/* ✅ get item (prepared) */
$sql = "SELECT i.*, u.full_name
        FROM items i
        JOIN users u ON i.user_id = u.user_id
        WHERE i.item_id = ?
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if(!$stmt){
  header("Location: $BASE/items/item_list.php");
  exit();
}
mysqli_stmt_bind_param($stmt, "i", $item_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$res || mysqli_num_rows($res) === 0) {
  header("Location: $BASE/items/item_list.php");
  exit();
}
$item = mysqli_fetch_assoc($res);

/* owner */
$owner_id = (int)($item['user_id'] ?? 0);
$is_owner = ($is_login && $owner_id === (int)($_SESSION['user_id'] ?? 0));

/* liked? */
$is_liked = false;
if ($is_login) {
  $q = mysqli_prepare($conn, "SELECT 1 FROM wishlist WHERE user_id=? AND item_id=? LIMIT 1");
  if($q){
    mysqli_stmt_bind_param($q, "ii", $_SESSION['user_id'], $item_id);
    mysqli_stmt_execute($q);
    $rr = mysqli_stmt_get_result($q);
    $is_liked = ($rr && mysqli_num_rows($rr) > 0);
  }
}

/* ✅ approval status */
$appr = strtolower(trim((string)($item['status'] ?? 'approved')));
$isApproved = ($appr === 'approved');
$apprLabel = 'Approved';
$apprClass = 'green';
if ($appr === 'pending') { $apprLabel = 'Pending Admin Approval'; $apprClass = 'yellow'; }
else if ($appr === 'rejected') { $apprLabel = 'Rejected'; $apprClass = 'red'; }

/* RENT ONLY – unified rental price */
$rent_day =
  $item['rental_price_per_day']
  ?? $item['rent_price']
  ?? $item['rental_price']
  ?? null;

$rent_day = ($rent_day !== null) ? (float)$rent_day : null;

/* ===== Reward (discount) ===== */
require_once __DIR__ . "/../includes/reward_helper.php";

$benefits = ['discount'=>0, 'badge'=>'', 'featured'=>false];
if ($is_login) {
  $sus = getUserSustainability($conn, (int)$_SESSION['user_id']);
  $benefits = getLevelBenefits($sus['eco_level'] ?? '');
}

$discount_pct = (int)($benefits['discount'] ?? 0);

/* discount applies to rental per day */
$final_rent_day = $rent_day;
if ($rent_day !== null && $discount_pct > 0) {
  $final_rent_day = $rent_day * (1 - $discount_pct / 100);
}

/* eco impact: rough co2 calculator */
function co2_saved_kg($category, $condition){
  $cat = strtolower(trim((string)$category));
  $cond = strtolower(trim((string)$condition));

  $base = 0.6; // default
  if (strpos($cat, 'mobile') !== false || strpos($cat, 'phone') !== false) $base = 1.1;
  else if (strpos($cat, 'laptop') !== false || strpos($cat, 'computer') !== false) $base = 2.0;
  else if (strpos($cat, 'camera') !== false) $base = 1.8;
  else if (strpos($cat, 'gaming') !== false || strpos($cat, 'console') !== false) $base = 1.5;
  else if (strpos($cat, 'audio') !== false || strpos($cat, 'headphone') !== false) $base = 0.8;
  else if (strpos($cat, 'fashion') !== false || strpos($cat, 'women') !== false || strpos($cat, 'men') !== false) $base = 0.6;
  else if (strpos($cat, 'furniture') !== false) $base = 3.5;
  else if (strpos($cat, 'appliance') !== false || strpos($cat, 'electronics') !== false) $base = 1.2;

  $mul = 1.0;
  if ($cond === 'like new') $mul = 1.1;
  else if ($cond === 'good') $mul = 1.0;
  else if ($cond === 'fair') $mul = 0.8;

  return round($base * $mul, 1);
}

$avail = strtolower(trim((string)($item['availability_status'] ?? '')));
$is_avail = ($isApproved && $avail === 'available');   // ✅ pending/rejected = false
$is_rentable = $is_avail;

/* ✅ check if current user already requested this item */
$my_request_status = null;
$has_active_request = false;

if ($is_login && !$is_owner) {
  $q = "SELECT request_status
        FROM borrow_requests
        WHERE item_id=? AND borrower_id=?
        ORDER BY created_at DESC
        LIMIT 1";
  $st = mysqli_prepare($conn, $q);
  if($st){
    mysqli_stmt_bind_param($st, "ii", $item_id, $_SESSION['user_id']);
    mysqli_stmt_execute($st);
    $rr = mysqli_stmt_get_result($st);
    if ($rr && mysqli_num_rows($rr) > 0) {
      $rowReq = mysqli_fetch_assoc($rr);
      $my_request_status = strtolower(trim((string)$rowReq['request_status']));
      $has_active_request = in_array($my_request_status, ['pending','approved']);
    }
  }
}

/* image */
$img = trim($item['image'] ?? '');
$img_url = '';
if ($img !== '') {
  if (strpos($img, '/') !== false) $img_url = $BASE . "/uploads/" . ltrim($img, '/');
  else $img_url = $BASE . "/uploads/items/" . $img;
}

/* fallback image */
$placeholder = "data:image/svg+xml;utf8," . rawurlencode('
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600">
<rect width="100%" height="100%" fill="#f1f5f9"/>
<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
 fill="#64748b" font-family="Arial" font-size="28">No Image</text>
</svg>');

/* eco numbers */
$co2 = co2_saved_kg($item['category'] ?? '', $item['condition_status'] ?? '');

$NETFLIX_KG_PER_HR   = 0.037;
$PHONE_KG_PER_CHARGE = 0.006;
$LAUNDRY_KG_PER_LOAD = 0.2;

$netflixHours = max(1, (int) round($co2 / $NETFLIX_KG_PER_HR));
$phoneCharges = max(1, (int) round($co2 / $PHONE_KG_PER_CHARGE));
$laundryLoads = max(1, (int) round($co2 / $LAUNDRY_KG_PER_LOAD));

$showLimitedTime = ($discount_pct > 0);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= h($item['item_name']) ?> - EcoSwap</title>
  <link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css?v=DETAIL_REWARD_2">

  <style>
    /* layout */
    .page{ max-width:1100px; margin: 12px auto; padding: 0 12px; padding-bottom: 90px; }
    .wrap{ display:grid; grid-template-columns: 1.2fr 1fr; gap: 18px; }
    @media(max-width:900px){ .wrap{ grid-template-columns: 1fr; } }

    .panel{ background:#fff; border:1px solid #e6eef0; border-radius:22px; box-shadow: 0 12px 30px rgba(15,23,42,.10); }
    .imgbox{ overflow:hidden; border-radius:22px; }
    .imgbox img{ width:100%; height: 460px; object-fit: cover; display:block; background:#f8fafc; }
    @media(max-width:900px){ .imgbox img{ height: 360px; } }

    .info{ padding: 16px; }
    .title{ font-weight:900; font-size:26px; margin: 0; }
    .muted{ color:#64748b; font-size:14px; line-height:1.55; }
    .meta{ display:flex; flex-wrap:wrap; gap:8px; margin: 10px 0 14px; align-items:center; }
    .badge{
      padding:6px 10px; border-radius:999px; font-weight:800; font-size:12px;
      border:1px solid #e6eef0; background:#f8fafc;
      display:inline-flex; align-items:center;
      gap:8px;
    }
    .badge.green{ background:#eafff1; border-color:#b7f7cf; }
    .badge.red{ background:#ffecec; border-color:#ffc0c0; }
    .badge.yellow{ background:#fffbeb; border-color:#fde68a; color:#92400e; }

    .desc{ margin-top: 14px; padding-top: 14px; border-top: 1px solid #eef2f7; }
    .btnrow{ display:flex; gap:10px; flex-wrap:wrap; margin-top: 16px; }

    .btn{
      display:inline-flex; align-items:center; justify-content:center;
      padding: 10px 14px; border-radius:999px;
      text-decoration:none; font-weight:900;
      border:1px solid #e6eef0; background:#fff; color:#0f172a;
      cursor:pointer;
      min-height:44px;
    }
    .btn.dark{ background:#0f172a; color:#fff; border:none; }
    .btn.disabled{ opacity:.55; pointer-events:none; }

    .co2Badge{
      font-weight:900;
      font-size:12px;
      color:#059669;
      background:#ecfdf5;
      border:1px solid #a7f3d0;
      padding:4px 8px;
      border-radius:999px;
      display:inline-flex;
      align-items:center;
      gap:6px;
      cursor:pointer;
    }

    /* ===== price display ===== */
    .priceBlock{ margin:6px 0 10px; display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; }
    .priceNew{ font-size:24px; font-weight:950; color:#10b981; display:flex; align-items:baseline; gap:8px; }
    .priceOld{ font-size:14px; font-weight:900; color:#94a3b8; text-decoration:line-through; }
    .perDay{ font-size:14px; font-weight:900; opacity:.85; }

    .discTag{
      font-size:12px; font-weight:950;
      padding:6px 10px;
      border-radius:999px;
      background:#111827;
      color:#fff;
      display:inline-flex;
      gap:6px;
      align-items:center;
      white-space:nowrap;
    }
    .discNote{
      width:100%;
      font-size:12px;
      font-weight:800;
      color:#6b7280;
      margin-top:2px;
    }

    .ltTag{
      font-size:11px;
      font-weight:950;
      padding:6px 10px;
      border-radius:999px;
      background:#fff7ed;
      color:#9a3412;
      border:1px solid #fed7aa;
      display:inline-flex;
      align-items:center;
      gap:6px;
      white-space:nowrap;
      animation: ltPulse 1.6s ease-in-out infinite;
    }
    @keyframes ltPulse{
      0%,100%{ transform: translateY(0); box-shadow: 0 0 0 rgba(234,88,12,0); }
      50%{ transform: translateY(-1px); box-shadow: 0 10px 26px rgba(234,88,12,.12); }
    }

    /* Eco Impact Modal */
    .ecoOverlay{
      position: fixed; inset: 0;
      background: rgba(0,0,0,.55);
      opacity: 0; pointer-events: none;
      transition: opacity .22s ease;
      z-index: 200000;
    }
    .ecoOverlay.show{ opacity: 1; pointer-events: auto; }

    .ecoSheet{
      position: fixed; left: 50%; bottom: 0;
      transform: translateX(-50%) translateY(110%);
      width: min(560px, calc(100vw - 18px));
      background: #0b1220;
      color: #e5e7eb;
      border-radius: 18px 18px 0 0;
      box-shadow: 0 -18px 40px rgba(0,0,0,.35);
      z-index: 200001;
      padding-bottom: env(safe-area-inset-bottom);
      transition: transform .32s cubic-bezier(.2, .9, .2, 1), opacity .22s ease;
      opacity: 0;
      will-change: transform, opacity;
    }
    .ecoSheet.show{ transform: translateX(-50%) translateY(0); opacity: 1; }

    .ecoGrab{
      width: 44px; height: 5px;
      background: rgba(255,255,255,.25);
      border-radius: 99px;
      margin: 10px auto 6px;
      animation: ecoGrabPulse 1.6s ease-in-out infinite;
    }
    @keyframes ecoGrabPulse{
      0%,100%{ opacity: .45; transform: translateY(0); }
      50%{ opacity: .85; transform: translateY(-1px); }
    }

    .ecoHead{ padding: 10px 16px 0; }
    .ecoTag{
      display: inline-block;
      font-size: 12px;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.12);
    }

    .ecoBody{ padding: 14px 16px 16px; }
    .ecoLine{ font-size: 14px; line-height:1.45; color: rgba(229,231,235,.92); }

    .ecoBig{
      margin: 10px 0 12px;
      font-size: 44px;
      font-weight: 950;
      letter-spacing: -0.5px;
      text-align: center;
      transform: scale(.92);
      opacity: 0;
    }
    .ecoSheet.show .ecoBig{ animation: ecoPop .42s cubic-bezier(.2, .9, .2, 1) .05s both; }
    @keyframes ecoPop{
      0%{ transform: scale(.92); opacity: 0; }
      70%{ transform: scale(1.03); opacity: 1; }
      100%{ transform: scale(1); opacity: 1; }
    }

    .ecoSub{
      text-align:center;
      color: rgba(229,231,235,.75);
      font-size: 14px;
      margin-bottom: 12px;
      opacity: 0;
      transform: translateY(6px);
    }
    .ecoSheet.show .ecoSub{ animation: ecoFadeUp .28s ease .10s both; }

    .ecoLine, .ecoFootnote{ opacity: 0; transform: translateY(6px); }
    .ecoSheet.show .ecoLine{ animation: ecoFadeUp .28s ease .02s both; }
    .ecoSheet.show .ecoFootnote{ animation: ecoFadeUp .28s ease .32s both; }

    @keyframes ecoFadeUp{ to{ opacity: 1; transform: translateY(0); } }

    .ecoCards{ display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    @media(max-width:420px){ .ecoCards{ grid-template-columns: 1fr; } }

    .ecoCard{
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.10);
      border-radius: 14px;
      padding: 10px;
      min-height: 86px;
      display: flex;
      gap: 10px;
      align-items: center;
      opacity: 0;
      transform: translateY(10px);
      transition: transform .15s ease, background .15s ease;
    }
    .ecoSheet.show .ecoCard:nth-child(1){ animation: ecoCardIn .32s ease .14s both; }
    .ecoSheet.show .ecoCard:nth-child(2){ animation: ecoCardIn .32s ease .20s both; }
    .ecoSheet.show .ecoCard:nth-child(3){ animation: ecoCardIn .32s ease .26s both; }
    @keyframes ecoCardIn{ to{ opacity: 1; transform: translateY(0); } }

    .ecoCard:active{ transform: scale(.985); }
    .ecoIcon{ font-size: 30px; }
    .ecoCardTitle{ font-weight: 950; font-size: 13px; color: #fff; line-height: 1.2; }
    .ecoCardSmall{ font-size: 12px; color: rgba(229,231,235,.75); margin-top: 2px; }

    .ecoFootnote{ margin: 12px 4px 14px; font-size: 12px; color: rgba(229,231,235,.6); line-height: 1.4; }

    .ecoOkBtn{
      width: 100%;
      border: none;
      padding: 12px 14px;
      border-radius: 14px;
      font-weight: 950;
      font-size: 16px;
      cursor: pointer;
      background: #10b981;
      color: #052016;
      transform: translateY(8px);
      opacity: 0;
    }
    .ecoSheet.show .ecoOkBtn{ animation: ecoFadeUp .28s ease .38s both; }
    .ecoOkBtn:active{ transform: scale(.99); }

    /* ✅ toast */
    #toast{
      position:fixed; left:50%; bottom:92px; transform:translateX(-50%);
      background:#0f172a; color:#fff; padding:10px 14px; border-radius:999px;
      font-weight:900; font-size:13px; opacity:0; pointer-events:none;
      transition:opacity .18s ease, transform .18s ease;
      z-index:250000;
      max-width: calc(100vw - 24px);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    #toast.show{ opacity:1; transform:translateX(-50%) translateY(-6px); }
  </style>
</head>

<body>
<?php
@include __DIR__ . "/../partials/mobile_appbar.php";
@include __DIR__ . "/../partials/mobile_drawer.php";
?>

<div class="page">
  <div class="wrap">

    <!-- Left: Image -->
    <div class="panel imgbox">
      <img src="<?= $img_url ? h($img_url) : $placeholder ?>"
           onerror="this.onerror=null;this.src='<?= $placeholder ?>';"
           alt="<?= h($item['item_name']) ?>">
    </div>

    <!-- Right: Info -->
    <div class="panel info">

      <div class="headRow" style="display:flex; gap:10px; align-items:center; justify-content:space-between;">
        <div class="title"><?= h($item['item_name']) ?></div>

        <button
          type="button"
          id="btnLike"
          class="btn"
          aria-label="<?= $is_liked ? 'Remove from wishlist' : 'Add to wishlist' ?>"
          data-liked="<?= $is_liked ? '1' : '0' ?>"
          style="gap:8px;">
          <span id="likeIcon"><?= $is_liked ? '❤️' : '🤍' ?></span>
          <span id="likeText"><?= $is_liked ? 'Liked' : 'Like' ?></span>
        </button>
      </div>

      <?php if ($rent_day !== null): ?>
        <div class="priceBlock">
          <?php if ($discount_pct > 0): ?>
            <div class="priceOld">RM <?= h(number_format($rent_day, 2)) ?></div>

            <div class="priceNew">
              RM <?= h(number_format($final_rent_day, 2)) ?>
              <span class="discTag">🌱 <?= (int)$discount_pct ?>% OFF</span>
              <?php if ($showLimitedTime): ?>
                <span class="ltTag">⏳ Limited Time</span>
              <?php endif; ?>
              <span class="perDay">/ day</span>
            </div>

            <div class="discNote">
              Eco discount applied based on your tier (<?= h($benefits['badge'] ?? '') ?>).
            </div>
          <?php else: ?>
            <div class="priceNew">
              RM <?= h(number_format($final_rent_day, 2)) ?>
              <span class="perDay">/ day</span>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="muted" style="margin:6px 0 10px;">Rental price not set</div>
      <?php endif; ?>

      <div class="meta">
        <span class="badge"><?= h($item['category'] ?? 'Item') ?></span>

        <span class="badge">
          <?= h($item['condition_status'] ?? 'Good') ?>
          <button type="button" class="co2Badge" onclick="openEcoModal()" aria-label="View eco impact">
            🌱 Saves <?= h($co2) ?>kg of CO2
          </button>
        </span>

        <span class="badge <?= h($apprClass) ?>"><?= h($apprLabel) ?></span>
      </div>

      <?php if ($appr === 'rejected'): ?>
        <div class="muted" style="margin-top:8px;">
          <b>Reject reason:</b> <?= h($item['rejected_reason'] ?? 'Not provided') ?>
        </div>
      <?php endif; ?>

      <div class="muted">
        Owner: <b><?= h($item['full_name']) ?></b><br>
        Posted: <?= h($item['created_at'] ?? '-') ?>
      </div>

      <div class="desc">
        <div style="font-weight:900; margin-bottom:6px;">Description</div>
        <div class="muted">
          <?= nl2br(h($item['description'] ?? 'No description provided.')) ?>
        </div>
      </div>

      <div class="btnrow">
        <?php if ($is_owner): ?>
          <span class="btn disabled">This is your item</span>

        <?php else: ?>

          <?php if ($is_rentable): ?>
            <?php if ($is_login): ?>

              <?php if ($has_active_request): ?>
                <span class="btn dark disabled">
                  Requested to rent<?= ($my_request_status === 'approved' ? ' (Approved)' : '') ?>
                </span>

                <a class="btn" href="<?= $BASE ?>/borrow/my_borrow.php?focus_item=<?= (int)$item_id ?>">
                  View in My Borrow
                </a>

              <?php else: ?>
                <form method="POST" action="<?= $BASE ?>/borrow/request_borrow_process.php"
                      style="margin:0; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">

                  <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                  <?= csrf_field() ?>

                  <label class="badge" style="gap:8px;">
                    Days
                    <input
                      type="number"
                      name="borrow_days"
                      min="1"
                      max="30"
                      value="3"
                      required
                      style="width:72px; padding:8px 10px; border-radius:12px; border:1px solid #e6eef0; font-weight:900;"
                    >
                  </label>

                  <button class="btn dark" type="submit">Request to Rent</button>
                </form>
              <?php endif; ?>

              <a class="btn" href="<?= $BASE ?>/chat/chat.php?user=<?= $owner_id ?>&item_id=<?= (int)$item_id ?>">
                Chat Owner
              </a>

            <?php else: ?>
              <a class="btn dark" href="<?= $BASE ?>/auth/login.php">Login to Request</a>
            <?php endif; ?>

          <?php else: ?>
            <?php if (!$isApproved): ?>
              <span class="btn disabled">Pending Admin Approval</span>
            <?php else: ?>
              <span class="btn disabled">Not Available</span>
            <?php endif; ?>
          <?php endif; ?>

        <?php endif; ?>

        <a class="btn" href="<?= $BASE ?>/items/item_list.php?cat=<?= urlencode($item['category'] ?? '') ?>">
          More in <?= h($item['category'] ?? 'Category') ?>
        </a>
      </div>

    </div>
  </div>
</div>

<div id="toast"></div>

<div id="ecoOverlay" class="ecoOverlay" aria-hidden="true"></div>

<div id="ecoSheet" class="ecoSheet" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="ecoGrab"></div>

  <div class="ecoHead">
    <div class="ecoTag">Eco Impact</div>
  </div>

  <div class="ecoBody">
    <div class="ecoLine">
      By choosing this secondhand item on EcoSwap instead of new, you potentially help to save
    </div>

    <div class="ecoBig">
      ~<span id="ecoCo2Num" data-target="<?= h($co2) ?>">0.0</span>kg CO2
    </div>

    <div class="ecoSub">It's roughly equal to</div>

    <div class="ecoCards">
      <div class="ecoCard">
        <div class="ecoIcon">🍿</div>
        <div class="ecoCardText">
          <div class="ecoCardTitle">
            Watching <span class="ecoCount" data-target="<?= (int)$netflixHours ?>">0</span>h
          </div>
          <div class="ecoCardSmall">of Netflix</div>
        </div>
      </div>

      <div class="ecoCard">
        <div class="ecoIcon">🔋</div>
        <div class="ecoCardText">
          <div class="ecoCardTitle">Charging your</div>
          <div class="ecoCardSmall">
            phone <span class="ecoCount" data-target="<?= (int)$phoneCharges ?>">0</span> times
          </div>
        </div>
      </div>

      <div class="ecoCard">
        <div class="ecoIcon">🧺</div>
        <div class="ecoCardText">
          <div class="ecoCardTitle">
            Doing <span class="ecoCount" data-target="<?= (int)$laundryLoads ?>">0</span> loads
          </div>
          <div class="ecoCardSmall">of laundry</div>
        </div>
      </div>
    </div>

    <div class="ecoFootnote">
      The avoided emission shown is an estimate within the item's category, calculated based on average factors.
    </div>

    <button type="button" class="ecoOkBtn" onclick="closeEcoModal()">Okay</button>
  </div>
</div>

<script>
function showToast(msg){
  const t = document.getElementById('toast');
  if(!t) return;
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(window.__toastTimer);
  window.__toastTimer = setTimeout(()=> t.classList.remove('show'), 1400);
}

function animateNumber(el, to, duration=650, decimals=0){
  const start = performance.now();
  const from = 0;

  function frame(t){
    const p = Math.min(1, (t - start) / duration);
    const eased = 1 - Math.pow(1 - p, 3);
    const val = from + (to - from) * eased;
    el.textContent = decimals ? val.toFixed(decimals) : Math.round(val);
    if(p < 1) requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
}

function runEcoCounters(){
  const co2El = document.getElementById('ecoCo2Num');
  if (co2El){
    const target = parseFloat(co2El.dataset.target || '0');
    co2El.textContent = '0.0';
    animateNumber(co2El, target, 720, 1);
  }

  document.querySelectorAll('#ecoSheet .ecoCount').forEach((el, idx)=>{
    const target = parseInt(el.dataset.target || '0', 10);
    el.textContent = '0';
    animateNumber(el, target, 520 + idx*80, 0);
  });
}

function openEcoModal(){
  const o = document.getElementById('ecoOverlay');
  const s = document.getElementById('ecoSheet');
  if (!o || !s) return;

  o.classList.add('show');
  s.classList.add('show');
  document.body.style.overflow = 'hidden';

  setTimeout(runEcoCounters, 120);
}

function closeEcoModal(){
  const o = document.getElementById('ecoOverlay');
  const s = document.getElementById('ecoSheet');
  if (!o || !s) return;

  o.classList.remove('show');
  s.classList.remove('show');
  document.body.style.overflow = '';
}

document.addEventListener('click', (e)=>{
  if(e.target && e.target.id === 'ecoOverlay') closeEcoModal();
});
document.addEventListener('keydown', (e)=>{
  if(e.key === 'Escape') closeEcoModal();
});
</script>

<script src="<?= $BASE ?>/assets/js/mobile_drawer.js?v=DRAWER_DETAIL_2"></script>

<script>
(function(){
  const btn = document.getElementById('btnLike');
  if(!btn) return;

  const icon = document.getElementById('likeIcon');
  const text = document.getElementById('likeText');

  btn.addEventListener('click', async () => {
    btn.disabled = true;

    const fd = new FormData();
    fd.append('item_id', '<?= (int)$item_id ?>');

    try{
      const res = await fetch('<?= $BASE ?>/wishlist/toggle_like.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });

      const data = await res.json();
      if(!data.ok){
        showToast(data.error || 'Failed.');
        return;
      }

      const liked = !!data.liked;
      btn.dataset.liked = liked ? '1' : '0';

      icon.textContent = liked ? '❤️' : '🤍';
      text.textContent = liked ? 'Liked' : 'Like';

      const badge = document.querySelector('[data-wishlist-badge]');
      if(badge && typeof data.count !== 'undefined'){
        badge.textContent = data.count;
        badge.style.display = data.count > 0 ? 'inline-flex' : 'none';
      }

      showToast(liked ? 'Saved to wishlist' : 'Removed from wishlist');

    }catch(e){
      showToast('Network error.');
    }finally{
      btn.disabled = false;
    }
  });
})();
</script>

</body>
</html>
