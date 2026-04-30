<?php get_header(); ?>
<article class="mixtape font-smoothing pb-5 fade-in delay-1" style="background-color:<?php echo esc_attr( get_field('color') ); ?>">
    <div class="container">
        <div class="row pt-5">
            <div class="col-md-8 col-xs fade-in delay-2">
                <h2 class="mb-0"><?php the_title(); ?></h2>
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
            <div class="col-md-4 text-right buttons d-none d-sm-none d-md-none d-lg-block fade-in delay-3">
                <button class="like__btn animated like-btn">
                    🔥&nbsp;
                    <span class="like__number"><?php if(!get_field('likes_number')) { echo "0"; } else { the_field('likes_number'); } ?></span>
                </button>
                <button class="like-btn" type="button" data-toggle="collapse" data-target=".multi-collapse" aria-expanded="false" aria-controls="image comments">💬&nbsp;&nbsp;<?php printf( _nx( '1', '%1$s', get_comments_number(), 'comments title', 'lamixtape' ), number_format_i18n(get_comments_number() ) ); ?></button>
            </div>
        </div>
        <hr class="my-4">
        <div class="row tracklist fade-in delay-4">
            <div class="col-md-8 col-xs fade-in delay-5">
                <p class="mb-4 curated author-<?php the_author_meta('ID') ?>">
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
                <ul class="list-unstyled text-lowercase" id="playlist">
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
            <div class="col-4 d-none d-sm-none d-md-none d-lg-block fade-in delay-6">
                <div class="tab-content">
                    <div class="collapse multi-collapse show" id="image">
                        <?php if( has_post_thumbnail() ): ?>
                            <a href="#" data-toggle="modal" data-target="#donatemodal" class="no--hover"><img src="<?php the_post_thumbnail_url(); ?>" class="img-fluid mt-4 illustration" alt="<?php the_title(); ?>"></a>
                        <?php endif; ?>
                    </div>
                    <div class="collapse multi-collapse" id="comments">
                        <!-- Comments are intentionally closed -->
                        <p><?php esc_html_e('Comments are now closed.', 'lamixtape'); ?></p>
                    </div>
                    <!-- Container for player iframes -->
                    <div id="player-container"></div>
                    <audio id="audioPlayer" style="display:none;"></audio>
                    <div class="embed-responsive embed-responsive-16by9" style="display:none">
                        <div id="youtubePlayer" class="player-frame"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row" style="margin-left: 0;">
            <div class="action-buttons fade-in delay-6 visible">
                    <?php
                        // Show a random mixtape link
                        $the_query = new WP_Query( array ( 'orderby' => 'rand', 'posts_per_page' => 1 ) );
                        while ( $the_query->have_posts() ) : $the_query->the_post();
                            echo '<a href="' . esc_url( get_permalink() ) . '">';
                            esc_html_e( '🔀 Random Mixtape', 'lamixtape' );
                            echo '</a>';
                        endwhile;
                        wp_reset_postdata();
                        ?>
                    <a data-toggle="modal" data-target="#contactmodal" href="#" class="middle">💌 Send feedback</a>
                    <a data-toggle="modal" data-target="#donatemodal" href="#">⚡️ Support us</a>
                </div>
        </div>
    </div>
</article>
<section class="mixtape-list">
    <?php
    $pageposts = lmt_get_previous_mixtapes( get_the_ID() );
    if ($pageposts):
        global $post;
        foreach ($pageposts as $post):
            setup_postdata($post);
            get_template_part( 'template-parts/card-mixtape', null, array(
                'delay'                 => 7,
                'article_extra_classes' => 'font-smoothing',
                'highlight_mode'        => 'conditional',
                'hide_curator_on_small' => false,
                'tag_link_attr'         => 'alt',
            ) );
        endforeach;
        wp_reset_postdata();
    else : ?>
        <?php // No output for else, as before ?>
    <?php endif;?>
</section>
<footer>
</footer>
<?php include "player.php" ?>
<?php get_footer(); ?>
