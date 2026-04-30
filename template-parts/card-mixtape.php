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
 *   - 'delay'                 int     (1..N) — fade-in delay class
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
 *                                       tw:hidden tw:lg:block (true
 *                                       everywhere except single.php
 *                                       previous-loop)
 *   - 'tag_link_attr'         string  — 'alt' (legacy index/single/search,
 *                                       invalid HTML on <a>, A11Y-005)
 *                                       or 'title' (category.php legacy).
 *                                       Preserved as-is in Phase 2;
 *                                       fix tracked under A11Y-005.
 *
 * @package Lamixtape
 */

$args = wp_parse_args(
    isset( $args ) && is_array( $args ) ? $args : array(),
    array(
        'delay'                 => 3,
        'article_extra_classes' => '',
        'h2_extra_classes'      => '',
        'highlight_mode'        => 'always_span',
        'hide_curator_on_small' => true,
        'tag_link_attr'         => 'alt',
    )
);

$article_classes = trim( $args['article_extra_classes'] . ' fade-in delay-' . (int) $args['delay'] );
$h2_classes      = trim( $args['h2_extra_classes'] . ' tw:mb-0 tw:pt-2 tw:truncate' );
$curator_classes = ( $args['hide_curator_on_small'] ? 'tw:hidden tw:lg:block ' : '' )
    . 'tw:float-right curator author-' . get_the_author_meta( 'ID' );

$is_highlight = (bool) get_field( 'highlight' );
?>
<article style="background-color:<?php echo esc_attr( get_field( 'color' ) ); ?>;" class="<?php echo esc_attr( $article_classes ); ?>">
    <div class="tw:container tw:mx-auto tw:px-4">
        <?php if ( 'always_span' === $args['highlight_mode'] ) : ?>
            <span class="highlight tw:float-left tw:-mr-4"><?php echo $is_highlight ? '🔥' : ''; ?></span>
        <?php elseif ( 'conditional' === $args['highlight_mode'] && $is_highlight ) : ?>
            <span class="highlight tw:float-left tw:-mr-4">🔥</span>
        <?php endif; ?>
        <a href="<?php the_permalink(); ?>"><h2 class="<?php echo esc_attr( $h2_classes ); ?>"><?php the_title(); ?><span class="<?php echo esc_attr( $curator_classes ); ?>"><?php esc_html_e( 'Curated by', 'lamixtape' ); ?> <?php the_author(); ?></span></h2></a>
        <div class="tags tw:pb-2"><?php
            $categories = get_the_category();
            $separator  = ' ';
            $output     = '';
            if ( ! empty( $categories ) ) {
                foreach ( $categories as $category ) {
                    $output .= '<a class="tw:mr-1" href="' . esc_url( get_category_link( $category->term_id ) ) . '" '
                        . esc_attr( $args['tag_link_attr'] ) . '="' . esc_attr( sprintf( __( 'View all posts in %s', 'lamixtape' ), $category->name ) ) . '">'
                        . esc_html( $category->name ) . '</a>' . $separator;
                }
                echo trim( $output, $separator );
            }
        ?></div>
    </div>
</article>
