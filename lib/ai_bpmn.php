<?php

/**
 * Превращает BPMN XML в компактное текстовое описание для ИИ:
 * участники/дорожки, элементы с типами и подписями, потоки.
 * Так модель видит логику схемы, а не координаты и служебный XML.
 */

const AI_BPMN_NS = 'http://www.omg.org/spec/BPMN/20100524/MODEL';

/** @return array{text:string,stats:array<string,int>,valid:bool} */
function bpmnSummarize(string $xml): array
{
    $xml = trim($xml);
    if ($xml === '') {
        return ['text' => '(схема пустая)', 'stats' => [], 'valid' => false];
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return ['text' => '(не удалось разобрать BPMN XML)', 'stats' => [], 'valid' => false];
    }

    $xp = new DOMXPath($dom);
    $xp->registerNamespace('b', AI_BPMN_NS);

    $names = [];
    $types = [];
    $nodeLane = [];
    $lines = [];
    $stats = [];

    foreach ($xp->query('//b:participant') as $p) {
        $lines[] = 'Пул: «' . bpmnName($p) . '»';
        $stats['pools'] = ($stats['pools'] ?? 0) + 1;
    }

    foreach ($xp->query('//b:lane') as $lane) {
        $laneName = bpmnName($lane);
        $stats['lanes'] = ($stats['lanes'] ?? 0) + 1;
        foreach ($xp->query('b:flowNodeRef', $lane) as $ref) {
            $nodeLane[trim($ref->textContent)] = $laneName;
        }
    }

    $skip = ['definitions', 'collaboration', 'participant', 'process', 'laneSet', 'lane', 'flowNodeRef',
        'incoming', 'outgoing', 'sequenceFlow', 'messageFlow', 'association', 'text', 'documentation',
        'conditionExpression', 'extensionElements', 'dataInputAssociation', 'dataOutputAssociation',
        'sourceRef', 'targetRef', 'timeDate', 'timeDuration', 'timeCycle', 'textAnnotation', 'group',
        'category', 'categoryValue', 'property', 'ioSpecification', 'inputSet', 'outputSet', 'dataInput', 'dataOutput'];

    $elements = [];
    foreach ($xp->query('//b:*[@id]') as $el) {
        /** @var DOMElement $el */
        $local = $el->localName;
        if (in_array($local, $skip, true) || str_ends_with($local, 'EventDefinition')) {
            continue;
        }
        $id = $el->getAttribute('id');
        $type = $local;
        $defs = [];
        foreach ($el->childNodes as $child) {
            if ($child instanceof DOMElement && str_ends_with($child->localName, 'EventDefinition')) {
                $defs[] = str_replace('EventDefinition', '', $child->localName);
            }
        }
        if ($defs) {
            $type .= '(' . implode(',', $defs) . ')';
        }
        if ($el->hasAttribute('attachedToRef')) {
            $type .= ' boundary→' . $el->getAttribute('attachedToRef');
        }
        $names[$id] = bpmnName($el);
        $types[$id] = $type;
        $elements[] = $id;
        $statKey = preg_replace('/\(.*/', '', $local);
        $stats[$statKey] = ($stats[$statKey] ?? 0) + 1;
    }

    $lines[] = '';
    $lines[] = 'Элементы:';
    foreach ($elements as $id) {
        $lane = isset($nodeLane[$id]) ? ' [дорожка: ' . $nodeLane[$id] . ']' : '';
        $lines[] = '- ' . $id . ' ' . $types[$id] . ' «' . $names[$id] . '»' . $lane;
    }

    $label = static fn(string $id): string => ($names[$id] ?? '') !== ''
        ? '«' . $names[$id] . '»'
        : $id . (isset($types[$id]) ? ' (' . $types[$id] . ')' : '');

    $flows = [];
    foreach (['sequenceFlow' => '→', 'messageFlow' => '⇢ (сообщение)'] as $flowType => $arrow) {
        foreach ($xp->query('//b:' . $flowType) as $flow) {
            /** @var DOMElement $flow */
            $name = bpmnName($flow);
            $flows[] = '- ' . $label($flow->getAttribute('sourceRef')) . ' ' . $arrow . ' '
                . $label($flow->getAttribute('targetRef')) . ($name !== '' ? ' [условие: ' . $name . ']' : '');
            $stats[$flowType] = ($stats[$flowType] ?? 0) + 1;
        }
    }
    foreach ($xp->query('//b:dataInputAssociation | //b:dataOutputAssociation') as $assoc) {
        $stats['dataAssociation'] = ($stats['dataAssociation'] ?? 0) + 1;
    }

    $lines[] = '';
    $lines[] = 'Потоки:';
    $lines = array_merge($lines, $flows ?: ['- (нет)']);

    ksort($stats);
    $lines[] = '';
    $lines[] = 'Счётчики: ' . implode(', ', array_map(static fn($k, $v) => $k . '=' . $v, array_keys($stats), $stats));

    return ['text' => implode("\n", $lines), 'stats' => $stats, 'valid' => true];
}

function bpmnName(DOMElement $el): string
{
    $name = $el->getAttribute('name');

    return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
}
