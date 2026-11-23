-- Схема базы данных проекта BPMN/BPA Testing System (SQLite)
-- Выполнить один раз при первом запуске на чистой машине
-- (см. database/database.sqlite — если файл уже существует и содержит данные,
-- этот скрипт выполнять не нужно).

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
    tempPasswordExpiresAt        DATETIME,
    approvedAt                   DATETIME,
    rejectedAt                   DATETIME,
    rejectionReason               TEXT,
    mustChangePassword           INTEGER NOT NULL DEFAULT 0,
    failedLoginAttempts          INTEGER NOT NULL DEFAULT 0,
    accountLockedUntil           DATETIME,
    lastLoginAt                  DATETIME,

    -- Восстановление пароля
    passwordResetRequested       INTEGER NOT NULL DEFAULT 0,
    passwordResetAllowed         INTEGER NOT NULL DEFAULT 0,
    passwordResetRequestedAt     DATETIME,
    passwordResetAllowedAt       DATETIME,

    -- Теоретический тест
    answers                      TEXT,   -- JSON: ответы участника
    score                        INTEGER,
    total                        INTEGER,
    percent                      REAL,
    status                       TEXT,   -- passed | failed
    submittedAt                  DATETIME,

    -- Практическое задание
    practicalAnswers             TEXT,   -- JSON: BPMN-схемы и расчет сложности
    practicalSubmittedAt         DATETIME,
    practicalPreviousScore       INTEGER,
    practicalNewScore            INTEGER,
    practicalMetricsScore        INTEGER,
    practicalScoreTotal          INTEGER,
    practicalGradedAt            DATETIME,

    -- Сертификат
    certificateNumber            TEXT,
    certificateToken             TEXT,
    certificateGeneratedAt       DATETIME,
    certificateEmailedAt         DATETIME,

    createdAt                    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt                    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_participant_email ON Participant(email);
CREATE INDEX IF NOT EXISTS idx_participant_session ON Participant(sessionId);
CREATE INDEX IF NOT EXISTS idx_participant_status ON Participant(accountStatus);

-- Исторические сертификаты, выданные до запуска этой системы
-- (импортируются из Excel/CSV скриптом database/import_legacy.php)
CREATE TABLE IF NOT EXISTS LegacyCertificate (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    certificateNumber TEXT UNIQUE,
    certificateToken TEXT UNIQUE,
    fullName TEXT NOT NULL,
    course TEXT,
    completionDate TEXT,
    level TEXT,
    extra TEXT,            -- JSON: колонки исходного файла, не попавшие в основные поля
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_legacy_token ON LegacyCertificate(certificateToken);
CREATE INDEX IF NOT EXISTS idx_legacy_number ON LegacyCertificate(certificateNumber);

CREATE TABLE IF NOT EXISTS custom_question (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    variant TEXT NOT NULL CHECK (variant IN ('A', 'B', 'C', 'all')),
    question_text TEXT NOT NULL,
    question_text_kk TEXT,
    options_json TEXT NOT NULL,
    options_json_kk TEXT,
    correct_index INTEGER NOT NULL CHECK (correct_index BETWEEN 0 AND 3),
    image_path TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS custom_practical_task (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pool TEXT NOT NULL CHECK (pool IN ('first', 'second')),
    title TEXT,
    title_kk TEXT,
    description TEXT NOT NULL,
    description_kk TEXT,
    reference_file TEXT,
    solution_notes TEXT,   -- эталонное решение словами (для ИИ-проверки)
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ИИ: загруженные методички, черновики сгенерированных материалов, подсказки оценок
CREATE TABLE IF NOT EXISTS ai_source (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ai_draft (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kind TEXT NOT NULL,              -- question | practical
    target TEXT NOT NULL,            -- A/B/C/all или first/second
    payload_json TEXT NOT NULL,
    source_id BIGINT,
    status TEXT NOT NULL DEFAULT 'pending', -- pending | approved | rejected
    published_id BIGINT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME
);
CREATE INDEX IF NOT EXISTS idx_ai_draft_status ON ai_draft(status);

CREATE TABLE IF NOT EXISTS ai_practical_review (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT NOT NULL,
    result_json TEXT NOT NULL,
    model TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_ai_review_session ON ai_practical_review(session_id);

-- Дополнительная защита SQLite на случай прямой записи в базу в обход API.
CREATE TRIGGER IF NOT EXISTS participant_required_fields_before_insert
BEFORE INSERT ON Participant
WHEN trim(COALESCE(NEW.id, '')) = ''
  OR trim(COALESCE(NEW.sessionId, '')) = ''
  OR trim(COALESCE(NEW.fullName, '')) = ''
  OR trim(COALESCE(NEW.email, '')) = ''
  OR trim(COALESCE(NEW.phone, '')) = ''
  OR trim(COALESCE(NEW.organization, '')) = ''
  OR trim(COALESCE(NEW.variantId, '')) = ''
BEGIN
    SELECT RAISE(ABORT, 'Participant required fields must not be blank');
END;

CREATE TRIGGER IF NOT EXISTS participant_required_fields_before_update
BEFORE UPDATE OF id, sessionId, fullName, email, phone, organization, variantId ON Participant
WHEN trim(COALESCE(NEW.id, '')) = ''
  OR trim(COALESCE(NEW.sessionId, '')) = ''
  OR trim(COALESCE(NEW.fullName, '')) = ''
  OR trim(COALESCE(NEW.email, '')) = ''
  OR trim(COALESCE(NEW.phone, '')) = ''
  OR trim(COALESCE(NEW.organization, '')) = ''
  OR trim(COALESCE(NEW.variantId, '')) = ''
BEGIN
    SELECT RAISE(ABORT, 'Participant required fields must not be blank');
END;
