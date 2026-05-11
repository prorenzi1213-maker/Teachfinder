<?php

$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$db   = getenv('MYSQL_DATABASE');  // 👈 IMPORTANT CHANGE
$port = getenv('MYSQLPORT');

if (!$host) {
    die("Missing DB environment variables");
}

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
?>
