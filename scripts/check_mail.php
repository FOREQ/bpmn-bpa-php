<?php

// CLI only. Sending is opt-in and never changes participants or certificates.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/mailer.php';

$options = getopt('', ['send-to:']);
try {
    $config = require __DIR__ . '/../config/mail.php';
    if (!isset($options['send-to'])) {
        if ($config['transport'] !== 'ews') {
            throw new RuntimeException('Read-only mailbox check requires EWS.');
        }
        require_once __DIR__ . '/../lib/ews_client.php';
        $client = new EwsClient($config['ews_url'], $config['username'], $config['password'], $config['from_email']);
        $client->checkMailboxAccess();
        echo "EWS_ACCESS_OK: no messages read or sent.\n";
        exit(0);
    }

    $recipient = $options['send-to'];
    if (!is_string($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Supply one valid --send-to address.');
    }
    $mail = createMailer();
    $mail->addAddress($recipient);
    $mail->Subject = 'BPMN: проверка отправки / хат жіберуді тексеру';
    $mail->isHTML(true);
    $mail->Body = '<p>Тестовое письмо с сайта BPMN. Во вложении тестовый PDF, это не сертификат.</p>' .
        '<p>BPMN сайтынан сынақ хаты. Тіркемедегі PDF — сынақ файлы, сертификат емес.</p>';
    $mail->AltBody = 'Тестовое письмо с сайта BPMN. PDF — тестовый файл, не сертификат.' . "\n" .
        'BPMN сайтынан сынақ хаты. PDF — сынақ файлы, сертификат емес.';
    $pdf = new TCPDF();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 16);
    $pdf->Write(10, 'BPMN MAIL TEST - NOT A CERTIFICATE');
    $mail->addStringAttachment($pdf->Output('', 'S'), 'bpmn-mail-test.pdf', 'base64', 'application/pdf');
    if (!$mail->send()) {
        throw new RuntimeException('Mail transport did not accept the test message.');
    }
    echo "MAIL_ACCEPTED: transport accepted one test message with a PDF attachment.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'MAIL_CHECK_FAILED: ' . $error->getMessage() . "\n");
    exit(1);
}
