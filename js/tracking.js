/**
 * Lamixtape — Umami custom events tracking helper.
 *
 * Wrapper around umami.track() with silent fail for adblockers,
 * Umami load failures, or any other unexpected runtime issue.
 * NEVER breaks user experience for tracking — fire-and-forget.
 *
 * Specifications : _docs/tracking-plan.md
 *
 * Loaded in <head> via wp_enqueue_script with no dependency, so
 * window.lmtTrack and window.lmtGetMixtapeSlug are available
 * before any other theme script runs (footer-loaded). Umami itself
 * is loaded async via <script defer> in analytics.php (footer
 * inline) and becomes available shortly after — by the time a
 * user click triggers a tracking call, Umami is loaded.
 *
 * @package Lamixtape
 * @since   1.0.0 (Phase Tracking v1)
 */

( function () {
    'use strict';

    /**
     * Send a custom event to Umami.
     *
     * Silent fail under any of these conditions :
     *   - Umami script not loaded (adblocker, network error)
     *   - umami.track is not a function (Umami API changed)
     *   - umami.track throws (defensive try/catch)
     *
     * @param {string} eventName Snake_case event name (cf. tracking-plan.md).
     * @param {Object} [data]    Event properties (no PII).
     */
    window.lmtTrack = function ( eventName, data ) {
        data = data || {};

        if ( typeof umami === 'undefined' ) {
            return;
        }
        if ( typeof umami.track !== 'function' ) {
            return;
        }

        try {
            umami.track( eventName, data );
        } catch ( e ) {
            // Silent fail — never break UX for tracking.
        }
    };

    /**
     * Extract the mixtape slug from window.location.pathname.
     *
     * Convention WordPress permaliens : /<slug>/ for single posts.
     * Returns the last non-empty segment of the pathname.
     *
     * Examples :
     *   /classic-masters/        -> "classic-masters"
     *   /classic-masters         -> "classic-masters"
     *   /                        -> "" (home, no mixtape)
     *   /category/hip-hop/       -> "hip-hop"
     *   /search/dub/             -> "dub"
     *
     * Caller should know the context — `play_start` etc. only fire
     * on single mixtape pages where the slug is meaningful.
     *
     * @return {string} Mixtape slug (or "" on home).
     */
    window.lmtGetMixtapeSlug = function () {
        var path = window.location.pathname || '';
        var segments = path.replace( /^\/+|\/+$/g, '' ).split( '/' );
        return segments[ segments.length - 1 ] || '';
    };

    /**
     * Delegated click listener for the PayPal donate button.
     *
     * Phase Tracking v1 event 5/6 — fire `donate_paypal_click` when
     * the user clicks the "Donate via PayPal" button inside
     * #donatemodal (footer.php:16). Fire-and-forget : `target="_blank"`
     * opens a new window and the synchronous track() call initiates
     * the POST before the browser navigates the new tab.
     *
     * Delegation on document so the listener stays valid even if
     * the modal is recreated or moved in the DOM (current setup
     * doesn't do this, but defensive).
     */
    document.addEventListener( 'click', function ( event ) {
        var paypalBtn = event.target.closest( '.btn-donate' );
        if ( paypalBtn ) {
            window.lmtTrack( 'donate_paypal_click' );
        }
    } );

    /**
     * Phase 5 — Manual Umami pageview after a PJAX swap.
     *
     * Umami auto-track only catches native navigations (full page
     * loads). PJAX swaps don't trigger a load, so analytics would
     * miss every navigation after the initial hit. This listener
     * bridges the gap by calling umami.track() (no args) on every
     * lmt:pjax:swapped event — which is dispatched by lmt-pjax.js
     * for both forward clicks AND popstate (browser back/forward),
     * so all PJAX-driven URL changes are reported.
     *
     * umami.track() with no arguments emits a default pageview using
     * the current window.location.pathname, document.title and
     * document.referrer. Those values are already up-to-date by the
     * time we run : performFetchAndSwap performs history.pushState +
     * document.title + meta tags update BEFORE dispatching
     * lmt:pjax:swapped, so Umami sees the new URL.
     *
     * No double-pageview risk on hard reload : the listener only
     * fires on lmt:pjax:swapped, which never dispatches at initial
     * load (only on click/popstate-driven swaps).
     *
     * Silent fail (3 conditions) mirrors the lmtTrack() pattern so
     * adblockers / Umami-down / API drift never break the UX.
     */
    document.addEventListener( 'lmt:pjax:swapped', function () {
        if ( typeof umami === 'undefined' ) { return; }
        if ( typeof umami.track !== 'function' ) { return; }
        try {
            umami.track();
        } catch ( e ) {
            // Silent fail — never break UX for tracking.
        }
    } );
}() );
