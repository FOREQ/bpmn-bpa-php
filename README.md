# BPMN/BPA Testing System

Веб-приложение на PHP для регистрации участников, прохождения теоретического теста, выполнения практического задания BPMN/BPA, администрирования заявок и генерации сертификатов.

## Быстрый запуск через Docker

Это самый простой вариант для передачи проекта другому человеку. На компьютере нужен только Docker Desktop.

### 1. Установить Docker Desktop

Скачайте и установите Docker Desktop:

```text
https://www.docker.com/products/docker-desktop/
```

После установки откройте Docker Desktop и дождитесь, пока он полностью запустится.

Проверьте Docker в PowerShell:

```powershell
docker --version
docker compose version
```

Если Docker Desktop показывает ошибку `WSL not installed`, откройте PowerShell от имени администратора и выполните:

```powershell
wsl --install
```

После установки WSL перезагрузите компьютер, откройте Docker Desktop и проверьте:

```powershell
wsl --status
wsl --list --verbose
```

### 2. Скачать проект

```powershell
git clone <URL_РЕПОЗИТОРИЯ>
cd bpmn-bpa-php
```

Если проект передан архивом, распакуйте его и откройте папку проекта в PowerShell.

### 3. Запустить проект

Из корня проекта выполните:

```powershell
docker compose up --build
```

При первом запуске Docker скачает PHP-образ, установит Composer-зависимости и создаст SQLite-базу `database/database.sqlite`, если ее еще нет.

Откройте в браузере:

```text
http://localhost:8000
```

Админка:

```text
http://localhost:8000/public/admin_login.php
```

Данные входа администратора:

```text
Логин: admin
Пароль: BpmnAdmin_2026!Secure
```

Остановить сервер можно через `Ctrl+C` в PowerShell. Следующий запуск:

```powershell
docker compose up
```

Если нужно полностью пересобрать контейнер:

```powershell
docker compose build --no-cache --progress=plain
docker compose up
```

### Что делает Docker

Docker-контейнер сам:

- запускает PHP 8.2;
- включает нужные расширения `pdo_sqlite` и `zip`;
- устанавливает Composer-зависимости в `vendor/`;
- создает `database/database.sqlite` из `database/init.sql`, если базы еще нет;
- запускает сайт на порту `8000`.

В Docker не собирается отдельное расширение `sqlite3`, потому что проект использует SQLite через `PDO`.

### Проверка Docker-запуска

Когда сервер запущен, в другом PowerShell можно проверить контейнер:

```powershell
docker ps
```

В списке должен быть контейнер проекта со статусом `Up` и портом:

```text
0.0.0.0:8000->8000
```

Проверить статус Compose:

```powershell
docker compose ps
```

### Частые проблемы Docker

Если сборка падает на строке `failed to solve`, запустите подробную сборку:

```powershell
docker compose build --no-cache --progress=plain
```

Смотреть нужно не только последнюю строку, а 20-30 строк выше `failed to solve`.

Если ошибка связана с портом `8000`, значит он уже занят. Остановите старый сервер или контейнер:

```powershell
docker compose down
```

Если нужно удалить контейнеры проекта и запустить заново:

```powershell
docker compose down
docker compose up --build
```

## Запуск без Docker на Windows

Эта инструкция рассчитана на обычный запуск через XAMPP и PowerShell.

### 1. Установить программы

Нужно установить:

- **XAMPP** с PHP 8.1 или новее: <https://www.apachefriends.org/>
- **Composer**: <https://getcomposer.org/download/>
- **Git**: <https://git-scm.com/downloads>
- **7-Zip** необязательно, но полезно: <https://www.7-zip.org/>

После установки откройте новый PowerShell и проверьте:

```powershell
php -v
composer --version
git --version
```

Если `php` не находится, добавьте `C:\xampp\php` в переменную `Path` Windows и откройте PowerShell заново.

### 2. Включить PHP-расширения в XAMPP

Откройте файл:

```powershell
notepad C:\xampp\php\php.ini
```

Найдите эти строки и уберите `;` в начале, если он есть:

```ini
extension=curl
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip
```

Особенно важно включить `zip`: без него `composer install` может упасть с ошибкой:

```text
The zip extension and unzip/7z commands are both missing
```

После сохранения `php.ini` закройте PowerShell, откройте новый и проверьте:

```powershell
php -m | Select-String "curl|mbstring|openssl|pdo_sqlite|sqlite3|zip"
```

В выводе должны быть `pdo_sqlite`, `sqlite3` и `zip`.

### 3. Скачать проект

```powershell
git clone <URL_РЕПОЗИТОРИЯ>
cd bpmn-bpa-php
```

Если проект уже скачан архивом, распакуйте архив и откройте папку проекта в PowerShell.

### 4. Установить зависимости

Из корня проекта выполните:

```powershell
composer install
```

После успешной установки появится папка `vendor/`. Она не хранится в Git, поэтому на новом компьютере эту команду нужно выполнить обязательно.

Если снова появляется ошибка про `zip`, вернитесь к шагу 2 и проверьте, что редактируется именно этот файл:

```powershell
php --ini
```

В выводе должен быть путь к `C:\xampp\php\php.ini`.

### 5. Создать базу данных SQLite

Проект использует файл:

```text
database/database.sqlite
```

Он не хранится в Git, поэтому на новом компьютере его нужно создать.

Вариант 1, если установлена команда `sqlite3`:

```powershell
sqlite3 database/database.sqlite ".read database/init.sql"
```

Вариант 2, без установки SQLite CLI, через PHP:

```powershell
php --% -r "$db=new PDO('sqlite:database/database.sqlite'); $sql=file_get_contents('database/init.sql'); $db->exec($sql); echo 'Database created'.PHP_EOL;"
```

Проверьте, что файл появился:

```powershell
Get-Item database/database.sqlite
```

### 6. Настроить почту

Настройки SMTP находятся в файле:

```text
config/mail.php
```

Проверьте значения:

- `host` - SMTP-сервер;
- `port` - порт SMTP;
- `username` - логин почты;
- `password` - пароль приложения или SMTP-пароль;
- `from_email` - email отправителя;
- `from_name` - имя отправителя.

Для Gmail обычно нужен не обычный пароль от аккаунта, а **App Password**. Он создается в настройках Google-аккаунта при включенной двухфакторной аутентификации.

### 7. Запустить локальный сервер

Из корня проекта:

```powershell
php -S localhost:8000 router.php
```

Откройте в браузере:

```text
http://localhost:8000
```

Пока сервер работает, окно PowerShell закрывать нельзя. Для остановки нажмите `Ctrl+C`.

Важно: не запускайте проект командой `php -S localhost:8000 -t public`. В этом режиме сервер видит только папку `public/`, а API лежит в отдельной папке `api/`. Из-за этого при подаче заявки будет ошибка `api/register.php 404 Not Found`.

## Основные страницы

- Главная страница: <http://localhost:8000/>
- Регистрация участника: <http://localhost:8000/register.php>
- Вход участника: <http://localhost:8000/student_login.php>
- Кабинет участника: <http://localhost:8000/student_dashboard.php>
- Теоретический тест: <http://localhost:8000/test.php>
- Практическое задание: <http://localhost:8000/practical.php>
- Админ-панель: <http://localhost:8000/admin.php>
- Вход администратора: <http://localhost:8000/admin_login.php>
- Восстановление пароля: <http://localhost:8000/forgot_password.php>

## Проверка после запуска

1. Откройте <http://localhost:8000/>.
2. Зарегистрируйте нового участника.
3. Зайдите в админ-панель.
4. Подтвердите заявку участника.
5. Проверьте, что участнику отправляется письмо с временным паролем.
6. Войдите как участник.
7. Пройдите тест и практическое задание.
8. Проверьте результат и генерацию сертификата.

## Частые проблемы

### Composer пишет: `The zip extension and unzip/7z commands are both missing`

Включите `zip` в `C:\xampp\php\php.ini`:

```ini
extension=zip
```

Потом откройте новый PowerShell и проверьте:

```powershell
php -m | Select-String zip
```

После этого снова выполните:

```powershell
composer install
```

### Ошибка `could not find driver`

PHP не видит SQLite-драйвер.

Включите в `C:\xampp\php\php.ini`:

```ini
extension=pdo_sqlite
extension=sqlite3
```

Потом откройте новый PowerShell и проверьте:

```powershell
php -m | Select-String "pdo_sqlite|sqlite3"
```

### Ошибка `vendor/autoload.php not found`

Не установлены зависимости Composer.

Выполните:

```powershell
composer install
```

### База данных не найдена

Создайте файл базы:

```powershell
php --% -r "$db=new PDO('sqlite:database/database.sqlite'); $sql=file_get_contents('database/init.sql'); $db->exec($sql); echo 'Database created'.PHP_EOL;"
```

### Письма не отправляются

Проверьте `config/mail.php`:

- правильный SMTP-хост и порт;
- правильный логин;
- используется пароль приложения, а не обычный пароль от почты;
- почтовый провайдер разрешает SMTP-доступ.

## Полезные команды

Запустить сервер:

```powershell
php -S localhost:8000 router.php
```

Установить зависимости:

```powershell
composer install
```

Проверить PHP-файлы на синтаксис:

```powershell
php -l config/db.php
php -l config/mail.php
```

Запустить тесты, если они настроены:

```powershell
composer test
```

Проверить статус Git:

```powershell
git status
```
