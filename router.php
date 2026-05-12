<?php
/**
 * router.php — PHP built-in server router for Railway.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Block sensitive files
$blocked = [
    '.env', '.gitignore', 'composer.json', 'composer.lock',
    'nixpacks.toml', 'connections.php', 'config.php',
    'mailer.php', 'paymongo_config.php', 'router.php',
];
$basename = basename($uri);
if (in_array($basename, $blocked) || str_starts_with($basename, '.')) {
    http_response_code(403);
    exit('Forbidden');
}

$real_file = __DIR__ . $uri;

// Serve real static files (images, css, js) — NOT .php files
if (
    $uri !== '/' &&
    file_exists($real_file) &&
    !is_dir($real_file) &&
    pathinfo($real_file, PATHINFO_EXTENSION) !== 'php'
) {
    return false;
}

// Root → index.php
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

// Route to matching .php file
if (file_exists($real_file) && pathinfo($real_file, PATHINFO_EXTENSION) === 'php') {
    require $real_file;
    return true;
}

// Try appending .php (e.g. /login → login.php)
if (file_exists($real_file . '.php')) {
    require $real_file . '.php';
    return true;
}

http_response_code(404);
echo '<!DOCTYPE html><html><body><h1>404 Not Found</h1></body></html>';
