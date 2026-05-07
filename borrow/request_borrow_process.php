<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();

require_once __DIR__ . "/../config/db_connect.php";
require_once __DIR__ . "/../includes/reward_helper.php";
require_once __DIR__ . "/../auth/csrf.php";

csrf_verify_or_die();

$BASE    = "/RWDD2408/eco_hub";
$user_id = (int)($_SESSION['user_id'] ?? 0);

$item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
$days_in = filter_input(INPUT_POST, 'borrow_days', FILTER_VALIDATE_INT);

function redirect_back($url,$key,$msg){
    header("Location: ".$url.(strpos($url,'?')===false?'?':'&').$key."=".urlencode($msg));
    exit();
}

if(!$item_id || $item_id<=0){
    redirect_back("$BASE/items/item_list.php","error","Invalid item.");
}


function get_columns($conn,$table){
    $cols=[];
    $res=mysqli_query($conn,"SHOW COLUMNS FROM `$table`");
    if($res){
        while($r=mysqli_fetch_assoc($res)){
            $cols[]=$r['Field'];
        }
    }
    return $cols;
}
function pick_col($cols,$candidates){
    foreach($candidates as $c){
        if(in_array($c,$cols,true)) return $c;
    }
    return null;
}

mysqli_autocommit($conn,false);

try{
$stmt=mysqli_prepare($conn,"
SELECT item_id,user_id,status,availability_status
FROM items
WHERE item_id=? 
LIMIT 1 FOR UPDATE
");

if(!$stmt){
    throw new Exception("Prepare failed (item select).");
}

mysqli_stmt_bind_param($stmt,"i",$item_id);
mysqli_stmt_execute($stmt);
$item=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if(!$item){
    throw new Exception("Item not found.");
}

if(strtolower($item['status'])!=='approved'){
    throw new Exception("Item not approved.");
}

if((int)$item['user_id']===$user_id){
    throw new Exception("Cannot request own item.");
}

if(strtolower($item['availability_status'])!=='available'){
    throw new Exception("Item unavailable.");
}

$stmt=mysqli_prepare($conn,"
SELECT request_id
FROM borrow_requests
WHERE item_id=? AND borrower_id=? 
AND LOWER(request_status)='pending'
ORDER BY created_at DESC
LIMIT 1 FOR UPDATE
");
mysqli_stmt_bind_param($stmt,"ii",$item_id,$user_id);
mysqli_stmt_execute($stmt);
$cur=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if($cur){

    $cols=get_columns($conn,"borrow_requests");
    $col_updated=pick_col($cols,["updated_at","modified_at"]);

    $sql="UPDATE borrow_requests SET request_status='cancelled'";
    if($col_updated) $sql.=", `$col_updated`=NOW()";
    $sql.=" WHERE request_id=? AND borrower_id=? LIMIT 1";

    $upd=mysqli_prepare($conn,$sql);
    if(!$upd){
        throw new Exception("Prepare failed (cancel).");
    }

    mysqli_stmt_bind_param($upd,"ii",$cur['request_id'],$user_id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    mysqli_commit($conn);
    redirect_back("$BASE/items/item_detail.php?id=$item_id","ok","Request cancelled.");
}


if($days_in<1 || $days_in>30){
    throw new Exception("Invalid days.");
}

$sus=getUserSustainability($conn,$user_id);
$benefits=getLevelBenefits($sus['eco_level'] ?? '');
$discount_pct=(int)($benefits['discount'] ?? 0);

$cols=get_columns($conn,"borrow_requests");

$col_created  = pick_col($cols,["created_at","request_date","requested_at"]);
$col_updated  = pick_col($cols,["updated_at","modified_at"]);
$col_discount = pick_col($cols,["discount_percent","discount_pct","discount"]);
$col_days     = pick_col($cols,["borrow_days","days","rental_days"]);
$col_total    = pick_col($cols,["total_price","total_amount","total_cost"]);
$col_final    = pick_col($cols,["final_price","final_amount","payable_amount"]);

$fields=["item_id","borrower_id","request_status"];
$values=["?","?","'pending'"];
$types="ii";
$params=[$item_id,$user_id];

if($col_created){ $fields[]="`$col_created`"; $values[]="NOW()"; }
if($col_updated){ $fields[]="`$col_updated`"; $values[]="NOW()"; }

if($col_days){
    $fields[]="`$col_days`";
    $values[]="?";
    $types.="i";
    $params[]=$days_in;
}

if($col_discount){
    $fields[]="`$col_discount`";
    $values[]="?";
    $types.="i";
    $params[]=$discount_pct;
}

if($col_total){ $fields[]="`$col_total`"; $values[]="0"; }
if($col_final){ $fields[]="`$col_final`"; $values[]="0"; }

$sql="INSERT INTO borrow_requests(".implode(",",$fields).")
VALUES(".implode(",",$values).")";

$ins=mysqli_prepare($conn,$sql);
if(!$ins){
    throw new Exception("Prepare failed (insert).");
}

mysqli_stmt_bind_param($ins,$types,...$params);
mysqli_stmt_execute($ins);
mysqli_stmt_close($ins);
mysqli_commit($conn);

redirect_back("$BASE/items/item_detail.php?id=$item_id","ok","Request sent! Click again to cancel.");

}catch(Exception $e){

mysqli_rollback($conn);
error_log("Borrow Request Error: ".$e->getMessage());

redirect_back("$BASE/items/item_detail.php?id=$item_id","error","Action failed.");

}
