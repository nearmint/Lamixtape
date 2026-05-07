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
 * Triple gating (perf + non-regression elsewhere):
 *   1. functions.php only enqueues this script on home pages
 *      (is_front_page / is_home / is_page_template index.php)
 *   2. JS early-returns if document.body lacks the `home` class
 *   3. CSS rules in navbar.css are scoped to body.home...
 *
 * IntersectionObserver is preferred over a scroll listener: native,
 * passive, GPU-friendly, no jank on mobile.
 */
(function () {
    'use strict';

    if (!document.body.classList.contains('home')) {
        return;
    }

    var aboutSection = document.querySelector('section.about');
    if (!aboutSection) {
        return;
    }

    var STICKY_CLASS = 'lmt-sticky-header-active';

    var observer = new IntersectionObserver(function (entries) {
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
})();
