<?php /* Template Name: guests */ ?>
<?php get_header(); ?>
<div id="guests">
    <header>
        <div class="container mx-auto px-4">
            <hr class="mb-0">
			<div class="min-h-[85px] flex items-center justify-center">
				<h1 class="font-smoothing"><?php esc_html_e('Alongside our roaster of curators, we occasionally invite guest artists, DJs, and labels...', 'lamixtape'); ?></h1>
			</div>
        </div>
    </header>
    <section class="text-center font-smoothing">
        <?php
        $curators       = lmt_get_curators();
        $posts_by_author = lmt_get_posts_grouped_by_author();
        foreach ( $curators as $author ) :
            $curauth = get_userdata( $author->ID );
            $author_posts = isset( $posts_by_author[ (int) $curauth->ID ] ) ? $posts_by_author[ (int) $curauth->ID ] : array();
        ?>
            <span class="<?php echo esc_attr($curauth->nickname); ?>">
                <header>
                    <h2 class="pt-1 pb-0 mb-0 truncate"><?php echo esc_html($curauth->nickname); ?></h2>
                    <span class="hidden lg:block">
                        <?php foreach ( $author_posts as $author_post ) : ?>
                            <a href="<?php echo esc_url( get_permalink( $author_post ) ); ?>" class="mr-2"><?php echo esc_html( get_the_title( $author_post ) ); ?></a>
                        <?php endforeach; ?>
                    </span>
                </header>
            </span>
        <?php endforeach; ?>
    </section>
</div>
<?php get_footer(); ?>
