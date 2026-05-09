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

    // Phase 6.1 — module-level AbortController tracking the
    // currently in-flight PJAX fetch. Each call to
    // performFetchAndSwap aborts the previous fetch (if still
    // running) before starting a new one, so rapid concurrent
    // clicks A → B → C end up with only the latest navigation
    // winning. Without this, fetch A and fetch B could both
    // resolve, with B's swap potentially overwritten by A's
    // delayed .then() — visible bug : URL = B but content = A.
    //
    // The variable is reassigned on each call ; performFetchAndSwap
    // takes a local snapshot (thisController) so the .finally
    // clause can tell whether it is still the active controller
    // (i.e. the latest navigation) or a stale one that was
    // superseded by a more recent click.
    var currentAbortController = null;

    // Phase 6.3 — detect whether any user-fillable form has been
    // modified since render. Used to surface a confirm dialog
    // before discarding the user's input on PJAX nav. Targets the
    // CF7 contact form (#wpcf7-f6329-o1) inside #contactmodal —
    // the only realistic user-fill on the site.
    //
    // Skips :
    //   - Search forms (role='search' or .search-form) : query
    //     inputs are not "dirtiable" in user-meaningful sense.
    //   - Hidden / button / submit / reset inputs : not user-fill.
    //   - Akismet honeypot textarea (name starts with _wpcf7_ak) :
    //     hidden via CSS rather than type=hidden, would false-
    //     positive otherwise.
    //   - Turnstile injected inputs are naturally type=hidden.
    function isAnyFormDirty() {
        var forms = document.querySelectorAll('form');
        for (var i = 0; i < forms.length; i++) {
            var form = forms[i];
            if (form.matches('[role="search"], .search-form')) continue;
            var inputs = form.querySelectorAll('input, textarea, select');
            for (var j = 0; j < inputs.length; j++) {
                var input = inputs[j];
                if (input.type === 'hidden' || input.type === 'button' ||
                    input.type === 'submit' || input.type === 'reset') continue;
                if (input.name && input.name.indexOf('_wpcf7_ak') === 0) continue;
                if (input.name === 'hp') continue; // Phase 9.3 lmt-contact honeypot
                if (input.value !== input.defaultValue) return true;
            }
        }
        return false;
    }

    // Phase 6.3 — detect whether any form is currently mid-POST.
    // CF7 toggles class 'submitting' on its <form> for the duration
    // of the AJAX request (cf. wp-content/plugins/contact-form-7/
    // includes/js/index.js). The data-submitting attribute is a
    // generic fallback any future form might use.
    function isAnyFormSubmitting() {
        return !!document.querySelector('form.submitting, form[data-submitting="true"]');
    }

    // Phase 6.2 — close any open native <dialog> before navigating.
    // Prevents the modal from lingering on the swapped page (UX
    // confusion) and covers the popstate-with-modal edge case.
    // Targets : #donatemodal, #contactmodal (footer.php). Logic
    // inlined here rather than depending on closeAllDialogs() from
    // dialogs.js so lmt-pjax stays autonomous.
    //
    // Mobile menu (#mobile-menu-overlay) is NOT a native <dialog>
    // and uses its own closeMenu() in main.js — unaffected by this
    // helper. PhotoSwipe / lightboxes : not used in the theme
    // (confirmed via grep).
    function closeOpenModals() {
        document.querySelectorAll('dialog[open]').forEach(function(dlg) {
            try { dlg.close(); } catch (e) {}
        });
    }

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

        // Phase 6.3 — Q3 : silently block PJAX while a form is
        // mid-submission (don't lose the user's in-flight POST).
        // Applies to popstate too — never abort an in-flight submit.
        if (isAnyFormSubmitting()) {
            return;
        }

        // Phase 6.3 — Q2 : confirm before discarding dirty form
        // data. Skip on popstate : browser back/forward is an
        // intentional user nav, matches native browser behavior
        // (no confirm on back) and avoids the URL/content desync
        // that would arise if the user cancelled a popstate-driven
        // confirm (browser already moved through history).
        if (!isPopstate && isAnyFormDirty()) {
            if (!window.confirm('You have unsaved changes. Leave without sending?')) {
                return;
            }
        }

        // Phase 6.1 — cancel any in-flight fetch before starting a
        // new one. The local snapshot is what .catch / .finally
        // compare against later to know if they're still owners.
        if (currentAbortController) {
            currentAbortController.abort();
        }
        currentAbortController = new AbortController();
        var thisController = currentAbortController;

        // Phase 6.2 — close open dialogs immediately so the user
        // sees the modal disappear without waiting for the fetch.
        closeOpenModals();

        document.body.classList.add('lmt-pjax-loading');

        fetch(url, {
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            signal: thisController.signal
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
            // Phase 6.1 — AbortError = aborted by a more recent
            // click. Silent return ; do NOT trigger the
            // window.location.href fallback (which would wrongly
            // reload the page after the user chose a new link).
            if (err.name === 'AbortError') {
                return;
            }
            console.error('PJAX fetch failed:', err);
            window.location.href = url;
        })
        .finally(function() {
            // Phase 6.1 — only release the loading-bar class if we
            // are still the owning controller. If a newer click
            // superseded us, the new performFetchAndSwap call has
            // already re-added (idempotent) the class and will
            // remove it when its own fetch settles. This keeps the
            // loading bar visible continuously across rapid
            // concurrent clicks rather than flashing on/off.
            if (currentAbortController === thisController) {
                currentAbortController = null;
                document.body.classList.remove('lmt-pjax-loading');
            }
        });
    }

    // Phase 3 : re-init components hook (lmt:pjax:swapped listener)
    // Phase 4 : meta tags refinement
    // Phase 5 : Umami pageview emit
    // Phase 6 : edge cases (dialogs, forms)

})();
