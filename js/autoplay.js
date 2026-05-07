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
 * Current mixtape ID source : window.lmtData.post_id (exposed by
 * wp_localize_script on lmt-main, cf. functions.php).
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
        if (window.lmtData && window.lmtData.post_id) {
            return parseInt(window.lmtData.post_id, 10) || null;
        }
        // Fallback: parse `postid-X` body class (defensive — in case
        // lmt-main ever stops localizing post_id).
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
})();
