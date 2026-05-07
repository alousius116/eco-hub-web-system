<?php
// START SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "ecohub_apu"; // 你真实存在的 DB

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

