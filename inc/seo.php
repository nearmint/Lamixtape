<?php
/**
 * Theme SEO layer — Open Graph + Twitter Cards + JSON-LD fallback.
 *
 * Phase 6 (OTHER-003 + OTHER-006). Lamixtape ships with Rank Math
 * which already emits OG meta + JSON-LD when active. This file
 * provides a defensive fallback : if Rank Math is ever disabled,
 * uninstalled, or fails to load, the theme still emits sane Open
 * Graph + Twitter Cards meta on every template, plus a
 * `MusicPlaylist` JSON-LD on single mixtape pages. When Rank Math
 * is active, this file is a no-op (no duplication of meta).
 *
 * Loaded from functions.php (require_once) — flat-file structure
 * matching inc/queries.php and inc/rest.php (cf. CLAUDE.md D6).
 *
 * @package Lamixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Detect whether Rank Math SEO is loaded and active.
 *
 * Used as a guard before emitting any fallback meta : if Rank Math
 * is on, we assume it handles OG / Twitter / JSON-LD natively and
 * we do nothing (avoids tag duplication that would confuse social
 * scrapers and search engines).
 *
 * Detection via the `RANK_MATH_VERSION` constant which the plugin
 * defines very early (in its main bootstrap file). This is the
 * canonical detection pattern documented by Rank Math.
 *
 * @return bool
 */
function lmt_rank_math_active() {
    return defined( 'RANK_MATH_VERSION' );
}

/**
 * Emit Open Graph and Twitter Cards meta tags as a fallback when
 * Rank Math is not active.
 *
 * Hooked on `wp_head` at priority 20 (after most plugins) so that
 * Rank Math's emission at the default priority 10 has already
 * happened by the time we'd run — but we never run anyway when
 * Rank Math is active (early return via lmt_rank_math_active()).
 *
 * Coverage per template :
 *   - single (post) : og:type=article, title=post, description=
 *     excerpt, image=featured (or fallback), url=permalink
 *   - home          : og:type=website, title=site name, description=
 *     site tagline, image=fallback
 *   - category      : og:type=website, title=category name +
 *     "Genre" prefix, description=category description if any
 *   - search        : og:type=website, title="Search: <query>"
 *   - 404           : og:type=website, generic
 *   - other (text page templates colophon/legal-notice, explore,
 *     guests) : og:type=website with the page title
 *
 * twitter:card is `summary_large_image` when an image is present,
 * `summary` otherwise.
 *
 * All values are escaped via esc_attr / esc_url. The site_name
 * fallback is `get_bloginfo('name')`.
 *
 * @return void
 */
function lmt_emit_og_twitter_fallback() {
    if ( lmt_rank_math_active() ) {
        return;
    }

    $site_name   = get_bloginfo( 'name' );
    $site_locale = get_locale();
    $og_url      = home_url( add_query_arg( null, null ) );
    $og_type     = 'website';
    $og_title    = $site_name;
    $og_desc     = get_bloginfo( 'description' );
    $og_image    = '';

    if ( is_singular( 'post' ) ) {
        $og_type  = 'article';
        $og_title = get_the_title();
        $og_desc  = wp_trim_words( strip_tags( get_the_excerpt() ?: get_the_content() ), 30, '…' );
        if ( has_post_thumbnail() ) {
            $thumb = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
            if ( $thumb && ! empty( $thumb[0] ) ) {
                $og_image = $thumb[0];
            }
        }
    } elseif ( is_category() ) {
        $cat      = get_queried_object();
        $og_title = sprintf( __( 'Genre : %s', 'lamixtape' ), $cat->name );
        if ( ! empty( $cat->description ) ) {
            $og_desc = wp_trim_words( strip_tags( $cat->description ), 30, '…' );
        }
    } elseif ( is_search() ) {
        $og_title = sprintf( __( 'Search : %s', 'lamixtape' ), get_search_query() );
    } elseif ( is_404() ) {
        $og_title = sprintf( '%s — %s', __( 'Page not found', 'lamixtape' ), $site_name );
    } elseif ( is_page() ) {
        $og_title = get_the_title();
    }

    $twitter_card = $og_image ? 'summary_large_image' : 'summary';

    echo "\n<!-- Phase 6 OTHER-003 fallback (Rank Math inactive) -->\n";
    printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $og_type ) );
    printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $og_title ) );
    printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $og_desc ) );
    printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $og_url ) );
    printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( $site_name ) );
    printf( '<meta property="og:locale" content="%s">' . "\n", esc_attr( $site_locale ) );
    if ( $og_image ) {
        printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $og_image ) );
    }
    printf( '<meta name="twitter:card" content="%s">' . "\n", esc_attr( $twitter_card ) );
    printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $og_title ) );
    printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $og_desc ) );
    if ( $og_image ) {
        printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $og_image ) );
    }
    echo "<!-- /Phase 6 OTHER-003 fallback -->\n";
}
add_action( 'wp_head', 'lmt_emit_og_twitter_fallback', 20 );

/**
 * Emit a `MusicPlaylist` JSON-LD structured data block on single
 * mixtape pages, as a fallback when Rank Math is not active.
 *
 * Decision business utilisateur Phase 6 (Décision 5) :
 * `MusicPlaylist` retenu plutôt que `Article` car Lamixtape est
 * sémantiquement une plateforme de playlists curatées par un
 * curator. Coût : rich results Google plus limités que `Article`
 * — accepté en échange de la cohérence sémantique. Possibilité
 * future d'ajouter un schema `Article` en parallèle via @graph
 * sans rien casser.
 *
 * Champs émis :
 *   - name           : titre de la mixtape
 *   - description    : excerpt (30 mots, fallback content)
 *   - url            : permalink
 *   - datePublished  : date ISO 8601
 *   - dateModified   : date ISO 8601
 *   - image          : featured image si dispo
 *   - author         : Person { name = curator }
 *   - numTracks      : count des items dans le repeater ACF
 *   - track[]        : array de MusicRecording { name, url } —
 *                      uniquement si tracklist non vide
 *
 * `track[]` est extrait via `get_field('tracklist')` qui retourne
 * directement un array (ACF repeater) — coût marginal, pas de
 * placeholder fake si la donnée manque.
 *
 * Hook `wp_head` priorité 20 (cf. lmt_emit_og_twitter_fallback).
 * Bypass total si Rank Math actif (Rank Math émet son propre
 * JSON-LD `Article` ou `WebPage` selon configuration ; éviter
 * double JSON-LD pour ne pas confondre les crawlers).
 *
 * @return void
 */
function lmt_emit_jsonld_musicplaylist() {
    if ( lmt_rank_math_active() ) {
        return;
    }

    if ( ! is_singular( 'post' ) ) {
        return;
    }

    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return;
    }

    $description = wp_trim_words( strip_tags( get_the_excerpt() ?: get_the_content() ), 30, '…' );

    $data = array(
        '@context'      => 'https://schema.org',
        '@type'         => 'MusicPlaylist',
        'name'          => get_the_title(),
        'url'           => get_permalink(),
        'datePublished' => get_the_date( 'c' ),
        'dateModified'  => get_the_modified_date( 'c' ),
    );

    if ( $description ) {
        $data['description'] = $description;
    }

    if ( has_post_thumbnail() ) {
        $thumb = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
        if ( $thumb && ! empty( $thumb[0] ) ) {
            $data['image'] = $thumb[0];
        }
    }

    $author_name = get_the_author_meta( 'display_name' );
    if ( $author_name ) {
        $data['author'] = array(
            '@type' => 'Person',
            'name'  => $author_name,
        );
    }

    $tracklist = get_field( 'tracklist', $post_id );
    if ( is_array( $tracklist ) && ! empty( $tracklist ) ) {
        $tracks = array();
        foreach ( $tracklist as $row ) {
            $name = isset( $row['track'] ) ? trim( (string) $row['track'] ) : '';
            $url  = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
            if ( $name === '' ) {
                continue;
            }
            $entry = array(
                '@type' => 'MusicRecording',
                'name'  => $name,
            );
            if ( $url !== '' ) {
                $entry['url'] = $url;
            }
            $tracks[] = $entry;
        }
        if ( ! empty( $tracks ) ) {
            $data['numTracks'] = count( $tracks );
            $data['track']     = $tracks;
        }
    }

    $json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    if ( false === $json ) {
        return;
    }

    echo "\n<!-- Phase 6 OTHER-006 fallback (Rank Math inactive) -->\n";
    echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    echo "<!-- /Phase 6 OTHER-006 fallback -->\n";
}
add_action( 'wp_head', 'lmt_emit_jsonld_musicplaylist', 20 );
