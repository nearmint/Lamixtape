(function() {
    'use strict';

    var CONTAINER_SELECTOR = 'main';

    // Initialize history.state on first load so popstate (back/forward)
    // has a state object to read after a hard reload. Without this, the
    // first browser-back from a PJAX-navigated page would receive
    // event.state === null and bail out.
    if (!history.state || !history.state.url) {
        history.replaceState(
            { url: window.location.href, scrollY: 0 },
            '',
            window.location.href
        );
    }

    function shouldInterceptLink(link) {
        var href = link.getAttribute('href');
        if (!href) return false;
        if (link.target === '_blank') return false;
        if (link.hasAttribute('download')) return false;
        if (link.dataset.noPjax === 'true') return false;
        if (href.startsWith('#')) return false;
        if (href.startsWith('javascript:')) return false;
        if (href.startsWith('mailto:')) return false;
        if (href.startsWith('tel:')) return false;

        var url;
        try {
            url = new URL(href, window.location.origin);
        } catch (e) {
            return false;
        }
        if (url.host !== window.location.host) return false;

        if (url.pathname.startsWith('/wp-admin')) return false;
        if (url.pathname.startsWith('/wp-login')) return false;
        if (url.pathname.startsWith('/wp-json')) return false;
        if (url.pathname.startsWith('/feed')) return false;

        return true;
    }

    document.addEventListener('click', function(e) {
        if (e.button !== 0) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        var link = e.target.closest('a[href]');
        if (!link) return;

        if (!shouldInterceptLink(link)) return;

        var href = link.getAttribute('href');
        var url = new URL(href, window.location.origin);

        if (url.href === window.location.href) {
            e.preventDefault();
            return;
        }

        e.preventDefault();

        // Save current scroll position into the current history entry
        // BEFORE navigating away. When the user later hits back, the
        // popstate handler reads this scrollY and restores it.
        history.replaceState(
            { url: window.location.href, scrollY: window.scrollY },
            '',
            window.location.href
        );

        performFetchAndSwap(url.href, { isPopstate: false, scrollY: 0 });
    });

    window.addEventListener('popstate', function(e) {
        // Ignore popstate without a state object (e.g. hash-only changes
        // from outside our control, or navigations that predate our
        // first replaceState).
        if (!e.state || !e.state.url) {
            return;
        }
        performFetchAndSwap(e.state.url, {
            isPopstate: true,
            scrollY: e.state.scrollY || 0
        });
    });

    function performFetchAndSwap(url, options) {
        var isPopstate = options && options.isPopstate;
        var scrollY = (options && options.scrollY) || 0;

        document.body.classList.add('lmt-pjax-loading');

        fetch(url, {
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Fetch failed: ' + response.status);
            }
            return response.text();
        })
        .then(function(html) {
            var parser = new DOMParser();
            var newDoc = parser.parseFromString(html, 'text/html');

            var newMain = newDoc.querySelector(CONTAINER_SELECTOR);
            var currentMain = document.querySelector(CONTAINER_SELECTOR);

            if (!newMain || !currentMain) {
                console.error('PJAX: <main> not found in new HTML or current page. Falling back.');
                window.location.href = url;
                return;
            }

            currentMain.innerHTML = newMain.innerHTML;
            document.title = newDoc.title;
            document.body.className = newDoc.body.className;

            // Forward nav (click) : push a new history entry and scroll
            // to top. Popstate (back/forward) : the browser already
            // moved through history, so we just restore the scroll
            // position saved on the previous entry.
            if (!isPopstate) {
                history.pushState({ url: url, scrollY: 0 }, '', url);
                window.scrollTo(0, 0);
            } else {
                window.scrollTo(0, scrollY);
            }

            document.dispatchEvent(new CustomEvent('lmt:pjax:swapped', {
                detail: { url: url, fromPopstate: !!isPopstate }
            }));
        })
        .catch(function(err) {
            console.error('PJAX fetch failed:', err);
            window.location.href = url;
        })
        .finally(function() {
            document.body.classList.remove('lmt-pjax-loading');
        });
    }

    // Phase 3 : re-init components hook (lmt:pjax:swapped listener)
    // Phase 4 : meta tags refinement
    // Phase 5 : Umami pageview emit
    // Phase 6 : edge cases (dialogs, forms)

})();
