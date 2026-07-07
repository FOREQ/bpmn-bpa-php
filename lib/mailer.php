<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function createMailer(): PHPMailer
{
    $config = require __DIR__ . '/../config/mail.php';

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)$config['port'];
    $mail->Timeout = 15;

    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    $mail->setFrom($config['from_email'], $config['from_name']);

    return $mail;
}

function sendTemporaryPasswordEmail(
    string $toEmail,
    string $fullName,
    string $temporaryPassword,
    string $expiresAt
): bool {
    try {
        $mail = createMailer();

        $mail->addAddress($toEmail, $fullName);

        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($temporaryPassword, ENT_QUOTES, 'UTF-8');
        $safeExpiresAt = htmlspecialchars($expiresAt, ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Subject = 'Временный пароль для входа в систему тестирования BPMN';

        $mail->Body = "
            <p>Здравствуйте, {$safeName}!</p>

            <p>Ваша заявка на прохождение тестирования BPMN была подтверждена администратором.</p>

            <p>Ваш временный пароль для входа:</p>

            <h2 style='letter-spacing: 1px;'>{$safePassword}</h2>

            <p>
                Пароль действует 3 дня, до:
                <b>{$safeExpiresAt}</b>
            </p>

            <p>
                Для входа откройте страницу входа участника и введите ваш email и временный пароль.
            </p>

            <br>

            <p>С уважением,<br>BPMN Testing System</p>
        ";

        $mail->AltBody =
            "Здравствуйте, {$fullName}!\n\n" .
            "Ваша заявка на прохождение тестирования BPMN была подтверждена администратором.\n\n" .
            "Ваш временный пароль для входа: {$temporaryPassword}\n\n" .
            "Пароль действует 3 дня, до: {$expiresAt}\n\n" .
            "Для входа откройте страницу входа участника и введите ваш email и временный пароль.\n\n" .
            "BPMN Testing System";

        return $mail->send();

    } catch (Exception $e) {
        error_log('Temporary password email error: ' . $e->getMessage());
        return false;
    } catch (Throwable $e) {
        error_log('Temporary password email error: ' . $e->getMessage());
        return false;
    }
}

function sendRejectionEmail(
    string $toEmail,
    string $fullName,
    string $reason
): bool {
    try {
        $mail = createMailer();

        $mail->addAddress($toEmail, $fullName);

        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Subject = 'Заявка на тестирование BPMN отклонена';

        $mail->Body = "
            <p>Здравствуйте, {$safeName}!</p>

            <p>Ваша заявка на прохождение тестирования BPMN была отклонена администратором.</p>

            <p>
                <b>Причина отклонения:</b><br>
                {$safeReason}
            </p>

            <br>

            <p>С уважением,<br>BPMN Testing System</p>
        ";

        $mail->AltBody =
            "Здравствуйте, {$fullName}!\n\n" .
            "Ваша заявка на прохождение тестирования BPMN была отклонена администратором.\n\n" .
            "Причина отклонения: {$reason}\n\n" .
            "BPMN Testing System";

        return $mail->send();

    } catch (Exception $e) {
        error_log('Rejection email error: ' . $e->getMessage());
        return false;
    } catch (Throwable $e) {
        error_log('Rejection email error: ' . $e->getMessage());
        return false;
    }
}

function sendCertificateEmail(
    string $toEmail,
    string $fullName,
    string $certificateNumber,
    string $levelText,
    int $totalScore,
    int $percent,
    string $verifyUrl,
    string $pdfPath
): bool {
    try {
        $mail = createMailer();

        $mail->addAddress($toEmail, $fullName);
        $mail->addAttachment($pdfPath, 'certificate-' . $certificateNumber . '.pdf');

        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeNumber = htmlspecialchars($certificateNumber, ENT_QUOTES, 'UTF-8');
        $safeLevel = htmlspecialchars($levelText, ENT_QUOTES, 'UTF-8');
        $safeVerifyUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Subject = 'Сертификат о прохождении курса';

        $mail->Body = "
            <p>Здравствуйте, {$safeName}!</p>

            <p>
                Проверка вашей работы по курсу
                «Практическое применение методики реинжиниринга
                бизнес-процессов государственных органов» завершена.
            </p>

            <p>
                <b>Итоговый результат:</b> {$totalScore} из 50 ({$percent}%)<br>
                <b>Уровень:</b> {$safeLevel}<br>
                <b>Номер сертификата:</b> {$safeNumber}
            </p>

            <p>Ваш сертификат находится во вложении к этому письму.</p>

            <p>
                Проверить подлинность сертификата можно по ссылке:<br>
                <a href='{$safeVerifyUrl}'>{$safeVerifyUrl}</a>
            </p>

            <br>

            <p>С уважением,<br>BPMN Testing System</p>
        ";

        $mail->AltBody =
            "Здравствуйте, {$fullName}!\n\n" .
            "Проверка вашей работы по курсу «Практическое применение методики " .
            "реинжиниринга бизнес-процессов государственных органов» завершена.\n\n" .
            "Итоговый результат: {$totalScore} из 50 ({$percent}%)\n" .
            "Уровень: {$levelText}\n" .
            "Номер сертификата: {$certificateNumber}\n\n" .
            "Ваш сертификат находится во вложении к этому письму.\n\n" .
            "Проверить подлинность сертификата: {$verifyUrl}\n\n" .
            "BPMN Testing System";

        return $mail->send();

    } catch (Exception $e) {
        error_log('Certificate email error: ' . $e->getMessage());
        return false;
    } catch (Throwable $e) {
        error_log('Certificate email error: ' . $e->getMessage());
        return false;
    }
}