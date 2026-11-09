/**
 * Branded navigation treatment for the administration workspace.
 * The current dashboard remains visible while the next dashboard is fetched.
 * This avoids the browser's blank document state during a normal full-page
 * navigation; normal forms, filters, and detail actions remain unaffected.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var title = null;
    var subtitle = null;
    var isNavigating = false;
    try { sessionStorage.removeItem('aquaAdminNavigationStartedAt'); } catch (_) {}

    document.querySelectorAll('.sidebar .nav-link, .header-orders-btn').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey ||
                event.ctrlKey || event.shiftKey || event.altKey || isNavigating) return;

            var target = new URL(link.href, window.location.href);
            if (target.origin !== window.location.origin || target.href === window.location.href) return;

            event.preventDefault();
            isNavigating = true;
            if (title) title.textContent = 'Opening ' + (link.querySelector('.nav-label')?.textContent.trim() || 'dashboard');
            if (subtitle) subtitle.textContent = 'Preparing your workspace…';
            try { sessionStorage.setItem(storageKey, String(Date.now())); } catch (_) {}

            var controller = new AbortController();
            var timeout = window.setTimeout(function () { controller.abort(); }, 5000);
            var dashboardRequest = fetch(target.href, {
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Dashboard request failed.');
                    return response.text();
                });

            // The request is aborted at five seconds. A successful dashboard
            // opens immediately; there is no intentional minimum wait.
            dashboardRequest
                .then(function (html) {
                    window.clearTimeout(timeout);
                    // Keep the browser address and history correct, then
                    // replace the document only after the server has returned
                    // the complete page. This avoids a blank browser screen.
                    window.history.pushState({}, '', target.href);
                    document.open();
                    document.write(html);
                    document.close();
                })
                .catch(function () {
                    // Never fall back to a browser-level reload here: it is
                    // the source of the blank screen. Leave the operator on
                    // the current usable dashboard and explain the failure.
                    if (title) title.textContent = 'Dashboard unavailable';
                    if (subtitle) subtitle.textContent = 'You are still on your current dashboard. Please try again.';
                    window.clearTimeout(timeout);
                    window.setTimeout(function () {
                        hideOverlay();
                        isNavigating = false;
                    }, 2500);
                });
        });
    });
});
