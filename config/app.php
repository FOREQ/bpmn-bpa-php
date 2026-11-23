<?php

$verifyBaseUrl = trim((string)(getenv('VERIFY_BASE_URL') ?: ''));

if ($verifyBaseUrl === '') {
    $requestHost = $_SERVER['HTTP_HOST'] ?? '';
    $isLocalRequest = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $requestHost) === 1;

    if ($isLocalRequest) {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $verifyBaseUrl = ($isHttps ? 'https' : 'http') . '://' . $requestHost . '/certificate/';
    } else {
        $verifyBaseUrl = 'https://bpmn.govtec.kz/certificate/';
    }
}

return [
    // Базовый URL страницы проверки сертификата.
    // На него указывает QR-код в PDF-сертификате: <verify_base_url><токен>
    'verify_base_url' => rtrim($verifyBaseUrl, '/') . '/',
];
