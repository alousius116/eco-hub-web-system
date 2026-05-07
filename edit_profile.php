<?php
require_once __DIR__ . "/config/db_connect.php";
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$BASE = "/RWDD2408/eco_hub";
$error = $_GET['error'] ?? '';
$ok    = $_GET['ok'] ?? '';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['user_id'])) {
  header("Location: $BASE/auth/login.php");
  exit();
}

$uid = (int)$_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "
  SELECT full_name, display_name, tp_number
  FROM users
  WHERE user_id = ?
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $uid);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);

if (!$user) {
  header("Location: $BASE/profile.php?error=" . urlencode("User not found."));
  exit();
}

$email = strtolower($user['tp_number']) . "@mail.apu.edu.my";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Edit Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= h($BASE) ?>/assets/css/style.css">

  <!-- Page-only polish -->
  <style>
    body{ background:#f6f7fb; }

    .ep-wrap{
      max-width:520px;
      margin:0 auto;
      padding:16px;
      padding-bottom:90px;
    }

    .ep-header{
      display:flex;
      align-items:center;
      gap:10px;
      margin-bottom:12px;
    }

    .ep-back{
      text-decoration:none;
      font-size:14px;
      color:#374151;
    }

    .ep-title{
      font-size:22px;
      font-weight:900;
      margin:8px 0 4px;
    }

    .ep-sub{
      font-size:13px;
      color:#6b7280;
      margin-bottom:14px;
    }

    .ep-card{
      background:#fff;
      border:1px solid #e5e7eb;
      border-radius:16px;
      padding:16px;
      margin-bottom:14px;
    }

    .ep-card h4{
      margin:0 0 12px;
      font-size:15px;
      font-weight:800;
    }

    .ep-field{
      margin-bottom:12px;
    }

    .ep-field label{
      display:block;
      font-size:12px;
      font-weight:700;
      color:#6b7280;
      margin-bottom:4px;
    }

    .ep-field input{
      width:100%;
      padding:10px 12px;
      border-radius:12px;
      border:1px solid #e5e7eb;
      font-size:14px;
      background:#fff;
    }

    .ep-field input:disabled{
      background:#f3f4f6;
      color:#6b7280;
    }

    .ep-btn{
      width:100%;
      padding:12px;
      border-radius:14px;
      border:none;
      background:#111827;
      color:#fff;
      font-size:15px;
      font-weight:900;
      cursor:pointer;
    }

    .ep-note{
      font-size:12px;
      color:#6b7280;
      margin-top:6px;
    }
  </style>
</head>
<body>

<div class="ep-wrap">

  <div class="ep-header">
    <a class="ep-back" href="javascript:history.back()">← Back</a>
  </div>

  <div class="ep-title">Edit Profile</div>
  <div class="ep-sub">
    Update how your profile appears on EcoSwap.
  </div>

  <!-- ✅ IMPORTANT: use absolute path so it never 404 -->
  <form method="post" action="<?= $BASE ?>/update_profile.php">

    <?php if ($error): ?>
  <div style="margin:10px 0;background:#ffecec;border:1px solid #ffc0c0;color:#7f1d1d;padding:10px 12px;border-radius:14px;font-weight:800;">
    <?= htmlspecialchars($error, ENT_QUOTES) ?>
  </div>
<?php endif; ?>

<?php if ($ok): ?>
  <div style="margin:10px 0;background:#eafff1;border:1px solid #b7f7cf;color:#064e3b;padding:10px 12px;border-radius:14px;font-weight:800;">
    <?= htmlspecialchars($ok, ENT_QUOTES) ?>
  </div>
<?php endif; ?>

    <!-- Profile Info -->
    <div class="ep-card">
      <h4>Profile Information</h4>

      <div class="ep-field">
        <label>Display Name</label>
        <input type="text"
               name="display_name"
               value="<?= h($user['display_name'] ?? '') ?>"
               required>
        <div class="ep-note">
          This name will be shown publicly on listings and interactions.
        </div>
      </div>

      <div class="ep-field">
        <label>Full Name</label>
        <input type="text"
               value="<?= h($user['full_name']) ?>"
               disabled>
      </div>

      <div class="ep-field">
        <label>TP Number</label>
        <input type="text"
               value="<?= h($user['tp_number']) ?>"
               disabled>
      </div>

      <div class="ep-field">
        <label>Email</label>
        <input type="text"
               value="<?= h($email) ?>"
               disabled>
      </div>
    </div>

    <!-- Security -->
    <div class="ep-card">
      <h4>Security</h4>

      <div class="ep-field">
        <label>Current Password</label>
        <input type="password" name="current_password">
      </div>

      <div class="ep-field">
        <label>New Password</label>
        <input type="password" name="new_password">
      </div>

      <div class="ep-field">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password">
      </div>

      <div class="ep-note">
        Leave password fields empty if you do not wish to change it.
      </div>
    </div>

    <button type="submit" class="ep-btn">
      Save Changes
    </button>

  </form>

</div>

</body>
</html>


