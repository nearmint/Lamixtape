<?php

// -----------------------------------------------------
// ------  Enable Support for Post Thumbnails ----------
// -----------------------------------------------------
add_theme_support( 'post-thumbnails' );

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

    // Vendor CSS — load before theme CSS to preserve override semantics.
    wp_enqueue_style( 'lmt-bootstrap', $theme_uri . '/assets/vendor/bootstrap/bootstrap.min.css', array(), '4.4.1' );
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
        'comment-form'         => 'css/comment-form.css',
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
}
add_action( 'wp_enqueue_scripts', 'lmt_enqueue_assets' );

// -----------------------------------------------------
// ---------- Search on custom fields ------------------
// -----------------------------------------------------

// Join posts and postmeta tables for search
function cf_search_join( $join ) {
    global $wpdb;
    if ( is_search() ) {
        $join .=' LEFT JOIN '.$wpdb->postmeta. ' ON '. $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
    }
    return $join;
}
add_filter('posts_join', 'cf_search_join' );

// Modify the search query to include custom fields
function cf_search_where( $where ) {
    global $pagenow, $wpdb;
    if ( is_search() ) {
        $where = preg_replace(
            "/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "(".$wpdb->posts.".post_title LIKE $1) OR (".$wpdb->postmeta.".meta_value LIKE $1)", $where );
    }
    return $where;
}
add_filter( 'posts_where', 'cf_search_where' );

// Prevent duplicate results in search
function cf_search_distinct( $where ) {
    global $wpdb;
    if ( is_search() ) {
        return "DISTINCT";
    }
    return $where;
}
add_filter( 'posts_distinct', 'cf_search_distinct' );

// Exclude Pages from search results
function SearchFilter($query) {
    if ($query->is_search) {
        $query->set('post_type', 'post');
    }
    return $query;
}
add_filter('pre_get_posts','SearchFilter');

// -----------------------------------------------------
// ------------- Change Search page URL ----------------
// -----------------------------------------------------
function wp_change_search_url() {
    if ( is_search() && ! empty( $_GET['s'] ) ) {
        wp_safe_redirect( get_home_url( null, "/search/" ) . urlencode( get_query_var( 's' ) ) );
        exit();
    }
}
add_action( 'template_redirect', 'wp_change_search_url' );

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
function my_deregister_scripts(){
    wp_deregister_script( 'wp-embed' );
}
add_action( 'wp_footer', 'my_deregister_scripts' );

// Disable Gutenberg style in Front
function wps_deregister_styles() {
    wp_dequeue_style( 'wp-block-library' );
}
add_action( 'wp_print_styles', 'wps_deregister_styles', 100 );

// -----------------------------------------
// ---- Backoffice : rename "Posts" --------
// -----------------------------------------
function revcon_change_post_label() {
    global $menu;
    global $submenu;
    $menu[5][0] = 'Playlist';
    $submenu['edit.php'][5][0] = 'Playlists';
    $submenu['edit.php'][10][0] = 'Add Playlist';
    $submenu['edit.php'][16][0] = 'Playlist Tags';
}

function revcon_change_post_object() {
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
add_action( 'admin_menu', 'revcon_change_post_label' );
add_action( 'init', 'revcon_change_post_object' );

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
function wpb_remove_version() {
    return '';
}
add_filter('the_generator', 'wpb_remove_version');

// -----------------------------------------------------
// --------------- Secure WP Admin ---------------------
// -----------------------------------------------------
// Hide Login Errors in WordPress
function no_wordpress_errors(){
    return 'Something is wrong!';
}
add_filter( 'login_errors', 'no_wordpress_errors' );

// -----------------------------------------------------
// --------------- Next and Previous links -------------
// -----------------------------------------------------
add_filter('next_posts_link_attributes', 'posts_link_attributes_1');
add_filter('previous_posts_link_attributes', 'posts_link_attributes_2');
add_filter('next_post_link_attributes', 'posts_link_attributes_1');
add_filter('previous_post_link_attributes', 'posts_link_attributes_2');

function posts_link_attributes_1() {
    return 'class="prev-post"';
}

function posts_link_attributes_2() {
    return 'class="next-post"';
}

// -----------------------------------------------------
// -------------  Comments on Mixtapes  ----------------
// -----------------------------------------------------
function tape_comment($comment, $args, $depth) {
    if ( 'div' === $args['style'] ) {
        $tag       = 'div';
        $add_below = 'comment';
    } else {
        $tag       = 'li';
        $add_below = 'div-comment';
    }?>
    <<?php echo $tag; ?> <?php comment_class( empty( $args['has_children'] ) ? '' : 'parent' ); ?> id="comment-<?php comment_ID() ?>"><?php
    if ( 'div' != $args['style'] ) { ?>
        <div id="div-comment-<?php comment_ID() ?>" class="comment-body"><?php
    } ?>
    <?php echo get_comment_text(); ?><br>
        <p><small>&mdash; <?php
            if ( $args['avatar_size'] != 0 ) {
                echo get_avatar( $comment, $args['avatar_size'] );
            }
            echo get_comment_author_link(); ?>
        </small></p><?php
        if ( $comment->comment_approved == '0' ) { ?>
            <em class="comment-awaiting-moderation"><?php _e( 'Your comment is awaiting moderation.' ); ?></em><br/><?php
        } ?>
    <?php
        if ( 'div' != $args['style'] ) : ?>
            </div><?php
        endif;
}

// Customize comment form fields
function my_update_comment_fields( $fields ) {
    $commenter = wp_get_current_commenter();
    $req       = get_option( 'require_name_email' );
    $label     = $req ? '*' : ' ' . __( '(optional)', 'text-domain' );
    $aria_req  = $req ? "aria-required='true'" : '';

    $fields['author'] =
        '<p class="comment-form-author">
            <input id="author" name="author" type="text" placeholder="' . esc_attr__( "Name", "text-domain" ) . '" value="' . esc_attr( $commenter['comment_author'] ) .
        '" size="30" ' . $aria_req . ' />
        </p>';

    $fields['email'] =
        '<p class="comment-form-email">
            <input id="email" name="email" type="email" placeholder="' . esc_attr__( "name@email.com", "text-domain" ) . '" value="' . esc_attr( $commenter['comment_author_email'] ) .
        '" size="30" ' . $aria_req . ' />
        </p>';

    $fields['url'] =
        '<p class="comment-form-url">
            <input id="url" name="url" type="url"  placeholder="' . esc_attr__( "http://google.com", "text-domain" ) . '" value="' . esc_attr( $commenter['comment_author_url'] ) .
        '" size="30" />
        </p>';

    return $fields;
}
add_filter( 'comment_form_default_fields', 'my_update_comment_fields' );

// Customize the comment textarea field
function my_update_comment_field( $comment_field ) {
    $comment_field =
        '<p class="comment-form-comment">
            <textarea required id="comment" name="comment" placeholder="' . esc_attr__( "Comment...", "text-domain" ) . '" cols="45" rows="8" aria-required="true"></textarea>
        </p>';
    return $comment_field;
}
add_filter( 'comment_form_field_comment', 'my_update_comment_field' );

// -----------------------------------------------------
// ------------------- Images on RSS -------------------
// -----------------------------------------------------
// Add post thumbnails to RSS feeds
function wcs_post_thumbnails_in_feeds( $content ) {
    global $post;
    if( has_post_thumbnail( $post->ID ) ) {
        $content = '<p>' . get_the_post_thumbnail( $post->ID ) . '</p>' . $content;
    }
    return $content;
}
add_filter( 'the_excerpt_rss', 'wcs_post_thumbnails_in_feeds' );
add_filter( 'the_content_feed', 'wcs_post_thumbnails_in_feeds' );

// -----------------------------------------------------
// ------------------- Like buttons --------------------
// -----------------------------------------------------
// Enqueue AJAX script for like buttons
function loadmore_enqueue() {
    wp_enqueue_script( 'lmt-main', get_template_directory_uri() . '/js/main.js', array('jquery'), null, true);
}
add_action( 'wp_enqueue_scripts', 'loadmore_enqueue' );

// Localize site info + REST nonce on the lmt-main handle (SEC-006).
// Hooked on wp_enqueue_scripts (not init) so that lmt-main is already
// registered by loadmore_enqueue() — wp_localize_script silently
// no-ops on an unregistered handle.
function push_script() {
    wp_localize_script( 'lmt-main', 'lmtData', array(
        'template_url' => get_template_directory_uri(),
        'site_url'     => site_url(),
        'post_id'      => get_queried_object_id(),
        'nonce'        => wp_create_nonce( 'wp_rest' ),
    ));
}
add_action( 'wp_enqueue_scripts', 'push_script' );

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
