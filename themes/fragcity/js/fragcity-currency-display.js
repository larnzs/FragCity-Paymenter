(function () {
    'use strict';

    // This is a presentation-only formatter. Paymenter values, Livewire models,
    // form inputs and gateway requests are never changed.
    const excludedSelector = [
        'script', 'style', 'noscript', 'template',
        'input', 'textarea', 'select', 'option',
        'pre', 'code', '[contenteditable="true"]',
        '[data-fc-no-currency-format]'
    ].join(',');

    const markedSelector = '[data-fc-currency-value]';
    const numberPattern = /^[+-]?(?:\\d{1,3}(?:,\\d{3})+|\\d+)(?:\\.\\d{1,2})?$/;

    function latinDigits(value) {
        return String(value)
            .replace(/[۰-۹]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
            .replace(/[٠-٩]/g, digit => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)))
            .replace(/٬/g, ',')
            .replace(/٫/g, '.');
    }

    function isRialCode(value) {
        const code = String(value || '').trim().toUpperCase();
        return code === 'IRR' || code === 'RIAL' || code === 'ریال';
    }

    function parseRials(value, allowSuffix) {
        let amount = latinDigits(value).trim();

        if (allowSuffix) {
            amount = amount.replace(/\\s*(?:ریال|IRR|RIAL)$/iu, '').trim();
        }

        if (!numberPattern.test(amount)) return null;

        const numeric = Number(amount.replace(/,/g, ''));
        return Number.isFinite(numeric) ? numeric : null;
    }

    function formatToman(rials) {
        const toman = Math.round(rials / 10);
        return new Intl.NumberFormat('en-US', {
            maximumFractionDigits: 0,
            minimumFractionDigits: 0
        }).format(toman) + ' تومان';
    }

    function isExcluded(node) {
        const parent = node && (node.nodeType === Node.ELEMENT_NODE
            ? node
            : node.parentElement);

        return !parent || Boolean(parent.closest(
            excludedSelector + ', ' + markedSelector
        ));
    }

    function formatMarkedElement(element) {
        if (!element || !isRialCode(element.getAttribute('data-fc-currency-code'))) return;

        const rials = parseRials(element.textContent, true);
        if (rials === null) return;

        const formatted = formatToman(rials);
        if (element.textContent.trim() !== formatted) {
            element.textContent = formatted;
        }
    }

    function precedingNumericNode(node) {
        const previous = node.previousSibling;
        if (!previous) return null;

        if (previous.nodeType === Node.TEXT_NODE) {
            return numberPattern.test(latinDigits(previous.nodeValue.trim())) ? previous : null;
        }

        if (previous.nodeType === Node.ELEMENT_NODE &&
            previous.childNodes.length === 1 &&
            previous.firstChild.nodeType === Node.TEXT_NODE &&
            numberPattern.test(latinDigits(previous.textContent.trim()))) {
            return previous.firstChild;
        }

        return null;
    }

    function formatTextNode(node) {
        if (!node || node.nodeType !== Node.TEXT_NODE || isExcluded(node)) return;

        const parent = node.parentElement;
        const marked = parent && parent.closest(markedSelector);
        if (marked) {
            formatMarkedElement(marked);
            return;
        }

        const original = node.nodeValue;
        if (!original || !/(?:ریال|\\bIRR\\b|\\bRIAL\\b)/iu.test(original)) return;

        // Paymenter may render the amount and its currency suffix as adjacent
        // inline nodes. Handle that without replacing or rebuilding their markup.
        if (/^\\s*(?:ریال|IRR|RIAL)\\s*$/iu.test(original)) {
            const numericNode = precedingNumericNode(node);
            if (numericNode) {
                const rials = parseRials(numericNode.nodeValue, false);
                if (rials !== null) {
                    numericNode.nodeValue = formatToman(rials).replace(/\\s*تومان$/, '');
                    node.nodeValue = ' تومان';
                }
            }
            return;
        }

        const pattern = /(^|[^\\d.,])([+-]?(?:\\d{1,3}(?:,\\d{3})+|\\d+)(?:\\.\\d{1,2})?)\\s*(ریال|IRR|RIAL)(?![A-Za-z])/giu;
        const formatted = latinDigits(original).replace(pattern, function (_match, prefix, amount) {
            const rials = parseRials(amount, false);
            return rials === null ? _match : prefix + formatToman(rials);
        });

        if (formatted !== original) {
            node.nodeValue = formatted;
        }
    }

    function scan(root) {
        if (!root || !document.body || location.pathname.replace(/\\/+$/, '').startsWith('/admin')) return;

        if (root.nodeType === Node.ELEMENT_NODE) {
            if (root.matches(markedSelector)) formatMarkedElement(root);
            root.querySelectorAll(markedSelector).forEach(formatMarkedElement);
        }

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) {
            formatTextNode(node);
        }
    }

    function start() {
        if (!document.body || location.pathname.replace(/\\/+$/, '').startsWith('/admin')) return;

        scan(document.body);

        const observer = new MutationObserver(function (records) {
            for (const record of records) {
                if (record.type === 'characterData') {
                    formatTextNode(record.target);
                } else {
                    record.addedNodes.forEach(function (node) {
                        if (node.nodeType === Node.ELEMENT_NODE || node.nodeType === Node.TEXT_NODE) {
                            scan(node);
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
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
