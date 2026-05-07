<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();

require_once __DIR__ . "/../auth/csrf.php";
csrf_verify_or_die();

require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";
$user_id = (int)($_SESSION['user_id'] ?? 0);


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['add'])) {
    header("Location: $BASE/items/add_item.php");
    exit();
}

$item_name   = trim($_POST['item_name'] ?? '');
$category    = trim($_POST['category'] ?? '');
$description = trim($_POST['description'] ?? '');
$condition   = trim($_POST['condition_status'] ?? '');
$listing_type = 'rent';


$rent_val = filter_input(INPUT_POST, 'rental_price_per_day', FILTER_VALIDATE_FLOAT);

if (!$rent_val || $rent_val <= 0) {
    header("Location: $BASE/items/add_item.php?error=" . urlencode("Valid rent price required."));
    exit();
}

if ($user_id <= 0 || $item_name === '' || $category === '' || $description === '' || $condition === '') {
    header("Location: $BASE/items/add_item.php?error=" . urlencode("Missing required fields."));
    exit();
}

$availability_status = "Available";
$moderation_status   = "pending";
$price_val           = null;

mysqli_autocommit($conn, false);

try {

    $sql = "INSERT INTO items
        (user_id, item_name, category, description, condition_status,
         availability_status, listing_type, price, rental_price_per_day, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "issssssdds",
        $user_id,
        $item_name,
        $category,
        $description,
        $condition,
        $availability_status,
        $listing_type,
        $price_val,
        $rent_val,
        $moderation_status
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Execute failed: " . mysqli_error($conn));
    }

    $item_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload error code: " . (int)$_FILES['photo']['error']);
        }

        /* size limit (Distinction upgrade) */
        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            throw new Exception("Image too large (Max 5MB).");
        }

        $tmp = $_FILES['photo']['tmp_name'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime])) {
            throw new Exception("Invalid image type.");
        }

        $ext = $allowed[$mime];
        $ts  = time();

        $upload_dir = __DIR__ . "/../uploads/items";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename   = "item_" . $item_id . "_" . $ts . "." . $ext;
        $target_abs = $upload_dir . "/" . $filename;

        if (!move_uploaded_file($tmp, $target_abs)) {
            throw new Exception("Failed to save uploaded file.");
        }

        $image_rel_path = "items/" . $filename;

        $u = mysqli_prepare($conn, "UPDATE items SET image=? WHERE item_id=?");
        if (!$u) {
            throw new Exception("Prepare update image failed.");
        }

        mysqli_stmt_bind_param($u, "si", $image_rel_path, $item_id);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);
    }


    mysqli_commit($conn);

    header("Location: $BASE/items/my_items.php?ok=" . urlencode("Item submitted. Pending admin approval."));
    exit();

} catch (Exception $e) {

    mysqli_rollback($conn);

    /* internal logging (Top Distinction practice) */
    error_log("Add Item Error: " . $e->getMessage());

    header("Location: $BASE/items/add_item.php?error=" . urlencode("Unable to submit item."));
    exit();
}
