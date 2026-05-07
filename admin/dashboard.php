<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../auth/admin_guard.php";

// stats
$total_users   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users"))[0];
$total_items   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM items"))[0];
$pending_items = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM items WHERE status='pending'"))[0];
$total_borrow  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM borrow_requests"))[0];

$IS_ADMIN_PAGE = true;

?>

<style>
/* ===== Admin Navigation ===== */
.admin-nav {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-bottom: 20px;
}

.admin-link {
  text-decoration: none;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s ease;
}

/* Back */
.admin-link.back {
  color: #374151;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
}

.admin-link.back:hover {
  background: #e5e7eb;
}

/* Logout */
.admin-link.logout {
  color: #fff;
  background: #ef4444;
  border: 1px solid #ef4444;
}

.admin-link.logout:hover {
  background: #dc2626;
  border-color: #dc2626;
}

/* ===== Admin Layout (basic) ===== */
.admin-wrap {
  max-width: 1100px;
  margin: auto;
}

.admin-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.admin-sub {
  color: #6b7280;
  font-size: 14px;
}

.admin-chip {
  background: #e0f2fe;
  color: #0369a1;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 13px;
}

.admin-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 16px;
}

.admin-card {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border: 1px solid #e5e7eb;
}

.admin-left {
  display: flex;
  gap: 12px;
  align-items: center;
}

.admin-ico {
  font-size: 26px;
}

.admin-label {
  font-size: 13px;
  color: #6b7280;
}

.admin-value {
  font-size: 22px;
  font-weight: bold;
}

.admin-actions {
  margin-top: 25px;
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.admin-btn {
  text-decoration: none;
  padding: 10px 16px;
  border-radius: 10px;
  background: #10b981;
  color: #fff;
  font-weight: 500;
  transition: 0.2s;
}

.admin-btn:hover {
  background: #059669;
}

.admin-note {
  margin-top: 25px;
  background: #f0fdf4;
  border-left: 4px solid #22c55e;
  padding: 14px;
  font-size: 14px;
}
</style>

<div class="admin-wrap">

  <div class="admin-nav">
    <a href="javascript:history.back()" class="admin-link back">← Back</a>
    <a href="../auth/logout.php" class="admin-link logout">Logout</a>
  </div>

  <div class="admin-title">
    <div>
      <h2>Admin Dashboard</h2>
      <p class="admin-sub">Overview of platform activity and moderation tasks.</p>
    </div>
    <div class="admin-chip">Role: Admin</div>
  </div>

  <div class="admin-grid">

    <div class="admin-card">
      <div class="admin-left">
        <div class="admin-ico">👤</div>
        <div>
          <p class="admin-label">Users</p>
          <p class="admin-value"><?= (int)$total_users ?></p>
        </div>
      </div>
      <div class="admin-chip">Total</div>
    </div>

    <div class="admin-card">
      <div class="admin-left">
        <div class="admin-ico">📦</div>
        <div>
          <p class="admin-label">Items</p>
          <p class="admin-value"><?= (int)$total_items ?></p>
        </div>
      </div>
      <div class="admin-chip">Listings</div>
    </div>

    <div class="admin-card">
      <div class="admin-left">
        <div class="admin-ico">⏳</div>
        <div>
          <p class="admin-label">Pending Items</p>
          <p class="admin-value"><?= (int)$pending_items ?></p>
        </div>
      </div>
      <div class="admin-chip">
        <?= ((int)$pending_items > 0) ? 'Action needed' : 'All clear' ?>
      </div>
    </div>

    <div class="admin-card">
      <div class="admin-left">
        <div class="admin-ico">🔁</div>
        <div>
          <p class="admin-label">Borrow Requests</p>
          <p class="admin-value"><?= (int)$total_borrow ?></p>
        </div>
      </div>
      <div class="admin-chip">Requests</div>
    </div>

  </div>

  <div class="admin-actions">
    <a class="admin-btn" href="users.php">👥 Manage Users</a>
    <a class="admin-btn" href="items.php">✅ Approve Items</a>
    <a class="admin-btn" href="eco_report.php">🌱 Eco Impact Report</a>
  </div>

  <div class="admin-note">
    Tip: New listings are created as <b>pending</b> and become publicly visible only after admin approval.
  </div>

</div>