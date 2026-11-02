(function () {
    'use strict';

    const root = document.documentElement;
    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

    let noAnimation = root.classList.contains('fc-no-animation');
    let intersectionObserver = null;
    let mutationObserver = null;

    const revealSelector = '[data-fc-reveal]';
    const interactiveSelector = '.fc-card--interactive, [data-fc-interactive]';

    function disabled() {
        return noAnimation || motionQuery.matches;
    }

    function showImmediately(element) {
        element.classList.add('is-visible');
        element.style.removeProperty('--fc-reveal-delay');
        delete element.dataset.fcMotionReady;
    }

    function getObserver() {
        if (intersectionObserver) return intersectionObserver;

        if (!('IntersectionObserver' in window)) return null;

        intersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                entry.target.classList.add('is-visible');
                intersectionObserver.unobserve(entry.target);
            });
        }, {
            threshold: 0.08,
            rootMargin: '0px 0px -36px 0px'
        });

        return intersectionObserver;
    }

    function preparePageBlocks() {
        const main = document.querySelector('main');
        if (!main) return;

        Array.from(main.children).forEach((element, index) => {
            if (!(element instanceof Element)) return;
            if (element.hasAttribute('data-fc-no-motion')) return;
            if (element.tagName === 'SCRIPT' || element.tagName === 'STYLE') return;

            if (!element.hasAttribute('data-fc-reveal')) {
                element.setAttribute('data-fc-reveal', 'up');
                element.setAttribute(
                    'data-fc-reveal-delay',
                    String((index % 5) * 42)
                );
            }
        });
    }

    function prepareReveal(element) {
        if (disabled()) {
            showImmediately(element);
            return;
        }

        if (element.dataset.fcMotionReady === 'true') return;

        element.dataset.fcMotionReady = 'true';

        const authoredDelay = element.getAttribute('data-fc-reveal-delay');
        const parsedDelay = authoredDelay === null
            ? 0
            : Number.parseInt(authoredDelay, 10);

        const delay = Number.isFinite(parsedDelay)
            ? Math.min(Math.max(parsedDelay, 0), 420)
            : 0;

        if (delay > 0) {
            element.style.setProperty('--fc-reveal-delay', delay + 'ms');
        }

        const observer = getObserver();

        if (observer) observer.observe(element);
        else element.classList.add('is-visible');
    }

    function scan(scope) {
        const container = scope || document;

        preparePageBlocks();

        const candidates = [];

        if (
            container instanceof Element &&
            (container.matches(revealSelector) || container.matches(interactiveSelector))
        ) {
            candidates.push(container);
        }

        if (typeof container.querySelectorAll === 'function') {
            container.querySelectorAll(
                revealSelector + ',' + interactiveSelector
            ).forEach((element) => candidates.push(element));
        }

        candidates.forEach((element) => {
            if (disabled()) {
                if (element.matches(revealSelector)) showImmediately(element);
                return;
            }

            if (element.matches(interactiveSelector)) {
                element.classList.add('fc-motion-interactive');
            }

            if (element.matches(revealSelector)) {
                prepareReveal(element);
            }
        });
    }

    function animatePage() {
        if (disabled()) return;

        const main = document.querySelector('main');
        if (!main) return;

        main.classList.remove('fc-motion-page-enter');
        void main.offsetWidth;
        main.classList.add('fc-motion-page-enter');
    }

    function disconnectObservers() {
        if (intersectionObserver) intersectionObserver.disconnect();
        if (mutationObserver) mutationObserver.disconnect();

        intersectionObserver = null;
        mutationObserver = null;
    }

    function watchContent() {
        if (disabled() || !document.body || !('MutationObserver' in window)) {
            return;
        }

        if (mutationObserver) mutationObserver.disconnect();

        mutationObserver = new MutationObserver((records) => {
            records.forEach((record) => {
                record.addedNodes.forEach((node) => {
                    if (node instanceof Element) scan(node);
                });
            });
        });

        mutationObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function enableMotion() {
        if (disabled()) {
            disableMotion();
            return;
        }

        root.classList.add('fc-motion-enabled', 'fc-motion-ready');

        scan(document);
        animatePage();
        watchContent();
    }

    function disableMotion() {
        disconnectObservers();

        root.classList.remove('fc-motion-enabled', 'fc-motion-ready');

        document.querySelectorAll(revealSelector).forEach(showImmediately);
        document.querySelectorAll('.fc-motion-page-enter').forEach((element) => {
            element.classList.remove('fc-motion-page-enter');
        });
    }

    function refresh(container) {
        if (disabled()) {
            disableMotion();
            return;
        }

        scan(container || document);
    }

    function setNoAnimation(enabled) {
        noAnimation = Boolean(enabled);
        root.classList.toggle('fc-no-animation', noAnimation);

        if (disabled()) disableMotion();
        else enableMotion();
    }

    window.FragCityMotion = Object.freeze({
        version: 4,
        refresh,
        setNoAnimation,
        reducedMotion: motionQuery.matches
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enableMotion, { once: true });
    } else {
        enableMotion();
    }

    document.addEventListener('livewire:navigated', () => {
        if (disabled()) return;

        refresh(document);
        animatePage();
    });

    window.addEventListener('fc-no-animation-change', (event) => {
        setNoAnimation(Boolean(event.detail && event.detail.enabled));
    });

    if (motionQuery.addEventListener) {
        motionQuery.addEventListener('change', enableMotion);
    }
})();
