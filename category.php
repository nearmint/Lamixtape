<?php get_header(); ?>

<div id="category">
    <header class="font-smoothing">
        <div class="container mx-auto px-4">
            <hr class="mb-6 pb-1">
            <h4>
                <?php esc_html_e('Genre :', 'lamixtape'); ?> <?php single_cat_title(); ?>
                <a href="<?php echo esc_url( home_url('/explore') ); ?>" class="float-right no--hover">
                    <!-- “X” icon -->
                </a>
            </h4>
        </div>
    </header>

    <section class="mixtape-list">
        <?php
        $cat_id     = get_query_var('cat');
        $batch_size = defined( 'LMT_INFINITE_SCROLL_BATCH_SIZE' ) ? LMT_INFINITE_SCROLL_BATCH_SIZE : 30;

        $mixtape_query = new WP_Query( array(
            'cat'            => $cat_id,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
        ) );

        if ( $mixtape_query->have_posts() ) :
            ?>
            <div id="lmt-mixtapes-container">
                <?php while ( $mixtape_query->have_posts() ) : $mixtape_query->the_post(); ?>
                    <?php get_template_part( 'template-parts/card-mixtape', null, array(
                        'article_extra_classes' => 'font-smoothing',
                        'highlight_mode'        => 'none',
                        'hide_curator_on_small' => true,
                    ) ); ?>
                <?php endwhile; ?>
            </div>
            <?php if ( $mixtape_query->found_posts > $batch_size ) : ?>
                <div id="lmt-infinite-sentinel"
                     data-context="category"
                     data-initial-offset="<?php echo (int) $batch_size; ?>"
                     data-category="<?php echo (int) $cat_id; ?>"
                     aria-hidden="true"></div>
            <?php endif; ?>
        <?php else : ?>
            <div class="container mx-auto px-4 nothing--found">
                <h2 class="font-smoothing"><?php esc_html_e('No playlist found', 'lamixtape'); ?><br>
                    <small><?php esc_html_e('Let us know if you want a specific genre or artist', 'lamixtape'); ?><br>
                    → <a href="mailto:hello@lamixtape.fr">hello@lamixtape.fr</a></small>
                </h2>
            </div>
        <?php
        endif;
        wp_reset_postdata();
        ?>
    </section>
</div>

<?php get_footer(); ?>
