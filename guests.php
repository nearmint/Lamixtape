<?php /* Template Name: guests */ ?>
<?php get_header(); ?>
<div id="guests">
    <header class="fade-in delay-1">
        <div class="tw:container tw:mx-auto tw:px-4">
            <hr class="tw:mb-0">
			<div class="tw:h-[85px] tw:flex tw:items-center tw:justify-center">
				<h1 class="font-smoothing"><?php esc_html_e('Alongside our roaster of curators, we occasionally invite guest artists, DJs, and labels...', 'lamixtape'); ?></h1>
			</div>
        </div>
    </header>
    <section class="tw:text-center font-smoothing">
        <?php
        $curators       = lmt_get_curators();
        $posts_by_author = lmt_get_posts_grouped_by_author();
        $delay          = 2;
        foreach ( $curators as $author ) :
            $curauth = get_userdata( $author->ID );
            $author_posts = isset( $posts_by_author[ (int) $curauth->ID ] ) ? $posts_by_author[ (int) $curauth->ID ] : array();
        ?>
            <span class="<?php echo esc_attr($curauth->nickname); ?> fade-in delay-<?php echo $delay; ?>">
                <header>
                    <h2 class="tw:pt-2 tw:pb-0 tw:mb-0 tw:truncate"><?php echo esc_html($curauth->nickname); ?></h2>
                    <span class="tw:hidden tw:lg:block">
                        <?php foreach ( $author_posts as $author_post ) : ?>
                            <a href="<?php echo esc_url( get_permalink( $author_post ) ); ?>" class="tw:mr-2"><?php echo esc_html( get_the_title( $author_post ) ); ?></a>
                        <?php endforeach; ?>
                    </span>
                </header>
            </span>
        <?php $delay++; ?>
        <?php endforeach; ?>
    </section>
</div>
<?php get_footer(); ?>
