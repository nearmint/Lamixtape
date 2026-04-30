<?php

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
        array( 'comment-form', 'comment-list', 'gallery', 'caption', 'search-form', 'style', 'script' )
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
}
add_action( 'wp_enqueue_scripts', 'lmt_enqueue_assets' );

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
/**
 * Custom callback for wp_list_comments() — renders a single comment
 * as <div> or <li> depending on $args['style'].
 *
 * @param  WP_Comment $comment
 * @param  array      $args
 * @param  int        $depth
 * @return void
 */
function lmt_comment_callback( $comment, $args, $depth ) {
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

/**
 * Customize the default comment form fields (author / email / url) to
 * use placeholders only and our own markup.
 *
 * @param  array $fields  Default comment form fields.
 * @return array
 */
function lmt_comment_form_fields( $fields ) {
    $commenter = wp_get_current_commenter();
    $req       = get_option( 'require_name_email' );
    $label     = $req ? '*' : ' ' . __( '(optional)', 'lamixtape' );
    $aria_req  = $req ? "aria-required='true'" : '';

    $fields['author'] =
        '<p class="comment-form-author">
            <input id="author" name="author" type="text" placeholder="' . esc_attr__( "Name", "lamixtape" ) . '" value="' . esc_attr( $commenter['comment_author'] ) .
        '" size="30" ' . $aria_req . ' />
        </p>';

    $fields['email'] =
        '<p class="comment-form-email">
            <input id="email" name="email" type="email" placeholder="' . esc_attr__( "name@email.com", "lamixtape" ) . '" value="' . esc_attr( $commenter['comment_author_email'] ) .
        '" size="30" ' . $aria_req . ' />
        </p>';

    $fields['url'] =
        '<p class="comment-form-url">
            <input id="url" name="url" type="url"  placeholder="' . esc_attr__( "http://google.com", "lamixtape" ) . '" value="' . esc_attr( $commenter['comment_author_url'] ) .
        '" size="30" />
        </p>';

    return $fields;
}
add_filter( 'comment_form_default_fields', 'lmt_comment_form_fields' );

/**
 * Customize the comment textarea markup (replace the default <p>
 * wrapper with our own).
 *
 * @param  string $comment_field  Default textarea HTML.
 * @return string
 */
function lmt_comment_form_textarea( $comment_field ) {
    $comment_field =
        '<p class="comment-form-comment">
            <textarea required id="comment" name="comment" placeholder="' . esc_attr__( "Comment...", "lamixtape" ) . '" cols="45" rows="8" aria-required="true"></textarea>
        </p>';
    return $comment_field;
}
add_filter( 'comment_form_field_comment', 'lmt_comment_form_textarea' );

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
