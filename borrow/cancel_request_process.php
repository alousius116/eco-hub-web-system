<?php
require_once __DIR__ . "/../auth/auth_guard.php";
require_login();

require_once __DIR__ . "/../config/db_connect.php";

$BASE    = "/RWDD2408/eco_hub";
$user_id = (int)($_SESSION['user_id'] ?? 0);

$request_id = filter_input(INPUT_POST,'request_id',FILTER_VALIDATE_INT);

if(!$request_id || $request_id<=0){
    header("Location: $BASE/borrow/my_borrow.php?error=".urlencode("Invalid request."));
    exit();
}

mysqli_autocommit($conn,false);

try{
$sql="
SELECT request_id
FROM borrow_requests
WHERE request_id=? 
AND borrower_id=? 
AND LOWER(request_status)='pending'
LIMIT 1
FOR UPDATE
";

$stmt=mysqli_prepare($conn,$sql);
if(!$stmt){
    throw new Exception("Prepare failed.");
}

mysqli_stmt_bind_param($stmt,"ii",$request_id,$user_id);
mysqli_stmt_execute($stmt);

$row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if(!$row){
    throw new Exception("Unable to cancel (maybe already processed).");
}

$sql2="
UPDATE borrow_requests
SET request_status='cancelled'
WHERE request_id=? AND borrower_id=? AND LOWER(request_status)='pending'
LIMIT 1
";

$st2=mysqli_prepare($conn,$sql2);
if(!$st2){
    throw new Exception("Prepare failed (update).");
}

mysqli_stmt_bind_param($st2,"ii",$request_id,$user_id);
mysqli_stmt_execute($st2);

if(mysqli_stmt_affected_rows($st2)<=0){
    mysqli_stmt_close($st2);
    throw new Exception("Unable to cancel.");
}
mysqli_stmt_close($st2);
mysqli_commit($conn);

header("Location: $BASE/borrow/my_borrow.php?ok=".urlencode("Request cancelled."));
exit();

}catch(Exception $e){

mysqli_rollback($conn);
error_log("Cancel Request Error: ".$e->getMessage());

header("Location: $BASE/borrow/my_borrow.php?error=".urlencode("Action failed."));
exit();

}

