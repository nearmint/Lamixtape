<?php get_header(); ?>

<div id="category">
    <header class="font-smoothing fade-in delay-1">
        <div class="container">
            <hr class="mb-4 pb-1">
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
        $cat_id = get_query_var('cat');
        $paged  = max( 1, get_query_var('paged') );

        $args = array(
            'cat'            => $cat_id,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'paged'          => $paged,
        );
        $mixtape_query = new WP_Query( $args );

        if ( $mixtape_query->have_posts() ) :
            while ( $mixtape_query->have_posts() ) : $mixtape_query->the_post();
                get_template_part( 'template-parts/card-mixtape', null, array(
                    'delay'                 => 2,
                    'article_extra_classes' => 'font-smoothing',
                    'highlight_mode'        => 'none',
                    'hide_curator_on_small' => true,
                    'tag_link_attr'         => 'title',
                ) );
            endwhile;

            // Optional: Pagination
            // echo paginate_links( array(
            //   'total'   => $mixtape_query->max_num_pages,
            //   'current' => $paged,
            // ) );

        else :
        ?>
            <div class="container nothing--found">
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
