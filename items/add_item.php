<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();
require_once __DIR__ . "/../auth/csrf.php";   // ✅ add
$BASE = "/RWDD2408/eco_hub";

if (isset($_GET['cancel'])) {
  header("Location: $BASE/items/my_items.php");
  exit();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Add Item</title>
  <link rel="stylesheet" href="<?= $BASE ?>/assets/css/style.css?v=ADD_ITEM_RENT_ONLY_1">
  <style>
    .page{max-width:720px;margin:18px auto;padding:0 14px;}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:14px;}
    .row{margin-bottom:12px;}
    label{font-weight:800;display:block;margin-bottom:6px;}
    input, textarea, select{
      width:100%; padding:10px 12px; border-radius:12px;
      border:1px solid #e5e7eb; outline:none; background:#f9fafb;
    }
    textarea{min-height:110px; resize:vertical;}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;}
    .btn{
      display:inline-flex; align-items:center; justify-content:center;
      padding:10px 14px; border-radius:999px; font-weight:900;
      border:1px solid #e5e7eb; background:#fff; text-decoration:none; color:#111827;
      cursor:pointer;
    }
    .btn.dark{background:#111827;color:#fff;border:0;}

    .pillRow{display:flex;gap:10px;flex-wrap:wrap;}
    .pill{
      display:inline-flex; align-items:center; gap:8px;
      padding:8px 12px; border-radius:999px;
      border:1px solid #e5e7eb; background:#fff; cursor:pointer;
      font-weight:900;
    }
    .pill input{width:auto;}
    .hint{font-size:12px;opacity:.75;line-height:1.4;margin-top:6px;}
  </style>
</head>
<body>

<div class="page">
  <div class="card">
    <h2 style="margin:0 0 10px;">Add Item</h2>

    <form action="add_item_process.php" method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?> <!-- ✅ add -->

      <div class="row">
        <label>Item Name</label>
        <input type="text" name="item_name" required maxlength="100">
      </div>

      <!-- ✅ RENT ONLY -->
      <div class="row">
        <label>Listing Type</label>
        <div class="pillRow">
          <label class="pill">
            <input type="radio" name="listing_type" value="rent" checked>
            Rent (RM / day)
          </label>
        </div>
        <div class="hint">This item will be listed for rent with price per day.</div>
      </div>

      <div class="row" id="rentPriceBox">
        <label>Rent Price (RM / day)</label>
        <input type="number" name="rental_price_per_day" step="0.01" min="0.01" placeholder="e.g. 10.00" required>
        <div class="hint">Required. Example: 10.00</div>
      </div>

      <div class="row">
        <label>Category</label>
        <select name="category" required>
          <option value="">-- Select --</option>
          <optgroup label="Fashion">
            <option>Women</option>
            <option>Women Fashion</option>
            <option>Men Accessories</option>
            <option>Accessories</option>
          </optgroup>
          <optgroup label="Tech">
            <option>Mobile Phones</option>
            <option>Laptops</option>
            <option>Computers & Tech</option>
            <option>Computer Accessories</option>
            <option>Electronics</option>
          </optgroup>
          <optgroup label="Home">
            <option>Furniture</option>
            <option>Home Appliances</option>
          </optgroup>
          <optgroup label="Media & Entertainment">
            <option>Audio</option>
            <option>Audio Equipment</option>
            <option>Gaming</option>
            <option>Video Gaming</option>
            <option>Gaming Accessories</option>
            <option>Cameras</option>
          </optgroup>
          <optgroup label="Other">
            <option>Luxury</option>
            <option>Others</option>
          </optgroup>
        </select>
      </div>

      <div class="row">
        <label>Description</label>
        <textarea name="description" required></textarea>
      </div>

      <div class="row">
        <label>Condition</label>
        <select name="condition_status" required>
          <option value="">-- Select --</option>
          <option>Like New</option>
          <option>Good</option>
          <option>Fair</option>
        </select>
      </div>

      <div class="row">
        <label>Item Photo (optional)</label>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/jpg" capture="environment">
      </div>

      <div class="actions">
        <button type="submit" class="btn dark" name="add" value="1">Add Item</button>
        <a class="btn" href="<?= $BASE ?>/items/item_list.php">Cancel</a>
      </div>
    </form>
  </div>
</div>

</body>
</html>
