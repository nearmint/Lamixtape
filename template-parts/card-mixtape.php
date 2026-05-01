<?php
/**
 * Template part — single "mixtape card" used in archive-style lists.
 *
 * Used by index.php, single.php (previous-mixtapes loop), category.php
 * and search.php. Inside the loop, the global $post must already be set
 * (via $query->the_post() or setup_postdata()).
 *
 * Expected $args (all optional, sensible defaults applied below):
 *
 *   - 'article_extra_classes' string  — extra classes on <article>
 *                                       (e.g. 'font-smoothing')
 *   - 'h2_extra_classes'      string  — extra classes on the <h2>
 *                                       (e.g. 'font-smoothing')
 *   - 'highlight_mode'        string  — 'always_span' | 'conditional' | 'none'
 *                                       'always_span' renders an empty
 *                                       <span class="highlight ..."></span>
 *                                       wrapper even if highlight is false
 *                                       (matches the legacy index/search
 *                                       markup); 'conditional' only renders
 *                                       the span when highlight is true
 *                                       (matches single.php previous-loop);
 *                                       'none' omits the highlight span
 *                                       entirely (matches category.php).
 *   - 'hide_curator_on_small' bool    — wrap the curator span in
 *                                       hidden lg:block (true
 *                                       everywhere except single.php
 *                                       previous-loop)
 *
 * @package Lamixtape
 */

$args = wp_parse_args(
    isset( $args ) && is_array( $args ) ? $args : array(),
    array(
        'article_extra_classes' => '',
        'h2_extra_classes'      => '',
        'highlight_mode'        => 'always_span',
        'hide_curator_on_small' => true,
    )
);

$article_classes = trim( $args['article_extra_classes'] );
$h2_classes      = trim( $args['h2_extra_classes'] . ' mb-0 pt-2 truncate' );
$curator_classes = ( $args['hide_curator_on_small'] ? 'hidden lg:block ' : '' )
    . 'float-right curator author-' . get_the_author_meta( 'ID' );

$is_highlight = (bool) get_field( 'highlight' );
$bg_color     = get_field( 'color' );
$fg_color     = lmt_contrast_text_color( $bg_color );
?>
<article style="background-color:<?php echo esc_attr( $bg_color ); ?>; color:<?php echo esc_attr( $fg_color ); ?>;" class="<?php echo esc_attr( $article_classes ); ?>">
    <div class="container mx-auto px-4">
        <?php if ( 'always_span' === $args['highlight_mode'] ) : ?>
            <span class="highlight float-left -mr-4"><?php echo $is_highlight ? '🔥' : ''; ?></span>
        <?php elseif ( 'conditional' === $args['highlight_mode'] && $is_highlight ) : ?>
            <span class="highlight float-left -mr-4">🔥</span>
        <?php endif; ?>
        <a href="<?php the_permalink(); ?>"><h2 class="<?php echo esc_attr( $h2_classes ); ?>"><?php the_title(); ?><span class="<?php echo esc_attr( $curator_classes ); ?>"><?php esc_html_e( 'Curated by', 'lamixtape' ); ?> <?php the_author(); ?></span></h2></a>
        <div class="tags pb-2"><?php
            $categories = get_the_category();
            $separator  = ' ';
            $output     = '';
            if ( ! empty( $categories ) ) {
                foreach ( $categories as $category ) {
                    /* translators: %s = category name (e.g. "Hip-Hop"). */
                    $title    = sprintf( __( 'View all posts in %s', 'lamixtape' ), $category->name );
                    $output  .= '<a class="mr-1" href="' . esc_url( get_category_link( $category->term_id ) ) . '" '
                        . 'title="' . esc_attr( $title ) . '">'
                        . esc_html( $category->name ) . '</a>' . $separator;
                }
                echo trim( $output, $separator );
            }
        ?></div>
    </div>
</article>
