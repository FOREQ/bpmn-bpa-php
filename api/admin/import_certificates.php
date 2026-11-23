<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/legacy_certificate_import.php';
require_once __DIR__ . '/../../lib/error_logger.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function offlineImportResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (!isAdminLoggedIn()) offlineImportResponse(['success' => false, 'message' => 'Войдите как администратор.'], 401);
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') offlineImportResponse(['success' => false, 'message' => 'Метод не поддерживается.'], 405);
if (!csrfVerify(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) offlineImportResponse(['success' => false, 'message' => 'Обновите страницу и повторите действие.'], 403);

try {
    $phase = $_POST['phase'] ?? '';
    if ($phase === 'preview') {
        unset($_SESSION['offline_certificate_import']);
        $upload = $_FILES['file'] ?? null;
        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || ($upload['size'] ?? 0) > 1024 * 1024 || !is_uploaded_file($upload['tmp_name'] ?? '')) {
            throw new InvalidArgumentException('Выберите JSON-файл регистрации размером до 1 МБ.');
        }
        $json = file_get_contents($upload['tmp_name']);
        if ($json === false) throw new InvalidArgumentException('Не удалось прочитать файл.');
        $records = offlineCertificateRecords($json);
        $plan = offlineCertificateImportPlan(getDb(), $records);
        $previewId = bin2hex(random_bytes(24));
        $_SESSION['offline_certificate_import'] = ['id' => $previewId, 'expires' => time() + 900, 'records' => $records];
        offlineImportResponse(['success' => true, 'previewId' => $previewId, 'plan' => $plan]);
    }
    if ($phase === 'register') {
        $preview = $_SESSION['offline_certificate_import'] ?? null;
        $previewId = $_POST['previewId'] ?? '';
        if (!is_array($preview) || !is_string($previewId) || !hash_equals($preview['id'], $previewId)
            || $preview['expires'] < time()) {
            throw new InvalidArgumentException('Предпросмотр устарел. Выберите файл и проверьте его заново.');
        }
        $result = offlineCertificateRegister(getDb(), $preview['records']);
        unset($_SESSION['offline_certificate_import']);
        try { writeAdminLog('offline_certificates_registered', 'inserted=' . $result['inserted'] . '; existing=' . $result['existing']); }
        catch (Throwable $auditError) { logError($auditError, 'offline certificate import audit'); }
        offlineImportResponse(['success' => true, 'result' => $result]);
    }
    throw new InvalidArgumentException('Неизвестное действие.');
} catch (InvalidArgumentException $e) {
    offlineImportResponse(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    logError($e, 'api/admin/import_certificates.php');
    offlineImportResponse(['success' => false, 'message' => 'Не удалось зарегистрировать сертификаты. Данные не изменены; проверьте файл заново.'], 500);
}
