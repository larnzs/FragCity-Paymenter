(function () {
    'use strict';

    // Presentation-only: never touch Paymenter values, Livewire models,
    // editable controls or payment-gateway requests.
    const excludedSelector = [
        'script', 'style', 'noscript', 'template',
        'input', 'textarea', 'select', 'option',
        'pre', 'code', '[contenteditable="true"]',
        '[data-fc-no-currency-format]'
    ].join(',');

    const markedSelector = '[data-fc-currency-value]';
    const numberPattern = /^[+-]?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d{1,2})?$/;

    function latinDigits(value) {
        return String(value)
            .replace(/[۰-۹]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
            .replace(/[٠-٩]/g, digit => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)))
            .replace(/٬/g, ',')
            .replace(/٫/g, '.');
    }

    function isRialCode(value) {
        const code = String(value || '').trim().toUpperCase();
        return code === 'IRR' || code === 'RIAL' || code === 'ریال' || code === 'ريال';
    }

    function parseRials(value, allowSuffix) {
        let amount = latinDigits(value).trim();

        if (allowSuffix) {
            amount = amount.replace(/\s*(?:ریال|ريال|IRR|RIAL)$/iu, '').trim();
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
        let previous = node.previousSibling;
        while (previous && previous.nodeType === Node.TEXT_NODE && !previous.nodeValue.trim()) {
            previous = previous.previousSibling;
        }
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
        if (!node || node.nodeType !== Node.TEXT_NODE) return;

        const parent = node.parentElement;
        if (!parent) return;

        const marked = parent.closest(markedSelector);
        if (marked) {
            formatMarkedElement(marked);
            return;
        }

        if (parent.closest(excludedSelector)) return;

        const original = node.nodeValue;
        if (!original || !/(?:ریال|ريال|\bIRR\b|\bRIAL\b)/iu.test(original)) return;

        // Paymenter sometimes renders the numeric amount and currency label
        // in adjacent inline nodes. Preserve both elements and their styles.
        if (/^\s*(?:ریال|ريال|IRR|RIAL)\s*$/iu.test(original)) {
            const numericNode = precedingNumericNode(node);
            if (numericNode) {
                const rials = parseRials(numericNode.nodeValue, false);
                if (rials !== null) {
                    numericNode.nodeValue = formatToman(rials).replace(/\s*تومان$/, '');
                    node.nodeValue = ' تومان';
                }
            }
            return;
        }

        const pattern = /(^|[^\d.,])([+-]?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d{1,2})?)\s*(ریال|ريال|IRR|RIAL)(?![A-Za-z])/giu;
        const formatted = latinDigits(original).replace(pattern, function (_match, prefix, amount) {
            const rials = parseRials(amount, false);
            return rials === null ? _match : prefix + formatToman(rials);
        });

        if (formatted !== original) {
            node.nodeValue = formatted;
        }
    }

    function scan(root) {
        if (!root || !document.body || location.pathname.replace(/\/+$/, '').startsWith('/admin')) return;

        if (root.nodeType === Node.TEXT_NODE) {
            formatTextNode(root);
            return;
        }

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
        if (!document.body || location.pathname.replace(/\/+$/, '').startsWith('/admin')) return;

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
