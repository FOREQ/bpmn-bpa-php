/* Shared navigation and diagram controls; independent of test scoring. */
(() => {
    'use strict';
    const kk = document.documentElement.lang === 'kk';
    const text = (ru, kz) => kk ? kz : ru;
    const phone = window.matchMedia('(max-width: 700px)');
    if (document.body.classList.contains('assessment-page')) document.documentElement.classList.add('assessment-document');
    const header = document.querySelector('.site-header');
    const menu = header?.querySelector('.site-menu-toggle');
    const setMenu = (open) => {
        menu?.setAttribute('aria-expanded', String(open));
        header?.classList.toggle('menu-open', open);
    };
    header?.classList.add('mobile-ready');
    menu?.addEventListener('click', () => setMenu(menu.getAttribute('aria-expanded') !== 'true'));
    header?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu?.getAttribute('aria-expanded') === 'true') {
            setMenu(false);
            menu.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!header?.contains(event.target)) setMenu(false);
    });
    const details = new Set();
    function enhanceDetails(root) {
        root.querySelectorAll('[data-participant-details]').forEach(item => {
            if (details.has(item)) return;
            item.open = !phone.matches;
            details.add(item);
        });
    }
    enhanceDetails(document);
    phone.addEventListener('change', () => {
        setMenu(false);
        details.forEach(item => { item.open = !phone.matches; });
    });

    const button = (label, action, className = '') => {
        const node = document.createElement('button');
        node.type = 'button';
        node.textContent = label;
        node.className = className;
        node.addEventListener('click', action);
        return node;
    };
    // Keep names available to assistive technology and mouse tooltips without
    // spending the phone's working area on repeated button captions.
    const icon = (node, symbol, label = node.textContent) => {
        node.replaceChildren();
        node.classList.add('bpmn-icon-button');
        node.setAttribute('aria-label', label);
        node.title = label;
        const image = document.createElement('span');
        image.setAttribute('aria-hidden', 'true');
        if (symbol.startsWith('bpmn-icon-')) image.className = symbol;
        else image.textContent = symbol;
        node.append(image);
        return node;
    };

    // A modal with scrolling at actual image size makes dense BPMN readable.
    let imageDialog;
    let imageOpener;
    function openImage(source) {
        if (!imageDialog) {
            imageDialog = document.createElement('dialog');
            imageDialog.className = 'diagram-dialog';
            imageDialog.setAttribute('aria-label', text('Просмотр схемы', 'Сызбаны қарау'));
            const toolbar = document.createElement('div');
            toolbar.className = 'diagram-toolbar';
            const image = document.createElement('img');
            const surface = document.createElement('div');
            surface.className = 'diagram-surface';
            surface.tabIndex = 0;
            surface.setAttribute('aria-label', text('Схема. Прокручивайте для просмотра.', 'Сызба. Қарау үшін айналдырыңыз.'));
            surface.append(image);
            let scale = 1;
            const fitScale = () => Math.min(1, Math.max(1, surface.clientWidth - 24) / Math.max(1, image.naturalWidth));
            const resize = (value) => {
                scale = Math.max(fitScale() / 4, Math.min(4, value));
                image.style.width = `${image.naturalWidth * scale}px`;
                image.style.maxWidth = 'none';
            };
            toolbar.append(
                button('−', () => resize(scale / 1.4)),
                button('+', () => resize(scale * 1.4)),
                button(text('Вместить', 'Сыйдыру'), () => {
                    image.style.width = 'auto';
                    image.style.maxWidth = '100%';
                    scale = fitScale();
                }),
                button(text('Закрыть', 'Жабу'), () => imageDialog.close())
            );
            toolbar.children[0].setAttribute('aria-label', text('Уменьшить схему', 'Сызбаны кішірейту'));
            toolbar.children[1].setAttribute('aria-label', text('Увеличить схему', 'Сызбаны үлкейту'));
            image.addEventListener('load', () => {
                image.style.width = 'auto';
                image.style.maxWidth = '100%';
                scale = fitScale();
                surface.scrollTo(0, 0);
            });
            imageDialog.append(toolbar, surface);
            imageDialog.addEventListener('close', () => {
                document.body.classList.remove('diagram-modal-open');
                imageOpener?.focus({ preventScroll: true });
            });
            document.body.append(imageDialog);
        }
        imageOpener = source;
        const image = imageDialog.querySelector('img');
        image.src = source.src;
        image.alt = source.alt || text('BPMN-схема', 'BPMN сызбасы');
        document.body.classList.add('diagram-modal-open');
        imageDialog.showModal();
    }

    function enhanceImages(root) {
        root.querySelectorAll('.question-image, .option-image, .complexity-image-box img, .bpmn-image').forEach(img => {
            if (img.dataset.zoomReady) return;
            img.dataset.zoomReady = 'true';
            img.tabIndex = 0;
            img.setAttribute('role', 'button');
            img.setAttribute('aria-haspopup', 'dialog');
            img.setAttribute('aria-label', text('Увеличить схему', 'Сызбаны үлкейту'));
            img.title = text('Нажмите, чтобы увеличить схему', 'Сызбаны үлкейту үшін басыңыз');
            img.addEventListener('click', event => {
                event.preventDefault(); // Viewing an option image must not select its radio.
                event.stopPropagation();
                openImage(img);
            });
            img.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openImage(img);
                }
            });
        });
    }
    const observer = new MutationObserver(records => {
        for (const record of records) {
            for (const node of record.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    const root = node.parentElement || node;
                    enhanceImages(root);
                    enhanceDetails(root);
                }
            }
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
    enhanceImages(document);

    function attachModeler(container, modeler) {
        if (container.dataset.controlsReady) return;
        container.dataset.controlsReady = 'true';
        const workspace = document.createElement('div');
        workspace.className = 'bpmn-workspace';
        container.before(workspace);
        const toolbar = document.createElement('div');
        toolbar.className = 'diagram-toolbar bpmn-toolbar';
        toolbar.setAttribute('role', 'group');
        toolbar.setAttribute('aria-label', text('Управление BPMN-редактором', 'BPMN редакторын басқару'));
        workspace.append(toolbar, container);
        container.tabIndex = 0;
        const canvas = modeler.get('canvas');
        const bus = modeler.get('eventBus');
        const selection = modeler.get('selection');
        // A short desktop window must not become a phone editor. Landscape
        // phones and larger touch screens still need compact controls.
        const compactView = window.matchMedia('(max-width: 700px), (pointer: coarse) and (max-width: 1100px), (max-width: 1000px) and (max-height: 500px)');
        const keyboard = modeler.get('keyboard');
        const desktopHint = text('Перетащите элемент из панели слева. Двойной щелчок — название. L — выбор рамкой, S — пространство, Delete — удаление. Ctrl+Z — отмена.', 'Элементті сол жақ панельден сүйреңіз. Екі рет басу — атауы. L — жақтаумен таңдау, S — кеңістік, Delete — жою. Ctrl+Z — болдырмау.');
        const hint = document.createElement('div');
        hint.className = 'bpmn-touch-hint';
        hint.setAttribute('role', 'status');
        hint.setAttribute('aria-live', 'polite');
        const prompts = {
            select: text('Нажмите на фигуру для выбора. Тяните её для перемещения. Два пальца — масштаб.', 'Фигураны таңдау үшін басыңыз. Жылжыту үшін сүйреңіз. Екі саусақ — масштаб.'),
            pan: text('Перемещайте полотно одним пальцем; двумя меняйте масштаб. Фигуры остаются на месте.', 'Кенепті бір саусақпен жылжытыңыз; екі саусақпен масштабтаңыз. Фигуралар орнында қалады.'),
            multi: text('Выбирайте фигуры касаниями или рамкой. Тяните выбранную фигуру — переместится вся группа.', 'Фигураларды басып немесе жақтаумен таңдаңыз. Таңдалған фигураны сүйресеңіз, бүкіл топ жылжиды.'),
            space: text('Тяните свободное место между фигурами: расстояние изменится вместе с пулом. S — этот инструмент.', 'Фигуралар арасындағы бос орынды сүйреңіз: аралық пулмен бірге өзгереді. S — осы құрал.'),
            place: text('Нажмите на полотно в месте добавления. Можно сначала переместить или увеличить схему.', 'Қосу орнында кенепті басыңыз. Алдымен сызбаны жылжытуға немесе үлкейтуге болады.'),
            attach: text('Нажмите на задачу или подпроцесс: событие прикрепится к ближайшему краю.', 'Тапсырманы немесе ішкі процесті басыңыз: оқиға ең жақын шетіне бекітіледі.'),
            connect: text('Нажмите на начальную фигуру соединения.', 'Байланыстың бастапқы фигурасын басыңыз.'),
            target: text('Нажмите на конечную фигуру. Между пулами — поток сообщений.', 'Соңғы фигураны басыңыз. Пулдар арасында — хабарламалар ағыны.'),
            invalidPlace: text('Сюда нельзя добавить этот элемент. Выберите другое место.', 'Бұл элементті мұнда қосуға болмайды. Басқа орынды таңдаңыз.'),
            invalidTarget: text('Нажмите на фигуру, которую нужно соединить.', 'Байланыстыру керек фигураны басыңыз.'),
            invalidConnection: text('Такое соединение не допускается BPMN. Выберите другую фигуру.', 'BPMN мұндай байланысқа рұқсат бермейді. Басқа фигураны таңдаңыз.'),
            invalidMessageTarget: text('Это событие отправки. Для входящего сообщения выберите событие получения или задачу.', 'Бұл жіберу оқиғасы. Кіріс хабарламасы үшін қабылдау оқиғасын немесе тапсырманы таңдаңыз.'),
            invalidMessageSource: text('Это событие получения. Для исходящего сообщения выберите событие отправки или задачу.', 'Бұл қабылдау оқиғасы. Шығыс хабарламасы үшін жіберу оқиғасын немесе тапсырманы таңдаңыз.'),
            invalidMessageConnection: text('Поток сообщений связывает разные пулы, задачи и события сообщений. Шлюзы так не соединяются.', 'Хабарламалар ағыны әртүрлі пулдарды, тапсырмаларды және хабарлама оқиғаларын байланыстырады. Шлюздер бұлай байланыспайды.'),
            created: text('Элемент добавлен. Вы можете изменить его название или тип.', 'Элемент қосылды. Оның атауын немесе түрін өзгертуге болады.'),
            connected: text('Соединение добавлено.', 'Байланыс қосылды.'),
            connectedReversed: text('Соединение добавлено. Направление выбрано автоматически.', 'Байланыс қосылды. Бағыты автоматты түрде таңдалды.')
        };
        let modeButtons = [];
        const tools = window.PortalBpmnTools(modeler, state => {
            hint.replaceChildren(document.createTextNode(!compactView.matches && state === 'select' ? desktopHint : prompts[state]));
            workspace.dataset.bpmnMode = tools.getMode();
            modeButtons.forEach(([node, mode]) => {
                node.setAttribute('aria-pressed', String(tools.getMode() === mode));
                if (mode === 'select') node.hidden = tools.getMode() === 'select';
            });
        });
        const zoom = factor => canvas.zoom(Math.max(0.2, Math.min(4, canvas.zoom() * factor)), 'auto');
        let dialog;
        let anchor;
        const fit = () => {
            if (!container.clientWidth || !container.clientHeight) return;
            canvas.resized();
            canvas.zoom('fit-viewport', 'auto');
        };
        const expand = button(text('На весь экран', 'Толық экран'), () => {
            if (dialog?.open) { dialog.close(); return; }
            if (!dialog) {
                dialog = document.createElement('dialog');
                dialog.className = 'bpmn-editor-dialog';
                dialog.setAttribute('aria-label', text('BPMN-редактор', 'BPMN редакторы'));
                dialog.addEventListener('close', () => {
                    anchor.replaceWith(workspace);
                    document.body.classList.remove('diagram-modal-open');
                    icon(expand, '⛶', text('На весь экран', 'Толық экран'));
                    requestAnimationFrame(fit);
                    expand.focus({ preventScroll: true });
                });
                document.body.append(dialog);
            }
            anchor = document.createComment('BPMN workspace position');
            workspace.replaceWith(anchor);
            dialog.append(workspace);
            icon(expand, '✓', text('Готово', 'Дайын'));
            document.body.classList.add('diagram-modal-open');
            dialog.showModal();
            requestAnimationFrame(fit);
        }, 'bpmn-expand');
        icon(expand, '⛶');
        const minus = button('−', () => zoom(1 / 1.25));
        const plus = button('+', () => zoom(1.25));
        const commands = modeler.get('commandStack');
        const undo = button('↶', () => commands.undo());
        const redo = button('↷', () => commands.redo());
        // A normal text field avoids trying to edit a tiny SVG label with a phone keyboard.
        const nameDialog = document.createElement('dialog');
        nameDialog.className = 'bpmn-name-dialog';
        const nameForm = document.createElement('form');
        const nameLabel = document.createElement('label');
        nameLabel.textContent = text('Название элемента', 'Элементтің атауы');
        const nameInput = document.createElement('textarea');
        nameInput.rows = 3;
        nameLabel.append(nameInput);
        const saveName = button(text('Сохранить', 'Сақтау'), () => {});
        saveName.type = 'submit';
        nameForm.append(nameLabel, saveName, button(text('Отмена', 'Болдырмау'), () => nameDialog.close()));
        nameDialog.append(nameForm);
        document.body.append(nameDialog);
        let namedElement;
        nameForm.addEventListener('submit', event => {
            event.preventDefault();
            if (modeler.get('elementRegistry').get(namedElement.id)) modeler.get('modeling').updateLabel(namedElement, nameInput.value);
            nameDialog.close();
        });
        const rename = button(text('Название', 'Атауы'), () => {
            namedElement = selection.get()[0];
            if (!namedElement) return;
            namedElement = namedElement.labelTarget || namedElement;
            nameInput.value = namedElement.businessObject.name || namedElement.businessObject.text || '';
            nameDialog.showModal();
            nameInput.focus();
        });
        const remove = button(text('Удалить', 'Жою'), () => {
            // Deletion changes the live selection while commands execute.
            modeler.get('modeling').removeElements(modeler.get('selection').get().slice());
        });
        nameDialog.addEventListener('close', () => rename.focus({ preventScroll: true }));
        const chooser = document.createElement('div');
        chooser.className = 'bpmn-chooser';
        chooser.hidden = true;
        chooser.setAttribute('role', 'group');
        chooser.setAttribute('aria-label', text('Выберите элемент', 'Элементті таңдаңыз'));
        const groups = [
            ['tasks', 'Задачи', 'Тапсырмалар', 'bpmn-icon-task'],
            ['gateways', 'Шлюзы', 'Шлюздер', 'bpmn-icon-gateway-xor'],
            ['events', 'События', 'Оқиғалар', 'bpmn-icon-intermediate-event-none'],
            ['other', 'Пулы, данные и примечания', 'Пулдар, деректер және ескертпелер', 'bpmn-icon-participant']
        ];
        const types = [];
        const typeNames = new Map();
        const entry = (group, specification, ru, kz, symbol, english) => {
            types.push({ group, specification, label: text(ru, kz), symbol });
            if (english) typeNames.set(english, text(ru, kz));
        };
        for (const [type, ru, kz, symbol, english] of [
            ['Task', 'Задача', 'Тапсырма', 'task', 'Task'],
            ['UserTask', 'Задача пользователя', 'Пайдаланушы тапсырмасы', 'user-task', 'User task'],
            ['ServiceTask', 'Сервисная задача', 'Сервистік тапсырма', 'service-task', 'Service task'],
            ['ManualTask', 'Ручная задача', 'Қолмен орындалатын тапсырма', 'manual-task', 'Manual task'],
            ['ScriptTask', 'Скриптовая задача', 'Скрипт тапсырмасы', 'script-task', 'Script task'],
            ['BusinessRuleTask', 'Задача бизнес-правило', 'Бизнес ереже тапсырмасы', 'business-rule-task', 'Business rule task'],
            ['SendTask', 'Задача отправки', 'Жіберу тапсырмасы', 'send-task', 'Send task'],
            ['ReceiveTask', 'Задача получения', 'Қабылдау тапсырмасы', 'receive-task', 'Receive task'],
            ['CallActivity', 'Вызов процесса', 'Процесті шақыру', 'call-activity', 'Call activity']
        ]) entry('tasks', { type: `bpmn:${type}` }, ru, kz, `bpmn-icon-${symbol}`, english);
        entry('tasks', { type: 'bpmn:SubProcess', isExpanded: true }, 'Подпроцесс', 'Ішкі процесс', 'bpmn-icon-subprocess-expanded', 'Sub-process (expanded)');
        entry('tasks', { type: 'bpmn:SubProcess', isExpanded: false }, 'Свёрнутый подпроцесс', 'Жиналған ішкі процесс', 'bpmn-icon-subprocess-collapsed', 'Sub-process (collapsed)');
        entry('tasks', { type: 'bpmn:Transaction', isExpanded: true }, 'Транзакция', 'Транзакция', 'bpmn-icon-transaction', 'Transaction');
        for (const [type, ru, kz, symbol, english] of [
            ['ExclusiveGateway', 'Шлюз «ИЛИ»', '«НЕМЕСЕ» шлюзі', 'xor', 'Exclusive gateway'],
            ['ParallelGateway', 'Шлюз «И»', '«ЖӘНЕ» шлюзі', 'parallel', 'Parallel gateway'],
            ['InclusiveGateway', 'Шлюз «И/ИЛИ»', '«ЖӘНЕ/НЕМЕСЕ» шлюзі', 'or', 'Inclusive gateway'],
            ['EventBasedGateway', 'Событийный шлюз', 'Оқиғалық шлюз', 'eventbased', 'Event-based gateway'],
            ['ComplexGateway', 'Комплексный шлюз', 'Күрделі шлюз', 'complex', 'Complex gateway']
        ]) entry('gateways', { type: `bpmn:${type}` }, ru, kz, `bpmn-icon-gateway-${symbol}`, english);
        const eventKinds = [
            ['Message', 'сообщение', 'хабар', 'message'], ['Timer', 'таймер', 'таймер', 'timer'],
            ['Conditional', 'условие', 'шарт', 'condition'], ['Signal', 'сигнал', 'сигнал', 'signal'],
            ['Escalation', 'эскалация', 'эскалация', 'escalation'], ['Error', 'ошибка', 'қате', 'error'],
            ['Compensate', 'компенсация', 'өтемақы', 'compensation'], ['Link', 'ссылка', 'сілтеме', 'link'],
            ['Terminate', 'завершение всего процесса', 'бүкіл процесті аяқтау', 'terminate']
        ];
        const event = (type, prefix, ru, kz, allowed, none = true) => {
            const group = 'events';
            if (none) entry(group, { type: `bpmn:${type}` }, ru, kz, `bpmn-icon-${prefix}-none`);
            for (const [definition, ruKind, kzKind, marker] of eventKinds.filter(item => allowed.includes(item[0]))) {
                const labelRu = `${ru}: ${ruKind}`, labelKz = `${kz}: ${kzKind}`;
                const attrs = { type: `bpmn:${type}`, eventDefinitionType: `bpmn:${definition}EventDefinition` };
                entry(group, attrs, labelRu, labelKz, `bpmn-icon-${prefix}-${marker}`);
                const nativeKind = definition === 'Compensate' ? 'Compensation' : definition;
                const nativeType = { StartEvent: 'start event', IntermediateCatchEvent: 'intermediate catch event', IntermediateThrowEvent: 'intermediate throw event', BoundaryEvent: 'boundary event', EndEvent: 'end event' }[type];
                typeNames.set(`${nativeKind} ${nativeType}`, text(labelRu, labelKz));
                typeNames.set(`${nativeKind} ${nativeType} (non-interrupting)`, text(`${labelRu} (непрерывающее)`, `${labelKz} (үзбейтін)`));
            }
        };
        event('StartEvent', 'start-event', 'Начало', 'Бастау', ['Message', 'Timer', 'Conditional', 'Signal']);
        event('IntermediateCatchEvent', 'intermediate-event-catch', 'Ожидание', 'Күту', ['Message', 'Timer', 'Conditional', 'Signal', 'Link'], false);
        event('IntermediateThrowEvent', 'intermediate-event-throw', 'Промежуточное событие', 'Аралық оқиға', ['Message', 'Signal', 'Escalation', 'Compensate', 'Link'], false);
        entry('events', { type: 'bpmn:IntermediateThrowEvent' }, 'Промежуточное событие', 'Аралық оқиға', 'bpmn-icon-intermediate-event-none', 'Intermediate throw event');
        event('EndEvent', 'end-event', 'Конец', 'Аяқтау', ['Message', 'Signal', 'Escalation', 'Error', 'Compensate', 'Terminate']);
        event('BoundaryEvent', 'intermediate-event-catch', 'На границе', 'Шекарада', ['Message', 'Timer', 'Conditional', 'Signal', 'Escalation', 'Error', 'Compensate'], false);
        for (const [type, ru, kz, symbol] of [
            ['Participant', 'Пул', 'Пул', 'participant'], ['DataObjectReference', 'Документ', 'Құжат', 'data-object'],
            ['DataStoreReference', 'Хранилище данных', 'Деректер қоймасы', 'data-store'],
            ['TextAnnotation', 'Примечание', 'Ескертпе', 'text-annotation'], ['Group', 'Группа', 'Топ', 'group']
        ]) entry('other', { type: `bpmn:${type}` }, ru, kz, `bpmn-icon-${symbol}`);
        for (const [english, ru, kz] of [
            ['Start event', 'Начало', 'Бастау'], ['End event', 'Конец', 'Аяқтау'],
            ['Sub-process', 'Подпроцесс', 'Ішкі процесс'], ['Event sub-process', 'Событийный подпроцесс', 'Оқиғалық ішкі процесс'],
            ['Data store reference', 'Хранилище данных', 'Деректер қоймасы'], ['Data object reference', 'Документ', 'Құжат'],
            ['Expanded pool/participant', 'Развёрнутый пул', 'Ашылған пул'], ['Empty pool/participant', 'Свёрнутый пул', 'Жиналған пул'],
            ['Sequence flow', 'Поток управления', 'Басқару ағыны'], ['Default flow', 'Поток по умолчанию', 'Әдепкі ағын'],
            ['Conditional flow', 'Условный поток', 'Шартты ағын'], ['Loop', 'Цикл', 'Цикл'],
            ['Parallel multi-instance', 'Параллельное многократное выполнение', 'Параллель көп мәрте орындау'],
            ['Sequential multi-instance', 'Последовательное многократное выполнение', 'Бірізді көп мәрте орындау'],
            ['Collection', 'Коллекция документов', 'Құжаттар жинағы'], ['Participant multiplicity', 'Множество участников', 'Қатысушылар жиыны'],
            ['Ad-hoc', 'Произвольный подпроцесс', 'Еркін ішкі процесс'], ['Toggle non-interrupting', 'Непрерывающее событие', 'Үзбейтін оқиға'],
            ['Cancel end event', 'Конец: отмена транзакции', 'Аяқтау: транзакцияны болдырмау'],
            ['Cancel boundary event', 'На границе: отмена транзакции', 'Шекарада: транзакцияны болдырмау'],
            ['Error start event', 'Начало: ошибка', 'Бастау: қате'], ['Escalation start event', 'Начало: эскалация', 'Бастау: эскалация'],
            ['Compensation start event', 'Начало: компенсация', 'Бастау: өтемақы']
        ]) typeNames.set(english, text(ru, kz));
        const replaceProvider = modeler.get('replaceMenuProvider');
        const replacementEntries = element => element ? { ...replaceProvider.getPopupMenuHeaderEntries(element), ...replaceProvider.getPopupMenuEntries(element) } : {};
        let changingType = false, chooserOpener, activeGroup = 'tasks';
        const closeChooser = () => { chooser.hidden = true; chooserOpener?.focus({ preventScroll: true }); };
        const chooserHead = document.createElement('div');
        chooserHead.className = 'bpmn-chooser-head';
        const chooserGrid = document.createElement('div');
        chooserGrid.className = 'bpmn-chooser-grid';
        const chooseButton = (label, symbol, action) => icon(button(label, action), symbol);
        const renderChooser = () => {
            chooserHead.replaceChildren(); chooserGrid.replaceChildren();
            const heading = document.createElement('span');
            heading.className = 'bpmn-chooser-title';
            heading.textContent = changingType ? text('Тип', 'Түрі') : text('Добавить', 'Қосу');
            chooserHead.append(heading);
            if (!changingType) {
                const categories = document.createElement('select');
                categories.className = 'bpmn-category-select';
                categories.setAttribute('aria-label', text('Категория элементов', 'Элементтер санаты'));
                for (const [group, ru, kz] of groups) {
                    const option = document.createElement('option');
                    option.value = group; option.textContent = text(ru, kz); categories.append(option);
                }
                categories.value = activeGroup;
                categories.addEventListener('change', () => { activeGroup = categories.value; renderChooser(); chooserHead.querySelector('select')?.focus({ preventScroll: true }); });
                chooserHead.append(categories);
            }
            chooserHead.append(chooseButton(text('Закрыть', 'Жабу'), '×', closeChooser));
            if (changingType) {
                const element = selection.get()[0];
                for (const [id, item] of Object.entries(replacementEntries(element))) {
                    const label = typeNames.get(item.label || item.title) || item.label || item.title;
                    const taskSymbol = /^bpmn-icon-(user|service|manual|script|business-rule|send|receive)$/.test(item.className) ? `${item.className}-task` : item.className;
                    const choose = chooseButton(label, taskSymbol || 'bpmn-icon-intermediate-event-catch-non-interrupting-message', event => {
                        closeChooser();
                        if (selection.get()[0] !== element || !modeler.get('elementRegistry').get(element.id)) return;
                        item.action(event, item);
                        tools.setMode('select');
                    });
                    if (item.imageHtml) choose.firstChild.innerHTML = item.imageHtml;
                    choose.dataset.bpmnReplace = id;
                    if ('active' in item) choose.setAttribute('aria-pressed', String(item.active));
                    chooserGrid.append(choose);
                }
            } else for (const item of types.filter(item => item.group === activeGroup)) {
                const choose = chooseButton(item.label, item.symbol, () => { closeChooser(); tools.place(item.specification); });
                choose.dataset.bpmnType = item.specification.type;
                chooserGrid.append(choose);
            }
        };
        chooser.append(chooserHead, chooserGrid);
        chooser.addEventListener('keydown', event => { if (event.key === 'Escape') { event.stopPropagation(); closeChooser(); } });
        const openChooser = (replace, opener) => {
            if (!chooser.hidden && changingType === replace) { closeChooser(); return; }
            changingType = replace; chooserOpener = opener;
            tools.setMode('select'); renderChooser();
            chooser.hidden = false;
            chooser.style.top = `${container.offsetTop}px`;
            chooserHead.querySelector('select, button')?.focus({ preventScroll: true });
        };
        const add = button(text('+ Добавить', '+ Қосу'), () => openChooser(false, add));
        const changeType = button(text('Тип', 'Түрі'), () => openChooser(true, changeType));
        const addLane = button(text('+ Дорожка', '+ Жолақ'), () => {
            const element = selection.get()[0];
            if (element) modeler.get('modeling').addLane(element, 'bottom');
        });
        const updateSelection = () => {
            const element = selection.get()[0];
            const empty = selection.get().length !== 1;
            rename.disabled = empty;
            remove.disabled = selection.get().length === 0;
            changeType.disabled = empty || Object.keys(replacementEntries(element)).length === 0;
            addLane.disabled = empty || !['bpmn:Participant', 'bpmn:Lane'].includes(element.type) || element.di?.isExpanded === false;
        };
        modeler.get('eventBus').on('selection.changed', updateSelection);
        bus.on('selection.changed', () => { chooser.hidden = true; });
        updateSelection();
        undo.setAttribute('aria-label', text('Отменить действие', 'Әрекетті болдырмау'));
        redo.setAttribute('aria-label', text('Повторить действие', 'Әрекетті қайталау'));
        undo.title = undo.getAttribute('aria-label');
        redo.title = redo.getAttribute('aria-label');
        const updateHistory = () => {
            undo.disabled = !commands.canUndo();
            redo.disabled = !commands.canRedo();
        };
        modeler.get('eventBus').on('commandStack.changed', updateHistory);
        updateHistory();
        minus.setAttribute('aria-label', text('Уменьшить масштаб', 'Масштабты кішірейту'));
        plus.setAttribute('aria-label', text('Увеличить масштаб', 'Масштабты үлкейту'));
        const select = button(text('Отмена', 'Болдырмау'), () => { modeler.get('dragging').cancel(); chooser.hidden = true; tools.setMode('select'); });
        const pan = button(text('Полотно', 'Кенеп'), () => { chooser.hidden = true; tools.setMode(tools.getMode() === 'pan' ? 'select' : 'pan'); });
        const connect = button(text('Соединить', 'Байланыстыру'), () => { chooser.hidden = true; tools.connect(); });
        const activateGroupTool = mode => {
            modeler.get('dragging').cancel(); chooser.hidden = true;
            if (mode === 'multi') selection.select(selection.get().filter(item => !['bpmn:Participant', 'bpmn:Lane'].includes(item.type)));
            tools.setMode(tools.getMode() === mode ? 'select' : mode);
        };
        const multi = button(text('Множественный выбор', 'Бірнеше элементті таңдау'), () => activateGroupTool('multi'));
        const space = button(text('Изменить пространство (S)', 'Кеңістікті өзгерту (S)'), () => activateGroupTool('space'));
        modeButtons = [[select, 'select'], [pan, 'pan'], [connect, 'connect'], [multi, 'multi'], [space, 'space']];
        const selectionCount = document.createElement('span');
        selectionCount.className = 'bpmn-selection-count';
        const updateCount = () => { selectionCount.hidden = selection.get().length < 2; selectionCount.textContent = selection.get().length; };
        bus.on('selection.changed', updateCount);
        const groupKeys = event => {
            if (!compactView.matches) return; // Desktop uses the native keyboard bindings.
            if (event.target.closest('input, textarea, select, [contenteditable="true"]') || event.ctrlKey || event.metaKey || event.altKey) return;
            if (event.code === 'KeyS' || event.key.toLowerCase() === 's') activateGroupTool('space');
            else if (event.code === 'KeyL' || event.key.toLowerCase() === 'l') activateGroupTool('multi');
            else if (event.key === 'Escape') { modeler.get('dragging').cancel(); chooser.hidden = true; tools.setMode('select'); }
            else return;
            event.preventDefault(); event.stopPropagation();
        };
        workspace.addEventListener('keydown', groupKeys);
        const zoomLabel = document.createElement('output');
        zoomLabel.className = 'bpmn-zoom-value';
        const updateZoom = () => { zoomLabel.textContent = `${Math.round(canvas.zoom() * 100)}%`; };
        bus.on('canvas.viewbox.changed', updateZoom);
        updateZoom();
        const actions = document.createElement('div');
        actions.className = 'diagram-toolbar bpmn-actions';
        actions.append(add, connect, multi, space, pan, undo, redo);
        const selectedActions = document.createElement('div');
        selectedActions.className = 'diagram-toolbar bpmn-selected-actions';
        const commandActions = document.createElement('div');
        commandActions.className = 'bpmn-command-actions';
        commandActions.append(select, remove);
        selectedActions.append(rename, changeType, addLane, commandActions);
        const description = container.closest('.practical-card')?.querySelector('.practical-description');
        let instructionDialog;
        let gestureInstructions;
        let informationButton;
        if (description) {
            instructionDialog = document.createElement('dialog');
            instructionDialog.className = 'bpmn-instruction-dialog';
            instructionDialog.setAttribute('aria-label', text('Условие задания', 'Тапсырма шарты'));
            const instructionText = document.createElement('p');
            instructionText.textContent = description.textContent;
            instructionDialog.append(button(text('К схеме', 'Сызбаға'), () => instructionDialog.close()), instructionText);
            gestureInstructions = document.createElement('p');
            gestureInstructions.textContent = prompts.select + ' ' + prompts.pan;
            instructionDialog.append(gestureInstructions);
            document.body.append(instructionDialog);
            const showInstructions = button(text('Задание', 'Тапсырма'), () => instructionDialog.showModal());
            icon(showInstructions, '▤');
            instructionDialog.addEventListener('close', () => showInstructions.focus({ preventScroll: true }));
            selectedActions.prepend(showInstructions);
            informationButton = showInstructions;
        }
        selectedActions.setAttribute('aria-label', text('Выбранный элемент', 'Таңдалған элемент'));
        const help = icon(button(text('Подсказка по рисованию', 'Сурет салу нұсқауы'), () => {
            const expanded = workspace.classList.toggle('bpmn-help-open');
            help.setAttribute('aria-pressed', String(expanded));
        }), '?');
        if (!description) { selectedActions.prepend(help); informationButton = help; }
        icon(add, 'bpmn-icon-task'); icon(select, '×'); icon(connect, 'bpmn-icon-connection-multi'); icon(pan, 'bpmn-icon-hand-tool');
        icon(multi, 'bpmn-icon-lasso-tool'); icon(space, 'bpmn-icon-space-tool');
        multi.classList.add('bpmn-multi'); multi.append(selectionCount); updateCount();
        add.classList.add('bpmn-add');
        select.classList.add('bpmn-cancel-placement');
        addLane.classList.add('bpmn-add-lane');
        icon(rename, '✎'); icon(changeType, 'bpmn-icon-screw-wrench'); icon(addLane, 'bpmn-icon-lane-insert-below'); icon(remove, 'bpmn-icon-trash');
        remove.classList.add('bpmn-delete');
        const updateSelectedActions = () => {
            selectedActions.style.visibility = 'visible';
            select.hidden = tools.getMode() === 'select';
        };
        bus.on('selection.changed', updateSelectedActions);
        const fitButton = button('⌖', fit);
        fitButton.setAttribute('aria-label', text('Вместить', 'Сыйдыру'));
        fitButton.title = fitButton.getAttribute('aria-label');
        const desktopActions = document.createElement('div');
        desktopActions.className = 'bpmn-desktop-actions';
        toolbar.append(minus, zoomLabel, plus, fitButton, desktopActions, expand);
        // Keep the original palette and its native event handlers, but give it
        // its own column so it cannot cover shapes on the desktop canvas.
        const desktopPalette = document.createElement('div');
        desktopPalette.className = 'bpmn-desktop-palette';
        const nativePalette = container.querySelector('.djs-palette');
        if (nativePalette) desktopPalette.append(nativePalette);
        workspace.insertBefore(desktopPalette, container);
        const toolSections = document.createElement('div');
        toolSections.className = 'bpmn-tool-sections';
        toolSections.append(actions, selectedActions);
        workspace.insertBefore(toolSections, container);
        workspace.insertBefore(chooser, container);
        workspace.append(hint);
        tools.setMode('select');
        updateSelectedActions();
        let lastWidth = 0;
        const resize = new ResizeObserver(() => {
            if (!container.clientWidth || !container.clientHeight) return;
            canvas.resized();
            // Height changes (keyboard/browser chrome/chooser) must preserve the user's zoom.
            if (!lastWidth || Math.abs(lastWidth - container.clientWidth) > 40) fit();
            lastWidth = container.clientWidth;
        });
        resize.observe(container);
        let removeTouch;
        const updateEditorMode = () => {
            modeler.get('dragging').cancel();
            chooser.hidden = true;
            workspace.classList.remove('bpmn-help-open');
            help.setAttribute('aria-pressed', 'false');
            workspace.classList.toggle('bpmn-compact', compactView.matches);
            workspace.classList.toggle('bpmn-desktop', !compactView.matches);
            tools.setMode('select');
            if (gestureInstructions) gestureInstructions.textContent = compactView.matches ? prompts.select + ' ' + prompts.pan : desktopHint;
            removeTouch?.(); removeTouch = undefined;
            keyboard.unbind();
            if (compactView.matches) {
                actions.append(undo, redo);
                selectedActions.prepend(informationButton);
                removeTouch = window.PortalBpmnTouch(container, modeler, { getMode: tools.getMode, onTap: tools.tap });
            } else {
                desktopActions.append(undo, redo, informationButton);
                keyboard.bind(workspace);
            }
            requestAnimationFrame(() => canvas.resized());
        };
        const focusCanvas = event => {
            if (!event.target.closest('input, textarea, select, [contenteditable="true"]') && event.target.closest('.djs-container, .djs-palette')) container.focus({ preventScroll: true });
        };
        workspace.addEventListener('pointerdown', focusCanvas, true);
        compactView.addEventListener('change', updateEditorMode);
        updateEditorMode();
        modeler.get('eventBus').on('diagram.destroy', () => {
            removeTouch?.();
            compactView.removeEventListener('change', updateEditorMode);
            workspace.removeEventListener('pointerdown', focusCanvas, true);
            if (keyboard.getBinding() === workspace) keyboard.unbind();
            workspace.removeEventListener('keydown', groupKeys);
            resize.disconnect();
            if (dialog?.open) dialog.close();
            dialog?.remove();
            nameDialog.close();
            nameDialog.remove();
            instructionDialog?.close();
            instructionDialog?.remove();
        });
    }
    window.PortalMobile = { attachModeler };
})();
