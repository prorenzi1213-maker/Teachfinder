<?php

if (getenv('MYSQLHOST')) {

    // RAILWAY
    $host = $_ENV['MYSQLHOST'];
    $dbname = $_ENV['MYSQLDATABASE'];
    $username = $_ENV['MYSQLUSER'];
    $password = $_ENV['MYSQLPASSWORD'];
    $port = $_ENV['MYSQLPORT'];

} else {

    // LOCAL (XAMPP)
    $host = 'localhost';
    $dbname = 'teachfinder_db';
    $username = 'root';
    $password = '';
    $port = 3306;
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
?>
