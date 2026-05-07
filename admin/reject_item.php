<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();
require_once __DIR__ . "/../config/db_connect.php";
$BASE="/RWDD2408/eco_hub";


$item_id = (int)($_GET['item_id'] ?? 0);
if ($item_id<=0){
  header("Location: $BASE/admin/items.php");
  exit();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $reason = trim($_POST['reason'] ?? '');
  if($reason==='') $reason = 'Not specified';

  $stmt = mysqli_prepare($conn, "UPDATE items SET status='rejected', rejected_reason=? WHERE item_id=?");
  mysqli_stmt_bind_param($stmt, "si", $reason, $item_id);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);

  header("Location: $BASE/admin/items.php?ok=" . urlencode("Item rejected."));
  exit();
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reject Item</title>
  <link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css?v=ADMIN_REJECT_1">
</head>
<body>
  <div style="max-width:560px;margin:18px auto;padding:0 14px;">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:14px;">
      <h2 style="margin:0 0 8px;">Reject Item</h2>
      <p style="margin:0 0 12px;color:#6b7280;font-weight:800;">Please provide a reason (shown to the owner).</p>

      <form method="post">
        <textarea name="reason" required
          style="width:100%;min-height:120px;padding:10px 12px;border-radius:12px;border:1px solid #e5e7eb;"></textarea>

        <div style="display:flex;gap:10px;margin-top:12px;">
          <button type="submit" class="btn danger">Reject</button>
          <a class="btn" href="<?= $BASE ?>/admin/items.php">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>