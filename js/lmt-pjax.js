(function() {
    'use strict';

    var CONTAINER_SELECTOR = 'main';

    // Phase 4.1 — whitelist of <head> tags whose content/href is
    // page-specific and must be synced after a PJAX swap. Tags
    // outside this list (charset, viewport, theme-color, preload,
    // preconnect, dns-prefetch, icons, RSS feed, stylesheets) are
    // page-stable and intentionally NOT touched. Stylesheets in
    // particular are managed by WP enqueue dependencies — touching
    // them in PJAX would risk FOUC and broken script_loader chain.
    //
    // Source : pre-flight curl Local on home + single, output by
    // Rank Math (RANK_MATH_VERSION active in prod). The fallback
    // in inc/seo.php auto-disables when Rank Math is loaded.
    var META_SELECTORS = [
        // A. Description / canonical / robots (3)
        'meta[name="description"]',
        'meta[name="robots"]',
        'link[rel="canonical"]',
        // B. Open Graph (11)
        'meta[property="og:type"]',
        'meta[property="og:title"]',
        'meta[property="og:description"]',
        'meta[property="og:url"]',
        'meta[property="og:updated_time"]',
        'meta[property="og:image"]',
        'meta[property="og:image:secure_url"]',
        'meta[property="og:image:width"]',
        'meta[property="og:image:height"]',
        'meta[property="og:image:alt"]',
        'meta[property="og:image:type"]',
        // C. Twitter Cards (6)
        'meta[name="twitter:card"]',
        'meta[name="twitter:title"]',
        'meta[name="twitter:description"]',
        'meta[name="twitter:image"]',
        'meta[name="twitter:label2"]',
        'meta[name="twitter:data2"]',
        // D. Article-specific (2) — appended on home → single,
        // removed on single → home via the add/remove branches.
        'meta[property="article:publisher"]',
        'meta[property="article:section"]'
    ];

    // Phase 4.2 — whitelist of runtime body classes (set
    // dynamically by JS during the session) that must be preserved
    // when replacing body.className with newDoc.body.className.
    // Minimal/defensive approach : only preserve classes whose loss
    // would cause a visible bug.
    //
    // - lmt-pjax-loading : present at swap moment (loading bar
    //   visible). The .finally clause in performFetchAndSwap removes
    //   it ~1ms later. Without preservation, the bar would disappear
    //   prematurely between the swap and the .finally tick.
    //
    // Classes NOT preserved (handled by their own modules) :
    //   - lmt-sticky-header-active : initStickyHeader() resets the
    //     class and re-attaches the IntersectionObserver on each
    //     lmt:pjax:swapped (Phase 3.5). Preserving would risk a
    //     stale sticky on non-home pages.
    //   - lmt-sidebar-open : closeMenu() fires before the PJAX
    //     swap via the jQuery menu-internal handler (main.js).
    var RUNTIME_CLASSES = [
        'lmt-pjax-loading'
    ];

    function updateBodyClasses(newDoc) {
        var preserved = RUNTIME_CLASSES.filter(function(cls) {
            return document.body.classList.contains(cls);
        });
        document.body.className = newDoc.body.className;
        preserved.forEach(function(cls) {
            document.body.classList.add(cls);
        });
    }

    function updateMetaTags(newDoc) {
        META_SELECTORS.forEach(function(selector) {
            var newEl = newDoc.querySelector(selector);
            var currentEl = document.querySelector(selector);

            if (newEl && currentEl) {
                // Both exist : sync content (meta) or href (link).
                if (currentEl.tagName === 'LINK') {
                    currentEl.setAttribute('href', newEl.getAttribute('href') || '');
                } else {
                    currentEl.setAttribute('content', newEl.getAttribute('content') || '');
                }
            } else if (newEl && !currentEl) {
                // New page has tag, current doesn't : clone-append.
                document.head.appendChild(newEl.cloneNode(true));
            } else if (!newEl && currentEl) {
                // Current has tag, new doesn't : remove.
                currentEl.parentNode.removeChild(currentEl);
            }
            // Both absent : no-op.
        });
    }

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
            updateBodyClasses(newDoc);
            updateMetaTags(newDoc);

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
