<?php
require_once __DIR__ . "/../config/db_connect.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$BASE = "/RWDD2408/eco_hub";

// ✅ back button for appbar
$SHOW_BACK = true;
$BACK_HREF = $BASE . "/index.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['user_id'])) {
  header("Location: $BASE/auth/login.php");
  exit();
}

$uid = (int)$_SESSION['user_id'];

/* ✅ 总未读（Prepared）
   未读定义：receiver_id = 我 AND is_read = 0
*/
$unread_total = 0;
$sqlUnread = "SELECT COUNT(*) AS c FROM messages WHERE receiver_id = ? AND is_read = 0";
$stmtU = mysqli_prepare($conn, $sqlUnread);
if ($stmtU) {
  mysqli_stmt_bind_param($stmtU, "i", $uid);
  mysqli_stmt_execute($stmtU);
  $rsu = mysqli_stmt_get_result($stmtU);
  if ($rsu && ($r = mysqli_fetch_assoc($rsu))) $unread_total = (int)$r['c'];
}

/*
  ✅ Inbox 列表（Prepared）
  - other_id = 对话对象
  - last_mid = MAX(message_id)
  - unread_count = 对方发给我且未读
*/
$sql = "
SELECT 
  other_u.user_id AS other_id,
  other_u.full_name AS other_name,
  m.message_text,
  m.created_at,
  COALESCE(unread.unread_count, 0) AS unread_count
FROM (
  SELECT 
    CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_id,
    MAX(message_id) AS last_mid
  FROM messages
  WHERE sender_id = ? OR receiver_id = ?
  GROUP BY CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
) t
JOIN users other_u ON other_u.user_id = t.other_id
JOIN messages m ON m.message_id = t.last_mid
LEFT JOIN (
  SELECT sender_id AS other_id, COUNT(*) AS unread_count
  FROM messages
  WHERE receiver_id = ? AND is_read = 0
  GROUP BY sender_id
) unread ON unread.other_id = t.other_id
ORDER BY m.message_id DESC
";

$chats = [];
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
  // 这里 ? 一共出现 5 次 uid
  mysqli_stmt_bind_param($stmt, "iiiii", $uid, $uid, $uid, $uid, $uid);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  if ($res) {
    while($row = mysqli_fetch_assoc($res)) $chats[] = $row;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Messages</title>
  <link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css?v=CHAT_INBOX_UNREAD_2">

  <style>
    .page{ padding:12px; padding-bottom:90px; }
    .titleRow{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin:8px 0 12px; }
    .title{ font-size:18px; font-weight:900; margin:0; }

    .totalBadge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      font-weight:900;
      font-size:12px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid #e6eef0;
      background:#f8fafc;
      color:#0f172a;
      white-space:nowrap;
    }
    .totalBadge .dot{
      width:18px; height:18px;
      border-radius:999px;
      background:#ef4444;
      color:#fff;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      font-size:11px;
      font-weight:950;
    }

    .chatCard{
      display:block;
      padding:14px;
      margin-bottom:10px;
      text-decoration:none;
      color:inherit;
      position:relative;
    }
    .row{
      display:flex;
      justify-content:space-between;
      gap:10px;
      align-items:center;
    }
    .name{ font-weight:900; display:flex; align-items:center; gap:8px; min-width:0; }
    .nameText{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width: 220px; }
    .snippet{
      margin-top:6px;
      color:#6b7280;
      font-size:13px;
      overflow:hidden;
      text-overflow:ellipsis;
      white-space:nowrap;
    }
    .time{ color:#6b7280; font-size:12px; white-space:nowrap; }

    .ubadge{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-width:20px;
      height:20px;
      padding:0 6px;
      border-radius:999px;
      font-size:12px;
      font-weight:950;
      background:#ef4444;
      color:#fff;
      flex:0 0 auto;
    }
  </style>
</head>
<body>

<?php @include __DIR__ . "/../partials/mobile_appbar.php"; ?>
<?php @include __DIR__ . "/../partials/mobile_drawer.php"; ?>

<div class="page">

  <div class="titleRow">
    <div class="title">Messages</div>

    <?php if ($unread_total > 0): ?>
      <div class="totalBadge">
        Unread <span class="dot"><?= (int)$unread_total ?></span>
      </div>
    <?php endif; ?>
  </div>

  <?php if (count($chats) === 0): ?>
    <div class="card" style="padding:14px;">
      <b>No conversations yet</b>
      <div class="meta">Go to any item detail and start a chat with the owner.</div>
    </div>
  <?php else: ?>
    <?php foreach($chats as $c): ?>
      <?php $u = (int)($c['unread_count'] ?? 0); ?>
      <a class="card chatCard"
         href="<?= $BASE ?>/chat/chat.php?user=<?= (int)$c['other_id'] ?>">
        <div class="row">
          <div class="name">
            <span class="nameText"><?= h($c['other_name']) ?></span>
            <?php if ($u > 0): ?>
              <span class="ubadge"><?= $u ?></span>
            <?php endif; ?>
          </div>
          <div class="time"><?= h($c['created_at']) ?></div>
        </div>
        <div class="snippet"><?= h($c['message_text']) ?></div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<script src="<?= $BASE ?>/assets/js/mobile_drawer.js"></script>
</body>
</html>

