<?php

if (getenv('MYSQLHOST')) {

    // RAILWAY
    $host = getenv('MYSQLHOST');
    $user = getenv('MYSQLUSER');
    $pass = getenv('MYSQLPASSWORD');
    $db   = getenv('MYSQLDATABASE');
    $port = getenv('MYSQLPORT');

} else {

    // LOCAL (XAMPP)
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
