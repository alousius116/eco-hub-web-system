<?php
require_once __DIR__ . "/../config/db_connect.php";
require_once __DIR__ . "/../auth/admin_guard.php";
require_once __DIR__ . "/../auth/csrf.php";

$BASE = $BASE ?? "/RWDD2408/eco_hub";
$IS_ADMIN_PAGE = true;
$SHOW_BACK = false;

/* Handle actions (POST) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify_or_die();

  $action  = $_POST['action'] ?? '';
  $item_id = (int)($_POST['item_id'] ?? 0);

  if ($item_id > 0) {

    if ($action === 'approve') {
      $stmt = mysqli_prepare($conn, "UPDATE items SET status='approved', rejected_reason=NULL WHERE item_id=?");
      if (!$stmt) {
        header("Location: $BASE/admin/items.php?error=" . urlencode("DB error: " . mysqli_error($conn)));
        exit();
      }
      mysqli_stmt_bind_param($stmt, "i", $item_id);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      header("Location: $BASE/admin/items.php?ok=" . urlencode("Item approved."));
      exit();
    }

    if ($action === 'reject') {
      $reason = trim((string)($_POST['rejected_reason'] ?? ''));
      if ($reason === '') $reason = 'Not provided';

      $stmt = mysqli_prepare($conn, "UPDATE items SET status='rejected', rejected_reason=? WHERE item_id=?");
      if (!$stmt) {
        header("Location: $BASE/admin/items.php?error=" . urlencode("DB error: " . mysqli_error($conn)));
        exit();
      }

      mysqli_stmt_bind_param($stmt, "si", $reason, $item_id);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      header("Location: $BASE/admin/items.php?ok=" . urlencode("Item rejected."));
      exit();
    }
  }

  header("Location: $BASE/admin/items.php?error=" . urlencode("Invalid action."));
  exit();
}

$ok    = $_GET['ok'] ?? '';
$error = $_GET['error'] ?? '';

/* Load items */
$tab = $_GET['tab'] ?? 'pending';
$allowed = ['pending','approved','rejected'];
if (!in_array($tab, $allowed, true)) $tab = 'pending';

$stmt = mysqli_prepare($conn, "
  SELECT i.item_id, i.item_name, i.category, i.status, i.rejected_reason, i.created_at, u.full_name
  FROM items i
  JOIN users u ON i.user_id = u.user_id
  WHERE i.status = ?
  ORDER BY i.created_at DESC
");
if (!$stmt) {
  die("SQL prepare failed: " . htmlspecialchars(mysqli_error($conn)));
}
mysqli_stmt_bind_param($stmt, "s", $tab);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);


?>

<div class="container" style="max-width:1100px;margin:0 auto;padding:16px;">

  <!-- Back to Dashboard -->
  <div style="margin-bottom:12px;">
    <a href="<?= $BASE ?>/admin/dashboard.php" class="admin-link back">← Back</a>
  </div>

  <h2 style="margin:0;">Admin · Items</h2>

  <?php if ($ok): ?>
    <div style="padding:10px 12px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;margin-bottom:10px;">
      <?= htmlspecialchars($ok) ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div style="padding:10px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin-bottom:10px;">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
    <a class="btn" href="<?= $BASE ?>/admin/items.php?tab=pending">Pending</a>
    <a class="btn" href="<?= $BASE ?>/admin/items.php?tab=approved">Approved</a>
    <a class="btn" href="<?= $BASE ?>/admin/items.php?tab=rejected">Rejected</a>
  </div>

  <div style="overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
    <table style="width:100%;border-collapse:collapse;min-width:980px;">
      <thead>
        <tr style="background:#f9fafb;">
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">ID</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Item</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Category</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Owner</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Created</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Reject Reason</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Actions</th>
        </tr>
      </thead>

      <tbody>
        <?php while ($row = mysqli_fetch_assoc($res)): ?>
          <?php
            $status = (string)($row['status'] ?? '');
            $rid = (int)($row['item_id'] ?? 0);
          ?>
          <tr>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= $rid ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= htmlspecialchars($row['item_name'] ?? '') ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= htmlspecialchars($row['category'] ?? '') ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= htmlspecialchars($row['full_name'] ?? '') ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= htmlspecialchars($row['created_at'] ?? '') ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= htmlspecialchars($status) ?></td>

            <td style="padding:10px;border-bottom:1px solid #f1f5f9;color:#64748b;">
              <?php if ($status === 'rejected'): ?>
                <?= htmlspecialchars($row['rejected_reason'] ?? 'Not provided') ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>

            <td style="padding:10px;border-bottom:1px solid #f1f5f9;">

              <?php if ($status === 'pending'): ?>
                <form method="post" style="display:inline-block;margin-right:6px;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="item_id" value="<?= $rid ?>">
                  <button class="btn" type="submit">Approve</button>
                </form>

                <form method="post" style="display:inline-block;"
                      onsubmit="
                        var r = prompt('Reject reason (optional):', '');
                        if (r === null) return false;
                        this.querySelector('input[name=rejected_reason]').value = r.trim();
                        return true;
                      ">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="item_id" value="<?= $rid ?>">
                  <input type="hidden" name="rejected_reason" value="">
                  <button class="btn" type="submit" style="background:#ef4444;color:#fff;border-color:#ef4444;">
                    Reject
                  </button>
                </form>
              <?php else: ?>
                <span style="color:#64748b;">No actions</span>
              <?php endif; ?>

            </td>
          </tr>
        <?php endwhile; ?>

        <?php if (!$res || mysqli_num_rows($res) === 0): ?>
          <tr><td colspan="8" style="padding:14px;color:#64748b;">No items found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
mysqli_stmt_close($stmt);

?>

<!-- Admin button CSS -->
<style>
.admin-link.back {
    display: inline-block;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #374151;          /* dark text */
    background: #f3f4f6;     /* light gray bg */
    border: 1px solid #d1d5db; /* gray border */
    transition: all 0.2s ease;
}
.admin-link.back:hover {
    background: #e5e7eb;
    border-color: #9ca3af;
}

