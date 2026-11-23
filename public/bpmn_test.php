<?php require_once __DIR__ . '/../lib/i18n.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Тест BPMN-моделлера</title>

    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/diagram-js.css">
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/bpmn-font/css/bpmn.css">
    <link rel="stylesheet" href="style.css">

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

        .bpmn-container {
            width: 100%;
            height: 550px;
            border: 1px solid #d8e4ea;
            border-radius: 10px;
            background: #fff;
            margin: 20px 0 0;
            box-sizing: border-box;
        }

        .container > button {
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

        @media (max-width: 700px) {
            body { padding: 12px; }
            .container { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Тест BPMN-моделлера</h1>
    <p>На этой странице проверяется подключение BPMN-моделлера перед добавлением его в практическое задание.</p>

    <div id="canvas" class="bpmn-container"></div>

    <button id="getXmlBtn">Получить BPMN XML</button>
    <button id="clearXmlBtn">Очистить XML</button>

    <div id="message" class="message"></div>

    <textarea id="xmlOutput" placeholder="Здесь появится BPMN XML"></textarea>
</div>

<script src="bpmn_tools.js"></script>
<script src="bpmn_touch.js"></script>
<script src="mobile_ui.js"></script>
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

    const initialDiagram = `<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:collaboration id="Collaboration_1">
    <bpmn:participant id="Participant_1" processRef="Process_1" />
  </bpmn:collaboration>
  <bpmn:process id="Process_1" isExecutable="false" />
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Collaboration_1">
      <bpmndi:BPMNShape id="Participant_1_di" bpmnElement="Participant_1" isHorizontal="true">
        <dc:Bounds x="100" y="100" width="900" height="360" />
      </bpmndi:BPMNShape>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>`;

    async function openDiagram() {
        try {
            await bpmnModeler.importXML(initialDiagram);
            const canvasService = bpmnModeler.get('canvas');
            canvasService.zoom('fit-viewport', 'auto');
            window.PortalMobile.attachModeler(canvas, bpmnModeler);

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
