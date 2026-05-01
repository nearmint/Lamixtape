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
}() );
