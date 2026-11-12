(function () {
    'use strict';

    if (window.fragCityAuthModalReady) return;
    window.fragCityAuthModalReady = true;

    let returnFocus = null;

    function modal() {
        return document.getElementById('fcLoginModal');
    }

    function setTriggers(open) {
        document.querySelectorAll('[data-auth-modal-open]').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function openModal(trigger) {
        const root = modal();
        if (!root || !root.hidden) return;
        returnFocus = trigger || document.activeElement;
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        document.body.classList.add('fc-auth-modal-open');
        setTriggers(true);

        const dialog = root.querySelector('.fc-auth-modal__dialog');
        if (dialog) dialog.focus();

        window.setTimeout(function () {
            const input = root.querySelector('input:not([type="hidden"]):not([type="checkbox"])');
            if (input) input.focus({ preventScroll: true });
        }, 80);
    }

    function closeModal(restoreFocus) {
        const root = modal();
        if (!root || root.hidden) return;
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('fc-auth-modal-open');
        setTriggers(false);
        if (restoreFocus !== false && returnFocus instanceof HTMLElement && returnFocus.isConnected) {
            returnFocus.focus({ preventScroll: true });
        }
        returnFocus = null;
    }

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) return;
        const trigger = event.target.closest('[data-auth-modal-open]');
        if (trigger) {
            const root = modal();
            if (root) {
                event.preventDefault();
                openModal(trigger);
            }
            return;
        }

        if (event.target.closest('[data-auth-modal-close]')) {
            event.preventDefault();
            closeModal(true);
        }
    });

    document.addEventListener('keydown', function (event) {
        const root = modal();
        if (!root || root.hidden) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal(true);
            return;
        }

        if (event.key !== 'Tab') return;
        const focusable = Array.from(root.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (element) {
            return element.getClientRects().length > 0;
        });
        if (!focusable.length) {
            event.preventDefault();
            root.querySelector('.fc-auth-modal__dialog')?.focus();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    document.addEventListener('livewire:navigated', function () {
        closeModal(false);
    });
})();