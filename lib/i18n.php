<?php

const I18N_DEFAULT_LOCALE = 'ru';
const I18N_LOCALE_COOKIE = 'bpmn_locale';
const I18N_SUPPORTED_LOCALES = ['ru', 'kk'];

function i18nBootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    $bootstrapped = true;

    $requestedLocale = $_GET['lang'] ?? $_COOKIE[I18N_LOCALE_COOKIE] ?? I18N_DEFAULT_LOCALE;
    $locale = in_array($requestedLocale, I18N_SUPPORTED_LOCALES, true)
        ? $requestedLocale
        : I18N_DEFAULT_LOCALE;

    $GLOBALS['bpmn_locale'] = $locale;

    if (isset($_GET['lang']) && in_array($_GET['lang'], I18N_SUPPORTED_LOCALES, true)) {
        $_COOKIE[I18N_LOCALE_COOKIE] = $locale;

        if (!headers_sent()) {
            setcookie(I18N_LOCALE_COOKIE, $locale, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
    }

    if (PHP_SAPI !== 'cli') {
        header('Vary: Cookie', false);
        ob_start('i18nTransformOutput');
    }
}

function i18nLocale(): string
{
    return $GLOBALS['bpmn_locale'] ?? I18N_DEFAULT_LOCALE;
}

function i18nTranslations(): array
{
    static $translations = null;

    if ($translations === null) {
        $translations = require __DIR__ . '/../lang/kk.php';
    }

    return $translations;
}

function i18nTranslate(string $text): string
{
    if (i18nLocale() !== 'kk' || $text === '') {
        return $text;
    }

    return strtr($text, i18nTranslations());
}

function i18nLanguageUrl(string $locale): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($requestUri);
    $path = $parts['path'] ?? '/';
    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $query['lang'] = $locale;

    return $path . '?' . http_build_query($query);
}

function i18nSwitcherMarkup(bool $floating = false): string
{
    $locale = i18nLocale();
    $containerStyle = 'display:flex;align-items:center;gap:4px;padding:4px;'
        . 'border:1px solid rgba(148,163,184,.45);border-radius:999px;'
        . 'background:rgba(15,23,42,.88);box-shadow:0 4px 14px rgba(15,23,42,.18);'
        . 'margin-left:auto;white-space:nowrap;flex-shrink:0;';

    if ($floating) {
        $containerStyle .= 'position:fixed;top:14px;right:14px;z-index:10000;';
    }

    $linkStyle = 'display:inline-flex;align-items:center;justify-content:center;'
        . 'min-width:42px;height:30px;padding:0 9px;border-radius:999px;'
        . 'font:700 12px/1 Arial,sans-serif;text-decoration:none;';
    $activeStyle = 'background:#2ab7f6;color:#07111f;';
    $inactiveStyle = 'background:transparent;color:#fff;';

    $kkStyle = $linkStyle . ($locale === 'kk' ? $activeStyle : $inactiveStyle);
    $ruStyle = $linkStyle . ($locale === 'ru' ? $activeStyle : $inactiveStyle);

    return '<div class="i18n-switcher" data-i18n-switcher '
        . 'aria-label="Тілді таңдау / Выбор языка" style="' . $containerStyle . '">'
        . '<a href="' . htmlspecialchars(i18nLanguageUrl('kk'), ENT_QUOTES, 'UTF-8') . '" '
        . 'lang="kk" hreflang="kk" style="' . $kkStyle . '">ҚАЗ</a>'
        . '<a href="' . htmlspecialchars(i18nLanguageUrl('ru'), ENT_QUOTES, 'UTF-8') . '" '
        . 'lang="ru" hreflang="ru" style="' . $ruStyle . '">РУС</a>'
        . '</div>';
}

function i18nResponseType(): string
{
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            return strtolower(trim(substr($header, strlen('Content-Type:'))));
        }
    }

    return '';
}

function i18nTransformOutput(string $output): string
{
    $contentType = i18nResponseType();
    $isHtml = preg_match('/<!doctype\s+html|<html\b/i', $output) === 1;
    $trimmed = ltrim($output);
    $isJson = str_contains($contentType, 'application/json')
        || (($trimmed[0] ?? '') === '{')
        || (($trimmed[0] ?? '') === '[');
    $isText = $isHtml
        || $isJson
        || str_starts_with($contentType, 'text/')
        || $contentType === '';

    if (!$isText) {
        return $output;
    }

    if (i18nLocale() === 'kk') {
        $output = i18nTranslate($output);
    }

    if ($isHtml && !str_contains($output, 'data-site-favicon')) {
        $faviconMarkup = '<link rel="icon" type="image/png" sizes="128x128" '
            . 'href="../assets/favicon.png" data-site-favicon>';
        $output = preg_replace('/<head([^>]*)>/i', '<head$1>' . $faviconMarkup, $output, 1) ?? $output;
    }

    if (!$isHtml || str_contains($output, 'data-i18n-switcher')) {
        return $output;
    }

    $headerMarkup = i18nSwitcherMarkup();

    if (preg_match('/<\/nav>/i', $output) === 1) {
        return preg_replace('/<\/nav>/i', '</nav>' . $headerMarkup, $output, 1) ?? $output;
    }

    $floatingMarkup = i18nSwitcherMarkup(true);

    return preg_replace('/<body([^>]*)>/i', '<body$1>' . $floatingMarkup, $output, 1) ?? $output;
}

i18nBootstrap();
