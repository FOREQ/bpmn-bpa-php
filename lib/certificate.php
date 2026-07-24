<?php

require_once __DIR__ . '/../vendor/autoload.php';

const CERTIFICATE_COURSE_NAME = 'Практическое применение методики реинжиниринга бизнес-процессов государственных органов';

function certificateVerifyBaseUrl(): string
{
    $config = require __DIR__ . '/../config/app.php';

    return $config['verify_base_url'];
}

function certificatesDir(): string
{
    $dir = __DIR__ . '/../database/certificates';

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function certificateFilePath(string $token): string
{
    return certificatesDir() . '/certificate-' . $token . '.pdf';
}

function dbExistingColumns(PDO $pdo, string $table): array
{
    if (dbDriver() === 'pgsql') {
        $stmt = $pdo->prepare("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = :table
        ");
        $stmt->execute([':table' => strtolower($table)]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'column_name');
    }

    $existing = [];

    foreach ($pdo->query("PRAGMA table_info({$table})") as $column) {
        $existing[] = $column['name'];
    }

    return $existing;
}

function ensureCertificateColumns(PDO $pdo): void
{
    $existing = array_map('strtolower', dbExistingColumns($pdo, 'Participant'));
    $timestampType = dbDriver() === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';

    $needed = [
        'certificateNumber' => 'TEXT',
        'certificateToken' => 'TEXT',
        'certificateGeneratedAt' => $timestampType,
        'certificateEmailedAt' => $timestampType,
    ];

    foreach ($needed as $name => $type) {
        if (!in_array(strtolower($name), $existing, true)) {
            $pdo->exec("ALTER TABLE Participant ADD COLUMN {$name} {$type}");
        }
    }
}

function certificateOverallScore(array $participant): array
{
    $testPercent = (float)($participant['percent'] ?? 0);
    $practicalTotal = (int)($participant['practicalScoreTotal'] ?? 0);

    $testPoints = (int)round(($testPercent / 100) * 20);
    $total = $testPoints + $practicalTotal;

    $percent = (int)round(($total / 50) * 100);

    return [
        'testPoints' => $testPoints,
        'practicalPoints' => $practicalTotal,
        'total' => $total,
        'percent' => $percent,
    ];
}

function certificateLevel(float $percent): array
{
    if ($percent >= 91) {
        return [
            'letter' => 'A',
            'text' => 'Категории A - от 91% до 100% правильных ответов',
        ];
    }

    if ($percent >= 76) {
        return [
            'letter' => 'B',
            'text' => 'Категории B - от 76% до 90% правильных ответов',
        ];
    }

    if ($percent >= 51) {
        return [
            'letter' => 'C',
            'text' => 'Категории C - от 51% до 75% правильных ответов',
        ];
    }

    return [
        'letter' => null,
        'text' => 'Прослушал курс - до 50% правильных ответов',
    ];
}

function generateCertificateNumber(PDO $pdo): string
{
    $year = date('Y');

    do {
        $number = 'CERT-' . $year . '-' . random_int(1000, 9999);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Participant WHERE certificateNumber = :number");
        $stmt->execute([':number' => $number]);
    } while ((int)$stmt->fetchColumn() > 0);

    return $number;
}

function generateCertificateToken(PDO $pdo): string
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    do {
        $token = '';

        for ($i = 0; $i < 12; $i++) {
            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Participant WHERE certificateToken = :token");
        $stmt->execute([':token' => $token]);
    } while ((int)$stmt->fetchColumn() > 0);

    return $token;
}

function renderCertificatePdf(
    string $filePath,
    string $fullName,
    string $completionDate,
    string $levelText,
    string $certificateNumber,
    string $verifyUrl
): void {
    $pageWidth = 842.25;
    $pageHeight = 595.5;

    $pdf = new TCPDF('L', 'pt', [$pageHeight, $pageWidth], true, 'UTF-8', false);

    $pdf->SetCreator('BPMN Testing System');
    $pdf->SetAuthor('Центр Поддержки Цифрового Правительства');
    $pdf->SetTitle('Сертификат ' . $certificateNumber);

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0, true);
    $pdf->SetAutoPageBreak(false);

    $pdf->AddPage();

    $fontDir = __DIR__ . '/../assets/certificate/fonts/';

    $pdf->AddFont('montserrat', '', $fontDir . 'montserrat.php');
    $pdf->AddFont('montserratmedium', '', $fontDir . 'montserratmedium.php');
    $pdf->AddFont('montserratsemib', '', $fontDir . 'montserratsemib.php');
    $pdf->AddFont('montserratb', '', $fontDir . 'montserratb.php');

    $regular = 'montserrat';
    $medium = 'montserratmedium';
    $semiBold = 'montserratsemib';
    $bold = 'montserratb';

    $template = __DIR__ . '/../assets/certificate/template.png';
    $pdf->Image($template, 0, 0, $pageWidth, $pageHeight, 'PNG', '', '', false, 300);

    $darkBlue = [2, 33, 84];      // #022154 — заголовок
    $ink = [15, 23, 42];          // #0F172A — ФИО и подпись организации
    $gray = [51, 51, 51];         // #333333 — основной текст

    // Заголовок «С Е Р Т И Ф И К А Т»
    $pdf->SetFont($bold, '', 48);
    $pdf->SetTextColorArray($darkBlue);
    $pdf->SetXY(0, 165);
    $pdf->Cell($pageWidth, 60, 'С Е Р Т И Ф И К А Т', 0, 0, 'C');

    // ФИО участника (над длинной линией шаблона)
    $pdf->SetFont($semiBold, '', 18);
    $pdf->SetTextColorArray($ink);
    $pdf->SetXY(0, 263);
    $pdf->Cell($pageWidth, 24, $fullName, 0, 0, 'C');

    // Текст о прохождении курса
    $bodyText = 'о том, что он(она) ' . $completionDate . ' прослушал(а) обучающий курс "'
        . CERTIFICATE_COURSE_NAME . '"';

    $pdf->SetFont($regular, '', 13);
    $pdf->SetTextColorArray($gray);
    $pdf->SetXY(($pageWidth - 620) / 2, 310);
    $pdf->MultiCell(620, 18, $bodyText, 0, 'C', false, 0);

    // Уровень
    $pdf->SetFont($semiBold, '', 12);
    $pdf->SetTextColorArray($gray);
    $pdf->SetXY(0, 370);
    $pdf->Cell($pageWidth, 16, 'Уровень: ' . $levelText, 0, 0, 'C');

    // Дата завершения
    $pdf->SetFont($regular, '', 11);
    $pdf->SetTextColorArray($gray);
    $pdf->SetXY(75, 471);
    $pdf->Cell(300, 14, 'Дата завершения: ' . $completionDate, 0, 0, 'L');

    // Подпись организации
    $pdf->SetFont($bold, '', 12);
    $pdf->SetTextColorArray($ink);
    $pdf->SetXY(75, 488);
    $pdf->Cell(300, 15, 'Центр Поддержки', 0, 0, 'L');
    $pdf->SetXY(75, 503);
    $pdf->Cell(300, 15, 'Цифрового Правительства', 0, 0, 'L');

    // Город и год
    $pdf->SetFont($bold, '', 10);
    $pdf->SetTextColorArray($gray);
    $pdf->SetXY(0, 533);
    $pdf->Cell($pageWidth, 13, 'г. Астана ' . date('Y') . ' г.', 0, 0, 'C');

    // Номер сертификата
    $pdf->SetFont($medium, '', 9);
    $pdf->SetTextColorArray($gray);
    $pdf->SetXY(572, 442);
    $pdf->Cell(120, 12, 'Номер сертификата:', 0, 0, 'L');
    $pdf->SetXY(572, 457);
    $pdf->Cell(120, 12, $certificateNumber, 0, 0, 'L');

    // QR-код для проверки подлинности
    $qrStyle = [
        'border' => 0,
        'padding' => 0,
        'fgcolor' => [0, 0, 0],
        'bgcolor' => false,
    ];
    $pdf->write2DBarcode($verifyUrl, 'QRCODE,H', 692.25, 435.5, 80, 80, $qrStyle, 'N');

    $pdf->Output($filePath, 'F');
}

/**
 * Генерирует (или перегенерирует) сертификат участника и сохраняет
 * номер/токен/дату генерации в базе. Номер и токен при повторной
 * генерации не меняются.
 */
function generateCertificate(PDO $pdo, array $participant): array
{
    ensureCertificateColumns($pdo);

    $certificateNumber = $participant['certificateNumber'] ?? null;
    $certificateToken = $participant['certificateToken'] ?? null;

    if (empty($certificateNumber)) {
        $certificateNumber = generateCertificateNumber($pdo);
    }

    if (empty($certificateToken)) {
        $certificateToken = generateCertificateToken($pdo);
    }

    $score = certificateOverallScore($participant);
    $level = certificateLevel($score['percent']);

    $gradedAt = $participant['practicalGradedAt'] ?? null;
    $completionDate = $gradedAt
        ? date('d.m.Y', strtotime($gradedAt))
        : date('d.m.Y');

    $filePath = certificateFilePath($certificateToken);
    $verifyUrl = certificateVerifyBaseUrl() . $certificateToken;

    renderCertificatePdf(
        $filePath,
        $participant['fullName'] ?? '',
        $completionDate,
        $level['text'],
        $certificateNumber,
        $verifyUrl
    );

    $stmt = $pdo->prepare("
        UPDATE Participant
        SET
            certificateNumber = :number,
            certificateToken = :token,
            certificateGeneratedAt = CURRENT_TIMESTAMP,
            updatedAt = CURRENT_TIMESTAMP
        WHERE sessionId = :sessionId
    ");

    $stmt->execute([
        ':number' => $certificateNumber,
        ':token' => $certificateToken,
        ':sessionId' => $participant['sessionId'],
    ]);

    return [
        'number' => $certificateNumber,
        'token' => $certificateToken,
        'filePath' => $filePath,
        'verifyUrl' => $verifyUrl,
        'percent' => $score['percent'],
        'total' => $score['total'],
        'levelText' => $level['text'],
        'levelLetter' => $level['letter'],
        'completionDate' => $completionDate,
    ];
}

function ensureLegacyCertificateTable(PDO $pdo): void
{
    $isPgsql = dbDriver() === 'pgsql';
    $idColumn = $isPgsql ? 'id SERIAL PRIMARY KEY' : 'id INTEGER PRIMARY KEY AUTOINCREMENT';
    $timestampType = $isPgsql ? 'TIMESTAMP' : 'DATETIME';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS LegacyCertificate (
            {$idColumn},
            certificateNumber TEXT UNIQUE,
            certificateToken TEXT UNIQUE,
            fullName TEXT NOT NULL,
            course TEXT,
            completionDate TEXT,
            level TEXT,
            extra TEXT,
            createdAt {$timestampType} DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

/**
 * Ищет сертификат по токену из QR-кода: сначала среди участников
 * текущей системы, затем среди импортированных исторических
 * сертификатов. Возвращает единый набор публичных полей
 * (без email/телефона/организации) либо null.
 */
function findCertificateByToken(PDO $pdo, string $token): ?array
{
    ensureCertificateColumns($pdo);
    ensureLegacyCertificateTable($pdo);

    $stmt = $pdo->prepare("
        SELECT fullName, certificateNumber, practicalGradedAt,
               percent, practicalScoreTotal
        FROM Participant
        WHERE certificateToken = :token
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);

    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($participant && !empty($participant['certificateNumber'])) {
        $score = certificateOverallScore($participant);
        $level = certificateLevel($score['percent']);

        return [
            'source' => 'current',
            'certificateNumber' => $participant['certificateNumber'],
            'fullName' => $participant['fullName'],
            'course' => CERTIFICATE_COURSE_NAME,
            'completionDate' => $participant['practicalGradedAt']
                ? date('d.m.Y', strtotime($participant['practicalGradedAt']))
                : null,
            'level' => $level['text'],
            'totalScore' => $score['total'],
            'percent' => $score['percent'],
        ];
    }

    $stmt = $pdo->prepare("
        SELECT fullName, certificateNumber, course, completionDate, level
        FROM LegacyCertificate
        WHERE certificateToken = :token
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);

    $legacy = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($legacy) {
        return [
            'source' => 'legacy',
            'certificateNumber' => $legacy['certificateNumber'],
            'fullName' => $legacy['fullName'],
            'course' => $legacy['course'] ?: CERTIFICATE_COURSE_NAME,
            'completionDate' => $legacy['completionDate'],
            'level' => $legacy['level'],
            'totalScore' => null,
            'percent' => null,
        ];
    }

    return null;
}
