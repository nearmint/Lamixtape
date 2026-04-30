<?php
/**
 * Theme query layer.
 *
 * All custom WP_Query / get_posts / get_users calls used by templates
 * live here. Templates should never instantiate WP_Query directly:
 * they consume the return values of the lmt_get_* helpers below.
 *
 * Loaded from functions.php (require_once) — flat-file structure, no
 * class layer. See _docs/prompt-phase-2.md D6.
 *
 * @package Lamixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fetch the mixtapes published strictly before the given post.
 *
 * Used by single.php to render the "previous mixtapes" block. Phase 3
 * (PERF-002) bounds the initial render to LMT_INFINITE_SCROLL_BATCH_SIZE
 * (default 30) — the rest is fetched on demand by the infinite-scroll
 * JS via /wp-json/lamixtape/v1/posts?context=single_previous.
 *
 * The `posts_where` filter is applied via a closure (no global
 * function) so it cannot leak into other queries even on exception
 * paths — fixes PERF-009.
 *
 * @param  int $current_post_id  Reference post; only posts older than
 *                               this one's publish date are returned.
 * @param  int $limit            -1 for all (legacy default), or a
 *                               positive integer to cap the batch.
 * @param  int $offset           Number of rows to skip (pagination).
 * @return WP_Query              Full WP_Query so callers can read
 *                               ->posts, ->found_posts, etc.
 */
function lmt_get_previous_mixtapes( $current_post_id, $limit = -1, $offset = 0 ) {
    $publish_date = get_the_date( 'Y-m-d', $current_post_id );

    $where_filter = function ( $where ) use ( $publish_date ) {
        global $wpdb;
        return $where . $wpdb->prepare( ' AND post_date < %s', $publish_date );
    };
    add_filter( 'posts_where', $where_filter );

    $query = new WP_Query( array(
        'order'          => 'DESC',
        'posts_per_page' => $limit,
        'offset'         => $offset,
    ) );

    remove_filter( 'posts_where', $where_filter );

    return $query;
}

/**
 * Run the front-end search query for the current request.
 *
 * Wraps the WP_Query call previously inlined at the top of search.php.
 * The search term is read via get_search_query(false) (raw, WP handles
 * SQL escaping internally).
 *
 * posts_per_page stays at -1 to keep the historical visual; switching
 * to a real paginated cap is part of the Phase 3 pagination work.
 *
 * @return WP_Query
 */
function lmt_get_search_results() {
    return new WP_Query( array(
        's'              => get_search_query( false ),
        'posts_per_page' => -1, // PERF-002 / PERF-007 tracked, pagination strategy in Phase 3
    ) );
}

/**
 * List the curators (= every WP user) shown on the /guests/ page,
 * excluding the site admin (user ID 1).
 *
 * Replaces a raw $wpdb->get_results() with string interpolation
 * (SEC-003 in AUDIT.md) by the WP-native get_users() API. ID 1 is
 * the historical admin account hidden from the curator list (the
 * legacy SQL also tried to filter via a $site_admin variable that
 * was always an empty string, so the practical filter was never
 * applied — switching to exclude => [1] is the correct fix).
 *
 * @return WP_User[]
 */
function lmt_get_curators() {
    return get_users( array(
        'exclude' => array( 1 ),
        'orderby' => 'nicename',
        'fields'  => array( 'ID', 'user_nicename' ),
    ) );
}

/**
 * Return one random published mixtape, cached per call site.
 *
 * `ORDER BY RAND()` MySQL is O(n) on the full posts table and is
 * called from 4 templates (header mobile menu, home about section,
 * single "Random Mixtape" button, 404 fallback). Caching the
 * resulting post ID in a transient lets all 4 spots share their
 * cost across visitors instead of running ORDER BY RAND() on every
 * page view.
 *
 * Each call site passes a unique cache_key suffix so different
 * "random" picks remain independent (the home random and the 404
 * random can point at different posts within the same cache window).
 *
 * Trade-off (acknowledged): for the duration of the transient, every
 * visitor sees the same "random" pick for a given call site. Default
 * TTL is HOUR_IN_SECONDS (cf. D8 in prompt-phase-3.md). Lower the TTL
 * if you need fresher randomness; raise it if BDD load is the
 * concern.
 *
 * @param  string $cache_key  Unique suffix per call site (e.g.
 *                            'header_mobile_menu', 'home_about',
 *                            'single_random_button', '404_fallback').
 * @param  int    $ttl        Cache duration in seconds. Default
 *                            HOUR_IN_SECONDS.
 * @return WP_Post|null       The cached or freshly-picked post, or
 *                            null if the site has no published posts.
 */
function lmt_get_random_mixtape( $cache_key, $ttl = null ) {
    if ( null === $ttl ) {
        $ttl = HOUR_IN_SECONDS;
    }

    $transient_key = 'lmt_random_mixtape_' . sanitize_key( $cache_key );
    $cached_id     = get_transient( $transient_key );

    if ( false !== $cached_id ) {
        $post = get_post( (int) $cached_id );
        if ( $post && 'publish' === $post->post_status ) {
            return $post;
        }
        // Cached post got unpublished/deleted; fall through to a fresh pick.
    }

    $query = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'orderby'        => 'rand',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ) );

    if ( empty( $query->posts ) ) {
        return null;
    }

    $id = (int) $query->posts[0];
    set_transient( $transient_key, $id, $ttl );

    return get_post( $id );
}

/**
 * Return every published mixtape, grouped by author ID.
 *
 * Replaces the per-author WP_Query loop in guests.php (PERF-008 N+1):
 * one query for all posts, then bucket them in PHP by post_author so
 * the template can render each curator's titles without re-querying.
 *
 * Result is cached in a 24h transient (D8 "structures lentes type
 * curators count"). The cache is invalidated automatically on
 * save_post / deleted_post via lmt_invalidate_posts_grouped_cache.
 *
 * @return array<int, WP_Post[]>  Map of author_id => array of WP_Post.
 */
function lmt_get_posts_grouped_by_author() {
    $cached = get_transient( 'lmt_posts_grouped_by_author' );
    if ( false !== $cached && is_array( $cached ) ) {
        return $cached;
    }

    $posts   = get_posts( array(
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );
    $grouped = array();
    foreach ( $posts as $post ) {
        $grouped[ (int) $post->post_author ][] = $post;
    }

    set_transient( 'lmt_posts_grouped_by_author', $grouped, DAY_IN_SECONDS );

    return $grouped;
}

/**
 * Invalidate cached query results when a post is saved or deleted,
 * so the /guests/ page picks up newly published mixtapes (or removes
 * just-trashed ones) without waiting for the 24h TTL to expire.
 *
 * Hooked on save_post + deleted_post + trashed_post.
 *
 * @param  int $post_id
 * @return void
 */
function lmt_invalidate_posts_grouped_cache( $post_id ) {
    delete_transient( 'lmt_posts_grouped_by_author' );
}
add_action( 'save_post',     'lmt_invalidate_posts_grouped_cache' );
add_action( 'deleted_post',  'lmt_invalidate_posts_grouped_cache' );
add_action( 'trashed_post',  'lmt_invalidate_posts_grouped_cache' );
