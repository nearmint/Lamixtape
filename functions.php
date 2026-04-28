<?php

// -----------------------------------------------------
// ------  Enable Support for Post Thumbnails ----------
// -----------------------------------------------------
add_theme_support( 'post-thumbnails' );

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

// -----------------------------------------------------
// ---------- Add page slug to body class --------------
// -----------------------------------------------------
add_filter( 'body_class', 'prefix_conditional_body_class' );
function prefix_conditional_body_class( $classes ) {
    if( is_page_template('about.php') )
        $classes[] = 'about';
    return $classes;
}

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
define( 'WP_POST_REVISIONS', 3 );

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
            printf( __( '%s' ), get_comment_author_link() ); ?>
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
// Enqueue AJAX script for like/dislike buttons
function loadmore_enqueue() {
    wp_enqueue_script( 'ajax-script', get_template_directory_uri() . '/js/main.js', array('jquery'), null, true);
}
add_action( 'wp_enqueue_scripts', 'loadmore_enqueue' );

// Localize script with site info for AJAX
function push_script() {
    wp_localize_script( 'jquery', 'bloginfo', array(
        'template_url' => get_bloginfo('template_url'),
        'site_url' => get_bloginfo('url'),
        'post_id'   => get_queried_object()
    ));
}
add_action('init', 'push_script');

// Register REST API routes for like/dislike
add_action( 'rest_api_init', function () {
    register_rest_route( 'social/v2', '/likes/(?P<id>\d+)', array(
        'methods' => array('GET','POST'),
        'callback' => 'social__like',
    ) );
    register_rest_route( 'social/v2', '/dislikes/(?P<id>\d+)', array(
        'methods' => array('GET','POST'),
        'callback' => 'social__dislike',
    ) );
});

// Callback for like endpoint
function social__like( WP_REST_Request $request ) {
    // Custom field slug
    $field_name = 'likes_number';
    // Get the current like number for the post
    $current_likes = get_field($field_name, $request['id']);
    // Add 1 to the existing number
    $updated_likes = $current_likes + 1;
    // Update the field with a new value on this post
    $likes = update_field($field_name, $updated_likes, $request['id']);
    return $likes;
}

// (Add social__dislike and any other missing functions as needed)

// -----------------------------------------------------
// -- End of functions.php -----------------------------
// -----------------------------------------------------
