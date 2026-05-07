<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();
require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";
$owner_id = (int)($_SESSION['user_id'] ?? 0);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

function badge_class($status){
  $s = strtolower(trim((string)$status));
  if ($s === 'approved') return "badge green";
  if ($s === 'pending')  return "badge";
  if ($s === 'rejected') return "badge red";
  if ($s === 'returned') return "badge";
  return "badge";
}

function nice_date($dt){
  if(!$dt) return "-";
  $t = strtotime($dt);
  if(!$t) return (string)$dt;
  return date('Y-m-d H:i', $t);
}

$ok    = $_GET['ok'] ?? '';
$error = $_GET['error'] ?? '';

$sql = "
SELECT
  br.request_id,
  br.request_status,
  br.created_at,
  br.borrow_days,
  br.total_cost,
  br.borrow_start_date,
  br.borrow_end_date,

  i.item_id,
  i.item_name,
  i.rental_price_per_day,
  i.availability_status,

  u.full_name AS borrower_name
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
  <title>Manage Borrow Requests</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

  <link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css?v=REQ_MGR_2">

  <style>
    .page{ max-width: 980px; margin: 12px auto; padding: 0 12px 90px; }
    .top-actions{ display:flex; gap:10px; flex-wrap:wrap; margin: 10px 0 16px; }

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

    .req-card{
      background:#fff;
      border:1px solid #e6eef0;
      border-radius:18px;
      box-shadow:0 10px 26px rgba(15,23,42,.08);
      padding:12px;
      margin-bottom:12px;

      display:flex;
      gap:12px;
      align-items:flex-start;
      justify-content:space-between;
    }
    .req-left{ min-width: 0; }
    .req-title{
      font-weight: 950;
      font-size: 16px;
      margin: 0 0 4px;
      line-height:1.2;
    }
    .req-sub{ color:#64748b; font-size: 13px; line-height:1.45; }

    .req-meta{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      align-items:center;
      margin-top:8px;
    }
    .pill{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid #e6eef0;
      background:#f8fafc;
      font-size:12px;
      font-weight:900;
      color:#0f172a;
    }
    .pill.money{ color:#10b981; background:#ecfdf5; border-color:#a7f3d0; }
    .pill.days{ background:#eff6ff; border-color:#bfdbfe; }
    .pill.item{ background:#fff7ed; border-color:#fed7aa; }
    .pill.status{ background:#f1f5f9; border-color:#cbd5e1; color:#0f172a; }

    .req-right{
      display:flex;
      flex-direction:column;
      align-items:flex-end;
      gap:10px;
      flex-shrink:0;
    }
    .btnrow{ display:flex; gap:8px; align-items:center; }
    .btn.small{ padding: 8px 12px; border-radius: 999px; font-weight: 950; }
    .mutedSmall{ font-size:12px; color:#64748b; font-weight:800; }

    .badge{ padding:6px 10px; border-radius:999px; font-weight:900; font-size:12px; border:1px solid #e6eef0; background:#f8fafc; }
    .badge.green{ background:#eafff1; border-color:#b7f7cf; }
    .badge.red{ background:#ffecec; border-color:#ffc0c0; }

    @media(max-width:520px){
      .req-card{ flex-direction:column; align-items:stretch; }
      .req-right{ align-items:flex-start; }
    }
  </style>
</head>

<body>

<?php
@include __DIR__ . "/../partials/mobile_appbar.php";
@include __DIR__ . "/../partials/mobile_drawer.php";
?>

<div class="page">
  <h2 style="margin:8px 0 6px;">Manage Borrow Requests</h2>
  <div class="req-sub" style="margin-bottom:10px;">
    Requests for items you own.
  </div>

  <?php if ($ok): ?>
    <div class="toast ok">
      <span><?= h($ok) ?></span>
      <a href="<?= $BASE ?>/items/manage_requests.php">Dismiss</a>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="toast err">
      <span><?= h($error) ?></span>
      <a href="<?= $BASE ?>/items/manage_requests.php">Dismiss</a>
    </div>
  <?php endif; ?>

  <div class="top-actions">
    <a class="btn" href="<?= $BASE ?>/items/my_items.php">Back to My Items</a>
    <a class="btn" href="<?= $BASE ?>/index.php">Back to Home</a>
  </div>

  <?php if (!$res || mysqli_num_rows($res) === 0): ?>
    <div class="req-sub">No requests yet.</div>
  <?php else: ?>

    <?php while($row = mysqli_fetch_assoc($res)): ?>
      <?php
        $status = strtolower(trim((string)$row['request_status']));
        $days   = (int)($row['borrow_days'] ?? 0);

        $ppd    = $row['rental_price_per_day'];
        $ppd_ok = ($ppd !== null && $ppd !== '' && is_numeric($ppd));
        $total_cost = $row['total_cost'];

        // 预计总价：如果 DB 还没写 total_cost（pending），就用 days * ppd 临时算
        $est_total = null;
        if ($total_cost !== null && $total_cost !== '' && is_numeric($total_cost)) {
          $est_total = (float)$total_cost;
        } elseif ($ppd_ok && $days > 0) {
          $est_total = $days * (float)$ppd;
        }

        $dateLine = "Requested: " . nice_date($row['created_at'] ?? '');
        if ($status === 'approved') {
          $start = $row['borrow_start_date'] ?? '';
          $end   = $row['borrow_end_date'] ?? '';
          if ($start || $end) {
            $dateLine .= "<br>Period: <b>" . h($start ?: '-') . "</b> → <b>" . h($end ?: '-') . "</b>";
          }
        }
      ?>

      <div class="req-card">
        <div class="req-left">
          <div class="req-title"><?= h($row['item_name']) ?></div>
          <div class="req-sub">
            Borrower: <b><?= h($row['borrower_name']) ?></b><br>
            <?= $dateLine ?>
          </div>

          <div class="req-meta">
            <span class="pill item">Item #<?= (int)$row['item_id'] ?></span>
            <span class="pill days">Days: <?= $days > 0 ? $days : '-' ?></span>

            <?php if ($ppd_ok): ?>
              <span class="pill money">RM <?= h(number_format((float)$ppd, 2)) ?>/day</span>
            <?php endif; ?>

            <?php if ($est_total !== null): ?>
              <span class="pill money">Total: RM <?= h(number_format((float)$est_total, 2)) ?></span>
            <?php endif; ?>

            <span class="pill status">Item: <?= h($row['availability_status'] ?? '-') ?></span>
          </div>
        </div>

        <div class="req-right">
          <span class="<?= badge_class($row['request_status']) ?>">
            <?= h(ucfirst($status)) ?>
          </span>

          <div class="btnrow">
            <?php if ($status === 'pending'): ?>
              <form action="<?= $BASE ?>/borrow/approve_request_process.php" method="POST" style="margin:0;">
                <input type="hidden" name="request_id" value="<?= (int)$row['request_id'] ?>">
                <!-- ✅ 防重复 approve：把当前状态一起提交 -->
                <input type="hidden" name="current_status" value="pending">
                <button class="btn dark small" type="submit">Approve</button>
              </form>

              <!-- Reject（如果你还没做 reject_process，先留着注释）
              <form action="<?= $BASE ?>/borrow/reject_request_process.php" method="POST" style="margin:0;">
                <input type="hidden" name="request_id" value="<?= (int)$row['request_id'] ?>">
                <input type="hidden" name="current_status" value="pending">
                <button class="btn small" type="submit">Reject</button>
              </form>
              -->
            <?php else: ?>
              <span class="mutedSmall">
                <?= ($status === 'approved') ? 'Approved ✔' : 'Closed' ?>
              </span>
            <?php endif; ?>
          </div>

        </div>
      </div>
    <?php endwhile; ?>

  <?php endif; ?>

</div>

<script src="<?= $BASE ?>/assets/js/mobile_drawer.js?v=1"></script>
</body>
</html>
