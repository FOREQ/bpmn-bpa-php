<?php

/**
 * Простой файловый лимит запросов (без изменений схемы БД).
 * Счётчики хранятся в database/ratelimit/, доступ к папке database закрыт.
 */

/** IP клиента. X-Forwarded-For учитывается, только если запрос пришёл от прокси из TRUSTED_PROXIES. */
function clientIp(): string
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $trusted = array_values(array_filter(array_map('trim', explode(',', (string)(getenv('TRUSTED_PROXIES') ?: '')))));

    if ($remote !== '' && $trusted && ipInRanges($remote, $trusted)) {
        $forwarded = array_map('trim', explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
        // Справа налево: первый адрес, не принадлежащий доверенным прокси, и есть клиент.
        for ($i = count($forwarded) - 1; $i >= 0; $i--) {
            $candidate = $forwarded[$i];
            if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
                break;
            }
            if (!ipInRanges($candidate, $trusted)) {
                return $candidate;
            }
        }
    }

    return $remote !== '' ? $remote : 'unknown';
}

/** Отдельные адреса и CIDR, IPv4 и IPv6. */
function ipInRanges(string $ip, array $ranges): bool
{
    $ipBin = @inet_pton($ip);
    if ($ipBin === false) {
        return false;
    }
    foreach ($ranges as $range) {
        [$subnet, $bits] = array_pad(explode('/', $range, 2), 2, null);
        $subnetBin = @inet_pton((string)$subnet);
        if ($subnetBin === false || strlen($subnetBin) !== strlen($ipBin)) {
            continue;
        }
        $bits = $bits === null ? strlen($ipBin) * 8 : max(0, min((int)$bits, strlen($ipBin) * 8));
        $bytes = intdiv($bits, 8);
        $rest = $bits % 8;
        if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            continue;
        }
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        if (((ord($ipBin[$bytes]) ^ ord($subnetBin[$bytes])) & $mask) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Проверяет все окна и, если ни одно не превышено, засчитывает попытку.
 * $limits: [длина окна в секундах => максимум попыток]. false — лимит исчерпан.
 */
function rateLimitHit(string $bucket, string $key, array $limits): bool
{
    $directory = __DIR__ . '/../database/ratelimit';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Rate limit directory is not writable.');
    }

    $path = $directory . '/' . preg_replace('/[^a-z0-9_]/', '', $bucket) . '_' . hash('sha256', $key) . '.json';
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Rate limit file is not writable.');
    }

    try {
        flock($handle, LOCK_EX);
        $now = time();
        $longest = max(array_keys($limits));
        $hits = json_decode((string)stream_get_contents($handle), true);
        $hits = is_array($hits)
            ? array_values(array_filter($hits, static fn($t) => is_int($t) && $t > $now - $longest))
            : [];

        foreach ($limits as $window => $max) {
            $inWindow = count(array_filter($hits, static fn($t) => $t > $now - $window));
            if ($inWindow >= $max) {
                return false;
            }
        }

        $hits[] = $now;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($hits));
        fflush($handle);
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
