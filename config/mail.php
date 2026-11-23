<?php

$config = [
    'transport' => strtolower(trim((string)(getenv('MAIL_TRANSPORT') ?: 'smtp'))),
    'ews_url' => trim((string)(getenv('MAIL_EWS_URL') ?: '')),
    'host' => trim((string)(getenv('MAIL_HOST') ?: '')),
    'port' => (int)(getenv('MAIL_PORT') ?: 587),
    'username' => trim((string)(getenv('MAIL_USERNAME') ?: '')),
    'password' => (string)(getenv('MAIL_PASSWORD') ?: ''),
    'from_email' => trim((string)(getenv('MAIL_FROM_EMAIL') ?: '')),
    'from_name' => trim((string)(getenv('MAIL_FROM_NAME') ?: 'BPMN Testing System')),
    'encryption' => strtolower(trim((string)(getenv('MAIL_ENCRYPTION') ?: 'starttls'))),
];

$credentialFile = trim((string)(getenv('MAIL_CREDENTIALS_FILE') ?: ''));
if ($credentialFile !== '') {
    if (!is_file($credentialFile) || !is_readable($credentialFile)) {
        throw new RuntimeException('Mail credential file is not accessible.');
    }
    try {
        $json = file_get_contents($credentialFile, false, null, 0, 16385);
        if ($json === false || strlen($json) > 16384) {
            throw new RuntimeException('Invalid mail credential file.');
        }
        $credentials = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        foreach (['username', 'password', 'from_email'] as $field) {
            if (!isset($credentials[$field]) || !is_string($credentials[$field]) || $credentials[$field] === '') {
                throw new RuntimeException('Invalid mail credential file.');
            }
            $config[$field] = $credentials[$field];
        }
    } catch (JsonException $error) {
        throw new RuntimeException('Invalid mail credential file.');
    }
}

return $config;
