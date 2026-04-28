<?php
// Use a custom header for the search page
get_header('search');
global $wp_query;
?>
<div id="search">
    <header class="font-smoothing fade-in delay-1">
        <form role="search" method="get" id="" class="" action="<?php echo esc_url( get_bloginfo( 'wpurl' ) );?>">
            <div class="container">
                <hr class="mb-4">
                <h4><?php esc_html_e('Search:', 'text-domain'); ?>
                    <?php the_search_query(); ?>
                    <a href="<?php echo esc_url( get_bloginfo( 'wpurl' ) );?>/explore" class="float-right no--hover">
                        <svg class="bi bi-x" width="1em" height="1em" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M11.854 4.146a.5.5 0 010 .708l-7 7a.5.5 0 01-.708-.708l7-7a.5.5 0 01.708 0z" clip-rule="evenodd"/>
                            <path fill-rule="evenodd" d="M4.146 4.146a.5.5 0 000 .708l7 7a.5.5 0 00.708-.708l-7-7a.5.5 0 00-.708 0z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                </h4>
            </div>
        </form>
    </header>
    <section class="mixtape-list">
        <?php 
        // Query all posts matching the search
        $allsearch = new WP_Query("s=$s&showposts=-1"); 
        ?>
        <?php if ($allsearch->have_posts()) : ?>
            <?php while ($allsearch->have_posts()) : $allsearch->the_post(); ?>
                <article style="background-color:<?php echo esc_attr( get_field('color') ); ?>;" class="font-smoothing fade-in delay-2">
                    <div class="container">
                        <span class="highlight float-left mr-n3"><?php if(!get_field('highlight')) { echo ""; } else { echo "🔥"; } ?></span>
                        <a href="<?php the_permalink(); ?>"><h2 class="mb-0 pt-2 text-truncate"><?php the_title(); ?><span class="d-none d-sm-none d-md-none d-lg-block float-right curator author-<?php the_author_meta('ID') ?>"><?php esc_html_e('Curated by', 'text-domain'); ?> <?php the_author(); ?></span></h2></a>
                        <div class="tags pb-2"><?php
                            $categories = get_the_category();
                            $separator = ' ';
                            $output = '';
                            if ( ! empty( $categories ) ) {
                                foreach( $categories as $category ) {
                                    $output .= '<a class="mr-1" href="' . esc_url( get_category_link( $category->term_id ) ) . '" alt="' . esc_attr( sprintf( __( 'View all posts in %s', 'text-domain' ), $category->name ) ) . '">' . esc_html( $category->name ) . '</a>' . $separator;
                                }
                                echo trim( $output, $separator );
                            }
                        ?></div>
                    </div>
                </article>
            <?php endwhile; else: ?>
            <?php echo '<div class="container nothing--found"><h2 class="font-smoothing">' . esc_html__('Nothing found', 'text-domain') . '</h2></div>'; ?>
        <?php endif; ?>
    </section>
</div>
<?php get_footer(); ?>
