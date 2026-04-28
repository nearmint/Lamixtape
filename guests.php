<?php /* Template Name: guests */ ?>
<?php get_header(); ?>
<div id="guests">
    <header class="fade-in delay-1">
        <div class="container">
            <hr style="margin-bottom:0">
			<div style="height: 85px;display: flex;align-items: center;justify-content: center;">
				<h1 class="font-smoothing"><?php esc_html_e('Alongside our roaster of curators, we occasionally invite guest artists, DJs, and labels...', 'text-domain'); ?></h1>
			</div>
        </div>
    </header>
    <section class="text-center font-smoothing">
        <?php
        global $wpdb;
        $site_admin = "";
        // Query all users except the site admin, ordered by nickname
        $query = "SELECT ID, user_nicename from $wpdb->users WHERE ID != '$site_admin' ORDER BY user_nicename";
        $author_ids = $wpdb->get_results($query);
        $delay = 2;
        foreach($author_ids as $author) :
            $curauth = get_userdata($author->ID);
            $user_link = get_author_posts_url($curauth->ID);
        ?>
            <span class="<?php echo esc_attr($curauth->nickname); ?> fade-in delay-<?php echo $delay; ?>">
                <header>
                    <h2 class="pt-2 pb-0 mb-0 text-truncate"><?php echo esc_html($curauth->nickname); ?></h2>
                    <span class="d-none d-sm-none d-md-none d-lg-block">
                        <?php 
                        // List all posts by this author
                        $author_query = new WP_Query( 'author='.$curauth->ID.'&posts_per_page=-1&' );
                        while ( $author_query->have_posts() ) : $author_query->the_post();
                        ?>
                            <a href="<?php the_permalink(); ?>" class="mr-2"><?php the_title(); ?></a>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </span>
                </header>
            </span>
        <?php $delay++; ?>
        <?php endforeach; ?>
    </section>
</div>
<?php get_footer(); ?>
