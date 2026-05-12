<?php
// Load .env for local dev only
if (file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if (!getenv($k)) { putenv("$k=$v"); $_ENV[$k] = $v; }
    }
}

// Railway provides MYSQL_URL as a single connection string
// e.g. mysql://user:pass@host:port/dbname
$mysql_url = getenv('MYSQL_URL');

if ($mysql_url) {
    $parts = parse_url($mysql_url);
    $host  = $parts['host'];
    $user  = $parts['user'];
    $pass  = $parts['pass'] ?? '';
    $db    = ltrim($parts['path'], '/');
    $port  = $parts['port'] ?? 3306;
} elseif (getenv('MYSQLHOST')) {
    $host = getenv('MYSQLHOST');
    $user = getenv('MYSQLUSER');
    $pass = getenv('MYSQLPASSWORD');
    $db   = getenv('MYSQLDATABASE');
    $port = (int)(getenv('MYSQLPORT') ?: 3306);
} else {
    // Local XAMPP
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $db   = getenv('DB_NAME') ?: 'teachfinder_db';
    $port = (int)(getenv('DB_PORT') ?: 3306);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    error_log("DB connection failed: " . $e->getMessage());
    http_response_code(500);
    exit("Database connection failed: " . $e->getMessage());
}

$conn = $pdo;
