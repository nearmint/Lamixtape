(function() {
    'use strict';

    var CONTAINER_SELECTOR = 'main';

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
        navigateAjax(url.href);
    });

    function navigateAjax(url) {
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
            swapContent(html, url);
        })
        .catch(function(err) {
            console.error('PJAX fetch failed:', err);
            window.location.href = url;
        })
        .finally(function() {
            document.body.classList.remove('lmt-pjax-loading');
        });
    }

    function swapContent(html, url) {
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

        history.pushState({ url: url }, '', url);
        document.title = newDoc.title;
        document.body.className = newDoc.body.className;

        window.scrollTo(0, 0);

        document.dispatchEvent(new CustomEvent('lmt:pjax:swapped', {
            detail: { url: url }
        }));
    }

    // Phase 2 : popstate handler (back/forward)
    // Phase 3 : re-init components hook (lmt:pjax:swapped listener)
    // Phase 4 : meta tags refinement
    // Phase 5 : Umami pageview emit
    // Phase 6 : edge cases (dialogs, forms)

})();
