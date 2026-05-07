<?php
session_start();
require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";


$error = $_GET['error'] ?? '';
$ok = $_GET['ok'] ?? '';

if (!empty($_SESSION['user_id'])) {
  header("Location: $BASE/items/item_list.php");
  exit();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Login</title>

  <link rel="stylesheet" href="<?= h($BASE) ?>/assets/css/style.css?v=11">

  <style>
    :root{
      --bg: #f6f7fb;
      --card: #ffffff;
      --text: #0f172a;
      --muted: #64748b;
      --line: #e6eef0;
      --shadow: 0 14px 34px rgba(15,23,42,.10);
      --brand: #10b981;
      --brand2: #0ea5e9;
    }

    body{
      background: radial-gradient(1200px 600px at 10% -10%, rgba(16,185,129,.18), transparent 60%),
                  radial-gradient(900px 500px at 110% 0%, rgba(14,165,233,.16), transparent 55%),
                  var(--bg);
      color: var(--text);
    }

    .auth-shell{
      max-width: 980px;
      margin: 0 auto;
      padding: 18px 14px 40px;
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1.05fr .95fr;
      gap: 16px;
      align-items: center;
    }
    @media (max-width: 860px){
      .auth-shell{ grid-template-columns: 1fr; padding-top: 10px; }
    }

    .brand-hero{
      background: rgba(255,255,255,.6);
      border: 1px solid rgba(230,238,240,.9);
      border-radius: 22px;
      padding: 18px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(10px);
    }
    @media (max-width: 860px){
      .brand-hero{ display:none; }
    }

    .logoRow{ display:flex; align-items:center; gap:10px; margin-bottom: 10px; }
    .logoMark{
      width: 44px; height: 44px; border-radius: 14px;
      background: linear-gradient(135deg, rgba(16,185,129,1), rgba(14,165,233,1));
      display:flex; align-items:center; justify-content:center;
      color:#fff; font-weight:950; letter-spacing:.5px;
      box-shadow: 0 12px 22px rgba(16,185,129,.22);
      user-select:none;
    }
    .heroTitle{ font-size: 24px; font-weight: 950; margin: 0; line-height: 1.1; }
    .heroSub{ margin: 6px 0 12px; color: var(--muted); font-size: 14px; line-height: 1.5; }

    .heroList{ margin: 0; padding-left: 18px; color: #334155; font-size: 14px; line-height: 1.55; }
    .heroList li{ margin: 8px 0; }
    .heroHint{
      margin-top: 14px; padding: 10px 12px; border-radius: 14px;
      background: rgba(16,185,129,.08);
      border: 1px solid rgba(16,185,129,.20);
      color: #064e3b;
      font-size: 13px; line-height: 1.45; font-weight: 700;
    }

    .auth-wrap{ display:flex; flex-direction:column; justify-content:center; align-items:stretch; }
    .auth-card{
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 22px;
      box-shadow: var(--shadow);
      padding: 18px;
    }

    .auth-title{ margin: 0 0 4px; font-size: 22px; font-weight: 950; letter-spacing: -0.2px; }
    .auth-sub{ margin: 0 0 12px; color: var(--muted); font-size: 13px; line-height: 1.5; }

    .form-row{ margin: 12px 0; }
    .label{ display:block; font-weight: 900; font-size: 13px; margin-bottom: 6px; color:#0f172a; }
    .inp{
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 12px 12px;
      font-size: 14px;
      outline: none;
      background: #fff;
      transition: box-shadow .15s ease, border-color .15s ease, transform .05s ease;
    }
    .inp:focus{
      border-color: rgba(16,185,129,.55);
      box-shadow: 0 0 0 4px rgba(16,185,129,.12);
    }

    .pwToggle{ display:flex; gap:8px; align-items:center; user-select:none; margin-top: 8px; color: var(--muted); font-size: 13px; }

    /* ✅ unified actions (stacked like register) */
    .auth-actions{ display:flex; flex-direction:column; gap:10px; margin-top: 14px; }

    /* ✅ primary CTA (same as register) */
    .btn-primary{
      width:100%;
      height:52px;
      border:none;
      border-radius: 16px;
      background: linear-gradient(135deg, rgba(16,185,129,1), rgba(14,165,233,1));
      color:#fff;
      font-weight: 950;
      font-size: 16px;
      letter-spacing:.2px;
      cursor:pointer;
      box-shadow: 0 16px 28px rgba(16,185,129,.25);
    }
    .btn-primary:hover{ filter: brightness(1.03); }
    .btn-primary:active{ transform: scale(.99); }

    .btn-ghost{
      width:100%;
      height:46px;
      border-radius: 999px;
      border: 1px solid var(--line);
      background: #fff;
      color: #0f172a;
      font-weight: 950;
      text-decoration:none;
      display:flex;
      align-items:center;
      justify-content:center;
    }

    .errbox, .okbox{
      border-radius: 14px; padding: 10px 12px;
      font-size: 13px; font-weight: 800; line-height: 1.45; margin: 10px 0;
    }
    .errbox{ background:#ffecec; border:1px solid #ffc0c0; color:#7f1d1d; }
    .okbox{ background:#eafff1; border:1px solid #b7f7cf; color:#064e3b; }

    .auth-note{
      margin-top: 12px; padding: 10px 12px;
      border-radius: 14px; background: #f8fafc;
      border: 1px solid var(--line);
      color: #475569; font-size: 12.5px; line-height: 1.5;
      text-align:center;
    }

    .miniFoot{ text-align:center; margin-top: 12px; font-size: 13px; color: var(--muted); }
    .miniFoot a{ color:#0f172a; text-decoration: underline; font-weight: 900; }

    .mobileBrand{ display:none; text-align:center; padding: 22px 12px 8px; }
    .mobileBrand .name{ font-size: 24px; font-weight: 950; letter-spacing: .2px; }
    .mobileBrand .tagline{ margin-top: 6px; color: var(--muted); font-size: 13px; }
    @media (max-width: 860px){ .mobileBrand{ display:block; } }

    .linkRow{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      margin-top: 12px;
      font-size: 13px;
    }
    .linkRow a{ color: var(--muted); text-decoration: underline; font-weight: 800; }
  </style>
</head>

<body>

<div class="mobileBrand">
  <div class="name">EcoHub APU</div>
  <div class="tagline">Share, rent and reduce waste — one item at a time.</div>
</div>

<div class="auth-shell">

  <div class="brand-hero">
    <div class="logoRow">
      <div class="logoMark">E</div>
      <div>
        <p class="heroTitle" style="margin:0;">EcoHub APU</p>
        <div class="heroSub">Rent items with APU community.</div>
      </div>
    </div>

    <ul class="heroList">
      <li><b>Rent items</b> with clear RM/day pricing</li>
      <li><b>Chat owner</b> before requesting</li>
      <li><b>Track requests</b> in My Borrow</li>
      <li><b>Reduce waste</b> by reusing items</li>
    </ul>

    <div class="heroHint">
      Tip: Use your TP number (e.g. TP071222) to login.
    </div>
  </div>

  <div class="auth-wrap">
    <div class="auth-card">
      <h2 class="auth-title">Login</h2>
      <p class="auth-sub">Login to request items, view rent history and manage your profile.</p>

      <?php if ($error): ?>
        <div class="errbox"><?= h($error) ?></div>
      <?php endif; ?>

      <?php if ($ok): ?>
        <div class="okbox"><?= h($ok) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= h($BASE) ?>/auth/login_process.php">
        <div class="form-row">
          <label class="label">TP Number</label>
          <input class="inp" type="text" name="tp_number" placeholder="e.g. TP071222" required>
        </div>

        <div class="form-row">
          <label class="label">Password</label>
          <input class="inp" id="pw" type="password" name="password" placeholder="••••••••" required>
        </div>

        <div class="pwToggle">
          <input type="checkbox" id="showPw" style="transform:scale(1.05);">
          <label for="showPw" style="cursor:pointer;">Show password</label>
        </div>

        <div class="auth-actions">
          <button class="btn-primary" type="submit" name="login">Login</button>
          <a class="btn-ghost" href="<?= h($BASE) ?>/items/item_list.php">Browse items</a>
        </div>

        <div class="linkRow">
          <a href="<?= h($BASE) ?>/auth/forgot_password.php">Forgot password?</a>
        </div>

        <div class="auth-note">
          By logging in, you agree to rent items responsibly and return on time.
        </div>
      </form>
    </div>

    <div class="miniFoot">
      New here? <a href="<?= h($BASE) ?>/auth/register.php">Create an account</a>
    </div>
  </div>

</div>

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

