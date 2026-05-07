<?php
$IS_ADMIN_PAGE = true;
require_once __DIR__ . "/../auth/admin_guard.php";


/* =======================
   1. Core KPIs
======================= */

// Approved listings
$approved_items = (int)mysqli_fetch_row(mysqli_query($conn, "
  SELECT COUNT(*) 
  FROM items 
  WHERE status='approved'
"))[0];

// Completed borrows (returned + completed)
$completed_borrows = (int)mysqli_fetch_row(mysqli_query($conn, "
  SELECT COUNT(*) 
  FROM borrow_requests 
  WHERE LOWER(request_status) IN ('returned','completed')
"))[0];

// Estimated revenue earned
$total_revenue = (float)mysqli_fetch_row(mysqli_query($conn, "
  SELECT COALESCE(SUM(
    CASE 
      WHEN final_price > 0 THEN final_price
      ELSE total_cost
    END
  ),0)
  FROM borrow_requests
  WHERE LOWER(request_status) IN ('returned','completed')
"))[0];

/* =======================
   2. Impact Estimation Rules
======================= */

$CO2_PER_ITEM = 2;
$WASTE_PER_BORROW = 0.5;

$co2_saved = $approved_items * $CO2_PER_ITEM;
$waste_reduced = $completed_borrows * $WASTE_PER_BORROW;

/* =======================
   3. Category Breakdown
======================= */

$by_cat = mysqli_query($conn, "
  SELECT 
    i.category,
    COUNT(DISTINCT i.item_id) AS approved_listings,
    SUM(
      CASE 
        WHEN LOWER(br.request_status) IN ('returned','completed') 
        THEN 1 ELSE 0 
      END
    ) AS completed_borrows
  FROM items i
  LEFT JOIN borrow_requests br ON br.item_id = i.item_id
  WHERE i.status='approved'
  GROUP BY i.category
  ORDER BY completed_borrows DESC, approved_listings DESC
");
?>

<div style="margin-bottom:16px;">
  <a href="dashboard.php" class="admin-link back">← Back</a>
</div>

<h2 class="page-title">Eco Impact Report 🌱</h2>

<!-- KPI SECTION -->
<div class="kpi-grid">
  <div class="kpi-column">
    <div class="kpi-card">
      <span>Approved Items</span>
      <strong><?= $approved_items ?></strong>
    </div>

    <div class="kpi-card">
      <span>Estimated CO₂ Saved</span>
      <strong><?= $co2_saved ?> kg</strong>
    </div>

    <div class="kpi-card">
      <span>Estimated Revenue Earned</span>
      <strong>RM <?= number_format($total_revenue, 2) ?></strong>
    </div>
  </div>

  <div class="kpi-column">
    <div class="kpi-card">
      <span>Completed Borrows</span>
      <strong><?= $completed_borrows ?></strong>
    </div>

    <div class="kpi-card">
      <span>Estimated Waste Reduced</span>
      <strong><?= $waste_reduced ?> kg</strong>
    </div>
  </div>
</div>

<h3>Impact by Category</h3>

<table class="tbl">
  <tr>
    <th>Category</th>
    <th>Approved Listings</th>
    <th>Completed Borrows</th>
  </tr>

  <?php while($r = mysqli_fetch_assoc($by_cat)): ?>
    <tr>
      <td><?= htmlspecialchars($r['category'] ?? 'Uncategorised') ?></td>
      <td><?= (int)$r['approved_listings'] ?></td>
      <td><?= (int)$r['completed_borrows'] ?></td>
    </tr>
  <?php endwhile; ?>
</table>

<p class="note">
  <b>Methodology:</b><br>
  • Each approved reusable item is estimated to save <?= $CO2_PER_ITEM ?> kg of CO₂ emissions.<br>
  • Each completed borrow (returned/completed) reduces approximately <?= $WASTE_PER_BORROW ?> kg of waste.<br>
  • Revenue is calculated using recorded <code>final_price</code> or <code>total_cost</code> from completed borrow records.<br>
  This report supports SDG 11 (Sustainable Cities & Communities).
</p>

<!-- STYLES -->
<style>
.page-title {
    font-size: 22px;
    font-weight: 600;
    margin-bottom: 16px;
}

.kpi-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 28px;
}

.kpi-column {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.kpi-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px 18px;
}

.kpi-card span {
    font-size: 14px;
    color: #6b7280;
}

.kpi-card strong {
    font-size: 20px;
    font-weight: 600;
    color: #111827;
}

.tbl {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
}

.tbl th,
.tbl td {
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    font-size: 14px;
}

.tbl th {
    background: #f9fafb;
    font-weight: 600;
}

.tbl tr:last-child td {
    border-bottom: none;
}

.note {
    margin-top: 18px;
    font-size: 13px;
    color: #4b5563;
    line-height: 1.6;
}

.admin-link.back {
    display: inline-block;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    transition: all 0.2s ease;
}

.admin-link.back:hover {
    background: #e5e7eb;
    border-color: #9ca3af;
}
</style>
