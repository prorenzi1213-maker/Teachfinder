<?php

$url = getenv('MYSQL_URL');

if (!$url) {
    die("Missing DB environment variables (MYSQL_URL not found)");
}

$parts = parse_url($url);

$host = $parts['host'];
$port = $parts['port'] ?? 3306;
$user = $parts['user'];
$pass = $parts['pass'];
$db   = ltrim($parts['path'], '/');

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
?>
