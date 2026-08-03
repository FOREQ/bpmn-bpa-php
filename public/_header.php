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
?>
<header class="site-header">
    <div class="site-header-inner">
        <a href="<?= htmlspecialchars($navBase) ?>index.php" class="site-brand">
            <img src="<?= htmlspecialchars($logoSrc) ?>" alt="DGSC" class="site-logo">
            <span class="site-brand-text">
                <span class="site-brand-title">Центр Поддержки</span>
                <span class="site-brand-subtitle">Цифрового Правительства</span>
            </span>
        </a>

        <div class="site-header-right">
            <nav class="site-nav">
                <a href="<?= htmlspecialchars($navBase) ?>index.php" class="<?= $activeNav === 'index' ? 'active' : '' ?>">Главная</a>
                <a href="<?= htmlspecialchars($navBase) ?>register.php" class="<?= $activeNav === 'register' ? 'active' : '' ?>">Регистрация</a>
                <a href="<?= htmlspecialchars($navBase) ?>student_login.php" class="<?= $activeNav === 'login' ? 'active' : '' ?>">Войти</a>
                <a href="<?= htmlspecialchars($navBase) ?>admin_login.php" class="<?= $activeNav === 'admin' ? 'active' : '' ?>">Админ</a>
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
