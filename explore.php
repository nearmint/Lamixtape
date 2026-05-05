<?php /* Template Name: explore */ ?>
<?php get_header(); ?>
<div id="explore" class="font-smoothing">
    <header>
        <div class="container mx-auto px-4">
            <hr class="mb-[5px]">
            <h1 class="sr-only"><?php esc_html_e( 'Explore mixtapes', 'lamixtape' ); ?></h1>
            <form role="search" method="get" id="" class="mb-4 flex flex-wrap gap-3" action="<?php echo esc_url( get_bloginfo( 'wpurl' ) );?>">
                <div class="flex-none">
                    <svg aria-hidden="true" focusable="false" class="bi bi-search mt-5" width="30" height="30" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M10.442 10.442a1 1 0 011.415 0l3.85 3.85a1 1 0 01-1.414 1.415l-3.85-3.85a1 1 0 010-1.415z" clip-rule="evenodd"/>
                        <path fill-rule="evenodd" d="M6.5 12a5.5 5.5 0 100-11 5.5 5.5 0 000 11zM13 6.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="w-5/6">
                    <label for="s" class="sr-only"><?php esc_html_e( 'Search artists and more', 'lamixtape' ); ?></label>
                    <input type="text" value="<?php the_search_query(); ?>" name="s" id="s" data-placeholder-mobile="<?php esc_attr_e( 'Search', 'lamixtape' ); ?>" placeholder="<?php esc_attr_e('Search artists and more...', 'lamixtape'); ?>">
                </div>
            </form>
        </div>
    </header>
    <section class="text-center">
        <?php
        // Get all categories for display
        $category_ids = get_terms();
        $args = array(
            'orderby' => 'slug',
            'parent' => 0,
            'hide_empty' => false
        );
        $categories = get_categories( $args );
        foreach ( $categories as $category ) {
            echo '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '"><header class="flex items-center min-h-[100px] justify-center"><h2>' . esc_html( $category->name ) . '</h2></header></a>';
        }
        ?>
    </section>
</div>
<?php get_footer(); ?>
