<?php

$host = getenv('MYSQLHOST') 
     ?: parse_url(getenv('MYSQL_URL'), PHP_URL_HOST);

$user = getenv('MYSQLUSER') 
     ?: 'root';

$pass = getenv('MYSQLPASSWORD') 
     ?: getenv('MYSQL_ROOT_PASSWORD');

$db   = getenv('MYSQLDATABASE') 
     ?: getenv('MYSQL_DATABASE');

$port = getenv('MYSQLPORT') 
     ?: 3306;

if (!$host || !$user || !$db) {
    die("Missing DB environment variables");
}

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
?>
