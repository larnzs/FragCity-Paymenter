(function () {
    'use strict';

    if (window.fragCityHeaderDelegationReady) return;
    window.fragCityHeaderDelegationReady = true;

    const body = document.body;
    let lastScrollTop = Math.max(window.scrollY || 0, document.documentElement.scrollTop || 0);
    let scrollTicking = false;

    function currentHeader() {
        return document.getElementById('siteHeader');
    }

    function updateHeaderScrollState() {
        const siteHeader = currentHeader();
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

    function triggerFor(dropdown) {
        return dropdown ? dropdown.querySelector(':scope > button, :scope > a') : null;
    }

    function setDropdown(dropdown, open) {
        if (!dropdown) return;
        dropdown.classList.toggle('open', open);
        const trigger = triggerFor(dropdown);
        if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function closeDropdowns(except) {
        document.querySelectorAll('.frag-dropdown.open').forEach(function (dropdown) {
            if (dropdown !== except) setDropdown(dropdown, false);
        });
    }

    function currentMobileNodes() {
        return {
            nav: document.getElementById('mobileNav'),
            overlay: document.getElementById('mobileOverlay'),
            open: document.getElementById('mobileNavOpen'),
            close: document.getElementById('mobileNavClose')
        };
    }

    function openMobileMenu() {
        const nodes = currentMobileNodes();
        if (!nodes.nav || !nodes.overlay) return;
        closeDropdowns();
        nodes.nav.classList.add('open');
        nodes.overlay.classList.add('show');
        nodes.nav.setAttribute('aria-hidden', 'false');
        nodes.overlay.setAttribute('aria-hidden', 'false');
        if (nodes.open) nodes.open.setAttribute('aria-expanded', 'true');
        body.classList.add('mobile-menu-open');
    }

    function closeMobileMenu() {
        const nodes = currentMobileNodes();
        if (!nodes.nav || !nodes.overlay) return;
        nodes.nav.classList.remove('open');
        nodes.overlay.classList.remove('show');
        nodes.nav.setAttribute('aria-hidden', 'true');
        nodes.overlay.setAttribute('aria-hidden', 'true');
        if (nodes.open) nodes.open.setAttribute('aria-expanded', 'false');
        body.classList.remove('mobile-menu-open');
    }

    window.openMobileMenu = openMobileMenu;
    window.closeMobileMenu = closeMobileMenu;

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) return;

        const trigger = event.target.closest('.frag-dropdown > button, .frag-dropdown > a');
        if (trigger) {
            const dropdown = trigger.closest('.frag-dropdown');
            if (trigger.matches('button')) {
                event.preventDefault();
                event.stopPropagation();
                const shouldOpen = !dropdown.classList.contains('open');
                closeDropdowns(dropdown);
                setDropdown(dropdown, shouldOpen);
                return;
            }
        }

        const clickedAnchor = event.target.closest('a');
        if (clickedAnchor) {
            const containingDropdown = clickedAnchor.closest('.frag-dropdown');
            if (containingDropdown) setDropdown(containingDropdown, false);
        }

        document.querySelectorAll('.frag-dropdown.open').forEach(function (dropdown) {
            if (!dropdown.contains(event.target)) setDropdown(dropdown, false);
        });

        if (event.target.closest('#mobileNavOpen')) {
            event.preventDefault();
            openMobileMenu();
            return;
        }
        if (event.target.closest('#mobileNavClose, #mobileOverlay')) {
            event.preventDefault();
            closeMobileMenu();
            return;
        }
        if (event.target.closest('#mobileNav a')) closeMobileMenu();

        const motionToggle = event.target.closest('[data-motion-toggle]');
        if (motionToggle) {
            event.preventDefault();
            setNoAnimationMode(!document.documentElement.classList.contains('fc-no-animation'));
        }
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
            button.title = enabled ? 'حالت عملکرد روشن است — برای بازگشت انیمیشن کلیک کنید' : 'فعال‌سازی حالت عملکرد';
            const label = button.querySelector('.fc-motion-toggle__label');
            if (label) label.textContent = 'حالت عملکرد';
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
        } catch (error) {}
        window.dispatchEvent(new CustomEvent('fc-no-animation-change', { detail: { enabled: enabled } }));
        syncMotionToggleButtons();
    }

    try {
        const savedMotionPreference = localStorage.getItem(motionPreferenceKey);
        if (savedMotionPreference === '1' || savedMotionPreference === '0') {
            const enabled = savedMotionPreference === '1';
            document.documentElement.classList.toggle('fc-no-animation', enabled);
            window.dispatchEvent(new CustomEvent('fc-no-animation-change', { detail: { enabled: enabled } }));
        }
    } catch (error) {}

    window.addEventListener('fc-motion-mode-ready', syncMotionToggleButtons);
    document.addEventListener('livewire:navigated', function () {
        lastScrollTop = Math.max(window.scrollY || 0, document.documentElement.scrollTop || 0);
        updateHeaderScrollState();
        syncMotionToggleButtons();
        closeDropdowns();
        closeMobileMenu();
    });
    syncMotionToggleButtons();
    updateHeaderScrollState();
})();