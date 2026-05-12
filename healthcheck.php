<?php
// STANDALONE diagnostic — no require, no session
header('Content-Type: text/plain; charset=utf-8');

echo "=== TeachFinder Health Check ===\n\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

$exts = ['pdo', 'pdo_mysql', 'curl', 'mbstring', 'openssl'];
echo "Extensions:\n";
foreach ($exts as $e) {
    echo "  $e: " . (extension_loaded($e) ? "OK" : "MISSING") . "\n";
}

echo "\nComposer autoload: ";
echo file_exists(__DIR__ . '/vendor/autoload.php') ? "OK" : "MISSING";
echo "\n";

echo "\nEnvironment Variables:\n";
$vars = ['MYSQL_URL','MYSQLHOST','MYSQLDATABASE','MYSQLPORT',
         'PAYMONGO_SECRET_KEY','MAIL_USERNAME','APP_URL'];
foreach ($vars as $v) {
    $val = getenv($v);
    $sensitive = in_array($v, ['MYSQL_URL','MYSQLPASSWORD','PAYMONGO_SECRET_KEY','MAIL_USERNAME']);
    if (!$val) {
        echo "  $v: NOT SET\n";
    } elseif ($sensitive) {
        echo "  $v: " . substr($val, 0, 8) . "...\n";
    } else {
        echo "  $v: $val\n";
    }
}

echo "\nDatabase Connection: ";
$mysql_url = getenv('MYSQL_URL');
if ($mysql_url) {
    $p = parse_url($mysql_url);
    $host = $p['host']; $user = $p['user'];
    $pass = $p['pass'] ?? ''; $db = ltrim($p['path'], '/');
    $port = $p['port'] ?? 3306;
} else {
    $host = getenv('MYSQLHOST'); $user = getenv('MYSQLUSER');
    $pass = getenv('MYSQLPASSWORD'); $db = getenv('MYSQLDATABASE');
    $port = (int)(getenv('MYSQLPORT') ?: 3306);
}

if (!$host) {
    echo "SKIPPED — no DB host set\n";
} elseif (!extension_loaded('pdo_mysql')) {
    echo "SKIPPED — pdo_mysql missing\n";
} else {
    try {
        new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "OK — $db @ $host:$port\n";
    } catch (PDOException $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Done ===\n";
