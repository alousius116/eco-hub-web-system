<?php
require_once __DIR__ . "/../config/db_connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$BASE = "/RWDD2408/eco_hub";

/* appbar back */
$SHOW_BACK = true;
$BACK_HREF = $BASE . "/chat/inbox.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['user_id'])) {
  header("Location: $BASE/auth/login.php");
  exit();
}

$uid = (int)$_SESSION['user_id'];
$other_id = (int)($_GET['user'] ?? 0);

/* basic validation */
if ($other_id <= 0 || $other_id === $uid) {
  header("Location: $BASE/chat/inbox.php");
  exit();
}

/* =========================
   1) Verify other user exists
   ========================= */
$other_name = null;
$sql_u = "SELECT full_name FROM users WHERE user_id=? LIMIT 1";
$st_u = mysqli_prepare($conn, $sql_u);
mysqli_stmt_bind_param($st_u, "i", $other_id);
mysqli_stmt_execute($st_u);
$r = mysqli_stmt_get_result($st_u);
if ($r && ($row = mysqli_fetch_assoc($r))) {
  $other_name = trim((string)($row['full_name'] ?? ''));
}
if ($other_name === null || $other_name === '') {
  // user not found -> back to inbox
  header("Location: $BASE/chat/inbox.php");
  exit();
}

/* =========================
   2) Mark messages as READ
   ========================= */
$sql_read = "
  UPDATE messages
  SET is_read = 1
  WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
";
$st_read = mysqli_prepare($conn, $sql_read);
mysqli_stmt_bind_param($st_read, "ii", $other_id, $uid);
mysqli_stmt_execute($st_read);

/* =========================
   3) Send message (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $msg = trim((string)($_POST['message'] ?? ''));

  // basic hardening
  if ($msg !== '') {
    // limit length (adjust 500/1000 based on your DB field size)
    if (function_exists('mb_substr')) {
      $msg = mb_substr($msg, 0, 500);
    } else {
      $msg = substr($msg, 0, 500);
    }

    $sql_send = "
      INSERT INTO messages (sender_id, receiver_id, message_text, is_read, created_at)
      VALUES (?, ?, ?, 0, NOW())
    ";
    $st_send = mysqli_prepare($conn, $sql_send);
    mysqli_stmt_bind_param($st_send, "iis", $uid, $other_id, $msg);
    mysqli_stmt_execute($st_send);
  }

  header("Location: $BASE/chat/chat.php?user=" . (int)$other_id);
  exit();
}

/* =========================
   4) Load messages
   ========================= */
$messages = [];
$sql_msg = "
  SELECT message_id, sender_id, receiver_id, message_text, created_at
  FROM messages
  WHERE (sender_id=? AND receiver_id=?)
     OR (sender_id=? AND receiver_id=?)
  ORDER BY message_id ASC
  LIMIT 300
";
$st_msg = mysqli_prepare($conn, $sql_msg);
mysqli_stmt_bind_param($st_msg, "iiii", $uid, $other_id, $other_id, $uid);
mysqli_stmt_execute($st_msg);
$res = mysqli_stmt_get_result($st_msg);
while ($res && ($row = mysqli_fetch_assoc($res))) {
  $messages[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Chat</title>
  <link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css?v=CHAT_FINAL_2">

  <style>
    .chatWrap{ padding:12px; padding-bottom:90px; }
    .bubbleRow{ display:flex; margin:8px 0; }
    .bubble{
      max-width:78%;
      padding:10px 12px;
      border-radius:14px;
      border:1px solid #e5e7eb;
      background:#fff;
      font-size:14px;
      line-height:1.35;
      overflow-wrap:anywhere;
    }
    .me{ justify-content:flex-end; }
    .me .bubble{ background:#111827; color:#fff; border-color:#111827; }
    .time{ font-size:11px; opacity:.7; margin-top:4px; }

    .chatBar{
      position:fixed; left:0; right:0; bottom:0;
      background:#fff; border-top:1px solid #e5e7eb;
      padding:10px 12px;
      display:flex; gap:10px;
      z-index:80;
    }
    .chatBar input{
      flex:1; height:42px; border-radius:12px;
      border:1px solid #e5e7eb; padding:0 12px;
      background:#f9fafb;
      outline:none;
    }
    .chatBar button{
      height:42px; padding:0 14px;
      border-radius:12px; border:0;
      background:#111827; color:#fff;
      font-weight:800; cursor:pointer;
    }
    .chatTitle{
      margin:8px 0 12px;
      font-size:16px;
      font-weight:800;
    }
  </style>
</head>
<body>

<?php @include __DIR__ . "/../partials/mobile_appbar.php"; ?>
<?php @include __DIR__ . "/../partials/mobile_drawer.php"; ?>

<div class="chatWrap">
  <div class="chatTitle">💬 <?= h($other_name) ?></div>

  <?php foreach($messages as $m): ?>
    <?php $is_me = ((int)$m['sender_id'] === $uid); ?>
    <div class="bubbleRow <?= $is_me ? 'me' : '' ?>">
      <div class="bubble">
        <?= nl2br(h($m['message_text'])) ?>
        <div class="time"><?= h($m['created_at']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<form class="chatBar" method="post" autocomplete="off">
  <input name="message" placeholder="Type a message..." maxlength="500" required />
  <button type="submit">Send</button>
</form>

<script src="<?= $BASE ?>/assets/js/mobile_drawer.js"></script>
</body>
</html>
