/*
 * Схемы в вопросах теста хранятся как BPMN XML (.bpmn), а не картинками.
 * Этот скрипт находит <img src="...bpmn">, рисует схему из XML через bpmn-js
 * и подставляет результат. Увеличение по нажатию (mobile_ui.js) работает как раньше.
 * Нужен bpmn-js (window.BpmnJS) на странице.
 */
(function () {
    const cache = new Map();
    let queue = Promise.resolve();

    function renderXmlUrl(url) {
        if (!cache.has(url)) {
            // По одной схеме за раз: каждому рендеру нужен свой скрытый контейнер.
            queue = queue.then(async () => {
                const response = await fetch(url, { credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                const holder = document.createElement('div');
                holder.style.cssText = 'position:absolute;left:-10000px;top:0;width:1000px;height:600px;';
                document.body.appendChild(holder);
                try {
                    const viewer = new window.BpmnJS({ container: holder });
                    await viewer.importXML(await response.text());
                    const { svg } = await viewer.saveSVG();
                    viewer.destroy();
                    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
                } finally {
                    holder.remove();
                }
            });
            cache.set(url, queue);
            queue = queue.catch(() => null);
        }
        return cache.get(url);
    }

    function hydrate(root) {
        if (!window.BpmnJS || !root.querySelectorAll) {
            return;
        }
        root.querySelectorAll('img[src$=".bpmn"]:not([data-bpmn-src])').forEach(img => {
            const url = img.getAttribute('src');
            img.dataset.bpmnSrc = url;
            img.removeAttribute('src');
            img.classList.add('bpmn-live-loading');
            renderXmlUrl(url).then(dataUrl => {
                img.src = dataUrl;
                img.classList.remove('bpmn-live-loading');
            }).catch(() => {
                img.classList.remove('bpmn-live-loading');
                img.alt = 'Не удалось показать схему. Обновите страницу.';
            });
        });
    }

    new MutationObserver(records => {
        for (const record of records) {
            for (const node of record.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    hydrate(node.parentElement || node);
                }
            }
        }
    }).observe(document.documentElement, { childList: true, subtree: true });
    hydrate(document);
})();
