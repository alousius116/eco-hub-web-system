<?php
session_start();

$page_title = "Forgot Password";
$error = $_GET['error'] ?? '';
$ok = $_GET['ok'] ?? '';
$BASE = "/RWDD2408/eco_hub";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= h($page_title) ?></title>

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
    .labelRow{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:6px; }
    .label{ display:block; font-weight: 900; font-size: 13px; color:#0f172a; }
    .smallLink{ font-size: 13px; color: var(--muted); text-decoration: underline; font-weight: 800; }

    .inp{
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 12px 12px;
      font-size: 14px;
      outline: none;
      background: #fff;
      transition: box-shadow .15s ease, border-color .15s ease;
    }
    .inp:focus{
      border-color: rgba(16,185,129,.55);
      box-shadow: 0 0 0 4px rgba(16,185,129,.12);
    }

    .auth-actions{ display:flex; flex-direction:column; gap:10px; margin-top: 14px; }

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

    .errbox, .okbox{
      border-radius: 14px; padding: 10px 12px;
      font-size: 13px; font-weight: 800; line-height: 1.45; margin: 10px 0;
    }
    .errbox{ background:#ffecec; border:1px solid #ffc0c0; color:#7f1d1d; }
    .okbox{ background:#eafff1; border:1px solid #b7f7cf; color:#064e3b; }

    .note{
      margin-top: 12px;
      color:#64748b;
      font-size:12.5px;
      line-height:1.45;
      background:#f8fafc;
      border:1px solid var(--line);
      border-radius: 14px;
      padding: 10px 12px;
    }

    .miniFoot{ text-align:center; margin-top: 12px; font-size: 13px; color: var(--muted); }
    .miniFoot a{ color:#0f172a; text-decoration: underline; font-weight: 900; }

    .mobileBrand{ display:none; text-align:center; padding: 22px 12px 8px; }
    .mobileBrand .name{ font-size: 24px; font-weight: 950; letter-spacing: .2px; }
    .mobileBrand .tagline{ margin-top: 6px; color: var(--muted); font-size: 13px; }
    @media (max-width: 860px){ .mobileBrand{ display:block; } }

    .modal{
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,.55);
  display:flex;
  align-items:center;
  justify-content:center;
  padding: 16px;
  z-index: 9999;
}
.modal-card{
  width: 100%;
  max-width: 520px;
  background:#fff;
  border-radius: 18px;
  border:1px solid #e6eef0;
  box-shadow: 0 18px 50px rgba(0,0,0,.25);
  overflow:hidden;
}
.modal-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding: 12px 14px;
  border-bottom: 1px solid #e6eef0;
}
.modal-title{
  font-weight: 950;
  font-size: 15px;
  color:#0f172a;
}
.modal-x{
  border:none;
  background: transparent;
  font-size: 18px;
  cursor:pointer;
  color:#64748b;
}
.modal-body{
  padding: 14px;
}
.modal-hint{
  font-size: 13px;
  color:#64748b;
  margin-bottom: 10px;
  line-height:1.45;
}
.modal-linkbox{
  background:#f8fafc;
  border:1px solid #eef2f7;
  border-radius: 14px;
  padding: 10px 12px;
  margin-bottom: 12px;
}

  </style>
</head>

<body>

<div class="mobileBrand">
  <div class="name">EcoHub APU</div>
  <div class="tagline">Recover your account using your APU email.</div>
</div>

<div class="auth-shell">

  <div class="brand-hero">
    <div class="logoRow">
      <div class="logoMark">E</div>
      <div>
        <p class="heroTitle" style="margin:0;">EcoHub APU</p>
        <div class="heroSub">Recover your account securely (demo reset).</div>
      </div>
    </div>

    <ul class="heroList">
      <li><b>Reset link (demo)</b> — show confirmation after submit</li>
      <li><b>Privacy-friendly</b> — don’t reveal if email exists</li>
      <li><b>Email format</b> — tpXXXXXX@mail.apu.edu.my</li>
    </ul>

    <div class="heroHint">
      Tip: If you only remember your TP number, your email is:
      <b>tp + 6 digits + @mail.apu.edu.my</b><br>
      Example: <b>tp071222@mail.apu.edu.my</b>
    </div>
  </div>

  <!-- RIGHT FORM -->
  <div class="auth-wrap">
    <div class="auth-card">
      <h2 class="auth-title">Forgot Password</h2>
      <p class="auth-sub">Enter your APU email (generated from TP Number).</p>

      <?php if ($error): ?>
        <div class="errbox"><?= h($error) ?></div>
      <?php endif; ?>

      <?php if ($ok): ?>
        <div class="okbox"><?= h($ok) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= h($BASE) ?>/auth/forgot_password_process.php">
        <div class="form-row">
          <div class="labelRow">
            <label class="label" style="margin:0;">APU Email</label>
            <a class="smallLink" href="<?= h($BASE) ?>/auth/login.php">Back to Login</a>
          </div>
          <input class="inp" type="email" name="email" placeholder="e.g. tp071222@mail.apu.edu.my" required>
        </div>

        <div class="auth-actions">
          <button class="btn-primary" type="submit" name="send_reset">Send reset link</button>
        </div>

        <div class="note">
          Demo mode: reset email is not actually sent. You’ll get a confirmation message instead.
        </div>
      </form>
    </div>

    <div class="miniFoot">
      Need an account? <a href="<?= h($BASE) ?>/auth/register.php">Create account</a>
      &nbsp;·&nbsp;
      <a href="<?= h($BASE) ?>/index.php">Home</a>
    </div>
  </div>

</div>
          <?php $resetLink = $_SESSION['reset_link'] ?? ''; ?>

<!-- ✅ Modal (hidden by default) -->
<div id="resetModal" class="modal" style="display:none;">
  <div class="modal-card">
    <div class="modal-head">
      <div class="modal-title">Reset link generated</div>
      <button type="button" class="modal-x" onclick="closeResetModal()">✕</button>
    </div>

    <div class="modal-body">
      <div class="modal-hint">
        Demo mode: click the link below to reset your password.
      </div>

<div class="modal-linkbox">
  <a id="resetLinkA" class="btn-primary" href="#" style="text-decoration:none;">
    Go to Reset Password
  </a>
</div>

    </div>
  </div>
</div>

  <script>
  const RESET_LINK = <?= json_encode($resetLink) ?>;

  function openResetModal(){
    const m = document.getElementById('resetModal');
    const a = document.getElementById('resetLinkA');

    if (RESET_LINK) {
      a.href = RESET_LINK;
    } else {
      a.href = "<?= h($BASE) ?>/auth/reset_password.php";
    }

    a.textContent = "Go to Reset Password";
    a.style.pointerEvents = "auto";
    a.style.opacity = "1";

    m.style.display = 'flex';
  }

  function closeResetModal(){
    document.getElementById('resetModal').style.display = 'none';
    fetch('<?= h($BASE) ?>/auth/clear_reset_link.php');
  }

  <?php if (!empty($_GET['ok'])): ?>
    window.addEventListener('load', openResetModal);
  <?php endif; ?>


  </script>

</body>
</html>
