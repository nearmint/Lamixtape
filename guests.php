<?php /* Template Name: guests */ ?>
<?php get_header(); ?>
<div id="guests">
    <header class="fade-in delay-1">
        <div class="container">
            <hr style="margin-bottom:0">
			<div style="height: 85px;display: flex;align-items: center;justify-content: center;">
				<h1 class="font-smoothing"><?php esc_html_e('Alongside our roaster of curators, we occasionally invite guest artists, DJs, and labels...', 'lamixtape'); ?></h1>
			</div>
        </div>
    </header>
    <section class="text-center font-smoothing">
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
                    <h2 class="pt-2 pb-0 mb-0 text-truncate"><?php echo esc_html($curauth->nickname); ?></h2>
                    <span class="d-none d-sm-none d-md-none d-lg-block">
                        <?php foreach ( $author_posts as $author_post ) : ?>
                            <a href="<?php echo esc_url( get_permalink( $author_post ) ); ?>" class="mr-2"><?php echo esc_html( get_the_title( $author_post ) ); ?></a>
                        <?php endforeach; ?>
                    </span>
                </header>
            </span>
        <?php $delay++; ?>
        <?php endforeach; ?>
    </section>
</div>
<?php get_footer(); ?>
