<?php

// -----------------------------------------------------
// --------------- Module loading ----------------------
// -----------------------------------------------------
require_once get_template_directory() . '/inc/queries.php';
require_once get_template_directory() . '/inc/rest.php';

// -----------------------------------------------------
// ------------------- Theme setup ---------------------
// -----------------------------------------------------
/**
 * Register theme features (post-thumbnails, title-tag, html5,
 * automatic-feed-links, responsive-embeds, editor-styles).
 *
 * @return void
 */
function lmt_setup_theme() {
    load_theme_textdomain( 'lamixtape', get_template_directory() . '/languages' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'title-tag' );
    add_theme_support(
        'html5',
        array( 'gallery', 'caption', 'search-form', 'style', 'script' )
    );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'editor-styles' );
}
add_action( 'after_setup_theme', 'lmt_setup_theme' );

// -----------------------------------------------------
// ------------ Frontend assets (CSS only) -------------
// -----------------------------------------------------
// Enqueues vendor CSS (Bootstrap, MediaElement, Outfit) and the 14 theme
// CSS files in the exact cascade order of the legacy style.css @import
// chain. JS assets (jQuery, MediaElement JS, Bootstrap JS, main.js) are
// handled separately further down (and will be migrated in subsequent
// Phase 1.3 steps).
//
// Cascade contract: lmt-bootstrap MUST load first; every theme stylesheet
// declares lmt-bootstrap as a dependency so WP guarantees ordering.
function lmt_enqueue_assets() {
    $theme_uri = get_template_directory_uri();

    // Tailwind v4 — Phase 4 Axe A. Loaded BEFORE Bootstrap so any
    // collision (in case prefix(tw) ever leaks an unprefixed rule)
    // resolves in BS's favor during cohabitation. Templates use only
    // `tw:*` utilities until C19.5 strips the prefix in Axe D.
    // Version bound to file mtime for free cache busting on every
    // rebuild of the CLI output.
    $tailwind_path = get_template_directory() . '/assets/css/tailwind.css';
    $tailwind_ver  = file_exists( $tailwind_path ) ? filemtime( $tailwind_path ) : null;
    wp_enqueue_style( 'lmt-tailwind', $theme_uri . '/assets/css/tailwind.css', array(), $tailwind_ver );

    // Vendor CSS — load before theme CSS to preserve override semantics.
    wp_enqueue_style( 'lmt-bootstrap', $theme_uri . '/assets/vendor/bootstrap/bootstrap.min.css', array( 'lmt-tailwind' ), '4.4.1' );
    wp_enqueue_style( 'lmt-outfit',    $theme_uri . '/assets/vendor/outfit/outfit.css',          array( 'lmt-bootstrap' ), '1.0' );

    // MediaElement.js — use the WP-bundled version (matches our 4.2.16 target).
    // Enqueueing the script also enqueues the corresponding 'wp-mediaelement' CSS
    // via WP's internal dependency. Saves ~170 KB of self-hosted assets.
    wp_enqueue_style( 'wp-mediaelement' );
    wp_enqueue_script( 'wp-mediaelement' );

    // Theme CSS — strict order from the legacy style.css @import chain.
    // Each depends on lmt-bootstrap so it always loads after vendor CSS.
    // Loaded globally for now; conditional loading per template is a
    // follow-up optimization (deferred to keep this commit cascade-safe).
    $theme_css = array(
        'search'               => 'css/search.css',
        'category'             => 'css/category.css',
        'general'              => 'css/general.css',
        'navbar'               => 'css/navbar.css',
        'mixtape-page'         => 'css/mixtape-page.css',
        'mixtape-of-the-month' => 'css/mixtape-of-the-month.css',
        // 'newsletter' was removed in Phase 1.3 (commit 370eec3).
        'donation'             => 'css/donation.css',
        'guests'               => 'css/guests.css',
        'explore'              => 'css/explore.css',
        '404'                  => 'css/404.css',
        'list-of-mixtapes'     => 'css/list-of-mixtapes.css',
        'player'               => 'css/player.css',
        'text'                 => 'css/text.css',
    );
    foreach ( $theme_css as $slug => $rel ) {
        wp_enqueue_style( 'lmt-' . $slug, $theme_uri . '/' . $rel, array( 'lmt-bootstrap' ), '1.0' );
    }

    // Vendor JS — Bootstrap bundle includes Popper, required for the
    // data-toggle="tooltip" in index.php and for modal/dropdown plugins.
    // Loaded in the footer; jQuery is a hard dependency.
    wp_enqueue_script( 'lmt-bootstrap-bundle', $theme_uri . '/assets/vendor/bootstrap/bootstrap.bundle.min.js', array( 'jquery' ), '4.4.1', true );

    // Theme JS — main.js handles the like button, burger menu, fade-in
    // animations and smooth scroll. Localized with site info + REST nonce
    // (consumed as `lmtData` inside the closure in main.js).
    wp_enqueue_script( 'lmt-main', $theme_uri . '/js/main.js', array( 'jquery' ), null, true );
    wp_localize_script( 'lmt-main', 'lmtData', array(
        'template_url' => $theme_uri,
        'site_url'     => site_url(),
        'post_id'      => get_queried_object_id(),
        'nonce'        => wp_create_nonce( 'wp_rest' ),
    ) );

    // Player JS — only on single mixtape pages. Depends on jQuery and
    // wp-mediaelement (the .mediaelementplayer plugin lives on the WP
    // jQuery instance — cf. fix in commit 81e0af2).
    if ( is_singular( 'post' ) ) {
        wp_enqueue_script( 'lmt-player', $theme_uri . '/js/player.js', array( 'jquery', 'wp-mediaelement' ), null, true );
    }

    // Infinite scroll — home, single (previous mixtapes), category.
    // The script early-returns if no #lmt-infinite-sentinel is present,
    // so the conditional is mainly a network-payload optimisation.
    // Depends on lmt-main so the lmtData global (nonce + site_url) is
    // available when the script runs.
    if ( is_front_page() || is_home() || is_page_template( 'index.php' ) || is_singular( 'post' ) || is_category() ) {
        wp_enqueue_style(
            'lmt-infinite-scroll',
            $theme_uri . '/css/infinite-scroll.css',
            array( 'lmt-bootstrap' ),
            '1.0'
        );
        wp_enqueue_script(
            'lmt-infinite-scroll',
            $theme_uri . '/js/infinite-scroll.js',
            array( 'jquery', 'lmt-main' ),
            null,
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'lmt_enqueue_assets' );

/**
 * Preload the Outfit (latin subset) woff2 file in <head>.
 *
 * The latin subset covers ASCII + common Western European
 * accents (French é, è, à, ç...) which is what every page on
 * Lamixtape needs at first paint. The latin-ext subset is loaded
 * on demand by the browser only when an extra glyph is encountered,
 * so preloading it would waste bandwidth on most page loads and
 * isn't done here.
 *
 * crossorigin="anonymous" must match the implicit CORS mode of the
 * @font-face src: url() declaration (cf. assets/vendor/outfit/outfit.css).
 *
 * Hook priority 1 so the preload hint is emitted before any other
 * <link>/<script> from wp_head.
 *
 * @return void
 */
function lmt_preload_outfit_font() {
    $url = get_template_directory_uri() . '/assets/vendor/outfit/outfit-latin.woff2';
    echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( $url ) . '" crossorigin="anonymous">' . "\n";
}
add_action( 'wp_head', 'lmt_preload_outfit_font', 1 );

/**
 * Add defer to non-critical theme scripts.
 *
 * lmt-player and lmt-infinite-scroll only run after first paint
 * (lmt-player on user interaction, lmt-infinite-scroll on
 * IntersectionObserver firing), so they can be deferred without
 * any UX impact. Defer = parse the script while HTML continues to
 * stream + execute after the parser finishes, before DOMContentLoaded.
 *
 * lmt-main stays sync (it binds the like-button handler that may
 * be needed before DOMContentLoaded on a fast click) and so does
 * the Bootstrap bundle (modal init relies on synchronous BS4 boot).
 * wp-mediaelement is left to WP defaults.
 *
 * @param  string $tag     Existing <script> tag.
 * @param  string $handle  Script handle.
 * @return string
 */
function lmt_defer_scripts( $tag, $handle ) {
    $defer_handles = array( 'lmt-player', 'lmt-infinite-scroll' );
    if ( in_array( $handle, $defer_handles, true ) && false === strpos( $tag, ' defer' ) ) {
        return str_replace( ' src=', ' defer src=', $tag );
    }
    return $tag;
}
add_filter( 'script_loader_tag', 'lmt_defer_scripts', 10, 2 );

/**
 * Send baseline security headers on every front-end response.
 *
 * Verified via curl -I against prod (lamixtape.fr) before posting:
 * neither OVH nor Cloudflare set any of these, so no risk of
 * duplicate headers.
 *
 * - X-Content-Type-Options:    nosniff (block MIME sniffing).
 * - Referrer-Policy:           strict-origin-when-cross-origin
 *                              (full URL same-origin, only origin
 *                              cross-origin, nothing on downgrade).
 * - Strict-Transport-Security: 1y + includeSubDomains (HSTS).
 * - X-Frame-Options:           SAMEORIGIN (clickjacking guard;
 *                              CSP frame-ancestors will replace
 *                              this when we ship CSP, cf. Q11).
 * - Permissions-Policy:        deny geolocation/microphone/camera/
 *                              payment/usb (none of these features
 *                              are used by the theme; explicit deny
 *                              prevents future iframe abuse).
 * - X-Powered-By:              removed (PHP version leak).
 *
 * Content-Security-Policy is intentionally NOT posted here — the
 * matrix (Bootstrap inline, YouTube iframe, MediaElement, Cloudflare
 * Turnstile, Umami SaaS, ACF dynamic style="...") is non-trivial.
 * Tracked as Q11 in CLAUDE.md, scheduled for Phase 5/6 after the
 * Tailwind migration eliminates Bootstrap-driven inline behaviour.
 *
 * Skipped on the admin side: WP / plugins set their own headers
 * there and the trade-offs (e.g. iframe previews, framed media
 * uploaders) are different.
 *
 * Side note on X-Powered-By: PHP can also set it server-side via
 * expose_php in php.ini. If the prod check after deploy still
 * shows an X-Powered-By, ask the host to set expose_php = Off.
 *
 * @return void
 */
function lmt_send_security_headers() {
    if ( is_admin() ) {
        return;
    }
    header_remove( 'X-Powered-By' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()' );
}
add_action( 'send_headers', 'lmt_send_security_headers' );

// -----------------------------------------------------
// ---------- Search on custom fields ------------------
// -----------------------------------------------------

/**
 * Join wp_posts and wp_postmeta on search queries so meta_value can be
 * searched alongside post_title (cf. lmt_search_postmeta_where).
 *
 * @param  string $join  Existing JOIN clause built by WP.
 * @return string
 */
function lmt_search_postmeta_join( $join ) {
    global $wpdb;
    if ( is_search() ) {
        $join .=' LEFT JOIN '.$wpdb->postmeta. ' ON '. $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
    }
    return $join;
}
add_filter( 'posts_join', 'lmt_search_postmeta_join' );

/**
 * Extend the WHERE clause so search matches against postmeta.meta_value
 * in addition to post_title (relies on the JOIN added above).
 *
 * @param  string $where  Existing WHERE clause.
 * @return string
 */
function lmt_search_postmeta_where( $where ) {
    global $pagenow, $wpdb;
    if ( is_search() ) {
        $where = preg_replace(
            "/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "(".$wpdb->posts.".post_title LIKE $1) OR (".$wpdb->postmeta.".meta_value LIKE $1)", $where );
    }
    return $where;
}
add_filter( 'posts_where', 'lmt_search_postmeta_where' );

/**
 * Force DISTINCT on search queries to avoid duplicates introduced by
 * the postmeta JOIN.
 *
 * @param  string $where  Existing DISTINCT clause.
 * @return string
 */
function lmt_search_distinct( $where ) {
    global $wpdb;
    if ( is_search() ) {
        return "DISTINCT";
    }
    return $where;
}
add_filter( 'posts_distinct', 'lmt_search_distinct' );

/**
 * Restrict search results to the 'post' post type (excludes pages).
 *
 * @param  WP_Query $query
 * @return WP_Query
 */
function lmt_search_post_type_filter( $query ) {
    if ( $query->is_search ) {
        $query->set( 'post_type', 'post' );
    }
    return $query;
}
add_filter( 'pre_get_posts', 'lmt_search_post_type_filter' );

// -----------------------------------------------------
// ------------- Change Search page URL ----------------
// -----------------------------------------------------
/**
 * Redirect /?s=... to the pretty /search/<term>/ URL on search hits.
 *
 * @return void
 */
function lmt_search_url_redirect() {
    if ( is_search() && ! empty( $_GET['s'] ) ) {
        wp_safe_redirect( get_home_url( null, "/search/" ) . rawurlencode( get_query_var( 's' ) ) );
        exit();
    }
}
add_action( 'template_redirect', 'lmt_search_url_redirect' );

// -----------------------------------------
// ---------- Clean up the <head> ----------
// -----------------------------------------
remove_action('wp_head', 'rsd_link');
remove_action( 'wp_head', 'rel_canonical' );
remove_action('wp_head', 'wp_resource_hints', 2);
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'start_post_rel_link');
remove_action('wp_head', 'index_rel_link');
remove_action('wp_head', 'adjacent_posts_rel_link');
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
remove_action( 'wp_head',      'rest_output_link_wp_head'              );
remove_action( 'wp_head',      'wp_oembed_add_discovery_links'         );
remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );
/**
 * Deregister the wp-embed script (oEmbed JS not used on the frontend).
 *
 * @return void
 */
function lmt_deregister_wp_embed() {
    wp_deregister_script( 'wp-embed' );
}
add_action( 'wp_footer', 'lmt_deregister_wp_embed' );

/**
 * Dequeue the Gutenberg block library stylesheet on the frontend
 * (theme has no block-based templates).
 *
 * @return void
 */
function lmt_deregister_block_library_css() {
    wp_dequeue_style( 'wp-block-library' );
}
add_action( 'wp_print_styles', 'lmt_deregister_block_library_css', 100 );

// -----------------------------------------
// ---- Backoffice : rename "Posts" --------
// -----------------------------------------
/**
 * Relabel the admin "Posts" menu/submenu entries to "Playlist(s)".
 *
 * @return void
 */
function lmt_relabel_post_menu() {
    global $menu;
    global $submenu;
    $menu[5][0] = 'Playlist';
    $submenu['edit.php'][5][0] = 'Playlists';
    $submenu['edit.php'][10][0] = 'Add Playlist';
    $submenu['edit.php'][16][0] = 'Playlist Tags';
}

/**
 * Relabel the 'post' post type object so all admin strings read as
 * "Playlist(s)" rather than "Post(s)".
 *
 * @return void
 */
function lmt_relabel_post_object() {
    global $wp_post_types;
    $labels = &$wp_post_types['post']->labels;
    $labels->name = 'Playlists';
    $labels->singular_name = 'Playlists';
    $labels->add_new = 'Add Playlist';
    $labels->add_new_item = 'Add Playlist';
    $labels->edit_item = 'Edit Playlist';
    $labels->new_item = 'Playlists';
    $labels->view_item = 'View Playlist';
    $labels->search_items = 'Search Playlists';
    $labels->not_found = 'No Playlist found';
    $labels->not_found_in_trash = 'No Playlists found in Trash';
    $labels->all_items = 'All Playlists';
    $labels->menu_name = 'Playlists';
    $labels->name_admin_bar = 'Playlists';
}
add_action( 'admin_menu', 'lmt_relabel_post_menu' );
add_action( 'init', 'lmt_relabel_post_object' );

// -----------------------------------------------------
// ------------- Reduce Post Revisions -----------------
// -----------------------------------------------------
// NOTE: ideal location is wp-config.php (cf. PERF-014), but guarded here
// to avoid 'already defined' warnings when the constant is set upstream.
if ( ! defined( 'WP_POST_REVISIONS' ) ) {
    define( 'WP_POST_REVISIONS', 3 );
}

// -----------------------------------------------------
// ------------- Remove WP version # -------------------
// -----------------------------------------------------
/**
 * Strip the WordPress version from the generator meta tag.
 *
 * @return string
 */
function lmt_remove_generator_version() {
    return '';
}
add_filter( 'the_generator', 'lmt_remove_generator_version' );

// -----------------------------------------------------
// --------------- Secure WP Admin ---------------------
// -----------------------------------------------------
/**
 * Replace the (verbose) login error message with a generic one to
 * avoid leaking whether a username exists.
 *
 * @return string
 */
function lmt_obfuscate_login_errors() {
    return 'Something is wrong!';
}
add_filter( 'login_errors', 'lmt_obfuscate_login_errors' );

// -----------------------------------------------------
// --------------- Next and Previous links -------------
// -----------------------------------------------------
add_filter( 'next_posts_link_attributes',     'lmt_post_link_class_prev' );
add_filter( 'previous_posts_link_attributes', 'lmt_post_link_class_next' );
add_filter( 'next_post_link_attributes',      'lmt_post_link_class_prev' );
add_filter( 'previous_post_link_attributes',  'lmt_post_link_class_next' );

/**
 * Class attribute applied to the "next/previous posts" link (older).
 *
 * @return string
 */
function lmt_post_link_class_prev() {
    return 'class="prev-post"';
}

/**
 * Class attribute applied to the "previous/next post" link (newer).
 *
 * @return string
 */
function lmt_post_link_class_next() {
    return 'class="next-post"';
}

// -----------------------------------------------------
// ------------------- Images on RSS -------------------
// -----------------------------------------------------
/**
 * Prepend the post thumbnail to the RSS excerpt and content feeds.
 *
 * @param  string $content  Existing feed content.
 * @return string
 */
function lmt_rss_post_thumbnail( $content ) {
    global $post;
    if ( has_post_thumbnail( $post->ID ) ) {
        $content = '<p>' . get_the_post_thumbnail( $post->ID ) . '</p>' . $content;
    }
    return $content;
}
add_filter( 'the_excerpt_rss',  'lmt_rss_post_thumbnail' );
add_filter( 'the_content_feed', 'lmt_rss_post_thumbnail' );

// -----------------------------------------------------
// ----------- Likes — REST endpoint -------------------
// -----------------------------------------------------
// (lmt-main script enqueue + lmtData localize live in lmt_enqueue_assets
// at the top of this file — single source of truth for theme assets.)

// Register REST API routes for likes
add_action( 'rest_api_init', function () {
    register_rest_route( 'social/v2', '/likes/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::CREATABLE, // POST only
        'callback'            => 'lmt_social_like',
        'permission_callback' => 'lmt_social_like_permission',
        'args'                => array(
            'id' => array(
                'validate_callback' => function ( $param ) {
                    return is_numeric( $param ) && get_post( (int) $param );
                },
            ),
        ),
    ) );
});

/**
 * Permission callback for the likes REST endpoint.
 * Verifies the X-WP-Nonce header to block CSRF / cross-origin abuse.
 */
function lmt_social_like_permission( WP_REST_Request $request ) {
    $nonce = $request->get_header( 'X-WP-Nonce' );
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_Error(
            'lmt_invalid_nonce',
            __( 'Invalid or missing nonce', 'lamixtape' ),
            array( 'status' => 403 )
        );
    }
    return true;
}

/**
 * Callback for the likes REST endpoint.
 * Increments likes_number for the given post, with IP-based rate limit
 * (1 like per IP per post per hour). IPs are hashed via wp_hash for GDPR.
 *
 * @param WP_REST_Request $request
 * @return int|WP_Error new like count, or WP_Error on rate limit / invalid IP
 */
function lmt_social_like( WP_REST_Request $request ) {
    $post_id = (int) $request['id'];

    // Resolve and validate the client IP.
    $ip_raw = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
    $ip     = filter_var( $ip_raw, FILTER_VALIDATE_IP );
    if ( ! $ip ) {
        return new WP_Error(
            'lmt_invalid_ip',
            __( 'Could not resolve client IP', 'lamixtape' ),
            array( 'status' => 400 )
        );
    }

    // Rate-limit: 1 like / IP / post / hour. Hash IP for GDPR compliance.
    $ip_hash       = wp_hash( $ip );
    $transient_key = 'lmt_like_' . $ip_hash . '_' . $post_id;
    if ( false !== get_transient( $transient_key ) ) {
        return new WP_Error(
            'lmt_rate_limited',
            __( 'Already liked', 'lamixtape' ),
            array( 'status' => 429 )
        );
    }

    // Increment the counter.
    $field_name    = 'likes_number';
    $current_likes = (int) get_field( $field_name, $post_id );
    $updated_likes = $current_likes + 1;
    update_field( $field_name, $updated_likes, $post_id );

    set_transient( $transient_key, 1, HOUR_IN_SECONDS );

    return $updated_likes;
}

// -----------------------------------------------------
// -- End of functions.php -----------------------------
// -----------------------------------------------------
