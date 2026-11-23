<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/managed_courses.php';

/** Render only the immutable issued snapshot, never mutable course settings. */
function mcCertificatePdf(array $row): string
{
    $width = 842.25;
    $height = 595.5;
    $pdf = new TCPDF('L', 'pt', [$height, $width], true, 'UTF-8', false);
    $pdf->SetCreator('BPMN Course Registry');
    $pdf->SetAuthor('Центр Поддержки Цифрового Правительства');
    $pdf->SetTitle('Сертификат ' . $row['certificatenumber']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0, true);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $fontDir = __DIR__ . '/../assets/certificate/fonts/';
    foreach (['montserrat', 'montserratmedium', 'montserratsemib', 'montserratb'] as $font) {
        $pdf->AddFont($font, '', $fontDir . $font . '.php');
    }
    $pdf->Image(__DIR__ . '/../assets/certificate/template.png', 0, 0, $width, $height, 'PNG', '', '', false, 300);
    $pdf->SetFont('montserratb', '', 48);
    $pdf->SetTextColor(2, 33, 84);
    $pdf->SetXY(0, 165);
    $pdf->Cell($width, 60, 'С Е Р Т И Ф И К А Т', 0, 0, 'C');
    $pdf->SetTextColor(15, 23, 42);
    $nameSize = 18;
    do { $pdf->SetFont('montserratsemib', '', $nameSize); if ($pdf->GetStringWidth($row['fullname']) <= 730) break; $nameSize--; } while ($nameSize >= 11);
    $pdf->SetXY(56, 257);
    $pdf->MultiCell(730, 16, $row['fullname'], 0, 'C', false, 0);
    $text = 'о том, что он(она) ' . $row['completiondate'] . ' прослушал(а) обучающий курс "' . $row['course'] . '"';
    $pdf->SetTextColor(51, 51, 51);
    $size = 13;
    do { $pdf->SetFont('montserrat', '', $size); if ($pdf->getStringHeight(620, $text) <= 54) break; $size--; } while ($size >= 10);
    $pdf->SetXY(($width - 620) / 2, 310);
    $pdf->MultiCell(620, 16, $text, 0, 'C', false, 0);
    $levelText = 'Уровень: ' . $row['level'];
    $size = 12;
    do { $pdf->SetFont('montserratsemib', '', $size); if ($pdf->getStringHeight(740, $levelText) <= 32) break; $size--; } while ($size >= 9);
    $pdf->SetXY(51, 377);
    $pdf->MultiCell(740, 15, $levelText, 0, 'C', false, 0);
    $extra = json_decode($row['extra'] ?? '', true) ?? [];
    if (isset($extra['testPoints'], $extra['practicalPoints'])) {
        $pdf->SetFont('montserratmedium', '', 11);
        $pdf->SetXY(0, 410);
        $pdf->Cell($width, 15, 'Теоретический тест: ' . (int)$extra['testPoints'] . ' из 20; практическое задание: ' . (int)$extra['practicalPoints'] . ' из 30', 0, 0, 'C');
    }
    $pdf->SetFont('montserrat', '', 11);
    $pdf->SetXY(75, 471);
    $pdf->Cell(300, 14, 'Дата завершения: ' . $row['completiondate'], 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->SetFont('montserratb', '', 12);
    $pdf->SetXY(75, 488);
    $pdf->Cell(300, 15, 'Центр Поддержки', 0, 0, 'L');
    $pdf->SetXY(75, 503);
    $pdf->Cell(300, 15, 'Цифрового Правительства', 0, 0, 'L');
    $city = 'г. ' . ($extra['city'] ?? 'Астана') . ' ' . substr($row['completiondate'], -4) . ' г.';
    $pdf->SetTextColor(51, 51, 51);
    $pdf->SetFont('montserratb', '', 10);
    $pdf->SetXY(0, 533);
    $pdf->Cell($width, 13, $city, 0, 0, 'C');
    $pdf->SetFont('montserratmedium', '', 9);
    $pdf->SetXY(558, 427);
    $pdf->Cell(128, 12, 'Номер сертификата:', 0, 0, 'L');
    $pdf->SetXY(558, 442);
    $pdf->MultiCell(128, 11, $row['certificatenumber'], 0, 'L', false, 0);
    // Include a white quiet zone to keep QR readable against the template.
    $style = ['border' => 0, 'padding' => 4, 'fgcolor' => [0, 0, 0], 'bgcolor' => [255, 255, 255]];
    $pdf->write2DBarcode(OFFLINE_CERTIFICATE_VERIFY_BASE . $row['certificatetoken'], 'QRCODE,H', 692.25, 435.5, 80, 80, $style, 'N');
    return $pdf->Output('', 'S');
}
