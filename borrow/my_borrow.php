<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();
require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";
$user_id = (int)($_SESSION['user_id'] ?? 0);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

/* ✅ focus only 1 item after request */
$focus_item = (int)($_GET['focus_item'] ?? 0);

/* ✅ placeholder */
$PLACEHOLDER = "data:image/svg+xml;utf8," . rawurlencode("
<svg xmlns='http://www.w3.org/2000/svg' width='800' height='600'>
  <rect width='100%' height='100%' fill='#f1f5f9'/>
  <text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle'
   fill='#64748b' font-family='Arial' font-size='28'>No Image</text>
</svg>
");

/* ✅ image helper */
function build_img_url($img, $BASE, $PLACEHOLDER){
  $img = trim((string)$img);
  if ($img === '') return $PLACEHOLDER;
  if (strpos($img, '/') !== false) return $BASE . "/uploads/" . ltrim($img, '/');
  return $BASE . "/uploads/items/" . $img;
}

/* ✅ price per day (ONLY use columns you REALLY have) */
function pick_price_per_day($row){
  foreach (['rental_price_per_day','price'] as $k){
    if (isset($row[$k]) && $row[$k] !== null && $row[$k] !== '' && (float)$row[$k] > 0){
      return (float)$row[$k];
    }
  }
  return null;
}

/* ✅ SQL: pending + approved ONLY */
$sql = "
SELECT
  br.request_id,
  br.item_id,
  br.request_status,
  br.borrow_days,
  br.total_cost,
  br.borrow_start_date,
  br.borrow_end_date,
  br.created_at,
  i.item_name,
  i.image,
  i.rental_price_per_day,
  i.price
FROM borrow_requests br
JOIN items i ON i.item_id = br.item_id
WHERE br.borrower_id = ?
  AND LOWER(br.request_status) IN ('pending','approved')
";

if ($focus_item > 0) {
  $sql .= " AND br.item_id = ? ";
}

$sql .= " ORDER BY br.created_at DESC ";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
  die("SQL prepare failed: " . h(mysqli_error($conn)));
}

if ($focus_item > 0) mysqli_stmt_bind_param($stmt, "ii", $user_id, $focus_item);
else mysqli_stmt_bind_param($stmt, "i", $user_id);

mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>My Rentals</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css">

  <style>
    .page{ max-width:900px; margin:12px auto; padding:0 12px 90px; }

    /* ✅ Back/Browse UI */
    .nav-actions{ display:flex; gap:10px; align-items:center; margin:8px 0 14px; }
    .navBtn{
      display:inline-flex; align-items:center; gap:10px;
      padding:10px 14px;
      border-radius:999px;
      background:#f8fafc;
      border:1px solid #e5e7eb;
      color:#0f172a;
      font-weight:950;
      text-decoration:none;
      box-shadow:0 10px 24px rgba(15,23,42,.08);
      user-select:none;
    }
    .navBtn .ico{
      width:22px; height:22px;
      border-radius:999px;
      display:grid; place-items:center;
      background:#e2e8f0;
      font-size:13px;
      flex:0 0 22px;
    }
    .navBtn.primary{ background:#0f172a; border-color:#0f172a; color:#fff; }
    .navBtn.primary .ico{ background:rgba(255,255,255,.18); }
    .navBtn:active{ transform:scale(.99); }

    /* ✅ Cards */
    .borrow-card{
      display:flex; gap:12px; align-items:flex-start;
      background:#fff; border:1px solid #e6eef0; border-radius:18px;
      padding:12px; margin-bottom:12px;
      box-shadow:0 10px 26px rgba(15,23,42,.08);
      cursor:pointer; position:relative;
    }
    .borrow-card:active{ transform:scale(.999); }

    .borrow-thumb{
      width:86px; height:86px; border-radius:14px; overflow:hidden;
      flex:0 0 86px; background:#f1f5f9; border:1px solid #eef2f7;
    }
    .borrow-thumb img{ width:100%; height:100%; object-fit:cover; display:block; }

    .borrow-main{ flex:1; min-width:0; }
    .borrow-top{ display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }

    .borrow-title{
      font-weight:950; font-size:15px; line-height:1.2;
      margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    }
    .price{ font-size:14px; font-weight:950; color:#10b981; margin-top:4px; }
    .muted{ color:#64748b; font-size:12px; font-weight:800; }

    .borrow-right{ display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex:0 0 auto; }
    .statusBadge{
      padding:6px 10px; border-radius:999px; font-weight:950; font-size:12px;
      border:1px solid #e5e7eb; background:#f8fafc; white-space:nowrap;
    }
    .statusBadge.green{ background:#eafff1; border-color:#b7f7cf; color:#047857; }
    .statusBadge.yellow{ background:#fffbeb; border-color:#fde68a; color:#92400e; }

    .viewHint{
      display:inline-flex; align-items:center; gap:6px;
      font-size:12px; font-weight:950; color:#64748b;
      background:#f8fafc; border:1px solid #e6eef0;
      padding:6px 10px; border-radius:999px;
    }
    .viewHint .chev{ font-size:14px; line-height:1; }

    .chips{ display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .chip{
      display:inline-flex; align-items:center; gap:6px;
      padding:6px 10px; border-radius:999px;
      border:1px solid #e6eef0; background:#f8fafc;
      font-size:12px; font-weight:900; color:#111827;
    }
    .chip .sub{ font-weight:900; color:#64748b; }

    .borrow-actions{ margin-top:10px; display:flex; justify-content:flex-end; gap:8px; }
    .btnPill{
      border:none; padding:10px 12px; border-radius:999px;
      font-weight:950; font-size:13px; cursor:pointer;
      background:#111827; color:#fff;
    }
    .btnPill.secondary{
      background:#f1f5f9; color:#0f172a;
      border:1px solid #e2e8f0;
    }
    .btnPill:active{ transform:scale(.99); }

    @media(max-width:420px){
      .borrow-thumb{ width:76px; height:76px; flex-basis:76px; }
      .borrow-title{ font-size:14px; }
    }
  </style>
</head>
<body>

<?php
@include __DIR__ . "/../partials/mobile_appbar.php";
@include __DIR__ . "/../partials/mobile_drawer.php";
?>

<div class="page">
  <h2 style="margin:8px 0 6px;">My Rentals</h2>

  <div class="nav-actions">
    <a href="<?= $BASE ?>/profile.php" class="navBtn">
      <span class="ico">←</span><span class="txt">Back</span>
    </a>

    <a href="<?= $BASE ?>/items/item_list.php" class="navBtn primary">
      <span class="ico">🔍</span><span class="txt">Browse</span>
    </a>

    <?php if ($focus_item > 0): ?>
      <a href="<?= $BASE ?>/borrow/my_borrow.php" class="navBtn">
        <span class="ico">📄</span><span class="txt">View All</span>
      </a>
    <?php endif; ?>
  </div>

  <?php if(!$res || mysqli_num_rows($res) === 0): ?>
    <div class="card" style="padding:14px;border-radius:16px;">
      <div style="font-weight:900;">No borrow records</div>
      <div class="muted" style="margin-top:6px;">You have no pending/approved borrow requests.</div>
    </div>
  <?php else: ?>

    <?php while($row = mysqli_fetch_assoc($res)):
      $img = build_img_url($row['image'] ?? '', $BASE, $PLACEHOLDER);

      $status = strtolower((string)($row['request_status'] ?? 'pending'));
      $sClass = 'yellow'; $sText = 'Pending';
      if ($status === 'approved') { $sClass = 'green'; $sText = 'Approved'; }

      $detailHref = $BASE . "/items/item_detail.php?id=" . (int)$row['item_id'];

      $ppd = pick_price_per_day($row);
      $days = (int)($row['borrow_days'] ?? 0);
      $total_cost = (isset($row['total_cost']) && $row['total_cost'] !== null) ? (float)$row['total_cost'] : null;

      $start = $row['borrow_start_date'] ?? null;
      $end   = $row['borrow_end_date'] ?? null;
    ?>

      <div class="borrow-card" data-href="<?= h($detailHref) ?>" tabindex="0" role="link" aria-label="View item">
        <div class="borrow-thumb">
          <img src="<?= h($img) ?>" onerror="this.onerror=null;this.src='<?= h($PLACEHOLDER) ?>';" alt="item">
        </div>

        <div class="borrow-main">
          <div class="borrow-top">
            <div style="min-width:0;">
              <div class="borrow-title"><?= h($row['item_name'] ?? 'Item') ?></div>

              <?php if($ppd !== null): ?>
                <div class="price">
                  RM <?= h(number_format($ppd,2)) ?> <span class="muted">/ day</span>
                </div>
              <?php endif; ?>

              <div class="muted" style="margin-top:4px;">
                Requested on <?= h($row['created_at'] ?? '-') ?>
              </div>
            </div>

            <div class="borrow-right">
              <span class="statusBadge <?= h($sClass) ?>"><?= h($sText) ?></span>
              <span class="viewHint"><span>View</span><span class="chev">›</span></span>
            </div>
          </div>

          <?php if ($status === 'approved'): ?>
            <div class="chips">
              <div class="chip">📅 <span class="sub">Period</span> <?= h($start ?: '-') ?> → <?= h($end ?: '-') ?></div>
              <div class="chip">🗓 <span class="sub">Days</span> <?= (int)$days ?></div>
              <div class="chip">💰 <span class="sub">Total</span>
                <?= $total_cost !== null ? ("RM ".h(number_format($total_cost,2))) : "—" ?>
              </div>
            </div>

            <div class="borrow-actions" onclick="event.stopPropagation();">
              <form action="return_item_process.php" method="POST" style="margin:0;" onsubmit="event.stopPropagation();">
                <input type="hidden" name="request_id" value="<?= (int)$row['request_id'] ?>">
                <button class="btnPill" type="submit">Return Item</button>
              </form>
            </div>

          <?php else: /* pending */ ?>
            <div class="chips">
              <div class="chip">⏳ <span class="sub">Status</span> Waiting for owner approval</div>
            </div>

            <div class="borrow-actions" onclick="event.stopPropagation();">
              <form action="cancel_request_process.php" method="POST" style="margin:0;"
                    onsubmit="event.stopPropagation();return confirm('Cancel this request?');">
                <input type="hidden" name="request_id" value="<?= (int)$row['request_id'] ?>">
                <button class="btnPill secondary" type="submit">Cancel Request</button>
              </form>
            </div>
          <?php endif; ?>

        </div>
      </div>

    <?php endwhile; ?>

  <?php endif; ?>
</div>

<script src="<?= $BASE ?>/assets/js/mobile_drawer.js"></script>

<script>
  // ✅ card click -> item_detail (ignore clicks on buttons/forms)
  document.querySelectorAll('.borrow-card').forEach(card => {
    const go = () => {
      const href = card.getAttribute('data-href');
      if (href) window.location.href = href;
    };
    card.addEventListener('click', (e) => {
      const t = e.target;
      if (t.closest('button, a, input, form, textarea, select')) return;
      go();
    });
    card.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); }
    });
  });
</script>

</body>
</html>
