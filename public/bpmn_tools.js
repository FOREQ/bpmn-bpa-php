/* Tap-first editing, using BPMN rules and undoable modeling commands. */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory;
    else root.PortalBpmnTools = factory;
})(typeof window === 'undefined' ? globalThis : window, function (modeler, notify) {
    'use strict';
    notify = notify || (() => {});
    const canvas = modeler.get('canvas');
    const modeling = modeler.get('modeling');
    const rules = modeler.get('rules');
    const selection = modeler.get('selection');
    let mode = 'select';
    let type;
    let source;
    const setMode = value => { mode = value; source = null; notify(value); };
    const validSource = element => element?.parent && !element.waypoints && !element.labelTarget;
    const participant = element => {
        while (element && element.type !== 'bpmn:Participant') element = element.parent;
        return element;
    };
    const invalidConnection = (from, to) => {
        const a = participant(from), b = participant(to);
        if (a && b && a !== b) {
            const isMessage = item => item.businessObject?.eventDefinitions?.some(def => def.$type === 'bpmn:MessageEventDefinition');
            if (isMessage(to) && ['bpmn:IntermediateThrowEvent', 'bpmn:EndEvent'].includes(to.type)) return 'invalidMessageTarget';
            if (isMessage(from) && ['bpmn:IntermediateCatchEvent', 'bpmn:StartEvent', 'bpmn:BoundaryEvent'].includes(from.type)) return 'invalidMessageSource';
            return 'invalidMessageConnection';
        }
        return 'invalidConnection';
    };
    return {
        getMode: () => mode,
        setMode,
        place(value) { type = typeof value === 'string' ? { type: value } : value; setMode('place'); if (type.type === 'bpmn:BoundaryEvent') notify('attach'); },
        connect() {
            setMode('connect');
            const selected = selection.get()[0]?.labelTarget || selection.get()[0];
            if (validSource(selected)) { source = selected; notify('target'); }
        },
        tap(element, position) {
            element = element?.labelTarget || element;
            if (mode === 'multi') {
                if (validSource(element) && !['bpmn:Participant', 'bpmn:Lane'].includes(element.type)) {
                    const selected = selection.get();
                    selection.select(selected.includes(element) ? selected.filter(item => item !== element) : [...selected, element]);
                }
                notify('multi');
                return;
            }
            if (mode === 'place') {
                const factory = modeler.get('elementFactory');
                const attrs = {
                    ...type,
                    ...(['bpmn:SubProcess', 'bpmn:Transaction'].includes(type.type) ? { isExpanded: type.isExpanded !== false } : {})
                };
                // An expanded participant needs its own Process. createShape alone
                // produces a black-box participant after the first pool is added.
                const shape = type.type === 'bpmn:Participant'
                    ? factory.createParticipantShape({ ...attrs, isExpanded: type.isExpanded !== false })
                    : factory.createShape(attrs);
                if (type.type === 'bpmn:BoundaryEvent') {
                    const host = element?.labelTarget || element;
                    if (!host?.parent || !/Task$|SubProcess$|CallActivity$|Transaction$/.test(host.type)) { notify('invalidPlace'); return; }
                    const edges = [
                        { x: Math.max(host.x, Math.min(host.x + host.width, position.x)), y: host.y },
                        { x: Math.max(host.x, Math.min(host.x + host.width, position.x)), y: host.y + host.height },
                        { x: host.x, y: Math.max(host.y, Math.min(host.y + host.height, position.y)) },
                        { x: host.x + host.width, y: Math.max(host.y, Math.min(host.y + host.height, position.y)) }
                    ];
                    position = edges.sort((a, b) => Math.hypot(a.x - position.x, a.y - position.y) - Math.hypot(b.x - position.x, b.y - position.y))[0];
                    if (!rules.allowed('shape.attach', { shape, target: host, position })) { notify('invalidPlace'); return; }
                    const created = modeling.createShape(shape, position, host, { attach: true });
                    setMode('select'); selection.select(created); notify('created'); return created;
                }
                let parent = element || canvas.getRootElement();
                while (parent.parent && !['bpmn:Participant', 'bpmn:Lane', 'bpmn:SubProcess', 'bpmn:Transaction', 'bpmn:AdHocSubProcess'].includes(parent.type)) parent = parent.parent;
                if (type.type === 'bpmn:Participant') parent = canvas.getRootElement();
                if (!rules.allowed('shape.create', { shape, target: parent, position })) { notify('invalidPlace'); return; }
                const created = modeling.createShape(shape, position, parent);
                setMode('select');
                selection.select(created);
                notify('created');
                return created;
            }
            if (mode === 'connect') {
                if (!validSource(element)) { notify('invalidTarget'); return; }
                if (!source) { source = element; selection.select(source); notify('target'); return; }
                // A selected source may have been deleted or undone while choosing a target.
                if (modeler.get('elementRegistry').get(source.id) !== source) { source = null; notify('connect'); return; }
                let from = source, to = element;
                let attrs = rules.allowed('connection.create', { source: from, target: to });
                // diagram-js Connect tries the opposite direction only after an
                // explicit refusal. Keep the same behavior for two-tap drawing.
                let reversed = false;
                if (attrs === false && source !== element) {
                    const reverseAttrs = rules.allowed('connection.create', { source: element, target: source });
                    if (reverseAttrs) { attrs = reverseAttrs; from = element; to = source; reversed = true; }
                }
                if (!attrs || source === element) { notify(invalidConnection(source, element)); return; }
                const connection = modeling.connect(from, to, typeof attrs === 'object' ? attrs : undefined);
                setMode('select');
                selection.select(connection);
                notify(reversed ? 'connectedReversed' : 'connected');
                return connection;
            }
            if (mode !== 'pan') selection.select(element?.parent ? element : null);
        }
    };
});
