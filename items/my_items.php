<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();
require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";
$user_id = (int)$_SESSION['user_id'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/*
  ✅ include i.status (admin approval status)
  Expected values: pending / approved / rejected
*/
$sql = "
SELECT 
  i.*,
  (
    SELECT COUNT(*) 
    FROM borrow_requests br 
    WHERE br.item_id = i.item_id
      AND br.request_status = 'pending'
  ) AS pending_requests
FROM items i
WHERE i.user_id = ?
ORDER BY i.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>My Items - EcoSwap</title>

  <link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css?v=MYITEMS_2">

  <style>
    .page{ max-width: 980px; margin: 0 auto; padding: 12px 12px 90px; }
    .titleRow{ display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin: 8px 0 12px; }
    .title{ font-size: 22px; font-weight: 950; margin:0; }
    .sub{ color:#6b7280; font-size:13px; margin-top:4px; }

    .actions{ display:flex; gap:10px; flex-wrap:wrap; margin: 10px 0 14px; }
    .btn{
      display:inline-flex; align-items:center; justify-content:center;
      padding:10px 12px; border-radius:12px;
      border:1px solid #e5e7eb; background:#fff;
      text-decoration:none; font-weight:900; color:#111827;
    }
    .btn.primary{ background:#111827; color:#fff; border-color:#111827; }
    .btn.danger{ background:#fee2e2; color:#7f1d1d; border-color:#fecaca; }

    .list{ display:flex; flex-direction:column; gap:12px; }
    .itemCard{
      border:1px solid #e5e7eb;
      background:#fff;
      border-radius:16px;
      padding:12px;
      box-shadow: 0 10px 26px rgba(15,23,42,.06);
    }

    .rowTop{ display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
    .itemName{
      font-weight:950; font-size:15px; line-height:1.2;
      text-decoration:none; color:#111827;
      display:inline-block;
    }
    .meta{ color:#6b7280; font-size:12px; margin-top:6px; line-height:1.45; }

    .pillRow{ margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
    .pill{
      display:inline-flex; align-items:center; gap:6px;
      padding:6px 10px; border-radius:999px;
      border:1px solid #e5e7eb; background:#f9fafb;
      font-size:12px; font-weight:900; color:#111827;
    }
    .pill.green{ background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
    .pill.red{ background:#fee2e2; border-color:#fecaca; color:#7f1d1d; }
    .pill.yellow{ background:#fffbeb; border-color:#fde68a; color:#92400e; }
    .pill.gray{ background:#f3f4f6; border-color:#e5e7eb; color:#374151; }

    .btnRow{ margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; }
    .btn.sm{ padding:8px 10px; border-radius:999px; font-size:12px; font-weight:950; }

    .tableWrap{ display:none; }
    @media (min-width: 900px){
      .list{ display:none; }
      .tableWrap{ display:block; }
      table{ width:100%; border-collapse:collapse; background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; }
      th, td{ padding:12px; border-bottom:1px solid #eef2f7; text-align:left; }
      th{ background:#f9fafb; font-weight:950; font-size:13px; color:#111827; }
      tr:hover td{ background:#fafafa; }
      .link{ color:#111827; font-weight:900; text-decoration:none; }
      .link:hover{ text-decoration:underline; }
    }
  </style>
</head>

<body>

<?php
@include __DIR__ . "/../partials/mobile_appbar.php";
@include __DIR__ . "/../partials/mobile_drawer.php";
?>

<div class="page">
  <div class="titleRow">
    <div>
      <h1 class="title">My Items</h1>
      <div class="sub">Manage items you listed (edit, requests, delete).</div>
    </div>
  </div>

  <div class="actions">
    <a class="btn" href="<?= $BASE ?>/profile.php">← Back to Profile</a>
    <a class="btn" href="<?= $BASE ?>/index.php">Home</a>
    <a class="btn primary" href="<?= $BASE ?>/items/add_item.php">＋ Add Item</a>
  </div>

  <!-- ===== Mobile cards ===== -->
  <div class="list">
    <?php if (!$result || mysqli_num_rows($result) === 0): ?>
      <div class="itemCard">
        <div style="font-weight:950; font-size:15px;">No items yet</div>
        <div class="meta">Tap “Add Item” to start listing.</div>
      </div>
    <?php else: ?>
      <?php while($row = mysqli_fetch_assoc($result)): ?>
        <?php
          $item_id = (int)$row['item_id'];

          // ✅ Admin approval status
          $appr = strtolower((string)($row['status'] ?? 'approved')); // fallback
          $apprClass = "gray";
          $apprLabel = "Approved";

          if ($appr === 'pending') { $apprClass = "yellow"; $apprLabel = "Pending Admin Approval"; }
          else if ($appr === 'rejected') { $apprClass = "red"; $apprLabel = "Rejected"; }
          else { $apprClass = "green"; $apprLabel = "Approved"; }
        ?>
        <div class="itemCard">
          <div class="rowTop">
            <div style="min-width:0;">
              <a class="itemName" href="<?= $BASE ?>/items/item_detail.php?id=<?= $item_id ?>">
                <?= h($row['item_name']) ?>
              </a>
              <div class="meta">
                Category: <b><?= h($row['category']) ?></b><br>
                Item ID: #<?= $item_id ?>
              </div>
            </div>

            <!-- ✅ ONLY show approval pill (remove availability) -->
            <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
              <span class="pill <?= $apprClass ?>"><?= h($apprLabel) ?></span>
            </div>
          </div>

          <?php if ($appr === 'rejected'): ?>
            <div class="meta" style="margin-top:8px;">
              <b>Reject reason:</b> <?= h($row['rejected_reason'] ?? 'Not provided') ?>
            </div>
          <?php endif; ?>

          <div class="btnRow">
            <form action="<?= $BASE ?>/items/delete_item_process.php" method="POST"
                  onsubmit="return confirm('Delete this item?');"
                  style="margin:0;">
              <input type="hidden" name="item_id" value="<?= $item_id ?>">
              <button class="btn danger sm" type="submit">Delete</button>
            </form>

            <a class="btn sm" href="<?= $BASE ?>/items/item_detail.php?id=<?= $item_id ?>">
              View Details →
            </a>
          </div>
        </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>

  <!-- ===== Desktop table ===== -->
  <div class="tableWrap">
    <table>
      <tr>
        <th>Item</th>
        <th>Category</th>
        <th>Approval</th>
        <th>Action</th>
      </tr>

      <?php
      mysqli_stmt_execute($stmt);
      $result2 = mysqli_stmt_get_result($stmt);
      ?>
      <?php while($row = mysqli_fetch_assoc($result2)): ?>
        <?php
          $item_id = (int)$row['item_id'];

          $appr = strtolower((string)($row['status'] ?? 'approved'));
          $apprClass = "green"; $apprLabel = "Approved";
          if ($appr === 'pending'){ $apprClass="yellow"; $apprLabel="Pending"; }
          else if ($appr === 'rejected'){ $apprClass="red"; $apprLabel="Rejected"; }
        ?>
        <tr>
          <td>
            <a class="link" href="<?= $BASE ?>/items/item_detail.php?id=<?= $item_id ?>">
              <?= h($row['item_name']) ?>
            </a>
          </td>
          <td><?= h($row['category']) ?></td>
          <td><span class="pill <?= $apprClass ?>"><?= h($apprLabel) ?></span></td>
          <td style="display:flex; gap:8px; flex-wrap:wrap;">
            <a class="btn sm" href="<?= $BASE ?>/items/item_detail.php?id=<?= $item_id ?>">Details</a>
            <form action="<?= $BASE ?>/items/delete_item_process.php" method="POST"
                  onsubmit="return confirm('Delete this item?');"
                  style="margin:0;">
              <input type="hidden" name="item_id" value="<?= $item_id ?>">
              <button class="btn danger sm" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
    </table>
  </div>

</div>

<script src="<?= $BASE ?>/assets/js/mobile_drawer.js?v=1"></script>
</body>
</html>


