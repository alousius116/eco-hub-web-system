<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();
require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";
$owner_id = (int)($_SESSION['user_id'] ?? 0);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function badge_class($s){
  $s = strtolower(trim((string)$s));
  if($s === 'approved') return "badge green";
  if($s === 'pending')  return "badge";
  if($s === 'rejected') return "badge red";
  if($s === 'returned') return "badge";
  if($s === 'cancelled' || $s === 'canceled') return "badge red";
  return "badge";
}

function nice_date($dt){
  if(!$dt) return "-";
  $t = strtotime($dt);
  if(!$t) return (string)$dt;
  return date('Y-m-d H:i', $t);
}

$ok    = trim($_GET['ok'] ?? '');
$error = trim($_GET['error'] ?? '');

$sql = "
SELECT
  br.request_id,
  br.request_status,
  br.created_at,
  br.borrow_days,
  br.borrower_id,
  u.full_name AS borrower_name,

  i.item_id,
  i.item_name,
  i.rental_price_per_day,
  i.availability_status
FROM borrow_requests br
JOIN items i ON br.item_id = i.item_id
JOIN users u ON br.borrower_id = u.user_id
WHERE i.user_id = ?
ORDER BY br.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $owner_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Manage Requests</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <link rel="stylesheet" href="<?= h($BASE) ?>/assets/css/style.css?v=MANAGE_REQ_A_1">

  <style>
    .page{ max-width: 980px; margin: 12px auto; padding: 0 12px 90px; }

    /* ===== Top Actions UI (Carousell style) ===== */
    .top-actions{ display:flex; gap:10px; flex-wrap:wrap; margin: 6px 0 14px; }
    .top-actions .btn{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:10px 14px;
      border-radius:999px;
      border:1px solid #e6eef0;
      background:#fff;
      font-weight:950;
      font-size:13px;
      text-decoration:none;
      color:#0f172a;
      cursor:pointer;
      box-shadow:0 8px 20px rgba(15,23,42,.06);
      transition: transform .12s ease, filter .12s ease, box-shadow .12s ease;
    }
    .top-actions .btn:hover{
      filter: brightness(1.02);
      box-shadow:0 12px 26px rgba(15,23,42,.08);
    }
    .top-actions .btn:active{ transform: scale(.98); }
    .top-actions .btn.primary{
      background:#0f172a;
      color:#fff;
      border-color:#0f172a;
    }
    .top-actions .ico{
      width:26px;height:26px;
      border-radius:999px;
      display:grid; place-items:center;
      background:#f1f5f9;
      border:1px solid #e6eef0;
      font-size:14px;
    }
    .top-actions .btn.primary .ico{
      background: rgba(255,255,255,.14);
      border-color: rgba(255,255,255,.18);
    }

    .toast{
      background:#0f172a;
      color:#fff;
      border-radius:14px;
      padding:10px 12px;
      margin: 10px 0 14px;
      font-weight:900;
      font-size:13px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
    }
    .toast.ok{ background:#065f46; }
    .toast.err{ background:#7f1d1d; }
    .toast a{ color:#fff; opacity:.9; text-decoration:underline; }

    .cardx{
      background:#fff;
      border:1px solid #e6eef0;
      border-radius:18px;
      padding:14px;
      margin-bottom:12px;
      box-shadow:0 10px 26px rgba(15,23,42,.08);
    }
    .rowTop{
      display:flex; justify-content:space-between; gap:12px; align-items:flex-start;
      flex-wrap:wrap;
    }
    .title{ font-weight:950; font-size:16px; margin:0 0 4px; }
    .muted{ color:#64748b; font-size:13px; font-weight:800; }
    .price{ font-weight:950; color:#10b981; margin-top:6px; }

    .metaRow{ margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .pill{
      display:inline-flex; align-items:center; gap:6px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid #e6eef0;
      background:#f8fafc;
      font-size:12px;
      font-weight:950;
      color:#0f172a;
      white-space:nowrap;
    }
    .pill.days{ background:#eff6ff; border-color:#bfdbfe; }
    .pill.money{ background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
    .pill.status{ background:#f1f5f9; border-color:#cbd5e1; }

    .controls{
      margin-top:12px;
      display:flex; gap:10px; flex-wrap:wrap; align-items:center;
    }
    .btnSmall{
      padding:10px 14px;
      border-radius:999px;
      border:1px solid #e6eef0;
      background:#fff;
      font-weight:950;
      cursor:pointer;
    }
    .btnApprove{ background:#0f172a; color:#fff; border:none; }
    .btnReject{ background:#fff; }

    .hint{
      margin-top:8px;
      font-size:12px;
      color:#64748b;
      font-weight:800;
    }
    .top-actions{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
  margin:8px 0 14px;
}

.act-btn{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:8px 12px;
  border-radius:999px;
  background:#fff;
  border:1px solid #e5e7eb;
  font-size:13px;
  font-weight:900;
  color:#0f172a;
  text-decoration:none;
  box-shadow:0 6px 14px rgba(15,23,42,.06);
}



  </style>
</head>
<body>

<?php
@include __DIR__ . "/../partials/mobile_appbar.php";
@include __DIR__ . "/../partials/mobile_drawer.php";
?>

<div class="top-actions">
  <a class="act-btn back" href="<?= h($BASE) ?>/profile.php">
    ← Back
  </a>
</div>


  <h2 style="margin:6px 0 10px;">Manage Requests</h2>
  <div class="muted" style="margin-bottom:10px;">Requests for items you own.</div>

  <?php if ($ok): ?>
    <div class="toast ok">
      <span><?= h($ok) ?></span>
      <a href="<?= h($BASE) ?>/borrow/manage_requests.php">Dismiss</a>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="toast err">
      <span><?= h($error) ?></span>
      <a href="<?= h($BASE) ?>/borrow/manage_requests.php">Dismiss</a>
    </div>
  <?php endif; ?>

  <?php if (!$res || mysqli_num_rows($res) === 0): ?>
    <div class="muted">No borrow requests yet.</div>
  <?php endif; ?>

  <?php while($row = ($res ? mysqli_fetch_assoc($res) : null)): ?>
    <?php
      $status = strtolower(trim((string)($row['request_status'] ?? 'pending')));
      $days   = (int)($row['borrow_days'] ?? 0);

      $ppd = $row['rental_price_per_day'];
      $ppd_ok = ($ppd !== null && $ppd !== '' && is_numeric($ppd));
      $est_total = ($ppd_ok && $days > 0) ? ((float)$ppd * $days) : null;
    ?>

    <div class="cardx">
      <div class="rowTop">
        <div>
          <div class="title"><?= h($row['item_name'] ?? 'Item') ?></div>
          <div class="muted">
            Borrower: <b><?= h($row['borrower_name'] ?? '-') ?></b> ·
            Requested: <?= h(nice_date($row['created_at'] ?? '')) ?>
          </div>

          <?php if ($ppd_ok): ?>
            <div class="price">RM <?= h(number_format((float)$ppd, 2)) ?> / day</div>
          <?php endif; ?>

          <div class="metaRow">
            <span class="pill days">🗓 Requested Days: <?= $days > 0 ? (int)$days : '-' ?></span>
            <?php if ($est_total !== null): ?>
              <span class="pill money">💰 Est. Total: RM <?= h(number_format((float)$est_total, 2)) ?></span>
            <?php endif; ?>
            <span class="pill status">Item status: <?= h($row['availability_status'] ?? '-') ?></span>
          </div>
        </div>

        <div>
          <span class="<?= h(badge_class($row['request_status'] ?? 'pending')) ?>">
            <?= h(ucfirst($status)) ?>
          </span>
        </div>
      </div>

      <?php if ($status === 'pending'): ?>
        <div class="controls">
          <!-- ✅ Approve now ONLY needs request_id -->
          <form method="POST" action="<?= h($BASE) ?>/borrow/approve_request_process.php" style="margin:0;">
            <input type="hidden" name="request_id" value="<?= (int)$row['request_id'] ?>">
            <button class="btnSmall btnApprove" type="submit">Approve</button>
          </form>

          <form method="POST" action="<?= h($BASE) ?>/borrow/reject_request_process.php" style="margin:0;">
            <input type="hidden" name="request_id" value="<?= (int)$row['request_id'] ?>">
            <button class="btnSmall btnReject" type="submit">Reject</button>
          </form>
        </div>

        <div class="hint">Owner approval is required to confirm the rental.</div>
      <?php else: ?>
        <div class="hint">No action required.</div>
      <?php endif; ?>

    </div>
  <?php endwhile; ?>

</div>


<script src="<?= h($BASE) ?>/assets/js/mobile_drawer.js?v=DRAWER_MANAGE_REQ_A_1"></script>
</body>
</html>

