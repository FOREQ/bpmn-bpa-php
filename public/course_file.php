<?php
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/managed_courses.php';
try {
    $id = mcId($_GET['id'] ?? '');
    $q = getDb()->prepare('SELECT f.*, c.presentation_id FROM managed_course_file f JOIN managed_course c ON c.id = f.course_id WHERE f.id = ?');
    $q->execute([$id]);
    $file = $q->fetch(PDO::FETCH_ASSOC);
    if (!$file) { http_response_code(404); exit; }
    if ($file['kind'] === 'resume') requireAdmin();
    elseif ($file['kind'] !== 'presentation' || $file['presentation_id'] !== $id) { requireAdmin(); }
    $bytes = base64_decode($file['payload'], true);
    if ($bytes === false) throw new RuntimeException('Invalid PDF');
    // Downloads do not render an uploaded PDF in the portal's authenticated origin.
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="document.pdf"');
    header('Content-Security-Policy: sandbox');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
} catch (InvalidArgumentException $e) { http_response_code(404); }
catch (Throwable $e) { http_response_code(500); }
