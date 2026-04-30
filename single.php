<?php get_header(); ?>
<article class="mixtape font-smoothing tw:pb-12 fade-in delay-1" style="background-color:<?php echo esc_attr( get_field('color') ); ?>">
    <div class="tw:container tw:mx-auto tw:px-4">
        <div class="tw:flex tw:flex-wrap tw:pt-12">
            <div class="tw:flex-1 tw:md:flex-none tw:md:w-2/3 fade-in delay-2">
                <h2 class="tw:mb-0"><?php the_title(); ?></h2>
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
                <span class="tw:ml-1 tw:mr-2">·</span><span class="date"><?php the_time('F Y'); ?></span>
            </div>
            <div class="tw:hidden tw:lg:block tw:lg:w-1/3 tw:text-right buttons fade-in delay-3">
                <button class="like__btn animated like-btn">
                    🔥&nbsp;
                    <span class="like__number"><?php if(!get_field('likes_number')) { echo "0"; } else { the_field('likes_number'); } ?></span>
                </button>
            </div>
        </div>
        <hr class="tw:my-6">
        <div class="tw:flex tw:flex-wrap tracklist fade-in delay-4">
            <div class="tw:flex-1 tw:md:flex-none tw:md:w-2/3 fade-in delay-5">
                <p class="tw:mb-6 curated author-<?php the_author_meta('ID') ?>">
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
                <ul class="tw:list-none tw:p-0 tw:lowercase" id="playlist">
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
            <div class="tw:hidden tw:lg:block tw:lg:w-1/3 fade-in delay-6">
                <div>
                    <div>
                        <?php if( has_post_thumbnail() ): ?>
                            <a href="#" data-lmt-dialog="donatemodal" class="no--hover"><?php the_post_thumbnail( 'large', array(
                                'class'    => 'tw:max-w-full tw:h-auto tw:mt-6 illustration',
                                'alt'      => esc_attr( get_the_title() ),
                                'loading'  => 'lazy',
                                'decoding' => 'async',
                            ) ); ?></a>
                        <?php endif; ?>
                    </div>
                    <!-- Container for player iframes -->
                    <div id="player-container"></div>
                    <audio id="audioPlayer" class="tw:hidden"></audio>
                    <div class="tw:aspect-video tw:relative tw:hidden">
                        <div id="youtubePlayer" class="player-frame"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="tw:flex tw:flex-wrap tw:ml-0">
            <div class="action-buttons fade-in delay-6 visible">
                    <?php
                        $random = lmt_get_random_mixtape( 'single_random_button' );
                        if ( $random ) :
                            echo '<a href="' . esc_url( get_permalink( $random ) ) . '">';
                            esc_html_e( '🔀 Random Mixtape', 'lamixtape' );
                            echo '</a>';
                        endif;
                        ?>
                    <a data-lmt-dialog="contactmodal" href="#" class="middle">💌 Send feedback</a>
                    <a data-lmt-dialog="donatemodal" href="#">⚡️ Support us</a>
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
                    'delay'                 => 7,
                    'article_extra_classes' => 'font-smoothing',
                    'highlight_mode'        => 'conditional',
                    'hide_curator_on_small' => false,
                    'tag_link_attr'         => 'alt',
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
