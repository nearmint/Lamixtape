<?php get_header(); ?>

<div id="category">
    <header class="font-smoothing fade-in delay-1">
        <div class="container">
            <hr class="mb-4 pb-1">
            <h4>
                <?php esc_html_e('Genre :', 'text-domain'); ?> <?php single_cat_title(); ?>
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
        ?>
            <article style="background-color:<?php echo esc_attr( get_field('color') ); ?>;" class="font-smoothing fade-in delay-2">
                <div class="container">
                    <a href="<?php the_permalink(); ?>">
                        <h2 class="mb-0 pt-2 text-truncate">
                            <?php the_title(); ?>
                            <span class="d-none d-sm-none d-md-none d-lg-block float-right curator author-<?php the_author_meta('ID') ?>">
                                <?php esc_html_e('Curated by', 'text-domain'); ?> <?php the_author(); ?>
                            </span>
                        </h2>
                    </a>

                    <div class="tags pb-2">
                        <?php
                        $categories = get_the_category();
                        if ( ! empty( $categories ) ) {
                            foreach ( $categories as $category ) {
                                echo '<a class="mr-1" href="'. esc_url( get_category_link( $category->term_id ) ) .'" '
                                    . 'title="'. esc_attr( sprintf( __( 'View all posts in %s', 'text-domain' ), $category->name ) ) .'">'
                                    . esc_html( $category->name )
                                    . '</a> ';
                            }
                        }
                        ?>
                    </div>
                </div>
            </article>
        <?php
            endwhile;

            // Optional: Pagination
            // echo paginate_links( array(
            //   'total'   => $mixtape_query->max_num_pages,
            //   'current' => $paged,
            // ) );

        else :
        ?>
            <div class="container nothing--found">
                <h2 class="font-smoothing"><?php esc_html_e('No playlist found', 'text-domain'); ?><br>
                    <small><?php esc_html_e('Let us know if you want a specific genre or artist', 'text-domain'); ?><br>
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
