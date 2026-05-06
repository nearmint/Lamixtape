// Pass `jQuery` explicitly and alias as `$` inside the closure: stays
// safe regardless of whether `$` is bound globally (jQuery noConflict
// behaves differently depending on whether a CDN jQuery is loaded
// alongside the WP-bundled one).
jQuery(function ($) {
    // Like button AJAX logic + persistent liked state via localStorage
    // + tada animation (restored, was Animate.css leftover from Phase 1).
    //
    // Phase Recette F-7 — once a user clicks the like button, the
    // localStorage key `lmt_liked_<post_id>` is set to '1'. On subsequent
    // page loads the button renders in a greyed-out "🔥 <count> Already liked"
    // state to make the persistent like obvious to the same browser.
    // This is independent of the server-side IP-based 429 rate-limit
    // (which only catches same-IP repeat clicks within HOUR_IN_SECONDS).
    var LMT_TADA_DURATION_MS = 600;

    $(document).ready(function() {
        // Phase Recette II L3 — F7 : swap the explore search-input
        // placeholder between a long desktop label and a short mobile
        // label below 768px (input gets cropped on small screens).
        // The mobile label is sourced from data-placeholder-mobile so
        // i18n stays handled by esc_attr_e() in PHP.
        var searchInput = document.querySelector('#explore #s');
        if (searchInput) {
            var defaultPlaceholder = searchInput.placeholder;
            var mobilePlaceholder = searchInput.dataset.placeholderMobile || 'Search';
            var updateSearchPlaceholder = function () {
                searchInput.placeholder = window.innerWidth < 768
                    ? mobilePlaceholder
                    : defaultPlaceholder;
            };
            updateSearchPlaceholder();
            window.addEventListener('resize', updateSearchPlaceholder);
        }

        var likedKey = lmtData.post_id ? 'lmt_liked_' + lmtData.post_id : null;
        var $likeBtn = $('.like__btn');

        // Apply the persistent "already liked" state to a like button. The
        // markup (🔥 + count span) is preserved untouched — only attributes
        // and CSS class change. The "Already liked" hint surfaces via the
        // `title` attribute (native browser tooltip on hover).
        function applyAlreadyLikedState($btn) {
            $btn
                .attr('disabled', true)
                .attr('aria-pressed', 'true')
                .attr('title', 'Already liked')
                .removeClass('tada')
                .addClass('like__btn--already-liked');
        }

        // Hydrate persistent liked state on page load. The animation does
        // NOT play on hydrate — only on a fresh user click.
        if (likedKey && $likeBtn.length) {
            try {
                if (localStorage.getItem(likedKey) === '1') {
                    applyAlreadyLikedState($likeBtn);
                }
            } catch (e) { /* localStorage unavailable (private mode, full quota) — silent fail */ }
        }

        $likeBtn.on('click', function() {
            var $btn = $(this);
            $.ajax({
                url: lmtData.site_url + '/wp-json/social/v2/likes/' + lmtData.post_id,
                type: 'POST',
                beforeSend: function (xhr) {
                    if (lmtData.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', lmtData.nonce);
                    }
                },
                success: function (response) {
                    $btn.attr('disabled', true).addClass('tada');
                    // Server returns the new count; trust it over optimistic UI.
                    $('.like__number').html(response);
                    // Persist the liked state across sessions (F-7).
                    if (likedKey) {
                        try {
                            localStorage.setItem(likedKey, '1');
                        } catch (e) { /* silent fail */ }
                    }
                    // Phase Tracking v1 — fire like_click after server-confirmed
                    // success. Single-click design (button disables post-success +
                    // 429 rate-limit on subsequent clicks), so each AJAX success
                    // is a legitimate toggle ON. No toggle OFF case to handle.
                    if (typeof window.lmtTrack === 'function') {
                        window.lmtTrack('like_click', {
                            mixtape_slug: window.lmtGetMixtapeSlug()
                        });
                    }
                    // Let the tada animation play before transitioning to
                    // the persistent "Already liked" greyed-out state.
                    // Markup (🔥 + count) preserved; only class + title +
                    // disabled change.
                    setTimeout(function () {
                        applyAlreadyLikedState($btn);
                    }, LMT_TADA_DURATION_MS);
                },
                error: function (xhr) {
                    if (xhr.status === 429) {
                        // Already liked from this IP within the hour. Apply
                        // the persistent state immediately (no animation —
                        // server didn't accept the click). Markup unchanged.
                        applyAlreadyLikedState($btn);
                        if (likedKey) {
                            try {
                                localStorage.setItem(likedKey, '1');
                            } catch (e) { /* silent fail */ }
                        }
                    } else {
                        console.log('like failed:', xhr.status);
                    }
                }
            });
        });
    });

    // =============================
    // BURGER MENU (Mobile Navigation)
    // =============================
    $(function() {
        var $burgerBtn = $('#burger-btn');
        var $mobileMenu = $('#mobile-menu-overlay');
        var $closeBtn = $('#close-mobile-menu');
        // Phase 5 A11Y-006: focus return target after close.
        var lastFocused = null;
        // Toggle `inert` on every direct body child except the
        // overlay itself when the menu opens/closes. `inert` removes
        // descendants from sequential focus order AND prevents
        // pointer-events, providing a browser-native focus trap.
        // Supported in all evergreen browsers (Safari 15.4+, Chrome
        // 102+, Firefox 112+).
        function setSiblingsInert(inertValue) {
            var overlay = document.getElementById('mobile-menu-overlay');
            var children = document.body.children;
            for (var i = 0; i < children.length; i++) {
                if (children[i] !== overlay) {
                    children[i].inert = inertValue;
                }
            }
        }
        function openMenu() {
            lastFocused = document.activeElement;
            $mobileMenu.attr('aria-hidden', 'false');
            $burgerBtn.attr('aria-expanded', 'true');
            // Phase Recette II L4 step 2 — desktop push: the body gets
            // a margin-left via the `.lmt-sidebar-open` class so the
            // page content shifts right of the sidebar instead of
            // sitting underneath it. Mobile neutralises the push via
            // a media query in css/navbar.css.
            $('body').addClass('lmt-sidebar-open');
            // inert + body overflow:hidden are mobile-only. On desktop
            // push mode the user expects the pushed content to remain
            // clickable / scrollable (VS Code / Outlook pattern).
            // A11y trade-off accepted: focus trap dur (inert) was
            // double-protection over the keyboard ESC + focus return,
            // which still work in both modes.
            if (window.innerWidth < 992) {
                setSiblingsInert(true);
                $('body').css('overflow', 'hidden');
            }
            $mobileMenu.fadeIn(200, function() {
                $mobileMenu.css({'pointer-events': 'auto', 'opacity': 1});
                $closeBtn.trigger('focus');
            });
        }
        function closeMenu() {
            $mobileMenu.attr('aria-hidden', 'true');
            $burgerBtn.attr('aria-expanded', 'false');
            $('body').removeClass('lmt-sidebar-open');
            $mobileMenu.fadeOut(200, function() {
                $mobileMenu.css({'pointer-events': 'none', 'opacity': 0});
            });
            // Always reset, idempotent if openMenu skipped them.
            setSiblingsInert(false);
            $('body').css('overflow', '');
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }
        $burgerBtn.on('click', function(e) {
            e.preventDefault();
            openMenu();
        });
        $closeBtn.on('click', function(e) {
            e.preventDefault();
            closeMenu();
        });
        // Close on ESC key
        $(document).on('keydown', function(e) {
            if ($mobileMenu.is(':visible') && e.key === 'Escape') {
                closeMenu();
            }
        });
        // Optional: Close when clicking outside menu
        $mobileMenu.on('click', function(e) {
            if (e.target === this) closeMenu();
        });
        // Close menu when any link or dialog-trigger button inside the
        // overlay is tapped or clicked (for iOS/Safari reliability).
        // Phase Recette F-10: extending the selector to match
        // <button data-lmt-dialog> ensures Contact us / Support us also
        // close the mobile menu before the modal opens (the dialogs.js
        // handler on `document` fires later in the bubble path, so
        // closeMenu runs first by DOM order).
        $mobileMenu.find('a, button[data-lmt-dialog]').on('click touchend', function() {
            closeMenu();
        });
    });

    // Phase Recette II L4 step 2 — entrance animations on menu items
    // (animateMenuItems / resetMenuItems + .menu-fade-in markers)
    // removed alongside the CSS rules. The minimal sidebar opens
    // instantly via the existing fadeIn(200) on the overlay itself.

    // Homepage entrance animations (.fade-in stagger) removed in
    // Phase 4 CHECKPOINT 3 fix #4 — see css/general.css trailer
    // for the rationale.

    // =============================
    // PLAYER SLIDE-UP ANIMATION
    // =============================
    $(function() {
        setTimeout(function() {
            $('#footer-player').addClass('visible');
        }, 300);
    });
});

// Smooth scrolling
// TODO Phase 5: replace placeholder href="#" with <button> for a11y (cf. A11Y-002).
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    const href = this.getAttribute('href');
    if (!href || href === '#') return; // skip placeholder links (modal toggles, etc.)
    const target = document.querySelector(href);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
    }
  });
});