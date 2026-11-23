<?php
// Мок OpenAI-совместимого API для тестов ai_client.php. Режим задаётся в пути: /<mode>/chat/completions
$mode = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'))[0] ?? '';
$req = json_decode((string)file_get_contents('php://input'), true) ?: [];
$counter = sys_get_temp_dir() . '/ai_mock_' . $mode . '.cnt';
$n = (int)@file_get_contents($counter) + 1;
file_put_contents($counter, (string)$n);
header('Content-Type: application/json');
$reply = static fn(string $content, string $finish = 'stop') => print json_encode(['choices' => [['message' => ['content' => $content], 'finish_reason' => $finish]]]);
switch ($mode) {
    case 'echo':
        $reply(json_encode(['kwargs' => $req['chat_template_kwargs'] ?? null, 'max_tokens' => $req['max_tokens']]));
        break;
    case 'length':
        $reply('<think>долго думаю', 'length');
        break;
    case 'busy':
        if ($n < 3) { http_response_code(429); echo '{"error":{"message":"too many"}}'; break; }
        $reply('{"ok":true}');
        break;
    case 'down':
        http_response_code(502); echo 'bad gateway';
        break;
    case 'slow':
        sleep(2);
        $reply('{"ok":true}');
        break;
}
