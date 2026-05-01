<?php get_header(); ?>
<div class="container mx-auto px-4">
    <hr>
    <section class="text-right font-smoothing pb-12">
        <h2 class="mt-12 pt-12"><?php esc_html_e('Looks like you got lost', 'lamixtape'); ?></h2>
        <p><?php esc_html_e('Sorry, the page you are looking for has moved', 'lamixtape'); ?></p>
        <a class="inline-flex items-center bg-transparent text-white border-2 border-current rounded-none px-5 py-2.5 text-lg uppercase" href="<?php echo esc_url( get_bloginfo( 'wpurl' ) ); ?>/explore"><?php esc_html_e('Search', 'lamixtape'); ?></a>&nbsp;
        <?php
        $random = lmt_get_random_mixtape( '404_fallback' );
        if ( $random ) :
            echo '<a class="inline-flex items-center bg-transparent text-white border-2 border-current rounded-none px-5 py-2.5 text-lg uppercase" href="' . esc_url( get_permalink( $random ) ) . '">';
            // SVG icon for shuffle
            echo '<svg class="bi bi-shuffle" width="1em" height="1em" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
<path fill-rule="evenodd" d="M12.646 1.146a.5.5 0 01.708 0l2.5 2.5a.5.5 0 010 .708l-2.5 2.5a.5.5 0 01-.708-.708L14.793 4l-2.147-2.146a.5.5 0 010-.708zm0 8a.5.5 0 01.708 0l2.5 2.5a.5.5 0 010 .708l-2.5 2.5a.5.5 0 01-.708-.708L14.793 12l-2.147-2.146a.5.5 0 010-.708z" clip-rule="evenodd"/>
<path fill-rule="evenodd" d="M0 4a.5.5 0 01.5-.5h2c3.053 0 4.564 2.258 5.856 4.226l.08.123c.636.97 1.224 1.865 1.932 2.539.718.682 1.538 1.112 2.632 1.112h2a.5.5 0 010 1h-2c-1.406 0-2.461-.57-3.321-1.388-.795-.755-1.441-1.742-2.055-2.679l-.105-.159C6.186 6.242 4.947 4.5 2.5 4.5h-2A.5.5 0 010 4z" clip-rule="evenodd"/>
<path fill-rule="evenodd" d="M0 12a.5.5 0 00.5.5h2c3.053 0 4.564-2.258 5.856-4.226l.08-.123c.636-.97 1.224-1.865 1.932-2.539C11.086 4.93 11.906 4.5 13 4.5h2a.5.5 0 000-1h-2c-1.406 0-2.461.57-3.321 1.388-.795.755-1.441 1.742-2.055 2.679l-.105.159C6.186 9.758 4.947 11.5 2.5 11.5h-2a.5.5 0 00-.5.5z" clip-rule="evenodd"/>
</svg> ';
            esc_html_e('Random Mixtape', 'lamixtape');
            echo '</a>';
        endif;
        ?>
    </section>
    <img src="<?php echo esc_url( get_template_directory_uri() );?>/img/404.gif" class="travolta max-w-full h-auto hidden lg:block" alt="404" loading="lazy" decoding="async">
</div>
<?php get_footer(); ?>
