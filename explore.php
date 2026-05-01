<?php /* Template Name: explore */ ?>
<?php get_header(); ?>
<div id="explore" class="font-smoothing">
    <header>
        <div class="tw:container tw:mx-auto tw:px-4">
            <hr class="tw:mb-[5px]">
            <form role="search" method="get" id="" class="tw:mb-4 tw:flex tw:flex-wrap" action="<?php echo esc_url( get_bloginfo( 'wpurl' ) );?>">
                <div class="tw:flex-none">
                    <svg class="bi bi-search tw:mt-5" width="30" height="30" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M10.442 10.442a1 1 0 011.415 0l3.85 3.85a1 1 0 01-1.414 1.415l-3.85-3.85a1 1 0 010-1.415z" clip-rule="evenodd"/>
                        <path fill-rule="evenodd" d="M6.5 12a5.5 5.5 0 100-11 5.5 5.5 0 000 11zM13 6.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="tw:w-5/6">
                    <input type="text" value="<?php the_search_query(); ?>" name="s" id="s" placeholder="<?php esc_attr_e('Search artists and more...', 'lamixtape'); ?>">
                </div>
            </form>
        </div>
    </header>
    <section class="tw:text-center">
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
            echo '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '"><header><h2 class="tw:pt-6">' . esc_html( $category->name ) . '</h2></header></a>';
        }
        ?>
    </section>
</div>
<?php get_footer(); ?>
