<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/bpmn_fragment.php';

final class BpmnFragmentTest extends TestCase
{
    private function parse(string $xml): DOMXPath
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('bpmn', 'http://www.omg.org/spec/BPMN/20100524/MODEL');
        $xp->registerNamespace('bpmndi', 'http://www.omg.org/spec/BPMN/20100524/DI');

        return $xp;
    }

    public function testBuildsElementsFlowsAndDiagram(): void
    {
        $xml = bpmnFragmentToXml([
            'nodes' => [
                ['id' => 's', 'type' => 'startEvent'],
                ['id' => 'g', 'type' => 'exclusiveGateway', 'name' => 'Полная?'],
                ['id' => 'a', 'type' => 'userTask', 'name' => 'Принять'],
                ['id' => 'b', 'type' => 'sendTask', 'name' => 'Запросить'],
                ['id' => 'e', 'type' => 'endEvent', 'event' => 'terminate', 'highlight' => true],
            ],
            'flows' => [['s', 'g'], ['g', 'a', 'Да'], ['from' => 'g', 'to' => 'b', 'kind' => 'default'], ['a', 'e'], ['b', 'e']],
        ])['xml'];
        $xp = $this->parse($xml);
        $this->assertSame(5, $xp->query('//bpmndi:BPMNShape')->length);
        $this->assertSame(5, $xp->query('//bpmndi:BPMNEdge')->length);
        $this->assertSame(1, $xp->query('//bpmn:endEvent/bpmn:terminateEventDefinition')->length);
        $this->assertSame('Flow_3', $xp->query('//bpmn:exclusiveGateway')->item(0)->getAttribute('default'));
        $this->assertSame(1, preg_match('/bpmnElement="e" bioc:stroke/', $xml));
    }

    public function testAllowsErroneousFragmentsForQuestions(): void
    {
        // Поток из события завершения — ошибка, которую должен найти участник.
        $xml = bpmnFragmentToXml(['nodes' => [['id' => 'e', 'type' => 'endEvent'], ['id' => 't', 'type' => 'task']], 'flows' => [['e', 't']]])['xml'];
        $this->assertSame(1, $this->parse($xml)->query('//bpmn:endEvent/bpmn:outgoing')->length);
    }

    public function testLayoutIsDeterministicAndOrthogonal(): void
    {
        $fragment = ['nodes' => [['id' => 'a', 'type' => 'task'], ['id' => 'g', 'type' => 'parallelGateway'], ['id' => 'b', 'type' => 'task'], ['id' => 'c', 'type' => 'task']],
            'flows' => [['a', 'g'], ['g', 'b'], ['g', 'c'], ['c', 'a']]];
        $this->assertSame(bpmnFragmentToXml($fragment)['xml'], bpmnFragmentToXml($fragment)['xml']);
        $layout = bpmnFragmentLayout(bpmnFragmentNormalize($fragment));
        foreach ($layout['routes'] as $points) {
            for ($i = 0; $i < count($points) - 1; $i++) {
                $this->assertTrue($points[$i][0] === $points[$i + 1][0] || $points[$i][1] === $points[$i + 1][1], 'диагональный отрезок');
            }
        }
    }

    public function testRejectsUnknownTypesAndDanglingFlows(): void
    {
        $this->expectException(BpmnFragmentException::class);
        bpmnFragmentToXml(['nodes' => [['id' => 'a', 'type' => 'task']], 'flows' => [['a', 'zzz']]]);
    }
}
