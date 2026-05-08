/**
 * Auto-play next thematic mixtape.
 *
 * On single mixtape pages, when the last track of the current
 * mixtape ends (or errors out), js/player.js calls
 * window.lmtAutoplayInit() exposed by this module. We then :
 *
 *   1. Fetch /wp-json/lamixtape/v1/next-thematic-mixtape with the
 *      current post ID.
 *   2. If the API returns {found: false} (rare : mixtape with no
 *      category siblings AND no other curator post) → fire the
 *      `autoplay_no_next` Umami event and abort silently.
 *   3. If the API returns a candidate → build a fixed-position
 *      toast bottom-right above #footer-player with a 5-second
 *      countdown and a Cancel button.
 *   4. On countdown end : fire `autoplay_next` + redirect.
 *   5. On Cancel click  : fire `autoplay_cancel` + dismiss toast.
 *
 * Tracking helpers (window.lmtTrack, window.lmtGetMixtapeSlug)
 * come from js/tracking.js loaded in <head>. They silent-fail if
 * Umami is blocked.
 *
 * Current mixtape ID source (Phase 3.6) : window.lmtPlayerCurrentId,
 * exposed by js/player.js. Tracks the post_id of the mixtape loaded
 * in the player (= the one that will end), independent of which page
 * is currently displayed in <main>. Falls back to lmtData.post_id
 * (initial-load only) and body class (defensive). The page-current
 * value would be wrong during PJAX cross-page playback (e.g. user
 * starts mixtape A, navigates to home — when A ends, current_id
 * must be A, not the home page which has no postid).
 */
(function () {
    'use strict';

    var COUNTDOWN_SECONDS = 5;
    var REST_PATH         = '/wp-json/lamixtape/v1/next-thematic-mixtape';

    var state = {
        active:            false,  // guard against double-init
        countdownInterval: null,
        nextMixtape:       null,
        cancelled:         false,
        toast:             null
    };

    function getCurrentMixtapeId() {
        // Phase 3.6 — prefer the player-tracked id (= the mixtape
        // currently loaded in the player, which is the one that
        // just ended). Stays correct under PJAX cross-page playback.
        if (window.lmtPlayerCurrentId) {
            return window.lmtPlayerCurrentId;
        }
        // Fallback 1: lmtData.post_id (initial-load value, stale
        // after PJAX nav but the only canonical pre-3.6 source).
        if (window.lmtData && window.lmtData.post_id) {
            return parseInt(window.lmtData.post_id, 10) || null;
        }
        // Fallback 2: parse `postid-X` body class (defensive — only
        // valid when the displayed page IS the playing mixtape).
        var match = document.body.className.match(/postid-(\d+)/);
        return match ? parseInt(match[1], 10) : null;
    }

    function fetchNextMixtape(currentId) {
        var url = REST_PATH + '?current_id=' + encodeURIComponent(currentId);
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function buildToast(nextMixtape) {
        var toast = document.createElement('div');
        toast.className = 'lmt-autoplay-toast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');

        var content = document.createElement('div');
        content.className = 'lmt-autoplay-toast-content';

        var text = document.createElement('span');
        text.className = 'lmt-autoplay-toast-text';
        text.appendChild(document.createTextNode('Next mixtape in '));
        var counter = document.createElement('span');
        counter.className = 'lmt-autoplay-countdown';
        counter.textContent = String(COUNTDOWN_SECONDS);
        text.appendChild(counter);
        text.appendChild(document.createTextNode('s'));

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'lmt-autoplay-cancel';
        cancelBtn.setAttribute('aria-label', 'Cancel autoplay');
        cancelBtn.textContent = 'Cancel';

        content.appendChild(text);
        content.appendChild(cancelBtn);
        toast.appendChild(content);
        document.body.appendChild(toast);

        // Trigger CSS entry transition on next frame.
        requestAnimationFrame(function () {
            toast.classList.add('lmt-autoplay-toast-visible');
        });

        return toast;
    }

    function startCountdown() {
        var remaining = COUNTDOWN_SECONDS;
        var counter   = state.toast.querySelector('.lmt-autoplay-countdown');

        state.countdownInterval = setInterval(function () {
            remaining--;
            if (counter) {
                counter.textContent = String(Math.max(remaining, 0));
            }
            if (remaining <= 0) {
                clearInterval(state.countdownInterval);
                state.countdownInterval = null;
                if (!state.cancelled) {
                    triggerRedirect();
                }
            }
        }, 1000);
    }

    function triggerRedirect() {
        if (typeof window.lmtTrack === 'function' && state.nextMixtape) {
            window.lmtTrack('autoplay_next', {
                from_mixtape_id:   getCurrentMixtapeId(),
                to_mixtape_id:     state.nextMixtape.id,
                to_mixtape_slug:   state.nextMixtape.slug
            });
        }
        // Tiny delay so the tracking fetch has time to leave the
        // browser before navigation aborts in-flight requests.
        setTimeout(function () {
            window.location.href = state.nextMixtape.url;
        }, 80);
    }

    function cancelAutoplay() {
        // Phase 3.6 — early-return when no autoplay is active. This
        // makes cancelAutoplay() safe to call unconditionally on the
        // lmt:pjax:swapped hook (most navigations happen with no
        // toast in flight) and avoids spurious autoplay_cancel
        // tracking events on every PJAX swap.
        if (!state.active) { return; }
        if (state.cancelled) { return; }
        state.cancelled = true;

        if (state.countdownInterval) {
            clearInterval(state.countdownInterval);
            state.countdownInterval = null;
        }

        if (state.toast) {
            state.toast.classList.remove('lmt-autoplay-toast-visible');
            var toastRef = state.toast;
            setTimeout(function () {
                if (toastRef && toastRef.parentNode) {
                    toastRef.parentNode.removeChild(toastRef);
                }
            }, 300);
        }

        if (typeof window.lmtTrack === 'function') {
            window.lmtTrack('autoplay_cancel', {
                mixtape_id: getCurrentMixtapeId()
            });
        }

        // Phase 3.6 — reset state so a future autoplay cycle (after
        // PJAX nav + new mixtape ending) can fire. Pre-existing bug
        // fix: state.active was never reset to false, so once
        // cancelled, all subsequent lmtAutoplayInit() calls would
        // early-return at the re-entrancy guard.
        state.active = false;
        state.cancelled = false;
        state.nextMixtape = null;
        state.toast = null;
    }

    window.lmtAutoplayInit = function () {
        // Re-entrancy guard : if the player fires multiple
        // last-track events (rare race between ENDED + onError on
        // a glitchy YouTube embed), we still only init once.
        if (state.active) { return; }
        state.active = true;

        var currentId = getCurrentMixtapeId();
        if (!currentId) { return; }

        fetchNextMixtape(currentId).then(function (data) {
            if (!data || !data.found) {
                if (typeof window.lmtTrack === 'function') {
                    window.lmtTrack('autoplay_no_next', {
                        mixtape_id: currentId
                    });
                }
                return;
            }

            state.nextMixtape = data;
            state.toast       = buildToast(data);

            var cancelBtn = state.toast.querySelector('.lmt-autoplay-cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', cancelAutoplay);
            }

            // Allow Escape to cancel — keyboard parity with the
            // visible Cancel button.
            document.addEventListener('keydown', function escHandler(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    cancelAutoplay();
                    document.removeEventListener('keydown', escHandler);
                }
            });

            startCountdown();
        }).catch(function () {
            // Silent fail — never break the page on a fetch error.
        });
    };

    // PJAX phase 3.6 — dismiss the toast when the user navigates
    // during the countdown. The user has chosen a different
    // destination, so the parasitic redirect must not fire. Safe to
    // call unconditionally: cancelAutoplay() early-returns if no
    // autoplay is active (most swaps).
    document.addEventListener('lmt:pjax:swapped', function () {
        cancelAutoplay();
    });
})();
