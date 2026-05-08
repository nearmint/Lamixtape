/**
 * Sticky header reveal on the home template.
 *
 * Watches <section class="about"> via IntersectionObserver. When
 * the section exits the viewport through the top (user scrolled
 * past it), toggle `lmt-sticky-header-active` on <body> so the
 * CSS in css/navbar.css turns the <header> fixed with a slide-down
 * + fade-in animation. When the section re-enters, the class is
 * removed and the header returns to its natural place in the flow.
 *
 * Phase 3.5 (PJAX) — refactored into an idempotent
 * initStickyHeader() function so it can re-init after each
 * lmt:pjax:swapped event. Triple gating reduced to:
 *   1. JS body.home guard inside initStickyHeader()
 *   2. CSS rules in navbar.css scoped to body.home...
 * (PHP enqueue gate dropped for site-wide loading; the JS function
 * is harmless on non-home pages thanks to the guard above.)
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
        if (!document.body.classList.contains('home')) {
            return;
        }

        var aboutSection = document.querySelector('section.about');
        if (!aboutSection) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            return;
        }

        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                // The about section has exited the viewport through the
                // TOP when isIntersecting is false AND its top is above
                // the viewport (negative). We don't activate on bottom
                // exit (that would happen if the user resized to a tiny
                // viewport, edge case not worth covering).
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

        observer.observe(aboutSection);
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
