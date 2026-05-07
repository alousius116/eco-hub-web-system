<?php
session_start();
require_once __DIR__ . "/../config/db_connect.php";

$BASE  = "/RWDD2408/eco_hub";
$token = trim($_GET['token'] ?? '');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

$mode    = 'demo'; 
$valid   = false;
$reset_id= null;
$err     = "";
$ok      = trim($_GET['ok'] ?? '');
$pageErr = trim($_GET['error'] ?? '');

if ($token !== '') {
  $mode = 'real';
  $now  = date('Y-m-d H:i:s');

  $sql = "SELECT id, token_hash, expires_at, used_at
          FROM password_resets
          WHERE used_at IS NULL AND expires_at > ?
          ORDER BY created_at DESC
          LIMIT 50";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "s", $now);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);

  while ($row = ($res ? mysqli_fetch_assoc($res) : null)) {
    if (password_verify($token, $row['token_hash'])) {
      $valid    = true;
      $reset_id = (int)$row['id'];
      break;
    }
  }

  if (!$valid) {
    $err = "Reset link is invalid or expired. Please request a new one.";
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Reset Password</title>
  <link rel="stylesheet" href="<?= h($BASE) ?>/assets/css/style.css?v=11">

  <style>
    :root{
      --bg:#f6f7fb;
      --line:#e6eef0;
      --text:#0f172a;
      --muted:#64748b;
      --shadow:0 14px 34px rgba(15,23,42,.10);
      --brand:#10b981;
      --brand2:#0ea5e9;
    }
    body{
      background: radial-gradient(1200px 600px at 10% -10%, rgba(16,185,129,.18), transparent 60%),
                  radial-gradient(900px 500px at 110% 0%, rgba(14,165,233,.16), transparent 55%),
                  var(--bg);
      color: var(--text);
      margin:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }
    .wrap{
      max-width: 520px;
      margin: 0 auto;
      padding: 28px 14px 60px;
      min-height: 100vh;
      display:flex;
      align-items:flex-start;
      justify-content:center;
    }
    .card{
      width:100%;
      background:#fff;
      padding: 18px;
      border-radius: 22px;
      box-shadow: var(--shadow);
      border:1px solid var(--line);
      margin-top: 40px;
    }
    .brandRow{
      display:flex;
      align-items:center;
      gap:10px;
      margin-bottom: 10px;
    }
    .mark{
      width:44px;height:44px;border-radius:14px;
      display:flex;align-items:center;justify-content:center;
      background: linear-gradient(135deg, rgba(16,185,129,1), rgba(14,165,233,1));
      color:#fff;font-weight:950;
      box-shadow: 0 12px 22px rgba(16,185,129,.22);
      user-select:none;
    }
    .brandName{ font-weight:950; font-size:18px; margin:0; }
    .brandSub{ margin:2px 0 0; color:var(--muted); font-size:13px; }

    .title{margin: 6px 0 6px; font-weight:950; font-size:22px;}
    .sub{margin:0 0 12px;color:var(--muted);font-size:13.5px;line-height:1.45;}

    .tag{
      display:inline-block;
      padding:6px 10px;
      border-radius:999px;
      font-size:12.5px;
      font-weight:900;
      border:1px solid;
      margin: 2px 0 10px;
    }
    .tag.demo{ background:#ecfeff; border-color:#a5f3fc; color:#0f172a; }
    .tag.real{ background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
    .tag.bad{ background:#fff1f2; border-color:#fecdd3; color:#9f1239; }

    .errbox{
      margin-top:12px;
      background:#ffecec;
      border:1px solid #ffc0c0;
      color:#7f1d1d;
      padding:10px 12px;
      border-radius:14px;
      font-weight:800;
      font-size:13px;
      line-height:1.45;
    }

    .okbox{
      margin-top:12px;
      background:#ecfdf5;
      border:1px solid #a7f3d0;
      color:#065f46;
      padding:10px 12px;
      border-radius:14px;
      font-weight:800;
      font-size:13px;
      line-height:1.45;
    }

    .form-row{ margin: 12px 0; }
    .label{display:block;font-weight:900;font-size:13px;margin-bottom:6px;}
    .inp{
      width:100%;
      padding:12px;
      border-radius:14px;
      border:1px solid var(--line);
      outline:none;
      font-size:14px;
      background:#fff;
      transition: box-shadow .15s ease, border-color .15s ease;
    }
    .inp:focus{
      border-color: rgba(16,185,129,.55);
      box-shadow: 0 0 0 4px rgba(16,185,129,.12);
    }

    .btn-primary{
      width:100%;
      height:52px;
      border:none;
      border-radius:16px;
      background:linear-gradient(135deg,#10b981,#0ea5e9);
      color:#fff;
      font-weight:950;
      font-size:16px;
      cursor:pointer;
      margin-top:12px;
      box-shadow: 0 16px 28px rgba(16,185,129,.22);
    }
    .btn-primary:hover{ filter: brightness(1.03); }
    .btn-primary:active{ transform: scale(.99); }

    .note{
      margin-top:12px;
      color:#64748b;
      font-size:12.5px;
      line-height:1.45;
      background:#f8fafc;
      border:1px solid var(--line);
      border-radius:14px;
      padding:10px 12px;
    }

    .links{
      margin-top: 12px;
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:center;
    }
    .link{
      color:#0f172a;
      text-decoration: underline;
      font-weight: 900;
      font-size: 13px;
    }
  </style>
</head>

<body>
<div class="wrap">
  <div class="card">

    <div class="brandRow">
      <div class="mark">E</div>
      <div>
        <p class="brandName">EcoSwap APU</p>
        <p class="brandSub">Reset your account password</p>
      </div>
    </div>

    <h2 class="title">Reset Password</h2>

    <?php if ($pageErr !== ''): ?>
      <div class="errbox"><?= h($pageErr) ?></div>
    <?php endif; ?>

    <?php if ($ok !== ''): ?>
      <div class="okbox"><?= h($ok) ?></div>
    <?php endif; ?>

    <?php if ($mode === 'demo'): ?>
      <span class="tag demo">DEMO MODE</span>
      <p class="sub"><b>You can change your password now.</b> (No token provided)</p>

      <form method="POST" action="<?= h($BASE) ?>/auth/reset_password_process.php" id="rpForm">
        <input type="hidden" name="demo" value="1">

        <div class="form-row">
          <label class="label">New Password</label>
          <input class="inp" type="password" name="password" id="pw1" required>
        </div>

        <div class="form-row">
          <label class="label">Confirm Password</label>
          <input class="inp" type="password" name="confirm_password" id="pw2" required>
        </div>

        <div id="pwHint" class="errbox" style="display:none;">Passwords do not match.</div>

        <button class="btn-primary" type="submit">Update Password</button>

        <div class="note">
          Demo reset flow: password is not really saved. (For presentation only)
        </div>
      </form>

      <div class="links">
        <a class="link" href="<?= h($BASE) ?>/auth/forgot_password.php">Back to Forgot Password</a>
        <span style="color:#94a3b8;">·</span>
        <a class="link" href="<?= h($BASE) ?>/auth/login.php">Back to Login</a>
      </div>

    <?php elseif ($valid): ?>
      <span class="tag real">VALID TOKEN</span>
      <p class="sub"><b>You can change your password now.</b></p>

      <form method="POST" action="<?= h($BASE) ?>/auth/reset_password_process.php" id="rpForm">
        <input type="hidden" name="reset_id" value="<?= (int)$reset_id ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">

        <div class="form-row">
          <label class="label">New Password</label>
          <input class="inp" type="password" name="password" id="pw1" required>
        </div>

        <div class="form-row">
          <label class="label">Confirm Password</label>
          <input class="inp" type="password" name="confirm_password" id="pw2" required>
        </div>

        <div id="pwHint" class="errbox" style="display:none;">Passwords do not match.</div>
          <div class="pwToggle">
          <input type="checkbox" id="showPw" style="transform:scale(1.05);">
          <label for="showPw" style="cursor:pointer;">Show password</label>
        </div>
        <button class="btn-primary" type="submit">Update Password</button>
      </form>

      <div class="links">
        <a class="link" href="<?= h($BASE) ?>/auth/login.php">Back to Login</a>
      </div>

    <?php else: ?>
      <span class="tag bad">INVALID LINK</span>
      <div class="errbox"><?= h($err ?: "Reset link is invalid or expired. Please request a new one.") ?></div>
      <div class="links">
        <a class="link" href="<?= h($BASE) ?>/auth/forgot_password.php">Back to Forgot Password</a>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
  const pw1 = document.getElementById('pw1');
  const pw2 = document.getElementById('pw2');
  const pwHint = document.getElementById('pwHint');
  const rpForm = document.getElementById('rpForm');

  function checkPW(){
    if (!pw1 || !pw2) return true;
    if (!pw1.value || !pw2.value) { if (pwHint) pwHint.style.display = 'none'; return true; }
    const ok = pw1.value === pw2.value;
    if (pwHint) pwHint.style.display = ok ? 'none' : 'block';
    return ok;
  }

  if (pw1) pw1.addEventListener('input', checkPW);
  if (pw2) pw2.addEventListener('input', checkPW);

  if (rpForm) {
    rpForm.addEventListener('submit', (e) => {
      if (!checkPW()) { e.preventDefault(); pw2.focus(); }
    });
  }
</script>
<script>
  const cb = document.getElementById('showPw');
  const pw = document.getElementById('pw');
  if (cb && pw) {
    cb.addEventListener('change', () => {
      pw.type = cb.checked ? 'text' : 'password';
    });
  }
</script>

</body>
</html>

