<?php
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../lib/csrf.php';
$activeNav = 'admin';
$kz = i18nLocale() === 'kk';
$ui = $kz ? [
    'title' => 'Бұрын берілген сертификаттарды тіркеу', 'intro' => 'Офлайн-генератордағы JSON файлын таңдаңыз. Деректерді тексеріп, тіркеуді растаңыз.',
    'back' => 'Әкімші панеліне', 'file' => 'Сертификаттарды тіркеу файлы (JSON, 1 МБ дейін)', 'preview' => 'Тексеру және көрсету',
    'register' => 'Сертификаттарды тіркеу', 'name' => 'Алушы', 'number' => 'Сертификат нөмірі', 'course' => 'Курс',
    'date' => 'Оқуды аяқтау күні', 'level' => 'Санат', 'action' => 'Нәтиже', 'new' => 'Қосылады', 'existing' => 'Тіркелген',
    'conflict' => 'Қайшылық', 'checking' => 'Файл тексерілуде…', 'saving' => 'Тіркелуде…',
    'error' => 'Әрекет орындалмады. Бетті жаңартып, қайта көріңіз.', 'choose' => 'JSON файлын таңдаңыз.',
    'note' => 'Тексеру деректерді өзгертпейді. Тіркелгеннен кейін PDF ішіндегі QR-код сертификатты растайды. Бұрынғы жазбалар қайта жазылмайды.',
    'done' => 'Тіркеу аяқталды. Қосылды: ', 'summary' => 'Қосылады: ', 'already' => '; тіркелген: ', 'conflicts' => '; қайшылықтар: ',
] : [
    'title' => 'Регистрация ранее выданных сертификатов', 'intro' => 'Выберите JSON-файл из офлайн-генератора. Проверьте список и подтвердите регистрацию.',
    'back' => 'Вернуться в админку', 'file' => 'Файл регистрации сертификатов (JSON, до 1 МБ)', 'preview' => 'Проверить и показать',
    'register' => 'Зарегистрировать сертификаты', 'name' => 'Получатель', 'number' => 'Номер сертификата', 'course' => 'Курс',
    'date' => 'Дата завершения', 'level' => 'Категория', 'action' => 'Результат', 'new' => 'Будет добавлен', 'existing' => 'Уже зарегистрирован',
    'conflict' => 'Конфликт', 'checking' => 'Проверка файла…', 'saving' => 'Регистрация…',
    'error' => 'Не удалось выполнить действие. Обновите страницу и повторите попытку.', 'choose' => 'Выберите JSON-файл.',
    'note' => 'Проверка не изменяет данные. После регистрации QR-коды в PDF начнут подтверждать сертификаты. Существующие записи не перезаписываются.',
    'done' => 'Регистрация завершена. Добавлено: ', 'summary' => 'Будет добавлено: ', 'already' => '; уже зарегистрировано: ', 'conflicts' => '; конфликтов: ',
];
function certificateImportEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="<?= $kz ? 'kk' : 'ru' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= certificateImportEscape($ui['title']) ?></title><link rel="stylesheet" href="style.css">
<style>
.certificate-import{padding:36px 20px;max-width:1200px;margin:auto}.certificate-import-panel{background:#fff;border:1px solid #dce3ef;border-radius:16px;padding:24px;margin-top:20px}.certificate-import h1{font-size:28px;line-height:1.3}.certificate-import label{display:block;font-weight:600;margin-bottom:10px}.certificate-import input{display:block;max-width:100%;margin-bottom:18px}.certificate-import p{color:#52637a}.certificate-import-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}.certificate-import-status{margin-top:18px;white-space:pre-line}.certificate-import-table{overflow-x:auto;margin-top:20px}.certificate-import table{border-collapse:collapse;width:100%}.certificate-import th,.certificate-import td{padding:12px;text-align:left;border-bottom:1px solid #dce3ef;font-size:14px;vertical-align:top}.certificate-import [data-action=conflict]{background:#fff0f0}.certificate-import button:disabled{opacity:.5;cursor:default}[hidden]{display:none!important}
</style></head><body>
<?php require __DIR__ . '/_header.php'; ?>
<main class="certificate-import">
<a class="btn btn-secondary" href="admin.php"><?= certificateImportEscape($ui['back']) ?></a>
<h1><?= certificateImportEscape($ui['title']) ?></h1><p><?= certificateImportEscape($ui['intro']) ?></p>
<section class="certificate-import-panel">
<label for="certificateFile"><?= certificateImportEscape($ui['file']) ?></label><input id="certificateFile" type="file" accept=".json,application/json">
<p><?= certificateImportEscape($ui['note']) ?></p>
<div class="certificate-import-actions"><button class="btn btn-primary" type="button" id="previewImport"><?= certificateImportEscape($ui['preview']) ?></button>
<button class="btn btn-primary" type="button" id="registerImport" disabled><?= certificateImportEscape($ui['register']) ?></button></div>
<div id="importStatus" role="status" aria-live="polite" class="certificate-import-status"></div>
<div class="certificate-import-table" id="importTableWrap" hidden><table><thead><tr>
<?php foreach (['name', 'number', 'course', 'date', 'level', 'action'] as $key): ?><th scope="col"><?= certificateImportEscape($ui[$key]) ?></th><?php endforeach; ?>
</tr></thead><tbody id="importRows"></tbody></table></div>
</section></main>
<script>
'use strict';
const ui=<?= json_encode($ui, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const csrf=<?= json_encode(csrfToken()) ?>;
const file=document.getElementById('certificateFile'),preview=document.getElementById('previewImport'),register=document.getElementById('registerImport'),status=document.getElementById('importStatus'),body=document.getElementById('importRows'),wrap=document.getElementById('importTableWrap');
let previewId=null,busy=false;
function controls(value){busy=value;file.disabled=value;preview.disabled=value;register.disabled=value||!previewId;}
function reset(){previewId=null;register.disabled=true;wrap.hidden=true;body.replaceChildren();status.textContent='';}
file.addEventListener('change',reset);
async function request(data){const response=await fetch('../api/admin/import_certificates.php',{method:'POST',body:data});const type=response.headers.get('content-type')||'';if(!type.includes('application/json'))throw new Error(ui.error);const json=await response.json();if(!response.ok||!json.success)throw new Error(json.message||ui.error);return json;}
preview.addEventListener('click',async()=>{if(busy)return;reset();if(!file.files.length){status.textContent=ui.choose;return;}controls(true);status.textContent=ui.checking;try{const data=new FormData();data.append('csrf_token',csrf);data.append('phase','preview');data.append('file',file.files[0]);const json=await request(data),plan=json.plan;body.replaceChildren();for(const row of plan.rows){const tr=document.createElement('tr');tr.dataset.action=row.action;for(const value of [row.fullName,row.certificateNumber,row.course,row.completionDate,row.level,ui[row.action]]){const td=document.createElement('td');td.textContent=value;tr.append(td);}body.append(tr);}wrap.hidden=false;status.textContent=ui.summary+plan.counts.new+ui.already+plan.counts.existing+ui.conflicts+plan.counts.conflict;previewId=plan.counts.conflict===0&&plan.counts.new>0?json.previewId:null;}catch(error){status.textContent=error.message;}finally{controls(false);}});
register.addEventListener('click',async()=>{if(busy||!previewId)return;controls(true);status.textContent=ui.saving;try{const data=new FormData();data.append('csrf_token',csrf);data.append('phase','register');data.append('previewId',previewId);const json=await request(data);previewId=null;status.textContent=ui.done+json.result.inserted+ui.already+json.result.existing;wrap.hidden=true;}catch(error){previewId=null;status.textContent=error.message;}finally{controls(false);}});
</script></body></html>
