<?php

if (getenv('MYSQLHOST')) {

    // RAILWAY (production)
    $host = $_ENV['MYSQLHOST'];
    $user = $_ENV['MYSQLUSER'];
    $pass = $_ENV['MYSQLPASSWORD'];
    $db   = $_ENV['MYSQLDATABASE'];
    $port = $_ENV['MYSQLPORT'];

} else {

    // LOCAL (XAMPP)
    $host = 'localhost';
    $db   = 'teachfinder_db';
    $user = 'root';
    $pass = '';
    $port = 3306;
}
$host = 'localhost';
$db   = 'teachfinder_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options); 
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}


?>
