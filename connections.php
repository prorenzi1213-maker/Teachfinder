<?php
// 1. Define the missing variables
$host = 'localhost';       // The hostname (usually localhost for XAMPP)
$dbname = 'teachfinder_db';   // Your actual database name
$username = 'root';        // Default XAMPP username
$password = '';            // Default XAMPP password (usually empty)

// PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// MySQLi connection (for files using $conn)
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>