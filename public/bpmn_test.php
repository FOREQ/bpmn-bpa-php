<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Тест BPMN-моделлера</title>

    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/diagram-js.css">
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/bpmn-font/css/bpmn.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7fbfc;
            color: #183b59;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border: 1px solid #d8e4ea;
            border-radius: 12px;
            padding: 24px;
        }

        h1 {
            margin-top: 0;
        }

        #canvas {
            width: 100%;
            height: 550px;
            border: 1px solid #d8e4ea;
            border-radius: 10px;
            background: #fff;
            margin-top: 20px;
        }

        button {
            margin-top: 16px;
            margin-right: 10px;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            background: #10d6cf;
            color: #083344;
            font-weight: bold;
            cursor: pointer;
        }

        textarea {
            width: 100%;
            min-height: 180px;
            margin-top: 16px;
            padding: 12px;
            border: 1px solid #d8e4ea;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: Consolas, monospace;
        }

        .message {
            margin-top: 12px;
            padding: 12px;
            border-radius: 8px;
            display: none;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Тест BPMN-моделлера</h1>
    <p>На этой странице проверяется подключение BPMN-моделлера перед добавлением его в практическое задание.</p>

    <div id="canvas"></div>

    <button id="getXmlBtn">Получить BPMN XML</button>
    <button id="clearXmlBtn">Очистить XML</button>

    <div id="message" class="message"></div>

    <textarea id="xmlOutput" placeholder="Здесь появится BPMN XML"></textarea>
</div>

<script src="https://unpkg.com/bpmn-js@17.9.1/dist/bpmn-modeler.development.js"></script>

<script>
    const canvas = document.getElementById('canvas');
    const xmlOutput = document.getElementById('xmlOutput');
    const message = document.getElementById('message');

    function showMessage(text, type = 'success') {
        message.textContent = text;
        message.className = 'message ' + type;
        message.style.display = 'block';
    }

    const bpmnModeler = new BpmnJS({
        container: '#canvas'
    });

    const initialDiagram = `<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
                  id="Definitions_1"
                  targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" name="Старт">
      <bpmn:outgoing>Flow_1</bpmn:outgoing>
    </bpmn:startEvent>
    <bpmn:task id="Task_1" name="Задача">
      <bpmn:incoming>Flow_1</bpmn:incoming>
      <bpmn:outgoing>Flow_2</bpmn:outgoing>
    </bpmn:task>
    <bpmn:endEvent id="EndEvent_1" name="Конец">
      <bpmn:incoming>Flow_2</bpmn:incoming>
    </bpmn:endEvent>
    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="Task_1" />
    <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_1" targetRef="EndEvent_1" />
  </bpmn:process>

  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
      <bpmndi:BPMNShape id="StartEvent_1_di" bpmnElement="StartEvent_1">
        <dc:Bounds x="160" y="180" width="36" height="36" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="Task_1_di" bpmnElement="Task_1">
        <dc:Bounds x="260" y="160" width="120" height="80" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="EndEvent_1_di" bpmnElement="EndEvent_1">
        <dc:Bounds x="460" y="180" width="36" height="36" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNEdge id="Flow_1_di" bpmnElement="Flow_1">
        <di:waypoint x="196" y="198" />
        <di:waypoint x="260" y="200" />
      </bpmndi:BPMNEdge>
      <bpmndi:BPMNEdge id="Flow_2_di" bpmnElement="Flow_2">
        <di:waypoint x="380" y="200" />
        <di:waypoint x="460" y="198" />
      </bpmndi:BPMNEdge>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>`;

    async function openDiagram() {
        try {
            await bpmnModeler.importXML(initialDiagram);
            const canvasService = bpmnModeler.get('canvas');
            canvasService.zoom('fit-viewport');

            showMessage('BPMN-моделлер успешно загружен');
        } catch (error) {
            console.error(error);
            showMessage('Ошибка загрузки BPMN-моделлера', 'error');
        }
    }

    document.getElementById('getXmlBtn').addEventListener('click', async () => {
        try {
            const result = await bpmnModeler.saveXML({ format: true });
            xmlOutput.value = result.xml;
            showMessage('XML успешно получен');
        } catch (error) {
            console.error(error);
            showMessage('Ошибка получения XML', 'error');
        }
    });

    document.getElementById('clearXmlBtn').addEventListener('click', () => {
        xmlOutput.value = '';
        showMessage('Поле XML очищено');
    });

    openDiagram();
</script>
</body>
</html>