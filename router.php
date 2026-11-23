<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$decodedPath = urldecode($path);
$basePath = rtrim(trim((string)(getenv('APP_BASE_PATH') ?: '')), '/');

if ($basePath !== '') {
    if (!preg_match('#^/[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $basePath)) {
        http_response_code(500);
        return true;
    }

    if ($decodedPath === $basePath) {
        header('Location: ' . $basePath . '/' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 308);
        return true;
    }

    if (!str_starts_with($decodedPath, $basePath . '/')) {
        http_response_code(404);
        return true;
    }

    $decodedPath = substr($decodedPath, strlen($basePath));

    $prettyPages = [
        'index' => 'index.php',
        'register' => 'register.php',
        'login' => 'student_login.php',
        'admin-login' => 'admin_login.php',
        'admin' => 'admin.php',
        'content' => 'content.php',
        'admin-test' => 'admin_test.php',
        'forgot-password' => 'forgot_password.php',
        'dashboard' => 'student_dashboard.php',
        'test' => 'test.php',
        'result' => 'result.php',
        'practical' => 'practical.php',
        'bpmn-test' => 'bpmn_test.php',
        'logout' => 'logout.php',
        'student-logout' => 'student_logout.php',
        'verify' => 'verify.php',
    ];

    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)
        && preg_match('#^/(?:public/)?([a-z_]+)\.php$#', $decodedPath, $matches)) {
        $slug = array_search($matches[1] . '.php', $prettyPages, true);
        if ($slug !== false) {
            $canonical = $slug === 'index' ? $basePath . '/' : $basePath . '/' . $slug;
            $query = $_SERVER['QUERY_STRING'] ?? '';
            header('Location: ' . $canonical . ($query !== '' ? '?' . $query : ''), true, 308);
            return true;
        }
    }

    if ($decodedPath === '/') {
        $decodedPath = '/public/index.php';
    } else {
        $slug = ltrim($decodedPath, '/');
        if (isset($prettyPages[$slug])) {
            $decodedPath = '/public/' . $prettyPages[$slug];
        }
    }
}

// Independent course enrollment pages use stable public IDs.
if (preg_match('#^/courses/([a-f0-9]{32})/?$#', $decodedPath, $matches)) {
    $_GET['id'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/public/course.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/course.php';
    require __DIR__ . '/public/course.php';
    return true;
}

// Публичная проверка сертификата по QR-коду: /certificate/<токен>
if (preg_match('#^/certificate/([a-zA-Z0-9_-]+)$#', $decodedPath, $matches)) {
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
