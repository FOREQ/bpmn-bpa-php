<?php

// The XAMPP CLI configuration may point sessions to C:\xampp\tmp, which is
// not always writable when the built-in development server is used.
if (PHP_SAPI === 'cli-server' && session_status() === PHP_SESSION_NONE) {
    $localSessionPath = __DIR__ . '/database/sessions';

    if (!is_dir($localSessionPath)) {
        mkdir($localSessionPath, 0775, true);
    }

    if (is_writable($localSessionPath)) {
        session_save_path($localSessionPath);
    }
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$decodedPath = urldecode($path);

// Публичная проверка сертификата по QR-коду: /certificate/<токен>
if (preg_match('#^/certificate/([a-zA-Z0-9]+)$#', $decodedPath, $matches)) {
    $_GET['token'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/public/verify.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/verify.php';
    require __DIR__ . '/public/verify.php';
    return true;
}

$routes = [
    '/api/' => __DIR__ . '/api/',
    '/assets/' => __DIR__ . '/assets/',
    '/public/' => __DIR__ . '/public/',
    '/' => __DIR__ . '/public/',
];

$mimeTypes = [
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'pdf' => 'application/pdf',
];

foreach ($routes as $prefix => $baseDir) {
    if ($prefix !== '/' && !str_starts_with($decodedPath, $prefix)) {
        continue;
    }

    $relativePath = $prefix === '/'
        ? ltrim($decodedPath, '/')
        : substr($decodedPath, strlen($prefix));

    if ($relativePath === '') {
        $relativePath = 'index.php';
    }

    $file = realpath($baseDir . $relativePath);
    $base = realpath($baseDir);

    if ($file === false || $base === false || !str_starts_with($file, $base) || !is_file($file)) {
        if ($prefix === '/') {
            return false;
        }

        continue;
    }

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if ($extension !== 'php') {
        header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($file));
        readfile($file);
        return true;
    }

    $_SERVER['SCRIPT_NAME'] = $decodedPath;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    require $file;
    return true;
}

return false;
