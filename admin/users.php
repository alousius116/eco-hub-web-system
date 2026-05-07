<?php
require_once __DIR__ . "/../config/db_connect.php";
require_once __DIR__ . "/../auth/admin_guard.php";
require_once __DIR__ . "/../auth/csrf.php";

$BASE = $BASE ?? "/RWDD2408/eco_hub";
$IS_ADMIN_PAGE = true; 
$ok = $_GET['ok'] ?? '';
$error = $_GET['error'] ?? '';

/* Handle toggle (POST) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify_or_die();

  $action = $_POST['action'] ?? '';
  $target_id = (int)($_POST['user_id'] ?? 0);
  $me = (int)($_SESSION['user_id'] ?? 0);

  if ($action === 'toggle' && $target_id > 0) {
    if ($target_id === $me) {
      header("Location: $BASE/admin/users.php?error=" . urlencode("You cannot ban yourself."));
      exit();
    }

    // Toggle status: active <-> inactive
    $stmt = mysqli_prepare($conn, "UPDATE users SET status = IF(status='active','inactive','active') WHERE user_id=?");
    mysqli_stmt_bind_param($stmt, "i", $target_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: $BASE/admin/users.php?ok=" . urlencode("User status updated."));
    exit();
  }

  header("Location: $BASE/admin/users.php?error=" . urlencode("Invalid action."));
  exit();
}

/* Load users */
$res = mysqli_query($conn, "SELECT user_id, full_name, email, role, status, created_at FROM users ORDER BY created_at DESC");

?>

<!-- Admin Users Container -->
<div class="container" style="max-width:1100px;margin:0 auto;padding:16px;">

  <div style="margin-bottom:12px;">
    <a href="<?= $BASE ?>/admin/dashboard.php" class="admin-link back">← Back</a>
    <a href="<?= $BASE ?>/auth/logout.php" class="admin-link logout" style="margin-left:8px;">Logout</a>
  </div>

  <h2 style="margin:8px 0 12px;">Admin · Users</h2>

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

  <div style="overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
    <table style="width:100%;border-collapse:collapse;min-width:900px;">
      <thead>
        <tr style="background:#f9fafb;">
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">ID</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Name</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Email</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Role</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Created</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($u = mysqli_fetch_assoc($res)): ?>
          <tr>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= (int)$u['user_id'] ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= htmlspecialchars($u['email'] ?? '') ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= htmlspecialchars($u['role'] ?? '') ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= htmlspecialchars($u['status'] ?? '') ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;">
              <?php
                $is_admin = (($u['role'] ?? '') === 'admin');
                $is_me = ((int)$u['user_id'] === (int)($_SESSION['user_id'] ?? 0));
                $inactive = (($u['status'] ?? '') === 'inactive');
              ?>

              <?php if ($is_admin): ?>
                <span style="color:#64748b;">Admin</span>
              <?php elseif ($is_me): ?>
                <span style="color:#64748b;">Me</span>
              <?php else: ?>
                <form method="post" style="margin:0;display:inline-block;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                  <button class="btn" type="submit" style="<?= $inactive ? 'background:#10b981;color:#fff;border-color:#10b981;' : 'background:#ef4444;color:#fff;border-color:#ef4444;' ?>">
                    <?= $inactive ? 'Unban' : 'Ban' ?>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

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

.admin-link.logout {
    display: inline-block;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #fff;
    background: #ef4444;
    border: 1px solid #ef4444;
    transition: all 0.2s ease;
}
.admin-link.logout:hover {
    background: #dc2626;
    border-color: #dc2626;
}
</style>