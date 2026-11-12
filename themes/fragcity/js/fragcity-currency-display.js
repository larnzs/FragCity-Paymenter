(function () {
    'use strict';

    if (window.fragCityCurrencyDisplayReady) return;
    window.fragCityCurrencyDisplayReady = true;

    // Display-only formatting. Never modify form values, Livewire models,
    // IDs, gateway payloads or the numeric values stored by Paymenter.
    const markedSelector = '[data-fc-currency-value]';
    const excludedSelector = [
        'script', 'style', 'noscript', 'template',
        'input', 'textarea', 'pre', 'code',
        '[contenteditable="true"]',
        '[data-fc-no-currency-format]'
    ].join(',');

    const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
    const rialSuffix = /^(?:ریال|ريال|IRR|RIAL)$/iu;
    const tomanSuffix = /^تومان$/u;
    const amountPattern = /^[+-]?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d{1,2})?$/;
    const inlineRialPattern = /([+-]?(?:[\d٠-٩۰-۹]{1,3}(?:[,٬][\d٠-٩۰-۹]{3})+|[\d٠-٩۰-۹]+)(?:[.٫][\d٠-٩۰-۹]{1,2})?)\s*(ریال|ريال|IRR|RIAL)(?![A-Za-z])/giu;
    const inlineTomanPattern = /([+-]?(?:[\d٠-٩۰-۹]{1,3}(?:[,٬][\d٠-٩۰-۹]{3})+|[\d٠-٩۰-۹]+)(?:[.٫][\d٠-٩۰-۹]{1,2})?)\s*تومان/gu;
    const numericTokenPattern = /(^|[^\p{L}\p{N}_])([+-]?(?:[\d٠-٩۰-۹]{1,3}(?:[,٬][\d٠-٩-۹]{3})+|[\d٠-٩۰-۹]+)(?:[.٫][\d٠-٩۰-۹]+)?)(?=$|[^\p{L}\p{N}_])/gu;

    const containerStates = new WeakMap();
    const nodeStates = new WeakMap();
    const markedElementStates = new WeakMap();

    function normalizeDigits(value) {
        return String(value)
            .replace(/[۰-۹]/g, digit => String(digit.charCodeAt(0) - 0x06f0))
            .replace(/[٠-٩]/g, digit => String(digit.charCodeAt(0) - 0x0660))
            .replace(/٬/g, ',')
            .replace(/٫/g, '.');
    }

    function toPersianDigits(value) {
        return String(value).replace(/[0-9]/g, digit => persianDigits[Number(digit)]);
    }

    function parseAmount(value) {
        let amount = normalizeDigits(value).trim();
        amount = amount.replace(/\s*(?:ریال|ريال|IRR|RIAL|تومان)$/iu, '').trim();

        if (!amountPattern.test(amount)) return null;

        const numeric = Number(amount.replace(/,/g, ''));
        return Number.isFinite(numeric) ? numeric : null;
    }

    function formatTomanAmount(rials) {
        const toman = Math.round(rials / 10);
        const grouped = new Intl.NumberFormat('en-US', {
            maximumFractionDigits: 0,
            minimumFractionDigits: 0
        }).format(toman);

        return toPersianDigits(grouped);
    }

    function formatToman(rials) {
        return formatTomanAmount(rials) + ' تومان';
    }

    function isRialCode(value) {
        const code = String(value || '').trim().toUpperCase();
        return code === 'IRR' || code === 'RIAL' || code === 'ریال' || code === 'ريال';
    }

    function setRtl(element) {
        if (!element || element.nodeType !== Node.ELEMENT_NODE) return;
        element.setAttribute('dir', 'rtl');
        element.style.direction = 'rtl';
        element.style.unicodeBidi = 'isolate';
    }

    function getTextNodes(root) {
        if (!root || root.nodeType !== Node.ELEMENT_NODE) return [];
        const nodes = [];
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) nodes.push(node);
        return nodes;
    }

    function getState(container) {
        if (!container || container.nodeType !== Node.ELEMENT_NODE) return null;

        let state = containerStates.get(container);
        if (!state) {
            state = {
                container,
                lastPairAmount: null,
                inlineDisplays: new WeakMap()
            };
            containerStates.set(container, state);
        }

        container.setAttribute('data-fc-rial-price', '1');
        return state;
    }

    function findState(node) {
        if (!node) return null;

        const direct = nodeStates.get(node);
        if (direct) return direct;

        let element = node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement;
        while (element) {
            const state = containerStates.get(element);
            if (state) return state;

            if (element.matches && element.matches('[data-fc-rial-price]')) {
                return getState(element);
            }

            element = element.parentElement;
        }

        return null;
    }

    function previousNumericTextNode(nodes, suffixIndex) {
        for (let index = suffixIndex - 1; index >= 0; index--) {
            const candidate = nodes[index];
            const value = candidate.nodeValue.trim();

            if (!value) continue;
            if (parseAmount(value) !== null) return candidate;

            // Do not skip a label or unrelated text in search of an older number.
            return null;
        }

        return null;
    }

    function formatSeparatePair(state, nodes, suffixIndex) {
        const suffixNode = nodes[suffixIndex];
        const suffix = normalizeDigits(suffixNode.nodeValue.trim());
        if (!rialSuffix.test(suffix) && !tomanSuffix.test(suffix)) return false;

        const amountNode = previousNumericTextNode(nodes, suffixIndex);
        if (!amountNode) return false;

        const currentAmount = amountNode.nodeValue.trim();
        const alreadyDisplayed = state.lastPairAmount === currentAmount;

        if (rialSuffix.test(suffix) || !alreadyDisplayed) {
            const rials = parseAmount(currentAmount);
            if (rials === null) return false;

            const displayAmount = formatTomanAmount(rials);
            amountNode.nodeValue = displayAmount;
            state.lastPairAmount = displayAmount;
        }

        suffixNode.nodeValue = ' تومان';
        nodeStates.set(amountNode, state);
        nodeStates.set(suffixNode, state);
        setRtl(state.container);
        return true;
    }

    function formatInlinePriceNode(state, node) {
        const original = node.nodeValue;
        if (!original) return false;

        const normalized = normalizeDigits(original);
        const rialMatch = normalized.match(inlineRialPattern);

        if (rialMatch) {
            const formatted = normalized.replace(inlineRialPattern, function (_match, amount) {
                const rials = parseAmount(amount);
                return rials === null ? _match : formatToman(rials);
            });

            if (formatted !== original) node.nodeValue = formatted;
            state.inlineDisplays.set(node, node.nodeValue);
            nodeStates.set(node, state);
            setRtl(state.container);
            return true;
        }

        const tomanMatch = normalized.match(inlineTomanPattern);
        if (!tomanMatch) return false;

        const previousDisplay = state.inlineDisplays.get(node);
        if (previousDisplay === node.nodeValue) {
            nodeStates.set(node, state);
            return true;
        }

        // If Livewire changes only the amount and leaves the Toman suffix
        // in place, interpret the fresh value as Paymenter's Rial output.
        const formatted = normalized.replace(inlineTomanPattern, function (_match, amount) {
            const rials = parseAmount(amount);
            return rials === null ? _match : formatToman(rials);
        });

        if (formatted !== node.nodeValue) node.nodeValue = formatted;
        state.inlineDisplays.set(node, node.nodeValue);
        nodeStates.set(node, state);
        setRtl(state.container);
        return true;
    }

    function formatPriceContainer(state) {
        if (!state || !state.container || !state.container.isConnected) return;

        const nodes = getTextNodes(state.container);
        if (!nodes.length) return;

        // Common case: amount and currency are part of one text node.
        for (const node of nodes) {
            const normalized = normalizeDigits(node.nodeValue);
            if (/(?:ریال|ريال|\bIRR\b|\bRIAL\b)/iu.test(normalized)) {
                formatInlinePriceNode(state, node);
                return;
            }
        }

        // Paymenter can render the amount and currency label as separate nodes.
        for (let index = 0; index < nodes.length; index++) {
            const suffix = normalizeDigits(nodes[index].nodeValue.trim());
            if (rialSuffix.test(suffix) || tomanSuffix.test(suffix)) {
                if (formatSeparatePair(state, nodes, index)) return;
            }
        }

        // A Livewire morph may replace the amount text while keeping Toman.
        for (const node of nodes) {
            if (formatInlinePriceNode(state, node)) return;
        }
    }

    function markInlinePrice(node) {
        const parent = node.parentElement;
        if (!parent) return false;

        const state = getState(parent);
        formatPriceContainer(state);
        nodeStates.set(node, state);
        return true;
    }

    function markSeparatePrice(suffixNode) {
        let candidate = suffixNode.parentElement;

        for (let depth = 0; candidate && depth < 6; depth++, candidate = candidate.parentElement) {
            const nodes = getTextNodes(candidate);
            const suffixIndex = nodes.indexOf(suffixNode);
            if (suffixIndex < 0) continue;

            const amountNode = previousNumericTextNode(nodes, suffixIndex);
            if (!amountNode) continue;

            let container = amountNode.parentElement;
            while (container && !container.contains(suffixNode)) {
                container = container.parentElement;
            }
            if (!container) continue;

            const state = getState(container);
            nodeStates.set(amountNode, state);
            nodeStates.set(suffixNode, state);
            formatPriceContainer(state);
            return true;
        }

        return false;
    }

    function formatMarkedElement(element) {
        if (!element || !isRialCode(element.getAttribute('data-fc-currency-code'))) return;

        let state = markedElementStates.get(element);
        if (!state) {
            state = { lastDisplay: null };
            markedElementStates.set(element, state);
        }

        const current = element.textContent.trim();
        if (!current || current === state.lastDisplay) return;

        const rials = parseAmount(current);
        if (rials === null) return;

        const formatted = formatToman(rials);
        if (current !== formatted) element.textContent = formatted;

        state.lastDisplay = formatted;
        element.setAttribute('data-fc-last-display', formatted);
        setRtl(element);
    }

    function shouldSkipTextNode(node) {
        const parent = node && node.parentElement;
        if (!parent) return true;
        return Boolean(parent.closest(excludedSelector));
    }

    function localizeNumberTokens(node) {
        const original = node.nodeValue;
        if (!original || !/[0-9٠-٩]/.test(original)) return;

        const localized = original.replace(numericTokenPattern, function (match, prefix, token) {
            const digitCount = (token.match(/[0-9٠-٩۰-۹]/g) || []).length;
            // Very long digit runs are generally identifiers, not quantities.
            if (digitCount > 12) return match;
            return prefix + toPersianDigits(token);
        });

        if (localized !== original) node.nodeValue = localized;
    }

    function formatTextNode(node) {
        if (!node || node.nodeType !== Node.TEXT_NODE || shouldSkipTextNode(node)) return;

        const markedElement = node.parentElement.closest(markedSelector);
        if (markedElement) {
            formatMarkedElement(markedElement);
            return;
        }

        const state = findState(node);
        if (state) {
            formatPriceContainer(state);
            return;
        }

        const normalized = normalizeDigits(node.nodeValue);
        if (/(?:ریال|ريال|\bIRR\b|\bRIAL\b)/iu.test(normalized)) {
            markInlinePrice(node);
            if (findState(node)) return;
        }

        const suffix = normalized.trim();
        if (rialSuffix.test(suffix) && markSeparatePrice(node)) return;

        localizeNumberTokens(node);
    }

    function scan(root) {
        if (!root || !document.body || location.pathname.replace(/\/+$/, '').startsWith('/admin')) return;

        if (root.nodeType === Node.TEXT_NODE) {
            formatTextNode(root);
            return;
        }

        if (root.nodeType !== Node.ELEMENT_NODE && root.nodeType !== Node.DOCUMENT_NODE) return;

        if (root.matches && root.matches(markedSelector)) formatMarkedElement(root);
        if (root.querySelectorAll) {
            root.querySelectorAll(markedSelector).forEach(formatMarkedElement);
        }

        const rootState = findState(root);
        if (rootState) formatPriceContainer(rootState);

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) {
            formatTextNode(node);
        }
    }

    let scheduled = false;
    const pendingRoots = new Set();

    function scheduleScan(root) {
        if (!root) return;

        if (root.nodeType === Node.TEXT_NODE) root = root.parentElement;
        if (!root) return;

        pendingRoots.add(root);
        if (scheduled) return;

        scheduled = true;
        window.requestAnimationFrame(function () {
            scheduled = false;
            const roots = Array.from(pendingRoots);
            pendingRoots.clear();
            roots.forEach(scan);
        });
    }

    let livewireHooksAttached = false;

    function attachLivewireHooks() {
        const livewire = window.Livewire;
        if (!livewire || typeof livewire.hook !== 'function' || livewireHooksAttached) return;

        livewireHooksAttached = true;
        livewire.hook('morph.updated', ({ el }) => scheduleScan(el));
        livewire.hook('morph.added', ({ el }) => scheduleScan(el));
        livewire.hook('morphed', ({ el }) => scheduleScan(el));
    }

    function start() {
        if (!document.body || location.pathname.replace(/\/+$/, '').startsWith('/admin')) return;

        scan(document.body);

        const observer = new MutationObserver(function (records) {
            for (const record of records) {
                const state = findState(record.target);
                if (state) formatPriceContainer(state);

                if (record.type === 'characterData') {
                    scheduleScan(record.target);
                } else {
                    scheduleScan(record.target);
                    record.addedNodes.forEach(function (node) {
                        if (node.nodeType === Node.ELEMENT_NODE || node.nodeType === Node.TEXT_NODE) {
                            scheduleScan(node);
                        }
                    });
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            characterData: true,
            subtree: true
        });

        document.addEventListener('livewire:navigated', function () {
            scan(document.body);
            attachLivewireHooks();
        });

        attachLivewireHooks();
    }

    document.addEventListener('livewire:init', attachLivewireHooks);
    document.addEventListener('livewire:initialized', attachLivewireHooks);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
