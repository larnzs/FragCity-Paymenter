(function () {
    'use strict';

    // Keep the same static footer hook used by the FragCity main website.
    const footer = document.getElementById('siteFooter');
    if (!footer) return;

    footer.classList.add('is-static-footer');
})();
