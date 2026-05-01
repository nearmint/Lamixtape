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
 * Build the MusicPlaylist JSON-LD data array for the current single
 * post. Returns null if the current request is not a single post or
 * if data cannot be built.
 *
 * Decision business utilisateur Phase 6 (Décision 5) :
 * `MusicPlaylist` retenu plutôt que `Article` car Lamixtape est
 * sémantiquement une plateforme de playlists curatées par un
 * curator. Coût : rich results Google plus limités que `Article`
 * — accepté en échange de la cohérence sémantique. Possibilité
 * future d'ajouter un schema `Article` en parallèle via @graph
 * sans rien casser.
 *
 * Champs émis (omis si données absentes — pas de placeholder fake) :
 *   - @type          : MusicPlaylist
 *   - name           : titre de la mixtape
 *   - url            : permalink
 *   - datePublished  : date ISO 8601
 *   - dateModified   : date ISO 8601
 *   - description    : excerpt 30 mots (fallback content)
 *   - image          : featured image si dispo
 *   - author         : Person { name = curator display_name }
 *   - numTracks      : count des items dans le repeater ACF
 *   - track[]        : array de MusicRecording { name, url } —
 *                      uniquement si tracklist non vide
 *
 * Le `@context` est volontairement OMIS : ce helper retourne juste
 * une entrée d'array. Le @context est ajouté soit par Rank Math
 * (cas filter), soit par le fallback standalone (cas direct emit).
 *
 * Refactor post-Phase-7 (OTHER-006 audit finding) : extracted from
 * the original `lmt_emit_jsonld_musicplaylist()` so both code paths
 * (Rank Math filter integration AND standalone wp_head fallback)
 * partagent la même logique de construction (DRY).
 *
 * @return array|null  MusicPlaylist data ready for JSON-LD encoding,
 *                     or null si pas de single post ou erreur.
 */
function lmt_build_musicplaylist_data() {
    if ( ! is_singular( 'post' ) ) {
        return null;
    }

    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return null;
    }

    $description = wp_trim_words( strip_tags( get_the_excerpt() ?: get_the_content() ), 30, '…' );

    $data = array(
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

    return $data;
}

/**
 * Inject MusicPlaylist into Rank Math's `@graph` via the
 * `rank_math/json_ld` filter.
 *
 * Refactor post-Phase-7 (OTHER-006 audit finding) : Phase 7 audit
 * a détecté que Rank Math n'émet AUCUN JSON-LD sur les single
 * mixtapes (module Schema désactivé sur le post type `post`,
 * cf. `_docs/audit-post-refacto.md` section 2.3). Le fallback
 * Phase 6 d'origine (early return sur `defined('RANK_MATH_VERSION')`)
 * était trop défensif : il skippait la fallback même quand RM
 * n'émettait rien, laissant les singles sans aucune structured
 * data.
 *
 * Le filter `rank_math/json_ld` (cf. RM `class-jsonld.php:149`
 * `$data = $this->do_filter( 'json_ld', [], $this )`) reçoit
 * l'array de schemas qui devient le `@graph` final. Notre callback
 * y ajoute MusicPlaylist :
 *   - Si RM avait déjà émis d'autres schemas → MusicPlaylist
 *     coexiste dans le @graph (multiples @type valides en JSON-LD)
 *   - Si RM n'avait rien à émettre → notre injection rend le
 *     `$data` non-vide, RM émet un `<script>` contenant juste
 *     notre MusicPlaylist (résout le finding OTHER-006)
 *
 * Priorité 20 pour s'exécuter après les callbacks RM internes
 * (qui hookent à 8/10/99 cf. RM `class-frontend.php`).
 *
 * @param  array $data  Array of JSON-LD schemas accumulated par RM
 *                      et autres callbacks (peut être vide).
 * @return array        Same array avec MusicPlaylist ajoutée si
 *                      applicable, sinon inchangée.
 */
function lmt_inject_musicplaylist_to_rank_math( $data ) {
    if ( ! is_array( $data ) ) {
        $data = array();
    }
    $musicplaylist = lmt_build_musicplaylist_data();
    if ( $musicplaylist ) {
        $data['lmt_musicplaylist'] = $musicplaylist;
    }
    return $data;
}
add_filter( 'rank_math/json_ld', 'lmt_inject_musicplaylist_to_rank_math', 20, 1 );

/**
 * Emit a standalone `MusicPlaylist` JSON-LD `<script>` block when
 * Rank Math is NOT active.
 *
 * When Rank Math is active, our MusicPlaylist data is injected via
 * `rank_math/json_ld` filter (cf. lmt_inject_musicplaylist_to_rank_math)
 * and emitted by RM inside its own `<script class="rank-math-schema">`.
 * This standalone fallback emission only runs when RM is absent, to
 * avoid double JSON-LD (which would confuse crawlers).
 *
 * Hook `wp_head` priorité 20 (cohérent avec
 * lmt_emit_og_twitter_fallback).
 *
 * @return void
 */
function lmt_emit_jsonld_musicplaylist() {
    if ( lmt_rank_math_active() ) {
        return;
    }

    $musicplaylist = lmt_build_musicplaylist_data();
    if ( ! $musicplaylist ) {
        return;
    }

    $payload = array_merge(
        array( '@context' => 'https://schema.org' ),
        $musicplaylist
    );

    $json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    if ( false === $json ) {
        return;
    }

    echo "\n<!-- Phase 6 OTHER-006 fallback (Rank Math inactive) -->\n";
    echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    echo "<!-- /Phase 6 OTHER-006 fallback -->\n";
}
add_action( 'wp_head', 'lmt_emit_jsonld_musicplaylist', 20 );

/**
 * Suppress the obsolete `fb:app_id` Open Graph meta tag emitted by
 * Rank Math.
 *
 * Phase 8 ad-hoc cleanup HTML head — finding F5 (audit utilisateur
 * view-source). Facebook deprecated `fb:app_id` in 2021 (no longer
 * required for OG sharing, no longer parsed for App Insights since
 * Marketing API v15.0). Lamixtape doesn't use FB Login or any
 * App-id-bound integration, so the meta is pure pollution.
 *
 * Rank Math emits the tag via `RankMath\OpenGraph\Facebook::app_id()`
 * which calls `$this->tag( 'fb:app_id', $app_id )` (cf.
 * `includes/opengraph/class-facebook.php:248`). The shared
 * `tag()` method (`class-opengraph.php`) runs the value through
 * `do_filter( "opengraph/{$network}/$og_property", $content )`
 * — for fb:app_id with network=facebook, the filter is
 * `rank_math/opengraph/facebook/fb_app_id` (note `:` is replaced
 * by `_` to form a valid PHP filter name).
 *
 * Returning empty string from this filter triggers the early
 * `if ( empty( $content ) || ! is_scalar( $content ) ) { return false; }`
 * check in tag(), which skips the meta emission entirely.
 *
 * Verified by reading Rank Math source (plugin v1.x ; behavior
 * confirmed unchanged across recent releases).
 */
add_filter( 'rank_math/opengraph/facebook/fb_app_id', '__return_empty_string' );
