<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();

require_once __DIR__ . "/../config/db_connect.php";

$BASE = "/RWDD2408/eco_hub";

function go_manage($msgType,$msg){
    global $BASE;
    header("Location: $BASE/items/manage_requests.php?$msgType=".urlencode($msg));
    exit();
}

$request_id = filter_input(INPUT_POST,'request_id',FILTER_VALIDATE_INT);
$owner_id   = (int)($_SESSION['user_id'] ?? 0);

if(!$request_id || $request_id<=0){
    go_manage("error","Invalid request.");
}

mysqli_autocommit($conn,false);

try{
$sql="
SELECT
 br.request_id,
 br.request_status,
 br.borrower_id,
 br.item_id,
 i.user_id AS owner_id,
 i.item_name
FROM borrow_requests br
JOIN items i ON br.item_id=i.item_id
WHERE br.request_id=?
LIMIT 1
FOR UPDATE
";

$st=mysqli_prepare($conn,$sql);
if(!$st){
    throw new Exception("Prepare failed (select).");
}

mysqli_stmt_bind_param($st,"i",$request_id);
mysqli_stmt_execute($st);

$row=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if(!$row){
    throw new Exception("Request not found.");
}

if((int)$row['owner_id']!==$owner_id){
    throw new Exception("Not allowed.");
}

$db_status=strtolower(trim((string)$row['request_status']));
if($db_status!=='pending'){
    throw new Exception("Request already processed.");
}

$borrower_id=(int)$row['borrower_id'];
$item_name=(string)($row['item_name'] ?? 'this item');

$sql2="
UPDATE borrow_requests
SET request_status='rejected'
WHERE request_id=? AND LOWER(request_status)='pending'
LIMIT 1
";

$st2=mysqli_prepare($conn,$sql2);
if(!$st2){
    throw new Exception("Prepare failed (update).");
}

mysqli_stmt_bind_param($st2,"i",$request_id);
mysqli_stmt_execute($st2);

if(mysqli_stmt_affected_rows($st2)<=0){
    mysqli_stmt_close($st2);
    throw new Exception("Request already processed.");
}
mysqli_stmt_close($st2);

$msg="System: Your rent request for \"{$item_name}\" was rejected. You may chat with the owner for details.";

$notify=mysqli_prepare($conn,"
INSERT INTO messages(sender_id,receiver_id,message_text,is_read,created_at)
VALUES(?,?,?,?,NOW())
");

if($notify){
    $is_read=0;
    mysqli_stmt_bind_param($notify,"iisi",$owner_id,$borrower_id,$msg,$is_read);
    mysqli_stmt_execute($notify);
    mysqli_stmt_close($notify);
}

mysqli_commit($conn);

go_manage("ok","Request rejected.");

}catch(Exception $e){

mysqli_rollback($conn);
error_log("Reject Request Error: ".$e->getMessage());

go_manage("error","Operation failed.");

}

