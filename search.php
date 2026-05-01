<?php
// Use a custom header for the search page
get_header('search');
global $wp_query;
?>
<div id="search">
    <header class="font-smoothing">
        <form role="search" method="get" id="" action="<?php echo esc_url( get_bloginfo( 'wpurl' ) );?>">
            <div class="container mx-auto px-4">
                <hr class="mb-6">
                <h4><?php esc_html_e('Search:', 'lamixtape'); ?>
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
        <?php $allsearch = lmt_get_search_results(); ?>
        <?php if ($allsearch->have_posts()) : ?>
            <?php while ($allsearch->have_posts()) : $allsearch->the_post(); ?>
                <?php get_template_part( 'template-parts/card-mixtape', null, array(
                    'article_extra_classes' => 'font-smoothing',
                    'highlight_mode'        => 'always_span',
                    'hide_curator_on_small' => true,
                    'tag_link_attr'         => 'alt',
                ) ); ?>
            <?php endwhile; else: ?>
            <?php echo '<div class="container mx-auto px-4 nothing--found"><h2 class="font-smoothing">' . esc_html__('Nothing found', 'lamixtape') . '</h2></div>'; ?>
        <?php endif; ?>
    </section>
</div>
<?php get_footer(); ?>
