<?php

if (getenv('MYSQLHOST')) {

    $host = $_ENV['MYSQLHOST'];
    $user = $_ENV['MYSQLUSER'];
    $pass = $_ENV['MYSQLPASSWORD'];
    $db   = $_ENV['MYSQLDATABASE'];
    $port = $_ENV['MYSQLPORT'];

} else {

    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db   = 'teachfinder_db';
    $port = 3306;
}

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
?>
