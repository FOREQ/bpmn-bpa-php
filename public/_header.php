<?php
/**
 * Общая шапка сайта. Подключается через require из каждой страницы.
 * Ожидает (опционально) переменную $activeNav — одно из:
 * 'index', 'register', 'login', 'admin' — подсвечивает пункт меню.
 * Ожидает (опционально) $navBase/$logoSrc — для страниц, доступных
 * по нестандартному URL (например /certificate/<токен> в verify.php),
 * где относительные ссылки "index.php" ломаются.
 */
$activeNav = $activeNav ?? '';
$navBase = $navBase ?? '';
$logoSrc = $logoSrc ?? '../assets/logo.svg/logo-black.png';
$siteBasePath = rtrim(trim((string)(getenv('APP_BASE_PATH') ?: '')), '/');
$homeUrl = $siteBasePath !== '' ? $siteBasePath . '/' : $navBase . 'index.php';
$registerUrl = $siteBasePath !== '' ? $siteBasePath . '/register' : $navBase . 'register.php';
$loginUrl = $siteBasePath !== '' ? $siteBasePath . '/login' : $navBase . 'student_login.php';
$adminUrl = $siteBasePath !== '' ? $siteBasePath . '/admin-login' : $navBase . 'admin_login.php';
$mobileUiUrl = $siteBasePath !== '' ? $siteBasePath . '/mobile_ui.js' : $navBase . 'mobile_ui.js';
?>
<header class="site-header">
    <div class="site-header-inner">
        <a href="<?= htmlspecialchars($homeUrl) ?>" class="site-brand">
            <img src="<?= htmlspecialchars($logoSrc) ?>" alt="DGSC" class="site-logo">
            <span class="site-brand-text">
                <span class="site-brand-title">Центр Поддержки</span>
                <span class="site-brand-subtitle">Цифрового Правительства</span>
            </span>
        </a>

        <div class="site-header-right">
            <button type="button" class="site-menu-toggle" aria-expanded="false" aria-controls="siteNavigation"><?= i18nLocale() === 'kk' ? 'Мәзір' : 'Меню' ?> <span aria-hidden="true">☰</span></button>
            <nav class="site-nav" id="siteNavigation" aria-label="<?= i18nLocale() === 'kk' ? 'Негізгі мәзір' : 'Основное меню' ?>">
                <a href="<?= htmlspecialchars($homeUrl) ?>" class="<?= $activeNav === 'index' ? 'active' : '' ?>">Главная</a>
                <a href="<?= htmlspecialchars($registerUrl) ?>" class="<?= $activeNav === 'register' ? 'active' : '' ?>">Регистрация</a>
                <a href="<?= htmlspecialchars($loginUrl) ?>" class="<?= $activeNav === 'login' ? 'active' : '' ?>">Войти</a>
                <a href="<?= htmlspecialchars($adminUrl) ?>" class="<?= $activeNav === 'admin' ? 'active' : '' ?>">Админ</a>
            </nav>

            <div class="lang-toggle" role="group" aria-label="Тілді таңдау / Выбор языка" data-i18n-switcher>
                <a
                    href="<?= htmlspecialchars(i18nLanguageUrl('kk'), ENT_QUOTES, 'UTF-8') ?>"
                    lang="kk"
                    hreflang="kk"
                    class="<?= i18nLocale() === 'kk' ? 'active' : '' ?>"
                    <?= i18nLocale() === 'kk' ? 'aria-current="true"' : '' ?>
                >ҚАЗ</a>
                <a
                    href="<?= htmlspecialchars(i18nLanguageUrl('ru'), ENT_QUOTES, 'UTF-8') ?>"
                    lang="ru"
                    hreflang="ru"
                    class="<?= i18nLocale() === 'ru' ? 'active' : '' ?>"
                    <?= i18nLocale() === 'ru' ? 'aria-current="true"' : '' ?>
                >РУС</a>
            </div>
        </div>
    </div>
</header>
<script src="<?= htmlspecialchars(str_replace('mobile_ui.js', 'bpmn_tools.js', $mobileUiUrl)) ?>"></script>
<script src="<?= htmlspecialchars(str_replace('mobile_ui.js', 'bpmn_touch.js', $mobileUiUrl)) ?>"></script>
<script src="<?= htmlspecialchars($mobileUiUrl) ?>" defer></script>
