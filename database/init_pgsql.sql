-- Схема базы данных проекта BPMN/BPA Testing System (PostgreSQL)
-- Выполнить один раз при первом запуске на пустой базе.
--
-- Идентификаторы колонок специально не экранированы кавычками —
-- PostgreSQL сам приводит их к нижнему регистру, и весь код проекта
-- рассчитан именно на это (см. lib/db_compat.php — там имена колонок
-- возвращаются PHP-коду обратно в camelCase).

CREATE TABLE IF NOT EXISTS Participant (
    id                          TEXT PRIMARY KEY,
    sessionId                   TEXT UNIQUE NOT NULL,

    -- Регистрационные данные участника
    fullName                    TEXT NOT NULL,
    email                       TEXT NOT NULL,
    phone                       TEXT NOT NULL,
    organization                TEXT NOT NULL,

    -- Назначенный вариант теста и практики
    variantId                   TEXT NOT NULL,
    questionOrder                TEXT,   -- JSON: порядок вопросов теста
    optionOrder                  TEXT,   -- JSON: порядок вариантов ответов
    practicalTaskIds             TEXT,   -- JSON: id практических заданий
    complexityVariantId          TEXT,

    -- Доступ участника (заявка -> подтверждение -> временный пароль)
    passwordHash                 TEXT,
    accountStatus                TEXT NOT NULL DEFAULT 'pending', -- pending | approved | rejected
    tempPasswordHash             TEXT,
    tempPasswordExpiresAt        TIMESTAMP,
    approvedAt                   TIMESTAMP,
    rejectedAt                   TIMESTAMP,
    rejectionReason               TEXT,
    mustChangePassword           INTEGER NOT NULL DEFAULT 0,
    failedLoginAttempts          INTEGER NOT NULL DEFAULT 0,
    accountLockedUntil           TIMESTAMP,
    lastLoginAt                  TIMESTAMP,

    -- Восстановление пароля
    passwordResetRequested       INTEGER NOT NULL DEFAULT 0,
    passwordResetAllowed         INTEGER NOT NULL DEFAULT 0,
    passwordResetRequestedAt     TIMESTAMP,
    passwordResetAllowedAt       TIMESTAMP,

    -- Теоретический тест
    answers                      TEXT,   -- JSON: ответы участника
    score                        INTEGER,
    total                        INTEGER,
    percent                      REAL,
    status                       TEXT,   -- passed | failed
    submittedAt                  TIMESTAMP,

    -- Практическое задание
    practicalAnswers             TEXT,   -- JSON: BPMN-схемы и расчет сложности
    practicalSubmittedAt         TIMESTAMP,
    practicalPreviousScore       INTEGER,
    practicalNewScore            INTEGER,
    practicalMetricsScore        INTEGER,
    practicalScoreTotal          INTEGER,
    practicalGradedAt            TIMESTAMP,

    -- Сертификат
    certificateNumber            TEXT,
    certificateToken             TEXT,
    certificateGeneratedAt       TIMESTAMP,
    certificateEmailedAt         TIMESTAMP,

    createdAt                    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt                    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_participant_email ON Participant(email);
CREATE INDEX IF NOT EXISTS idx_participant_session ON Participant(sessionId);
CREATE INDEX IF NOT EXISTS idx_participant_status ON Participant(accountStatus);

-- Исторические сертификаты, выданные до запуска этой системы
-- (импортируются из Excel/CSV скриптом database/import_legacy.php)
CREATE TABLE IF NOT EXISTS LegacyCertificate (
    id SERIAL PRIMARY KEY,
    certificateNumber TEXT UNIQUE,
    certificateToken TEXT UNIQUE,
    fullName TEXT NOT NULL,
    course TEXT,
    completionDate TEXT,
    level TEXT,
    extra TEXT,            -- JSON: колонки исходного файла, не попавшие в основные поля
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_legacy_token ON LegacyCertificate(certificateToken);
CREATE INDEX IF NOT EXISTS idx_legacy_number ON LegacyCertificate(certificateNumber);
