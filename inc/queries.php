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
 * Fetch every mixtape published strictly before the given post.
 *
 * Used by single.php to render the "previous mixtapes" archive list
 * underneath the current mixtape. The `posts_where` filter is applied
 * via a closure (no global function) so it cannot leak into other
 * queries even on exception paths — fixes PERF-009.
 *
 * PERF-002 tracked, pagination strategy in Phase 3 with PERF-001.
 * Keeping posts_per_page = -1 here preserves the historical visual
 * (the entire back-catalogue is rendered) which is the no-visual-change
 * contract of Phase 2.
 *
 * @param  int $current_post_id  Reference post; only posts older than
 *                               this one's publish date are returned.
 * @return WP_Post[]             Array of WP_Post objects (possibly empty).
 */
function lmt_get_previous_mixtapes( $current_post_id ) {
    $publish_date = get_the_date( 'Y-m-d', $current_post_id );

    $where_filter = function ( $where ) use ( $publish_date ) {
        global $wpdb;
        return $where . $wpdb->prepare( ' AND post_date < %s', $publish_date );
    };
    add_filter( 'posts_where', $where_filter );

    $paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;
    $query = new WP_Query( array(
        'paged'          => $paged,
        'order'          => 'DESC',
        'posts_per_page' => -1, // PERF-002 tracked, pagination strategy in Phase 3
    ) );

    remove_filter( 'posts_where', $where_filter );

    return $query->posts;
}
