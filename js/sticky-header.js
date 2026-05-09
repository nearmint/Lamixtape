/**
 * Sticky header reveal on home + single templates.
 *
 * Watches a trigger element via IntersectionObserver. When the
 * element exits the viewport through the top (user scrolled past
 * it), toggle `lmt-sticky-header-active` on <body> so the CSS in
 * css/navbar.css turns the <header> fixed with a slide-down +
 * fade-in animation. When the element re-enters, the class is
 * removed and the header returns to its natural place in the flow.
 *
 * Trigger element per template :
 *   - body.home   → <section class="about">
 *   - body.single → <article class="mixtape">
 *
 * Phase 3.5 (PJAX) — refactored into an idempotent
 * initStickyHeader() function so it can re-init after each
 * lmt:pjax:swapped event. Gating reduced to:
 *   1. JS body.home || body.single guard inside initStickyHeader()
 *   2. CSS rules in navbar.css scoped to body:is(.home, .single)...
 * (PHP enqueue gate dropped for site-wide loading; the JS function
 * is harmless on non-home/non-single pages thanks to the guard above.)
 *
 * IntersectionObserver is preferred over a scroll listener: native,
 * passive, GPU-friendly, no jank on mobile.
 */
(function () {
    'use strict';

    var observer = null;
    var STICKY_CLASS = 'lmt-sticky-header-active';

    function initStickyHeader() {
        // Q2: disconnect previous observer + reset class for a clean
        // re-init. Avoids stacking observers across PJAX cycles and
        // resets the body class state for fresh page-top scroll.
        if (observer) {
            observer.disconnect();
            observer = null;
        }
        document.body.classList.remove(STICKY_CLASS);

        // Q1: early-return inside the function so it can be called
        // unconditionally on every PJAX swap (the JS file is now
        // loaded site-wide).
        if (!document.body.classList.contains('home') &&
            !document.body.classList.contains('single')) {
            return;
        }

        var triggerEl = document.querySelector('section.about, article.mixtape');
        if (!triggerEl) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            return;
        }

        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                // The trigger element has exited the viewport through
                // the TOP when isIntersecting is false AND its top is
                // above the viewport (negative). We don't activate on
                // bottom exit (would happen if the user resized to a
                // tiny viewport, edge case not worth covering).
                if (!entry.isIntersecting && entry.boundingClientRect.top < 0) {
                    document.body.classList.add(STICKY_CLASS);
                } else {
                    document.body.classList.remove(STICKY_CLASS);
                }
            });
        }, {
            threshold: 0,
            rootMargin: '0px'
        });

        observer.observe(triggerEl);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStickyHeader);
    } else {
        initStickyHeader();
    }

    document.addEventListener('lmt:pjax:swapped', function () {
        initStickyHeader();
    });
})();
