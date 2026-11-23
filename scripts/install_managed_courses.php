<?php
// Run once from CLI during deployment; public requests never create tables.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/managed_courses.php';
$db = getDb();
$db->beginTransaction();
try {
    managedCoursesInstall($db);
    $db->commit();
    echo "Managed course tables ready; existing records untouched.\n";
} catch (Throwable $e) { $db->rollBack(); throw $e; }
