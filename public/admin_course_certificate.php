<?php
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../lib/managed_course_pdf.php';
$temporary = null;
try {
    $db = getDb();
    $courseId = mcId($_GET['course'] ?? '');
    $course = mcCourse($db, $courseId);
    $export = $_GET['export'] ?? '';
    if ($export === 'csv' || $export === 'zip') {
        $q = $db->prepare('SELECT * FROM managed_course_person WHERE course_id = ? ORDER BY created_at, id');
        $q->execute([$courseId]);
        $people = $q->fetchAll(PDO::FETCH_ASSOC);
        if ($export === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="course-participants.csv"');
            $out = fopen('php://output', 'wb'); fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ФИО', 'Email', 'Телефон', 'Организация', 'Должность', 'Статус', 'Номер сертификата'], ';');
            foreach ($people as $person) {
                $fields = array_map(fn($k) => (string)($person[$k] ?? ''), ['full_name', 'email', 'phone', 'organization', 'position', 'status', 'certificate_number']);
                // Do not allow spreadsheet software to execute a submitted name/contact as a formula.
                $fields = array_map(fn($v) => preg_match('/^[\s]*[=+@-]/u', $v) ? "'" . $v : $v, $fields);
                fputcsv($out, $fields, ';');
            }
            fclose($out); exit;
        }
        $issued = array_values(array_filter($people, fn($p) => $p['certificate_token'] !== null));
        if (!$issued) throw new InvalidArgumentException('В этом курсе пока нет выданных сертификатов.');
        if (count($issued) > 200) throw new InvalidArgumentException('Для большого курса скачивайте сертификаты по одному. ZIP поддерживает до 200 сертификатов.');
        set_time_limit(180);
        $temporary = tempnam(sys_get_temp_dir(), 'course-pdf-');
        if (!$temporary) throw new RuntimeException('Temporary file unavailable');
        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ZIP unavailable');
        foreach ($issued as $index => $person) {
            $zip->addFromString(sprintf('certificate-%03d.pdf', $index + 1), mcCertificatePdf(mcCertificateRow($db, $courseId, $person['id'])));
        }
        if (!$zip->close()) throw new RuntimeException('ZIP failed');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="course-certificates.zip"');
        header('Content-Length: ' . filesize($temporary));
        readfile($temporary);
    } else {
        $row = mcCertificateRow($db, $courseId, mcId($_GET['person'] ?? ''));
        $pdf = mcCertificatePdf($row);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="certificate.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
} catch (Throwable $e) {
    error_log('Managed course download failed: ' . get_class($e));
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Не удалось подготовить файл. Повторите позже.';
} finally { if ($temporary && is_file($temporary)) unlink($temporary); }
