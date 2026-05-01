<?php get_header(); ?>
<?php
$bg_color = get_field( 'color' );
$fg_color = lmt_contrast_text_color( $bg_color );
?>
<article class="mixtape font-smoothing pb-12" style="background-color:<?php echo esc_attr( $bg_color ); ?>; color:<?php echo esc_attr( $fg_color ); ?>;">
    <div class="container mx-auto px-4">
        <div class="flex flex-wrap pt-12">
            <div class="flex-1 md:flex-none md:w-2/3">
                <h1 class="mb-0"><?php the_title(); ?></h1>
                <?php
                // Display categories for this mixtape
                $categories = get_the_category();
                $separator = ' ';
                $output = '';
                if ( ! empty( $categories ) ) {
                    foreach( $categories as $category ) {
                        $output .= '<a class="tag" href="' . esc_url( get_category_link( $category->term_id ) ) . '" alt="' . esc_attr( sprintf( __( 'View all posts in %s', 'lamixtape' ), $category->name ) ) . '">' . esc_html( $category->name ) . '</a>' . $separator;
                    }
                    echo trim( $output, $separator );
                }
                ?>
                <span class="ml-1 mr-2">·</span><span class="date"><?php the_time('F Y'); ?></span>
            </div>
            <div class="hidden lg:block lg:w-1/3 text-right buttons">
                <button class="like__btn animated like-btn" aria-label="<?php esc_attr_e( 'Like this mixtape', 'lamixtape' ); ?>">
                    <span aria-hidden="true">🔥&nbsp;</span>
                    <span class="like__number"><?php if(!get_field('likes_number')) { echo "0"; } else { the_field('likes_number'); } ?></span>
                </button>
            </div>
        </div>
        <hr class="my-6">
        <div class="flex flex-wrap tracklist">
            <div class="flex-1 md:flex-none md:w-2/3">
                <p class="mb-6 curated author-<?php the_author_meta('ID') ?>">
                    <?php esc_html_e('This mixtape has been curated by our guest,', 'lamixtape'); ?>
                    <?php
                    // Get author URL or fallback to author archive
                    if ( get_the_author_meta('url') ) {
                        $author_url = get_the_author_meta('url');
                    } else {
                        $author_url = get_author_posts_url( get_the_author_meta('ID') );
                    }
                    ?>
                    <a href="<?php echo esc_url($author_url); ?>?ref=lamixtape.fr" target="_blank" class="underline"><?php the_author(); ?></a>.
                </p>
                <ul class="list-none p-0 lowercase" id="playlist">
                    <?php if( have_rows('tracklist') ): ?>
                        <?php while( have_rows('tracklist') ): the_row();?>
                            <li>
                                <a href="#" data-src="<?php echo esc_url( get_sub_field('url') ); ?>" data-type="youtube">
                                    <?php echo esc_html( get_sub_field('track') ); ?>
                                </a>
                            </li>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="hidden lg:block lg:w-1/3">
                <div>
                    <div>
                        <?php if( has_post_thumbnail() ): ?>
                            <button type="button" class="lmt-link-button" data-lmt-dialog="donatemodal" aria-haspopup="dialog" aria-controls="donatemodal" aria-label="<?php esc_attr_e('Open the support us dialog', 'lamixtape'); ?>"><?php the_post_thumbnail( 'large', array(
                                'class'    => 'max-w-full h-auto mt-6 illustration',
                                'alt'      => esc_attr( get_the_title() ),
                                'loading'  => 'lazy',
                                'decoding' => 'async',
                            ) ); ?></button>
                        <?php endif; ?>
                    </div>
                    <!-- Container for player iframes -->
                    <div id="player-container"></div>
                    <audio id="audioPlayer" class="hidden"></audio>
                    <div class="aspect-video relative hidden">
                        <div id="youtubePlayer" class="player-frame"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap ml-0">
            <div class="action-buttons">
                    <?php
                        $random = lmt_get_random_mixtape( 'single_random_button' );
                        if ( $random ) :
                            echo '<a href="' . esc_url( get_permalink( $random ) ) . '">';
                            esc_html_e( '🔀 Random Mixtape', 'lamixtape' );
                            echo '</a>';
                        endif;
                        ?>
                    <button type="button" class="middle" data-lmt-dialog="contactmodal" aria-haspopup="dialog" aria-controls="contactmodal">💌 Send feedback</button>
                    <button type="button" data-lmt-dialog="donatemodal" aria-haspopup="dialog" aria-controls="donatemodal">⚡️ Support us</button>
                </div>
        </div>
    </div>
</article>
<section class="mixtape-list">
    <?php
    $batch_size  = defined( 'LMT_INFINITE_SCROLL_BATCH_SIZE' ) ? LMT_INFINITE_SCROLL_BATCH_SIZE : 30;
    $previous_q  = lmt_get_previous_mixtapes( get_the_ID(), $batch_size, 0 );
    $pageposts   = $previous_q->posts;
    $has_more    = (int) $previous_q->found_posts > count( $pageposts );
    if ($pageposts):
        global $post;
        ?>
        <div id="lmt-mixtapes-container">
            <?php foreach ($pageposts as $post): setup_postdata($post); ?>
                <?php get_template_part( 'template-parts/card-mixtape', null, array(
                    'article_extra_classes' => 'font-smoothing',
                    'highlight_mode'        => 'conditional',
                    'hide_curator_on_small' => false,
                ) ); ?>
            <?php endforeach; ?>
        </div>
        <?php wp_reset_postdata(); ?>
        <?php if ( $has_more ) : ?>
            <div id="lmt-infinite-sentinel"
                 data-context="single_previous"
                 data-initial-offset="<?php echo (int) count( $pageposts ); ?>"
                 data-exclude="<?php echo (int) get_the_ID(); ?>"
                 aria-hidden="true"></div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<footer>
</footer>
<?php include "player.php" ?>
<?php get_footer(); ?>
