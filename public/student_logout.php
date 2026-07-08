<?php

require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/student_logger.php';

session_start();

$studentSessionId = $_SESSION['student_session_id'] ?? 'unknown';

writeStudentLog(
    'student_logout',
    'sessionId=' . $studentSessionId
);

unset($_SESSION['student_session_id']);

header('Location: student_login.php');
exit;