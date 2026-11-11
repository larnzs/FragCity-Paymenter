(function () {
    'use strict';

    if (window.fragCityHeaderReady) return;
    window.fragCityHeaderReady = true;

    const body = document.body;
    const siteHeader = document.getElementById('siteHeader');
    let scrollTicking = false;
    let lastScrollTop = Math.max(window.scrollY || 0, document.documentElement.scrollTop || 0);

    function updateHeaderScrollState() {
        if (!siteHeader) {
            scrollTicking = false;
            return;
        }

        const scrollTop = Math.max(window.scrollY || 0, document.documentElement.scrollTop || 0);
        const delta = scrollTop - lastScrollTop;

        if (scrollTop <= 4) {
            siteHeader.classList.remove('is-scrolled', 'is-scroll-down', 'is-scroll-up');
        } else {
            siteHeader.classList.add('is-scrolled');

            if (delta > 2) {
                siteHeader.classList.add('is-scroll-down');
                siteHeader.classList.remove('is-scroll-up');
            } else if (delta < -2) {
                siteHeader.classList.add('is-scroll-up');
                siteHeader.classList.remove('is-scroll-down');
            }
        }

        lastScrollTop = scrollTop;
        scrollTicking = false;
    }

    window.addEventListener('scroll', function () {
        if (document.documentElement.classList.contains('fc-no-animation')) {
            updateHeaderScrollState();
            return;
        }
        if (scrollTicking) return;
        scrollTicking = true;
        window.requestAnimationFrame(updateHeaderScrollState);
    }, { passive: true });

    updateHeaderScrollState();

    const dropdowns = Array.from(document.querySelectorAll('.frag-dropdown'));
    const mobileNav = document.getElementById('mobileNav');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const mobileOpen = document.getElementById('mobileNavOpen');
    const mobileClose = document.getElementById('mobileNavClose');

    function getDropdownTrigger(dropdown) {
        return dropdown.querySelector(':scope > button, :scope > a');
    }

    function setDropdownState(dropdown, button, open) {
        if (!dropdown) return;
        dropdown.classList.toggle('open', open);
        if (button) button.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function closeDropdowns(except) {
        dropdowns.forEach(function (dropdown) {
            if (dropdown === except) return;
            setDropdownState(dropdown, getDropdownTrigger(dropdown), false);
        });
    }

    function openMobileMenu() {
        if (!mobileNav || !mobileOverlay) return;
        closeDropdowns();
        mobileNav.classList.add('open');
        mobileOverlay.classList.add('show');
        mobileNav.setAttribute('aria-hidden', 'false');
        mobileOverlay.setAttribute('aria-hidden', 'false');
        if (mobileOpen) mobileOpen.setAttribute('aria-expanded', 'true');
        body.classList.add('mobile-menu-open');
    }

    function closeMobileMenu() {
        if (!mobileNav || !mobileOverlay) return;
        mobileNav.classList.remove('open');
        mobileOverlay.classList.remove('show');
        mobileNav.setAttribute('aria-hidden', 'true');
        mobileOverlay.setAttribute('aria-hidden', 'true');
        if (mobileOpen) mobileOpen.setAttribute('aria-expanded', 'false');
        body.classList.remove('mobile-menu-open');
    }

    window.openMobileMenu = openMobileMenu;
    window.closeMobileMenu = closeMobileMenu;

    dropdowns.forEach(function (dropdown) {
        const trigger = getDropdownTrigger(dropdown);
        if (!trigger) return;

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            const shouldOpen = !dropdown.classList.contains('open');
            closeDropdowns(dropdown);
            setDropdownState(dropdown, trigger, shouldOpen);
        });
    });

    if (mobileOpen) mobileOpen.addEventListener('click', openMobileMenu);
    if (mobileClose) mobileClose.addEventListener('click', closeMobileMenu);
    if (mobileOverlay) mobileOverlay.addEventListener('click', closeMobileMenu);

    document.addEventListener('click', function (event) {
        dropdowns.forEach(function (dropdown) {
            if (!dropdown.contains(event.target)) {
                setDropdownState(dropdown, getDropdownTrigger(dropdown), false);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDropdowns();
            closeMobileMenu();
        }
    });

    const motionPreferenceKey = 'fragcity:no-animation-mode';

    function syncMotionToggleButtons() {
        const enabled = document.documentElement.classList.contains('fc-no-animation');
        document.querySelectorAll('[data-motion-toggle]').forEach(function (button) {
            button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            button.classList.toggle('is-active', enabled);
            button.title = enabled
                ? 'Performance Mode is on — click to restore animations'
                : 'Enable Performance Mode';
            const label = button.querySelector('.fc-motion-toggle__label');
            if (label) label.textContent = 'Performance Mode';
            const state = button.querySelector('.fc-motion-toggle__state');
            if (state) state.textContent = enabled ? 'روشن' : 'خاموش';
            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-gauge-high', !enabled);
                icon.classList.toggle('fa-bolt', enabled);
            }
        });
    }

    function setNoAnimationMode(enabled) {
        const root = document.documentElement;
        root.classList.toggle('fc-no-animation', enabled);

        try {
            localStorage.setItem(motionPreferenceKey, enabled ? '1' : '0');
        } catch (error) {
            // The visual mode still works for the current page when storage is unavailable.
        }

        window.dispatchEvent(new CustomEvent('fc-no-animation-change', {
            detail: { enabled: enabled }
        }));
        syncMotionToggleButtons();
    }

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) return;
        const toggle = event.target.closest('[data-motion-toggle]');
        if (!toggle) return;
        event.preventDefault();
        setNoAnimationMode(!document.documentElement.classList.contains('fc-no-animation'));
    });

    window.addEventListener('fc-motion-mode-ready', syncMotionToggleButtons);
    syncMotionToggleButtons();
})();
