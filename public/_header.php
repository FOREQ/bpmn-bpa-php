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

            <div class="lang-toggle" role="group" aria-label="Язык">
                <button type="button" onclick="document.querySelectorAll('.lang-toggle button').forEach(b=>b.classList.toggle('active', b===this))">ҚАЗ</button>
                <button type="button" class="active" onclick="document.querySelectorAll('.lang-toggle button').forEach(b=>b.classList.toggle('active', b===this))">РУС</button>
            </div>
        </div>
    </div>
</header>
