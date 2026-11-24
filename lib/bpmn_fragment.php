<?php

/**
 * Маленькие фрагменты BPMN для тестовых вопросов (1–10 элементов).
 *
 * ИИ описывает фрагмент смыслом (элементы и потоки), а раскладку и XML строит этот код —
 * модель никогда не выдаёт координаты. В отличие от cct-mcp здесь разрешены и неправильные
 * фрагменты (поток из события завершения и т.п.): они нужны для вопросов «найдите ошибку».
 *
 * Формат:
 *   {"nodes":[{"id":"n1","type":"startEvent","event":"message","name":"","highlight":false}],
 *    "flows":[{"from":"n1","to":"n2","name":"","kind":"default|conditional","highlight":false}]}
 */

final class BpmnFragmentException extends InvalidArgumentException
{
}

const BPMN_FRAGMENT_TYPES = [
    'startEvent', 'endEvent', 'intermediateCatchEvent', 'intermediateThrowEvent',
    'task', 'userTask', 'serviceTask', 'sendTask', 'receiveTask', 'manualTask', 'scriptTask', 'businessRuleTask',
    'subProcess', 'callActivity',
    'exclusiveGateway', 'parallelGateway', 'inclusiveGateway', 'eventBasedGateway', 'complexGateway',
];

const BPMN_FRAGMENT_EVENTS = [
    'message' => 'messageEventDefinition',
    'timer' => 'timerEventDefinition',
    'signal' => 'signalEventDefinition',
    'terminate' => 'terminateEventDefinition',
    'error' => 'errorEventDefinition',
    'escalation' => 'escalationEventDefinition',
    'conditional' => 'conditionalEventDefinition',
    'compensation' => 'compensateEventDefinition',
    'link' => 'linkEventDefinition',
];

/** Проверяет и нормализует фрагмент от модели. */
function bpmnFragmentNormalize(mixed $fragment): array
{
    if (!is_array($fragment)) {
        throw new BpmnFragmentException('Фрагмент схемы должен быть объектом');
    }
    $nodes = [];
    foreach ((array)($fragment['nodes'] ?? []) as $i => $node) {
        if (!is_array($node)) {
            continue;
        }
        $id = preg_replace('/[^A-Za-z0-9_]/', '', (string)($node['id'] ?? ''));
        $id = $id !== '' && !ctype_digit($id[0]) ? $id : 'n' . ($i + 1);
        $type = (string)($node['type'] ?? '');
        // Частые синонимы от модели.
        $type = ['intermediateEvent' => 'intermediateCatchEvent', 'gateway' => 'exclusiveGateway',
            'xor' => 'exclusiveGateway', 'and' => 'parallelGateway', 'or' => 'inclusiveGateway',
            'eventGateway' => 'eventBasedGateway', 'start' => 'startEvent', 'end' => 'endEvent'][$type] ?? $type;
        if (!in_array($type, BPMN_FRAGMENT_TYPES, true)) {
            throw new BpmnFragmentException('Неизвестный тип элемента: ' . mb_substr($type, 0, 40));
        }
        if (isset($nodes[$id])) {
            throw new BpmnFragmentException('Повторяется id элемента: ' . $id);
        }
        $event = strtolower((string)($node['event'] ?? 'none'));
        $nodes[$id] = [
            'id' => $id,
            'type' => $type,
            'event' => str_ends_with($type, 'Event') && isset(BPMN_FRAGMENT_EVENTS[$event]) ? $event : 'none',
            'name' => mb_substr(trim((string)($node['name'] ?? '')), 0, 60),
            'highlight' => !empty($node['highlight']),
        ];
    }
    if (!$nodes || count($nodes) > 12) {
        throw new BpmnFragmentException('Во фрагменте должно быть от 1 до 12 элементов');
    }

    $flows = [];
    foreach ((array)($fragment['flows'] ?? []) as $flow) {
        if (is_array($flow) && array_is_list($flow)) {
            $flow = ['from' => $flow[0] ?? '', 'to' => $flow[1] ?? '', 'name' => $flow[2] ?? ''];
        }
        if (!is_array($flow)) {
            continue;
        }
        $from = (string)($flow['from'] ?? $flow['source'] ?? '');
        $to = (string)($flow['to'] ?? $flow['target'] ?? '');
        if (!isset($nodes[$from], $nodes[$to]) || $from === $to) {
            throw new BpmnFragmentException('Поток ссылается на несуществующий элемент: ' . $from . ' → ' . $to);
        }
        $kind = (string)($flow['kind'] ?? '');
        $flows[] = [
            'from' => $from,
            'to' => $to,
            'name' => mb_substr(trim((string)($flow['name'] ?? '')), 0, 40),
            'kind' => in_array($kind, ['default', 'conditional'], true) ? $kind : '',
            'highlight' => !empty($flow['highlight']),
        ];
    }
    if (count($flows) > 20) {
        throw new BpmnFragmentException('Слишком много потоков во фрагменте');
    }

    return ['nodes' => array_values($nodes), 'flows' => $flows];
}

/** @return array{0:int,1:int} ширина и высота фигуры */
function bpmnFragmentSize(string $type): array
{
    if (str_ends_with($type, 'Event')) {
        return [36, 36];
    }
    if (str_ends_with($type, 'Gateway')) {
        return [50, 50];
    }

    return [100, 80];
}

/**
 * Детерминированная раскладка: колонка = самый длинный путь от входов (обратные потоки
 * не учитываются), ряд — ряд первого предшественника, если он свободен в этой колонке.
 */
function bpmnFragmentLayout(array $fragment): array
{
    $ids = array_column($fragment['nodes'], 'id');
    $out = array_fill_keys($ids, []);
    $in = array_fill_keys($ids, []);
    foreach ($fragment['flows'] as $f) {
        $out[$f['from']][] = $f['to'];
        $in[$f['to']][] = $f['from'];
    }

    // Обратные потоки (циклы) — по обходу в глубину в порядке объявления.
    $state = [];
    $back = [];
    $visit = function (string $id) use (&$visit, &$state, &$back, $out): void {
        $state[$id] = 1;
        foreach ($out[$id] as $next) {
            if (($state[$next] ?? 0) === 1) {
                $back[$id . '>' . $next] = true;
            } elseif (!isset($state[$next])) {
                $visit($next);
            }
        }
        $state[$id] = 2;
    };
    foreach ($ids as $id) {
        if (!$in[$id] && !isset($state[$id])) {
            $visit($id);
        }
    }
    foreach ($ids as $id) {
        if (!isset($state[$id])) {
            $visit($id);
        }
    }

    // Колонки: самый длинный путь без обратных потоков (граф ацикличен, хватит |V| проходов).
    $col = array_fill_keys($ids, 0);
    for ($pass = 0; $pass < count($ids); $pass++) {
        $changed = false;
        foreach ($fragment['flows'] as $f) {
            if (!isset($back[$f['from'] . '>' . $f['to']]) && $col[$f['to']] < $col[$f['from']] + 1) {
                $col[$f['to']] = $col[$f['from']] + 1;
                $changed = true;
            }
        }
        if (!$changed) {
            break;
        }
    }

    // Ряды.
    $row = [];
    $used = [];
    $order = $ids;
    usort($order, static fn($a, $b) => $col[$a] <=> $col[$b] ?: array_search($a, $ids, true) <=> array_search($b, $ids, true));
    foreach ($order as $id) {
        $wanted = 0;
        foreach ($in[$id] as $pred) {
            if (isset($row[$pred]) && !isset($back[$pred . '>' . $id])) {
                $wanted = $row[$pred];
                break;
            }
        }
        while (isset($used[$col[$id]][$wanted])) {
            $wanted++;
        }
        $row[$id] = $wanted;
        $used[$col[$id]][$wanted] = true;
    }

    $pos = [];
    foreach ($ids as $id) {
        $type = $fragment['nodes'][array_search($id, $ids, true)]['type'];
        [$w, $h] = bpmnFragmentSize($type);
        $cx = 80 + $col[$id] * 170;
        $cy = 80 + $row[$id] * 120;
        $pos[$id] = ['x' => $cx - $w / 2, 'y' => $cy - $h / 2, 'w' => $w, 'h' => $h, 'cx' => $cx, 'cy' => $cy, 'type' => $type];
    }

    $bottom = max(array_map(static fn($p) => $p['y'] + $p['h'], $pos)) + 45;
    $routes = [];
    $loop = 0;
    foreach ($fragment['flows'] as $i => $f) {
        $s = $pos[$f['from']];
        $t = $pos[$f['to']];
        if (isset($back[$f['from'] . '>' . $f['to']]) || $t['cx'] <= $s['cx']) {
            // Возврат назад — по отдельному каналу под схемой.
            $y = $bottom + 25 * $loop++;
            $routes[$i] = [[$s['cx'], $s['y'] + $s['h']], [$s['cx'], $y], [$t['cx'], $y], [$t['cx'], $t['y'] + $t['h']]];
        } elseif ($s['cy'] === $t['cy']) {
            $routes[$i] = [[$s['x'] + $s['w'], $s['cy']], [$t['x'], $t['cy']]];
        } elseif (str_ends_with($s['type'], 'Gateway') || count($out[$f['from']]) > 1) {
            // Ветка из шлюза (или из задачи с несколькими выходами) уходит вверх или вниз:
            // на одной грани — одна стрелка.
            $edgeY = $t['cy'] > $s['cy'] ? $s['y'] + $s['h'] : $s['y'];
            $routes[$i] = [[$s['cx'], $edgeY], [$s['cx'], $t['cy']], [$t['x'], $t['cy']]];
        } elseif (str_ends_with($t['type'], 'Gateway')) {
            // Слияние: заходим в шлюз сверху или снизу.
            $edgeY = $s['cy'] > $t['cy'] ? $t['y'] + $t['h'] : $t['y'];
            $routes[$i] = [[$s['x'] + $s['w'], $s['cy']], [$t['cx'], $s['cy']], [$t['cx'], $edgeY]];
        } else {
            $mx = (int)round(($s['x'] + $s['w'] + $t['x']) / 2);
            $routes[$i] = [[$s['x'] + $s['w'], $s['cy']], [$mx, $s['cy']], [$mx, $t['cy']], [$t['x'], $t['cy']]];
        }
    }

    return ['pos' => $pos, 'routes' => $routes];
}

function bpmnFragmentToXml(array $fragment, string $processName = 'Фрагмент'): array
{
    $fragment = bpmnFragmentNormalize($fragment);
    $layout = bpmnFragmentLayout($fragment);
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $flowIds = [];
    foreach ($fragment['flows'] as $i => $f) {
        $flowIds[$i] = 'Flow_' . ($i + 1);
    }
    $defaults = [];
    foreach ($fragment['flows'] as $i => $f) {
        if ($f['kind'] === 'default' && !isset($defaults[$f['from']])) {
            $defaults[$f['from']] = $flowIds[$i];
        }
    }

    $process = '';
    foreach ($fragment['nodes'] as $n) {
        $attrs = 'id="' . $e($n['id']) . '"' . ($n['name'] !== '' ? ' name="' . $e($n['name']) . '"' : '');
        if (isset($defaults[$n['id']]) && !str_ends_with($n['type'], 'Event') && $n['type'] !== 'parallelGateway' && $n['type'] !== 'eventBasedGateway') {
            $attrs .= ' default="' . $defaults[$n['id']] . '"';
        }
        $body = '';
        foreach ($fragment['flows'] as $i => $f) {
            if ($f['to'] === $n['id']) {
                $body .= '<bpmn:incoming>' . $flowIds[$i] . '</bpmn:incoming>';
            }
        }
        foreach ($fragment['flows'] as $i => $f) {
            if ($f['from'] === $n['id']) {
                $body .= '<bpmn:outgoing>' . $flowIds[$i] . '</bpmn:outgoing>';
            }
        }
        if ($n['event'] !== 'none') {
            $body .= '<bpmn:' . BPMN_FRAGMENT_EVENTS[$n['event']] . ' id="' . $e($n['id']) . '_def" />';
        }
        $process .= '<bpmn:' . $n['type'] . ' ' . $attrs . '>' . $body . '</bpmn:' . $n['type'] . '>' . "\n";
    }
    foreach ($fragment['flows'] as $i => $f) {
        $attrs = 'id="' . $flowIds[$i] . '" sourceRef="' . $e($f['from']) . '" targetRef="' . $e($f['to']) . '"'
            . ($f['name'] !== '' ? ' name="' . $e($f['name']) . '"' : '');
        $body = $f['kind'] === 'conditional' ? '<bpmn:conditionExpression xsi:type="bpmn:tFormalExpression">${condition}</bpmn:conditionExpression>' : '';
        $process .= '<bpmn:sequenceFlow ' . $attrs . ($body !== '' ? '>' . $body . '</bpmn:sequenceFlow>' : ' />') . "\n";
    }

    $hl = ' bioc:stroke="#d32f2f" bioc:fill="#ffebee" color:border-color="#d32f2f" color:background-color="#ffebee"';
    $di = '';
    foreach ($fragment['nodes'] as $n) {
        $p = $layout['pos'][$n['id']];
        $di .= '<bpmndi:BPMNShape id="' . $e($n['id']) . '_di" bpmnElement="' . $e($n['id']) . '"'
            . ($n['type'] === 'subProcess' ? ' isExpanded="false"' : '') . ($n['highlight'] ? $hl : '') . '>'
            . '<dc:Bounds x="' . $p['x'] . '" y="' . $p['y'] . '" width="' . $p['w'] . '" height="' . $p['h'] . '" />';
        if ($n['name'] !== '' && str_ends_with($n['type'], 'Event')) {
            $di .= '<bpmndi:BPMNLabel><dc:Bounds x="' . ($p['cx'] - 50) . '" y="' . ($p['y'] + $p['h'] + 6) . '" width="100" height="27" /></bpmndi:BPMNLabel>';
        } elseif ($n['name'] !== '' && str_ends_with($n['type'], 'Gateway')) {
            // Подпись шлюза — над ним: снизу уходят ветки.
            $di .= '<bpmndi:BPMNLabel><dc:Bounds x="' . ($p['cx'] - 50) . '" y="' . ($p['y'] - 33) . '" width="100" height="27" /></bpmndi:BPMNLabel>';
        }
        $di .= "</bpmndi:BPMNShape>\n";
    }
    foreach ($fragment['flows'] as $i => $f) {
        $di .= '<bpmndi:BPMNEdge id="' . $flowIds[$i] . '_di" bpmnElement="' . $flowIds[$i] . '"'
            . ($f['highlight'] ? ' bioc:stroke="#d32f2f" color:border-color="#d32f2f"' : '') . '>';
        foreach ($layout['routes'][$i] as [$x, $y]) {
            $di .= '<di:waypoint x="' . $x . '" y="' . $y . '" />';
        }
        if ($f['name'] !== '') {
            // Подпись — у самого длинного отрезка: над горизонтальным, справа от вертикального.
            $points = $layout['routes'][$i];
            $best = 0;
            $bestLength = -1;
            for ($k = 0; $k < count($points) - 1; $k++) {
                $length = abs($points[$k + 1][0] - $points[$k][0]) + abs($points[$k + 1][1] - $points[$k][1]);
                if ($length > $bestLength) {
                    $best = $k;
                    $bestLength = $length;
                }
            }
            [$x1, $y1] = $points[$best];
            [$x2, $y2] = $points[$best + 1];
            [$lx, $ly] = $y1 === $y2
                ? [(int)(($x1 + $x2) / 2 - 40), (int)($y1 - 20)]
                : [(int)($x1 + 6), (int)(($y1 + $y2) / 2 - 7)];
            $di .= '<bpmndi:BPMNLabel><dc:Bounds x="' . $lx . '" y="' . $ly . '" width="80" height="14" /></bpmndi:BPMNLabel>';
        }
        $di .= "</bpmndi:BPMNEdge>\n";
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<bpmn:definitions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"'
        . ' xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI" xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"'
        . ' xmlns:di="http://www.omg.org/spec/DD/20100524/DI" xmlns:bioc="http://bpmn.io/schema/bpmn/biocolor/1.0"'
        . ' xmlns:color="http://www.omg.org/spec/BPMN/non-normative/color/1.0"'
        . ' id="Definitions_fragment" targetNamespace="http://bpmn.io/schema/bpmn">' . "\n"
        . '<bpmn:process id="Process_fragment" name="' . $e($processName) . '" isExecutable="false">' . "\n" . $process . "</bpmn:process>\n"
        . '<bpmndi:BPMNDiagram id="Diagram_fragment"><bpmndi:BPMNPlane id="Plane_fragment" bpmnElement="Process_fragment">' . "\n"
        . $di . "</bpmndi:BPMNPlane></bpmndi:BPMNDiagram>\n</bpmn:definitions>\n";

    return ['xml' => $xml, 'fragment' => $fragment];
}

/**
 * Проверяет SVG, выгруженный из bpmn-js в браузере администратора, перед сохранением.
 * SVG показывается участникам через <img>, где скрипты не выполняются, но всё равно чистим.
 */
function bpmnSanitizeSvg(string $svg): string
{
    $svg = trim($svg);
    $svg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg) ?? '';
    $svg = preg_replace('/<!--.*?-->\s*/s', '', $svg) ?? '';
    $svg = trim(preg_replace('/<!DOCTYPE[^>]*>\s*/i', '', $svg) ?? '');
    if (!str_starts_with($svg, '<svg') || strlen($svg) > 3_000_000) {
        throw new InvalidArgumentException('Схема не выгрузилась в SVG');
    }
    $svg = preg_replace('#<(script|foreignObject|iframe|object|embed)\b.*?</\1\s*>#is', '', $svg) ?? '';
    $svg = preg_replace('#<(script|foreignObject|iframe|object|embed)\b[^>]*/>#is', '', $svg) ?? '';
    $svg = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $svg) ?? '';
    $svg = preg_replace('/\s(xlink:)?href\s*=\s*("(?!#)[^"]*"|\'(?!#)[^\']*\')/i', '', $svg) ?? '';

    $previous = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok = $doc->loadXML($svg, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$ok || $doc->documentElement?->localName !== 'svg') {
        throw new InvalidArgumentException('SVG схемы повреждён');
    }

    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $svg;
}
