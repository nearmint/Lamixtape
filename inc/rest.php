<?php
/**
 * Theme REST endpoints.
 *
 * Currently exposes:
 *   GET /wp-json/lamixtape/v1/posts
 *     Paginated mixtape feed used by the infinite-scroll JS on home,
 *     single mixtape (previous-mixtapes block) and category archives.
 *
 * Loaded from functions.php via require_once.
 *
 * @package Lamixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'LMT_INFINITE_SCROLL_BATCH_SIZE' ) ) {
    define( 'LMT_INFINITE_SCROLL_BATCH_SIZE', 30 );
}

/**
 * Permission callback for the pagination endpoint.
 *
 * Verifies the X-WP-Nonce header (CSRF / cross-origin abuse) and
 * applies an IP-based rate limit (100 requests per IP per hour).
 * IPs are hashed via wp_hash for GDPR compliance, mirroring the
 * pattern of lmt_social_like_permission (Phase 0).
 *
 * @param  WP_REST_Request $request
 * @return true|WP_Error
 */
function lmt_rest_pagination_permission( WP_REST_Request $request ) {
    $nonce = $request->get_header( 'X-WP-Nonce' );
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_Error(
            'lmt_invalid_nonce',
            __( 'Invalid or missing nonce', 'lamixtape' ),
            array( 'status' => 403 )
        );
    }

    $ip_raw = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
    $ip     = filter_var( $ip_raw, FILTER_VALIDATE_IP );
    if ( ! $ip ) {
        return new WP_Error(
            'lmt_invalid_ip',
            __( 'Could not resolve client IP', 'lamixtape' ),
            array( 'status' => 400 )
        );
    }

    $ip_hash    = wp_hash( $ip );
    $bucket_key = 'lmt_pagination_' . $ip_hash;
    $count      = (int) get_transient( $bucket_key );
    if ( $count >= 100 ) {
        return new WP_Error(
            'lmt_rate_limited',
            __( 'Too many pagination requests', 'lamixtape' ),
            array( 'status' => 429 )
        );
    }
    set_transient( $bucket_key, $count + 1, HOUR_IN_SECONDS );

    return true;
}

/**
 * Build the WP_Query args + the card-mixtape template-part args
 * for a given pagination context.
 *
 * Returned shape:
 *   array(
 *     'query_args' => array,            // passed to new WP_Query
 *     'card_args'  => array,            // passed to template-parts/card-mixtape
 *     'where_filter' => callable|null,  // posts_where filter to register
 *                                          for single_previous date guard
 *   )
 *
 * Returns WP_Error on bad input (missing required params per context).
 *
 * @param  string $context  'home' | 'single_previous' | 'category'.
 * @param  int    $offset
 * @param  int    $category
 * @param  int    $exclude
 * @return array|WP_Error
 */
function lmt_rest_build_paginated_args( $context, $offset, $category, $exclude ) {
    $limit = LMT_INFINITE_SCROLL_BATCH_SIZE;

    switch ( $context ) {
        case 'home':
            return array(
                'query_args'   => array(
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => $limit,
                    'offset'         => $offset,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                ),
                'card_args'    => array(
                    'h2_extra_classes'      => 'font-smoothing',
                    'highlight_mode'        => 'always_span',
                    'hide_curator_on_small' => true,
                ),
                'where_filter' => null,
            );

        case 'single_previous':
            if ( ! $exclude ) {
                return new WP_Error(
                    'lmt_missing_exclude',
                    __( 'The "exclude" parameter (current post ID) is required for single_previous context.', 'lamixtape' ),
                    array( 'status' => 400 )
                );
            }
            $publish_date = get_the_date( 'Y-m-d', $exclude );
            $where_filter = function ( $where ) use ( $publish_date ) {
                global $wpdb;
                return $where . $wpdb->prepare( ' AND post_date < %s', $publish_date );
            };
            return array(
                'query_args'   => array(
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => $limit,
                    'offset'         => $offset,
                    'order'          => 'DESC',
                ),
                'card_args'    => array(
                    'article_extra_classes' => 'font-smoothing',
                    'highlight_mode'        => 'conditional',
                    'hide_curator_on_small' => false,
                ),
                'where_filter' => $where_filter,
            );

        case 'category':
            if ( ! $category ) {
                return new WP_Error(
                    'lmt_missing_category',
                    __( 'The "category" parameter is required for category context.', 'lamixtape' ),
                    array( 'status' => 400 )
                );
            }
            return array(
                'query_args'   => array(
                    'cat'            => $category,
                    'post_status'    => 'publish',
                    'posts_per_page' => $limit,
                    'offset'         => $offset,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ),
                'card_args'    => array(
                    'article_extra_classes' => 'font-smoothing',
                    'highlight_mode'        => 'none',
                    'hide_curator_on_small' => true,
                ),
                'where_filter' => null,
            );
    }

    return new WP_Error(
        'lmt_invalid_context',
        __( 'Unknown context.', 'lamixtape' ),
        array( 'status' => 400 )
    );
}

/**
 * REST callback for /lamixtape/v1/posts.
 *
 * Returns the next batch of mixtape cards for infinite scroll. The
 * batch size is fixed by LMT_INFINITE_SCROLL_BATCH_SIZE (30, cf. D1).
 *
 * Response shape:
 *   {
 *     "html":        "<article>…</article><article>…</article>",
 *     "has_more":    true|false,
 *     "next_offset": int
 *   }
 *
 * Cards are rendered via the same template-parts/card-mixtape.php
 * template-part used in the initial server-side render so the visual
 * is byte-identical between the load-time cards and the AJAX-loaded
 * ones.
 *
 * @param  WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function lmt_rest_get_posts_paginated( WP_REST_Request $request ) {
    $context  = (string) $request->get_param( 'context' );
    $offset   = (int) $request->get_param( 'offset' );
    $category = (int) $request->get_param( 'category' );
    $exclude  = (int) $request->get_param( 'exclude' );

    $built = lmt_rest_build_paginated_args( $context, $offset, $category, $exclude );
    if ( is_wp_error( $built ) ) {
        return $built;
    }

    if ( $built['where_filter'] ) {
        add_filter( 'posts_where', $built['where_filter'] );
    }

    $query = new WP_Query( $built['query_args'] );

    if ( $built['where_filter'] ) {
        remove_filter( 'posts_where', $built['where_filter'] );
    }

    ob_start();
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            get_template_part( 'template-parts/card-mixtape', null, $built['card_args'] );
        }
    }
    wp_reset_postdata();
    $html = ob_get_clean();

    $returned    = count( $query->posts );
    $found_total = (int) $query->found_posts;
    $has_more    = ( $offset + $returned ) < $found_total;

    return rest_ensure_response( array(
        'html'        => $html,
        'has_more'    => $has_more,
        'next_offset' => $offset + $returned,
    ) );
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'lamixtape/v1', '/posts', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'lmt_rest_get_posts_paginated',
        'permission_callback' => 'lmt_rest_pagination_permission',
        'args'                => array(
            'context'  => array(
                'required' => true,
                'enum'     => array( 'home', 'single_previous', 'category' ),
            ),
            'offset'   => array(
                'required'          => true,
                'type'              => 'integer',
                'minimum'           => 0,
                'sanitize_callback' => 'absint',
            ),
            'category' => array(
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'exclude'  => array(
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );

    register_rest_route( 'lamixtape/v1', '/random-mixtape', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'lmt_rest_random_mixtape',
        'permission_callback' => '__return_true',
    ) );
} );

/**
 * REST callback for /lamixtape/v1/random-mixtape.
 *
 * Picks one published post at random and returns a 302 redirect to
 * its permalink. Every click on a "Random mixtape" link in templates
 * (header mobile menu, home about section, single "Random Mixtape"
 * button, 404 fallback) points to this endpoint, and the server picks
 * a fresh post every time (no transient cache, true random UX).
 *
 * Performance: each click triggers a WP_Query orderby=rand (PERF-005
 * pattern). Acceptable because the query runs once per user click
 * (not per page render), and fields=ids + no_found_rows + LIMIT 1
 * keep it cheap on the ~370-post catalog.
 *
 * Response:
 *   HTTP 302 Found
 *   Location: <permalink>
 *   Cache-Control: no-store, no-cache, must-revalidate, max-age=0
 *   Pragma: no-cache
 *
 * The no-cache headers prevent any CDN / browser cache from serving
 * a stale Location, which would defeat the "fresh random" UX.
 *
 * Public endpoint (no nonce, no rate-limit). The redirect is the
 * landing target of an <a href> click in templates, so requests come
 * from real navigation, not XHR. Abuse mitigation can be added later
 * via a transient rate-limit (cf. lmt_rest_pagination_permission)
 * if traffic monitoring shows misuse.
 *
 * @param  WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function lmt_rest_random_mixtape( WP_REST_Request $request ) {
    $query = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'orderby'        => 'rand',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ) );

    if ( empty( $query->posts ) ) {
        return new WP_Error(
            'lmt_no_mixtape',
            __( 'No mixtape available', 'lamixtape' ),
            array( 'status' => 404 )
        );
    }

    $id  = (int) $query->posts[0];
    $url = get_permalink( $id );
    if ( ! $url ) {
        return new WP_Error(
            'lmt_invalid_permalink',
            __( 'Could not resolve permalink', 'lamixtape' ),
            array( 'status' => 500 )
        );
    }

    $response = new WP_REST_Response( null, 302 );
    $response->header( 'Location', $url );
    $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
    $response->header( 'Pragma', 'no-cache' );
    return $response;
}
