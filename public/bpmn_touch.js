/* Bridge touch PointerEvents to diagram-js public interaction services. */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory;
    else root.PortalBpmnTouch = factory;
})(typeof window === 'undefined' ? globalThis : window, function bindBpmnTouch(container, modeler, options) {
    'use strict';
    options = options || {};
    const canvas = modeler.get('canvas');
    const dragging = modeler.get('dragging');
    const selection = modeler.get('selection');
    const registry = modeler.get('elementRegistry');
    const touches = new Map();
    let gesture;
    let hover;
    function resizeGrip(event) {
        const direct = event.target.closest('.djs-resizer');
        if (direct) return direct;
        const selected = selection.get?.()[0];
        if (!selected) return;
        const element = event.target.closest('.djs-element[data-element-id]');
        if (element && element.getAttribute('data-element-id') !== selected.id) return;
        // Handles stay easy to acquire at low zoom; never steal a direct tap
        // on another figure merely because it is near a selected pool's edge.
        return [...(container.querySelectorAll?.('.djs-resizer') || [])]
            .map(node => {
                const rect = (node.querySelector('.djs-resizer-hit') || node).getBoundingClientRect();
                return { node, distance: rect.width && rect.height ? Math.hypot(Math.max(rect.left - event.clientX, 0, event.clientX - rect.right), Math.max(rect.top - event.clientY, 0, event.clientY - rect.bottom)) : Infinity };
            }).filter(item => item.distance <= 12).sort((a, b) => a.distance - b.distance)[0]?.node;
    }
    function hitElement(event) {
        const hit = container.ownerDocument.elementFromPoint(event.clientX, event.clientY) || event.target;
        const gfx = hit.closest('.djs-element[data-element-id]');
        const element = gfx && registry.get(gfx.getAttribute('data-element-id'));
        const normalized = options.getMode?.() === 'connect' ? element?.labelTarget || element : element;
        if ((element?.parent && !['bpmn:Participant', 'bpmn:Lane', 'bpmn:SubProcess'].includes(element.type)) || !options.onTap) return normalized;
        // Small events and gateways get a finger-sized hit area, in screen pixels.
        const bounds = container.getBoundingClientRect();
        const box = canvas.viewbox();
        const x = box.x + (event.clientX - bounds.left) / box.scale;
        const y = box.y + (event.clientY - bounds.top) / box.scale;
        return registry.getAll().filter(item => item.parent && !item.waypoints && !item.labelTarget && !['bpmn:Participant', 'bpmn:Lane', 'bpmn:SubProcess'].includes(item.type))
            .map(item => ({ item, distance: Math.hypot(Math.max(item.x - x, 0, x - item.x - item.width), Math.max(item.y - y, 0, y - item.y - item.height)) * box.scale }))
            .filter(hit => hit.distance <= 12).sort((a, b) => a.distance - b.distance || a.item.width * a.item.height - b.item.width * b.item.height)[0]?.item || normalized;
    }
    function updateHover(event) {
        const hit = container.ownerDocument.elementFromPoint(event.clientX, event.clientY);
        if (!hit || !container.contains(hit) || hit.closest('.djs-palette, .djs-context-pad')) {
            if (hover) dragging.out();
            hover = null;
            return;
        }
        const gfx = hit.closest('.djs-element[data-element-id]');
        const element = gfx ? registry.get(gfx.getAttribute('data-element-id')) : canvas.getRootElement();
        if (element && element !== hover) {
            dragging.hover({ element, gfx: canvas.getGraphics(element) });
            hover = element;
        }
    }
    const point = event => ({ x: event.clientX, y: event.clientY });
    const midpoint = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });
    const distance = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
    const startPinch = () => {
        const [a, b] = [...touches.values()];
        dragging.cancel();
        hover = null;
        gesture = { type: 'pinch', distance: Math.max(1, distance(a, b)), zoom: canvas.zoom(), last: midpoint(a, b) };
    };
    function down(event) {
        const mode = options.getMode?.() || 'select';
        if ((event.pointerType !== 'touch' && mode === 'select') || event.target.closest('input, textarea, [contenteditable="true"]')) return;
        if (options.onTap) container.focus?.({ preventScroll: true });
        event.preventDefault();
        if (mode !== 'select') event.stopImmediatePropagation();
        touches.set(event.pointerId, point(event));
        container.setPointerCapture(event.pointerId);
        if (touches.size >= 2) { startPinch(); return; }
        hover = null;
        const resizer = ['select', 'pan'].includes(mode) && resizeGrip(event);
        if (resizer) {
            const direction = [...resizer.classList].map(value => /^djs-resizer-(n|e|s|w|nw|ne|se|sw)$/.exec(value)?.[1]).find(Boolean);
            const element = selection.get()[0];
            if (direction && element) {
                modeler.get('resize').activate(event, element, direction);
                gesture = { type: 'drag', resizing: true };
                return;
            }
        }
        if (options.onTap && ['place', 'connect', 'pan'].includes(mode)) {
            gesture = { type: 'tap', mode, origin: point(event), last: point(event) };
            return;
        }
        if (mode === 'space') {
            modeler.get('spaceTool').activateMakeSpace(event);
            updateHover(event);
            gesture = { type: 'drag', space: true };
            return;
        }
        if (mode === 'multi') {
            const element = hitElement(event);
            if (element?.parent && !element.waypoints && !['bpmn:Participant', 'bpmn:Lane'].includes(element.type)) {
                gesture = { type: selection.get().includes(element?.labelTarget || element) ? 'pendingMove' : 'tap', mode, origin: point(event), event, element: element?.labelTarget || element };
            } else gesture = { type: 'pendingLasso', origin: point(event), event };
            return;
        }
        const entry = event.target.closest('.entry[data-action]');
        if (entry?.closest('.djs-palette')) {
            modeler.get('palette').triggerEntry(entry.dataset.action, 'click', event, true);
            gesture = { type: 'drag' };
            return;
        }
        if (entry?.closest('.djs-context-pad')) {
            modeler.get('contextPad').triggerEntry(entry.dataset.action, 'click', event, true);
            gesture = { type: 'drag' };
            return;
        }
        const element = options.onTap ? hitElement(event) : registry.get(event.target.closest('.djs-element[data-element-id]')?.getAttribute('data-element-id'));
        if (element?.parent && !element.waypoints) {
            if (!selection.get().includes(element)) selection.select(element);
            if (options.onTap) gesture = { type: 'pendingMove', origin: point(event), event, element };
            else { modeler.get('move').start(event, element); gesture = { type: 'drag' }; }
        } else {
            selection.select(element?.waypoints ? element : null);
            gesture = { type: 'pan', last: point(event) };
        }
    }
    function move(event) {
        if (!touches.has(event.pointerId)) return;
        event.preventDefault();
        touches.set(event.pointerId, point(event));
        if (gesture?.type === 'tap' && distance(gesture.origin, point(event)) >= 8) gesture = { type: 'pan', last: gesture.origin };
        if (gesture?.type === 'pendingLasso' && distance(gesture.origin, point(event)) >= 8) {
            modeler.get('lassoTool').activateLasso(gesture.event, true);
            gesture = { type: 'drag', lasso: true };
        }
        if (gesture?.type === 'pendingMove' && distance(gesture.origin, point(event)) >= 8) {
            modeler.get('move').start(gesture.event, gesture.element);
            gesture = { type: 'drag' };
        }
        if (gesture?.type === 'pinch' && touches.size >= 2) {
            const [a, b] = [...touches.values()];
            const middle = midpoint(a, b);
            const bounds = container.getBoundingClientRect();
            canvas.zoom(Math.max(0.2, Math.min(4, gesture.zoom * distance(a, b) / gesture.distance)), {
                x: gesture.last.x - bounds.left, y: gesture.last.y - bounds.top
            });
            canvas.scroll({ dx: middle.x - gesture.last.x, dy: middle.y - gesture.last.y });
            gesture.last = middle;
        } else if (gesture?.type === 'drag') {
            // PointerEvent extends MouseEvent: diagram-js also resolves hover/drop targets.
            if (dragging.context()) {
                dragging.move(event);
                if (dragging.context()?.active) {
                    if (!gesture.resizing) { updateHover(event); dragging.move(event); }
                }
            }
        } else if (gesture?.type === 'pan') {
            const current = point(event);
            canvas.scroll({ dx: current.x - gesture.last.x, dy: current.y - gesture.last.y });
            gesture.last = current;
        }
    }
    function end(event) {
        if (!touches.has(event.pointerId)) return;
        event.preventDefault();
        if (event.type === 'pointerup' && gesture?.type === 'tap' && gesture.mode !== 'pan') {
            const bounds = container.getBoundingClientRect();
            const box = canvas.viewbox();
            options.onTap(hitElement(event), { x: box.x + (event.clientX - bounds.left) / box.scale, y: box.y + (event.clientY - bounds.top) / box.scale });
        }
        if (event.type === 'pointerup' && gesture?.type === 'pendingMove' && gesture.mode === 'multi') options.onTap(gesture.element);
        if (dragging.context()) {
            if (event.type === 'pointercancel' || event.type === 'lostpointercapture') dragging.cancel();
            else dragging.end(event);
            if (gesture?.lasso && event.type === 'pointerup') {
                // A frame surrounding a pool should select its contents, so
                // the subsequent drag moves the group inside the pool.
                selection.select(selection.get().filter(item => !['bpmn:Participant', 'bpmn:Lane'].includes(item.type)));
            }
            // The native space tool reactivates itself after completion. This
            // bridge starts a fresh gesture on each pointerdown instead.
            if (gesture?.space && dragging.context()) dragging.cancel();
        }
        touches.delete(event.pointerId);
        hover = null;
        if (container.hasPointerCapture(event.pointerId)) container.releasePointerCapture(event.pointerId);
        // After one pinch finger lifts, continue panning with the remaining finger.
        if (touches.size >= 2) startPinch();
        else gesture = touches.size ? { type: 'pan', last: [...touches.values()][0] } : null;
    }
    const listeners = { pointerdown: down, pointermove: move, pointerup: end, pointercancel: end, lostpointercapture: end };
    Object.entries(listeners).forEach(([name, handler]) => container.addEventListener(name, handler, { passive: false, capture: true }));
    const blockNative = event => {
        if (options.getMode && options.getMode() !== 'select' && !event.target.closest('input, textarea, [contenteditable="true"]')) { event.preventDefault(); event.stopImmediatePropagation(); }
    };
    container.addEventListener('mousedown', blockNative, true);
    const blockTouchStart = event => {
        if (!event.target.closest('input, textarea, [contenteditable="true"]')) { event.preventDefault(); event.stopImmediatePropagation(); }
    };
    container.addEventListener('touchstart', blockTouchStart, { passive: false, capture: true });
    const touchCreate = event => {
        if (event.originalEvent?.pointerType === 'touch') event.isTouch = true;
    };
    modeler.get('eventBus').on('create.end', 2000, touchCreate);
    return () => {
        Object.entries(listeners).forEach(([name, handler]) => container.removeEventListener(name, handler, true));
        container.removeEventListener('mousedown', blockNative, true);
        container.removeEventListener('touchstart', blockTouchStart, true);
        modeler.get('eventBus').off('create.end', touchCreate);
        if (touches.size) dragging.cancel();
        touches.forEach((_, id) => {
            if (container.hasPointerCapture(id)) container.releasePointerCapture(id);
        });
        touches.clear();
    };
});
